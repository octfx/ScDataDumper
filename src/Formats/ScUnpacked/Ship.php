<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridResolver;
use Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics\DriveCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics\DriveCharacteristicsVehicleCalculator;
use Octfx\ScDataDumper\Services\Vehicle\EmissionAggregator;
use Octfx\ScDataDumper\Services\Vehicle\FlightCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\HealthAggregator;
use Octfx\ScDataDumper\Services\Vehicle\InventoryContainerResolver;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use Octfx\ScDataDumper\Services\Vehicle\PropulsionSystemAggregator;
use Octfx\ScDataDumper\Services\Vehicle\QuantumTravelCalculator;
use Octfx\ScDataDumper\Services\Vehicle\ResourceAggregator;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartBuilder;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataOrchestrator;
use Octfx\ScDataDumper\Services\Vehicle\WeaponSystemAnalyzer;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    private readonly CargoGridResolver $cargoGridResolver;

    private readonly InventoryContainerResolver $inventoryContainerResolver;

    private readonly PortSummaryBuilder $portSummaryBuilder;

    private readonly StandardisedPartBuilder $standardisedPartBuilder;

    private readonly ?Vehicle $vehicle;

    public function __construct(private readonly VehicleWrapper $vehicleWrapper)
    {
        parent::__construct($this->vehicleWrapper->entity);

        $this->vehicle = $this->vehicleWrapper->vehicle;

        $this->cargoGridResolver = new CargoGridResolver;
        $this->inventoryContainerResolver = new InventoryContainerResolver;
        $this->portSummaryBuilder = new PortSummaryBuilder(new PortFinder);
        $entityPorts = $this->vehicleWrapper->entity->get('Components/SItemPortContainerComponentParams/Ports');
        $this->standardisedPartBuilder = new StandardisedPartBuilder(
            ServiceFactory::getItemService(),
            new ItemClassifierService,
            new PortClassifierService,
            $entityPorts
        );
    }

    public function toArray(): array
    {
        $attach = $this->vehicleWrapper->entity->getAttachDef();
        $vehicleComponent = $this->get('Components/VehicleComponentParams');

        $vehicleComponentData = [];
        if ($vehicleComponent) {
            $vehicleComponentData = new Element($vehicleComponent->getNode())->attributesToArray();
        } else {
            $vehicleComponent = $this->vehicleWrapper->entity->getAttachDef();
        }

        $manufacturerRef = $vehicleComponent->get('manufacturer');

        // Some actor-based vehicles (e.g. power suits) don't carry a valid manufacturer on AttachDef
        // and lack VehicleComponentParams entirely. Fall back to the insurance display params
        // which still hold the canonical manufacturer reference.
        if ($manufacturerRef === null || $manufacturerRef === '00000000-0000-0000-0000-000000000000') {
            $manufacturerRef = $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/displayParams@manufacturer');
        }

        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturerRef);

        // Debug: Trace $isVehicle detection for Ballista
        if ($this->item->getUuid() === 'a04a1503-ac5c-4520-bc22-4b1a1a198eff') {
            $vehicleCareer = $this->item->get('Components/VehicleComponentParams@vehicleCareer');
            $subTypeFromItem = $this->item->get('Components/SAttachableComponentParams/AttachDef@SubType');
            $subTypeFromVehicleComponent = $vehicleComponent->get('@SubType');
            $movementClass = $this->item->get('Components/VehicleComponentParams@movementClass');

            dump([
                'DEBUG Ballista $isVehicle detection' => [
                    'UUID' => $this->item->getUuid(),
                    'ClassName' => $this->item->getClassName(),
                    'vehicleCareer' => $vehicleCareer,
                    'SubType (from item->get)' => $subTypeFromItem,
                    'SubType (from vehicleComponent->get)' => $subTypeFromVehicleComponent,
                    'movementClass' => $movementClass,
                    'vehicleComponent type' => $vehicleComponent?->nodeName,
                ],
            ]);
        }

        $subType = $this->item->get('Components/SAttachableComponentParams/AttachDef@SubType');
        $vehicleCareer = $this->item->get('Components/VehicleComponentParams@vehicleCareer', '');
        $movementClass = $this->item->get('Components/VehicleComponentParams@movementClass');

        $isVehicle = $subType === 'Vehicle_GroundVehicle' ||
                     $movementClass === 'ArcadeWheeled' ||
                     str_contains($vehicleCareer, 'ground');
        $isGravlevValue = $this->item->get('Components/VehicleComponentParams@isGravlevVehicle');
        $isGravlev = filter_var($isGravlevValue, FILTER_VALIDATE_BOOLEAN) || (is_numeric($isGravlevValue) && (float) $isGravlevValue > 0);

        // Star Citizen bounding boxes are stored as maxBoundingBoxSize@x/y/z but
        // their axis assignment is not consistent between vehicles. We normalize
        // dimensions to be Length >= Width >= Height.
        $dimensions = [
            (float) $vehicleComponent->get('maxBoundingBoxSize@x', 0),
            (float) $vehicleComponent->get('maxBoundingBoxSize@y', 0),
            (float) $vehicleComponent->get('maxBoundingBoxSize@z', 0),
        ];

        rsort($dimensions, SORT_NUMERIC);

        $descriptionData = ItemDescriptionParser::parse(
            $vehicleComponent->get('/English@vehicleDescription') ?? $vehicleComponent->get('/Localization/English@Description', '')
        );

        $data = [
            'UUID' => $this->item->getUuid(),
            'ClassName' => $this->item->getClassName(),
            'Name' => trim(Arr::get($vehicleComponentData, 'vehicleName') ?? $vehicleComponent->get('/Localization/English@Name') ?? $this->item->getClassName()),
            'Description' => $vehicleComponent->get('/English@vehicleDescription') ?? $vehicleComponent->get('/Localization/English@Description', ''),
            'DescriptionData' => $descriptionData['data'] ?? null,
            'DescriptionText' => $descriptionData['description'] ?? null,

            'Career' => $vehicleComponent->get('/English@vehicleCareer', ''),
            'Role' => $vehicleComponent->get('/English@vehicleRole', ''),

            'Manufacturer' => $manufacturer ? [
                'UUID' => $manufacturer->getUuid(),
                'Code' => $manufacturer->getCode(),
                'Name' => $manufacturer->get('Localization/English@Name'),
            ] : [],

            'Size' => $attach?->get('Size', 0) ?? 0,
            'Length' => $dimensions[0] ?? 0,
            'Width' => $dimensions[1] ?? 0,
            'Height' => $dimensions[2] ?? 0,
            'Crew' => $vehicleComponent->get('crewSize', 1),

            'Insurance' => [
                'ExpeditedCost' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseExpeditingFee', 0),
                'ExpeditedClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@mandatoryWaitTimeMinutes', 0),
                'StandardClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseWaitTimeMinutes', 0),
            ],

            'IsVehicle' => $isVehicle,
            'IsGravlev' => $isGravlev,
            'IsSpaceship' => ! ($isVehicle || $isGravlev),

            'PenetrationMultiplier' => [
                'Fuse' => $vehicleComponent->get('fusePenetrationDamageMultiplier'),
                'Components' => $vehicleComponent->get('componentPenetrationDamageMultiplier', []),
            ],
        ];

        // Build standardised parts with loadout
        $standardisedParts = $this->standardisedPartBuilder->buildPartList(
            $this->vehicle?->get('//Parts')?->children() ?? [],
            $this->vehicleWrapper->loadout
        );
        if (! empty($standardisedParts)) {
            $data['parts'] = $this->buildPartsTree($standardisedParts);
        }

        $walker = new StandardisedPartWalker;

        $data['Mass'] = 0.0;
        foreach ($walker->walkParts($standardisedParts) as $part) {
            $data['Mass'] += Arr::get($part, 'part.Mass', 0.0);
        }

        $portSummary = $this->portSummaryBuilder->build($standardisedParts);

        $context = new VehicleDataContext(
            standardisedParts: $standardisedParts,
            portSummary: $portSummary,
            ifcsLoadoutEntry: Arr::get($portSummary, 'flightControllers.0.Port.InstalledItem.stdItem'),
            mass: $data['Mass'],
            loadoutMass: 0.0, // calculated by ResourceAggregator
            isVehicle: $isVehicle,
            isGravlev: $isGravlev,
            isSpaceship: ! ($isVehicle || $isGravlev),
            intermediateResults: [],
        );

        $calculators = [
            new EmissionAggregator($walker, $this->vehicleWrapper),
            new ResourceAggregator($walker),
            new PropulsionSystemAggregator,
            new QuantumTravelCalculator,
            new FlightCharacteristicsCalculator,
            new HealthAggregator($walker),
            new WeaponSystemAnalyzer,
            new DriveCharacteristicsVehicleCalculator(
                new DriveCharacteristicsCalculator,
                $this->vehicleWrapper
            ),
        ];

        $orchestrator = new VehicleDataOrchestrator($calculators);
        $calculatedData = $orchestrator->calculate($context);

        $data['MassLoadout'] = $calculatedData['mass_loadout'] ?? null;
        $data['MassTotal'] = round($data['Mass'] + ($data['MassLoadout'] ?? 0));
        $data['Mass'] = round($data['Mass']);

        // collect($portSummary)->map(fn ($collection, $key) => $collection->count())->dd();

        // Crew roles derived from installed turrets
        $weaponCrew = ($portSummary['mannedTurrets']->count() ?? 0) + ($portSummary['remoteTurrets']->count() ?? 0);
        $operationsCrew = max(
            $portSummary['miningTurrets']->count() ?? 0,
            $portSummary['utilityTurrets']->count() ?? 0
        );

        $data['WeaponCrew'] = $weaponCrew ?: null;
        $data['OperationsCrew'] = $operationsCrew ?: null;

        // Seats and beds from installed items
        $seatCount = 0;
        $bedCount = 0;
        $walker = new StandardisedPartWalker;

        foreach ($walker->walkItems($standardisedParts) as $entry) {
            $item = $entry['Item'];

            $type = $item['Type'] ?? $item['type'] ?? null;
            $name = strtolower($item['stdItem']['Name'] ?? $item['name'] ?? '');
            $className = strtolower($item['stdItem']['ClassName'] ?? $item['className'] ?? '');

            if ($type === 'Seat') {
                $seatCount++;
            }

            if (
                $type === 'Bed' ||
                str_contains($name, 'bed') ||
                str_contains($className, 'bed')
            ) {
                $bedCount++;
            }
        }

        $data['Seats'] = $seatCount ?: null;
        $data['Beds'] = $bedCount ?: null;

        $summary = [
            // 'Thrusters' => $this->buildThrusterSummary($portSummary),
        ];

        $crossSectionValues = [];

        // Shield Face Type
        foreach ($walker->walkItems($standardisedParts) as $entry) {
            if (Arr::has($entry, 'Item.stdItem.ShieldController')) {
                $data['ShieldController'] = Arr::get($entry, 'Item.stdItem.ShieldController');
                break;
            }
        }

        $crossSectionMultiplier = 1;

        if ($portSummary['armor']->count() > 0) {
            $armor = Arr::get($portSummary['armor']->first(), 'Port.InstalledItem.stdItem', []);

            $data['armor'] = [
                'UUID' => Arr::get($armor, 'UUID'),
                'Health' => Arr::get($armor, 'Durability.Health'),
                'ResistanceMultipliers' => array_map(static fn ($mult) => $mult['Multiplier'], Arr::get($armor, 'Durability.Resistance', [])),
                ...Arr::get($armor, 'Armor', []),
            ];
        }

        $signatureParams = $this->vehicleWrapper->entity->get('Components/SSCSignatureSystemParams');

        if ($signatureParams) {
            $data['cross_section'] = $signatureParams->get('/radarProperties/SSCRadarContactProperites/crossSectionParams/SSCSignatureSystemManualCrossSectionParams/crossSection')?->attributesToArray() ?? [];
            $data['cross_section'] = array_map(static fn ($x) => (float) $x * $crossSectionMultiplier, $data['cross_section']);
        }

        $cargoResult = $this->cargoGridResolver->resolveCargoGrids($this->vehicleWrapper);

        $summary['Cargo'] = $cargoResult->totalCapacity;
        $summary['CargoGrids'] = $cargoResult->grids->map(function ($grid) {
            return $this->transformArrayKeysToPascalCase($grid);
        });
        $cargoSizeLimits = $this->cargoGridResolver->calculateCargoGridSizeLimits($cargoResult->grids);
        $summary['CargoSizeLimits'] = $this->transformArrayKeysToPascalCase($cargoSizeLimits);

        $inventoryResult = $this->inventoryContainerResolver->resolveInventoryContainers($this->vehicleWrapper);
        if ($inventoryResult->stowageCapacity > 0) {
            $summary['Stowage'] = round($inventoryResult->stowageCapacity, 2);
        }
        if ($inventoryResult->containers->isNotEmpty()) {
            $summary['InventoryContainers'] = $inventoryResult->containers->map(function ($container) {
                return $this->transformArrayKeysToPascalCase($container);
            });
        }

        //        $personalInventory = collect($this->vehicleWrapper->loadout)
        //            ->filter(fn ($entry) => isset($entry['Item']['Components']['SCItemInventoryContainerComponentParams']))
        //            ->filter(fn ($entry) => str_contains(strtolower($entry['portName'] ?? ''), 'personal'))
        //            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0.);
        //
        //        $data['personal_inventory'] = $personalInventory;
        //
        //        $vehicleInventory = collect($this->vehicleWrapper->loadout)
        //            ->filter(fn ($entry) => isset($entry['Item']['Components']['SCItemInventoryContainerComponentParams']))
        //            ->reject(fn ($entry) => str_contains(strtolower($entry['portName'] ?? ''), 'personal'))
        //            ->reject(fn ($entry) => Arr::get($entry, 'Item.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid'
        //                || Arr::get($entry, 'Item.Type') === 'Ship.Container.Cargo')
        //            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0.);
        //
        //        $data['vehicle_inventory'] = $vehicleInventory;

        // Add orchestrator calculated data to the output
        //        if (! empty($calculatedData['Propulsion'])) {
        //            $data['fuel'] = [
        //                'capacity' => isset($calculatedData['Propulsion']['FuelCapacity']) ? $calculatedData['Propulsion']['FuelCapacity'] / 1000 : null,
        //                'intake_rate' => $calculatedData['Propulsion']['FuelIntakeRate'] ?? null,
        //                'usage' => [
        //                    'main' => $calculatedData['Propulsion']['FuelUsage']['Main'] ?? null,
        //                    'retro' => $calculatedData['Propulsion']['FuelUsage']['Retro'] ?? null,
        //                    'vtol' => $calculatedData['Propulsion']['FuelUsage']['Vtol'] ?? null,
        //                    'maneuvering' => $calculatedData['Propulsion']['FuelUsage']['Maneuvering'] ?? null,
        //                ],
        //            ];
        //        }

        //        if (! empty($calculatedData['QuantumTravel'])) {
        //            $data['quantum'] = [
        //                'quantum_speed' => $calculatedData['QuantumTravel']['Speed'] ?? null,
        //                'quantum_spool_time' => $calculatedData['QuantumTravel']['SpoolTime'] ?? null,
        //                'quantum_fuel_capacity' => isset($calculatedData['QuantumTravel']['FuelCapacity']) ? $calculatedData['QuantumTravel']['FuelCapacity'] / 1000 : null,
        //                'quantum_range' => isset($calculatedData['QuantumTravel']['Range']) ? $calculatedData['QuantumTravel']['Range'] / 1000 : null,
        //            ];
        //        }
        $data['emission'] = $data['emission'] ?? $calculatedData['emission'] ?? [];
        // $data['emission_budgeted'] = $calculatedData['power_budgeting'] ?? null;
        $data['cooling'] = $calculatedData['cooling'] ?? [];
        $data['power'] = $calculatedData['power'] ?? [];
        $data['power_pools'] = $calculatedData['power_pools'] ?? [];
        $data['shields_total'] = $calculatedData['shields_total'] ?? [];
        // deprecated, use shields_total.hp
        $data['shield_hp'] = round($calculatedData['shields_total']['hp'] ?? 0);
        $data['distortion'] = $calculatedData['distortion'] ?? [];
        $data['ammo'] = $calculatedData['ammo'] ?? [];
        $data['weapon_storage'] = $calculatedData['weapon_storage'] ?? [];

        // Add calculator data to summary
        if (! empty($calculatedData['Health'])) {
            $summary['Health'] = round($calculatedData['Health']);
        }
        if (! empty($calculatedData['DamageBeforeDestruction'])) {
            $summary['DamageBeforeDestruction'] = $calculatedData['DamageBeforeDestruction'];
        }
        if (! empty($calculatedData['DamageBeforeDetach'])) {
            $summary['DamageBeforeDetach'] = $calculatedData['DamageBeforeDetach'];
        }
        if (! empty($calculatedData['FlightCharacteristics'])) {
            $summary['FlightCharacteristics'] = $calculatedData['FlightCharacteristics'];
        }
        if (! empty($calculatedData['agility'])) {
            $data['agility'] = $calculatedData['agility'];
        }
        if (! empty($calculatedData['MannedTurrets'])) {
            $summary['MannedTurrets'] = $calculatedData['MannedTurrets'];
        }
        if (! empty($calculatedData['RemoteTurrets'])) {
            $summary['RemoteTurrets'] = $calculatedData['RemoteTurrets'];
        }
        if (! empty($calculatedData['Propulsion'])) {
            $summary['Propulsion'] = $calculatedData['Propulsion'];
        }
        if (! empty($calculatedData['QuantumTravel'])) {
            $summary['QuantumTravel'] = $calculatedData['QuantumTravel'];
        }
        if (! empty($calculatedData['DriveCharacteristics'])) {
            $summary['DriveCharacteristics'] = $calculatedData['DriveCharacteristics'];
        }

        $loadout = $this->buildLoadout($standardisedParts);
        if (! empty($loadout)) {
            $data['loadout'] = $loadout;
        }

        if (! empty($crossSectionValues)) {
            $data['cross_section'] = array_map(
                static fn (float $value): float => $value * $crossSectionMultiplier,
                $crossSectionValues
            );
        }

        $data = array_merge($data, $summary);

        $this->processArray($data);

        $data = $this->transformArrayKeysToPascalCase($data);

        return $this->removeNullValues($data);
    }

    private function processArray(&$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->processArray($value);
            } elseif ($value instanceof BaseFormat) {
                $value = $value->toArray();

                if (is_array($value)) {
                    $this->processArray($value);
                }
            }
        }
    }

    /**
     * Convert Types array to itemTypes format.
     * Example: ['WeaponGun', 'WeaponGun.Ballistic'] -> [{type: 'WeaponGun'}, {type: 'WeaponGun', subType: 'Ballistic'}]
     */
    private function buildItemTypes(array $types): array
    {
        $result = [];
        $seen = [];

        foreach ($types as $typeString) {
            $parts = explode('.', $typeString, 2);
            $majorType = $parts[0];
            $subType = $parts[1] ?? null;

            $key = $majorType.'|'.($subType ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $entry = ['type' => $majorType];
            if ($subType !== null) {
                $entry['subType'] = $subType;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Determine if children are editable.
     * If no children exist, match parent's editable status.
     * If children exist, check if any are editable.
     */
    private function determineEditableChildren(?array $nestedLoadout, bool $parentEditable): bool
    {
        if ($nestedLoadout === null || empty($nestedLoadout)) {
            return $parentEditable;
        }

        return array_any($nestedLoadout, fn ($childEntry) => ($childEntry['editable'] ?? false) === true);
    }

    /**
     * Recursively build nested loadout from ports.
     */
    private function buildNestedLoadout(array $ports): array
    {
        $result = [];

        foreach ($ports as $childPort) {
            $entry = $this->buildLoadoutEntry($childPort);
            if ($entry !== null) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Build a single loadout entry from port data.
     */
    private function buildLoadoutEntry(array $port): ?array
    {
        $portName = $port['PortName'] ?? null;
        if ($portName === null) {
            return null;
        }

        $installedItem = $port['InstalledItem']['stdItem'] ?? null;
        $uneditable = $port['Uneditable'] ?? false;

        $entry = [
            'HardpointName' => $portName,
        ];

        if ($installedItem) {
            $entry['ClassName'] = $installedItem['ClassName'] ?? null;
            $entry['UUID'] = $installedItem['UUID'] ?? null;
            $entry['Name'] = $installedItem['Name'] ?? null;
            $entry['ManufacturerName'] = $installedItem['Manufacturer']['Name'] ?? null;
            $entry['Type'] = $installedItem['Type'] ?? null;
            $entry['Grade'] = $installedItem['Grade'] ?? null;
        }

        $nestedLoadout = null;
        if ($installedItem && isset($installedItem['Ports']) && is_array($installedItem['Ports'])) {
            $nestedLoadout = $this->buildNestedLoadout($installedItem['Ports']);
        }

        if ($nestedLoadout !== null && ! empty($nestedLoadout)) {
            $entry['Loadout'] = $nestedLoadout;
        }

        $entry['Editable'] = ! $uneditable;
        $entry['EditableChildren'] = $this->determineEditableChildren($nestedLoadout, ! $uneditable);
        $entry['ItemTypes'] = $this->buildItemTypes($port['Types'] ?? []);
        $entry['MaxSize'] = (int) ($port['MaxSize'] ?? 0);
        $entry['MinSize'] = (int) ($port['MinSize'] ?? 0);

        $requiredTags = $port['RequiredTags'] ?? [];
        if (! empty($requiredTags)) {
            $tags = is_array($requiredTags)
                ? implode(' ', array_filter($requiredTags))
                : trim((string) $requiredTags);

            if ($tags !== '') {
                $entry['RequiredTags'] = $tags;
            }
        }

        return $entry;
    }

    private function buildPartsTree(array $parts): array
    {
        $result = [];

        foreach ($parts as $part) {
            $displayName = $part['DisplayName']
                ?? Arr::get($part, 'Port.DisplayName')
                ?? $part['Name']
                ?? null;

            $damageMax = $part['MaximumDamage'] ?? 0.0;
            if (($damageMax === null || (int) $damageMax === 0) && empty($part['Parts'])) {
                continue;
            }

            $result[] = [
                'name' => $part['Name'] ?? null,
                'display_name' => $displayName,
                'damage_max' => $damageMax,
                'children' => $this->buildPartsTree($part['Parts'] ?? []),
            ];
        }

        return $result;
    }

    /**
     * Build loadout array from standardised parts.
     * Flattens all categories into a single loadout array.
     */
    private function buildLoadout(array $standardisedParts): array
    {
        $loadout = [];
        $walker = new StandardisedPartWalker;

        foreach ($walker->walkParts($standardisedParts) as $entry) {
            $part = $entry['part'];
            $port = $part['Port'] ?? null;

            if ($port === null) {
                continue;
            }

            $loadoutEntry = $this->buildLoadoutEntry($port);
            if ($loadoutEntry !== null) {
                $loadout[] = $loadoutEntry;
            }
        }

        return $loadout;
    }
}
