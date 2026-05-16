<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;
use Octfx\ScDataDumper\Services\ItemService;

final class SocpakPersonalStorageExtractor
{
    private const string PERSONAL_STORAGE_PREFIX = 'PersonalStorage_';

    /** @var array<string, list<array{ClassName: string, InstanceName: string, Section: string, ContainerUUID: string|null, SCU: float}>> */
    private array $socpakPersonalStorageCache = [];

    public function __construct(
        private readonly SocpakReader $reader,
        private readonly ?ItemService $itemService = null,
    ) {}

    /**
     * Extract all PersonalStorage entries from ObjectContainer socpak files referenced by a vehicle entity.
     *
     * @return list<array{ClassName: string, InstanceName: string, Section: string, ContainerUUID: string|null, SCU: float}>
     */
    public function extractPersonalStorage(VehicleDefinition $entity): array
    {
        $ocRefs = $entity->getAll('Components/VehicleComponentParams/objectContainers/SVehicleObjectContainerParams');

        if ($ocRefs === []) {
            return [];
        }

        $allItems = [];

        foreach ($ocRefs as $ocRef) {
            if (! ($ocRef instanceof Element)) {
                continue;
            }

            $fileName = $ocRef->get('@fileName');
            $boneName = $ocRef->get('@boneName');

            if ($fileName === null || $boneName === null) {
                continue;
            }

            $socpakPath = $this->reader->resolveSocpakPath((string) $fileName);

            if ($socpakPath === null) {
                continue;
            }

            $items = $this->extractPersonalStorageFromSocpak($socpakPath, (string) $boneName);
            $allItems = [...$allItems, ...$items];
        }

        return $allItems;
    }

    /**
     * @return list<array{ClassName: string, InstanceName: string, Section: string, ContainerUUID: string|null, SCU: float}>
     */
    private function extractPersonalStorageFromSocpak(string $socpakPath, string $section): array
    {
        if (isset($this->socpakPersonalStorageCache[$socpakPath])) {
            $cached = $this->socpakPersonalStorageCache[$socpakPath];

            return array_map(static fn (array $item) => [...$item, 'Section' => $section], $cached);
        }

        $editorXml = $this->reader->extractEditorXml($socpakPath);

        if ($editorXml === null) {
            return $this->socpakPersonalStorageCache[$socpakPath] = [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($editorXml);
        $xpath = new DOMXPath($dom);

        $psObjects = $xpath->query('//Object[starts-with(@type, "'.self::PERSONAL_STORAGE_PREFIX.'")]');

        if ($psObjects === false || $psObjects->length === 0) {
            return $this->socpakPersonalStorageCache[$socpakPath] = [];
        }

        $items = [];

        foreach ($psObjects as $node) {
            if (! ($node instanceof DOMElement)) {
                continue;
            }

            $type = $node->getAttribute('type');
            $name = $node->getAttribute('name');
            $scu = 0.0;
            $containerUuid = null;

            if ($this->itemService !== null) {
                $psEntity = $this->itemService->getByClassName($type);
                if ($psEntity !== null) {
                    $psContainer = $psEntity->getInventoryContainer();
                    if ($psContainer !== null) {
                        $scu = $psContainer->getSCU();
                        $containerUuid = $psContainer->getUuid();
                    }
                }
            }

            $items[] = [
                'ClassName' => $type,
                'InstanceName' => $name,
                'Section' => $section,
                'ContainerUUID' => $containerUuid,
                'SCU' => $scu,
            ];
        }

        $this->socpakPersonalStorageCache[$socpakPath] = $items;

        return $items;
    }
}
