<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\DocumentTypes\InventoryContainer;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;
use Octfx\ScDataDumper\Services\InventoryContainerService;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\InventoryContainerResult;
use Octfx\ScDataDumper\ValueObjects\ScuCalculator;

final class InventoryContainerResolver
{
    public function __construct(
        private readonly ?SocpakPersonalStorageExtractor $psExtractor = null,
    ) {}

    public function resolveInventoryContainers(VehicleWrapper $vehicle): InventoryContainerResult
    {
        $result = new InventoryContainerResult;
        $inventoryService = ServiceFactory::getInventoryContainerService();
        $classifier = new ItemClassifierService;

        $vehicleContainerUuid = $this->addVehicleContainer($vehicle, $inventoryService, $result);

        foreach ($this->collectLoadoutContainers($vehicle->loadout) as $entry) {
            $itemRaw = $entry['itemRaw'] ?? null;
            if (! is_array($itemRaw)) {
                continue;
            }

            if ($this->isCargoContainer($itemRaw, $classifier)) {
                continue;
            }

            [$containerData, $key, $isClosed] = $this->resolveContainerFromItem($itemRaw, $inventoryService);

            if ($containerData === null) {
                continue;
            }

            // Skip loadout items whose container UUID matches the vehicle container
            // (prevents double-counting, e.g. Gladius SeatAccess referencing vehicle template)
            if ($vehicleContainerUuid !== null && $key === $vehicleContainerUuid) {
                continue;
            }

            $containerData['source'] = 'loadout';

            if (! empty($entry['portName'])) {
                $containerData['portName'] = $entry['portName'];
            }

            if (! empty($entry['className'])) {
                $containerData['itemClass'] = $entry['className'];
            }

            // Scope the dedup key to the hardpoint so the same container document
            // instantiated on multiple ports is counted once per port, not once globally.
            $dedupKey = ($entry['portName'] ?? null) !== null
                ? $entry['portName'].':'.$key
                : $key;

            $result->addContainer($containerData, $dedupKey, $isClosed);
        }

        // Extract PersonalStorage items from socpak interior files
        $psExtractor = $this->psExtractor ?? new SocpakPersonalStorageExtractor(
            new SocpakReader(ServiceFactory::getActiveScDataPath()),
            ServiceFactory::getItemService(),
        );

        foreach ($psExtractor->extractPersonalStorage($vehicle->entity) as $psItem) {
            $scu = $psItem['SCU'] ?? 0;

            if ($scu <= 0) {
                continue;
            }

            $containerData = [
                'uuid' => $psItem['ContainerUUID'],
                'class' => $psItem['ClassName'],
                'scu' => $scu,
                'capacity' => $scu,
                'capacity_name' => 'SCU',
                'x' => null,
                'y' => null,
                'z' => null,
                'isOpenContainer' => false,
                'isExternalContainer' => false,
                'isClosedContainer' => true,
                'source' => 'socpak',
            ];

            // Each PersonalStorage instance in a socpak is a unique physical locker.
            // Dedup by instance name to avoid counting the same physical locker twice.
            $dedupKey = 'socpak:'.$psItem['InstanceName'];

            // true = isClosedContainer; PersonalStorage lockers are always closed containers
            $result->addContainer($containerData, $dedupKey, true);
        }

        $vehicleContainerUuid = null;

        return $result;
    }

    private function addVehicleContainer(
        VehicleWrapper $vehicle,
        InventoryContainerService $inventoryService,
        InventoryContainerResult $result
    ): ?string {
        $container = $vehicle->entity->getInventoryContainer();
        if (! $container) {
            return null;
        }

        $containerData = $this->formatContainer($container);
        // Ship_TempItemCarryingCapacity_* containers are physics-based loose-item
        // carrying volumes, not personal storage. Exclude them from stowage.
        if (str_contains(strtolower($container->getClassName()), 'tempitemcarryingcapacity')) {
            return null;
        }

        $containerData['source'] = 'vehicle';

        $result->addContainer($containerData, $container->getUuid(), $container->isClosedContainer());

        return $container->getUuid();
    }

    private function collectLoadoutContainers(array $entries): array
    {
        $results = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            array_push($results, ...$this->extractLoadoutContainerEntry($entry));
        }

        return $results;
    }

    private function extractLoadoutContainerEntry(array $entry): array
    {
        $results = [];

        $itemRaw = Arr::get($entry, 'ItemRaw');
        if (is_array($itemRaw) && Arr::has($itemRaw, 'Components.SCItemInventoryContainerComponentParams')) {
            $results[] = [
                'itemRaw' => $itemRaw,
                'portName' => $entry['portName'] ?? null,
                'className' => $entry['className'] ?? null,
            ];
        }

        if (! empty($entry['entries']) && is_array($entry['entries'])) {
            foreach ($entry['entries'] as $child) {
                if (! is_array($child)) {
                    continue;
                }

                array_push($results, ...$this->extractLoadoutContainerEntry($child));
            }
        }

        return $results;
    }

    private function isCargoContainer(array $itemRaw, ItemClassifierService $classifier): bool
    {
        $type = strtolower((string) Arr::get($itemRaw, 'Components.SAttachableComponentParams.AttachDef.Type', ''));
        if ($type === 'cargogrid') {
            return true;
        }

        $classification = $classifier->classify($itemRaw);

        return in_array($classification, ['Ship.CargoGrid', 'Ship.Container.Cargo'], true);
    }

    /**
     * @return array{0: array|null, 1: string|null, 2: bool}
     */
    private function resolveContainerFromItem(array $itemRaw, InventoryContainerService $inventoryService): array
    {
        $containerRef = Arr::get($itemRaw, 'Components.SCItemInventoryContainerComponentParams.containerParams')
            ?? Arr::get($itemRaw, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.__ref')
            ?? Arr::get($itemRaw, '__ref');

        $container = null;

        if ($containerRef) {
            $container = $inventoryService->getByReference($containerRef);
        }

        if ($container === null) {
            $className = $itemRaw['className'] ?? $itemRaw['ClassName'] ?? null;
            if ($className) {
                $container = $inventoryService->getByClassName($className);
            }
        }

        if ($container !== null) {
            $data = $this->formatContainer($container);

            return [$data, $container->getUuid(), $container->isClosedContainer()];
        }

        $inlineContainer = Arr::get($itemRaw, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer');
        if (! is_array($inlineContainer)) {
            return [null, null, false];
        }

        [$capacityValue, $capacityName] = $this->parseCapacity($inlineContainer);
        $dimensions = Arr::get($inlineContainer, 'interiorDimensions', []);
        $isOpen = Arr::has($inlineContainer, 'inventoryType.InventoryOpenContainerType');
        $isClosed = Arr::has($inlineContainer, 'inventoryType.InventoryClosedContainerType');

        $scu = ScuCalculator::fromItem($itemRaw);

        $data = [
            'uuid' => $containerRef,
            'class' => $itemRaw['className'] ?? $itemRaw['ClassName'] ?? null,
            'scu' => $scu,
            'capacity' => $capacityValue,
            'capacity_name' => $capacityName,
            'x' => Arr::get($dimensions, 'x'),
            'y' => Arr::get($dimensions, 'y'),
            'z' => Arr::get($dimensions, 'z'),
            'minSize' => Arr::get($inlineContainer, 'inventoryType.InventoryOpenContainerType.minPermittedItemSize'),
            'maxSize' => Arr::get($inlineContainer, 'inventoryType.InventoryOpenContainerType.maxPermittedItemSize'),
            'isOpenContainer' => $isOpen,
            'isExternalContainer' => (bool) Arr::get($inlineContainer, 'inventoryType.InventoryOpenContainerType.isExternalContainer'),
            'isClosedContainer' => $isClosed,
        ];

        $key = $containerRef
            ?? ($itemRaw['className'] ?? $itemRaw['ClassName'] ?? null);

        return [$data, $key, $isClosed];
    }

    private function formatContainer(InventoryContainer $container): array
    {
        $dimensions = $container->getInteriorDimensions();

        return [
            'uuid' => $container->getUuid(),
            'class' => $container->getClassName(),
            'scu' => $container->getSCU(),
            'capacity' => $container->getCapacityValue(),
            'capacity_name' => $container->getCapacityName(),
            'x' => $dimensions['x'] ?? null,
            'y' => $dimensions['y'] ?? null,
            'z' => $dimensions['z'] ?? null,
            'isOpenContainer' => $container->isOpenContainer(),
            'isExternalContainer' => $container->isExternalContainer(),
            'isClosedContainer' => $container->isClosedContainer(),
        ];
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private function parseCapacity(array $container): array
    {
        $capacity = Arr::get($container, 'capacity');

        if (! is_array($capacity)) {
            return [null, null];
        }

        if (isset($capacity['SStandardCargoUnit']['standardCargoUnits'])) {
            return [(float) $capacity['SStandardCargoUnit']['standardCargoUnits'], 'SCU'];
        }

        if (isset($capacity['SCentiCargoUnit']['centiSCU'])) {
            return [(float) $capacity['SCentiCargoUnit']['centiSCU'], 'cSCU'];
        }

        if (isset($capacity['SMicroCargoUnit']['microSCU'])) {
            return [(float) $capacity['SMicroCargoUnit']['microSCU'], 'µSCU'];
        }

        return [null, null];
    }
}
