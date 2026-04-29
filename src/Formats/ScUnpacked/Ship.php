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
use Octfx\ScDataDumper\Services\Vehicle\SeatingAnalyzer;
use Octfx\ScDataDumper\Services\Vehicle\SocpakBedExtractor;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartBuilder;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\TurretControlMapper;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataOrchestrator;
use Octfx\ScDataDumper\Services\Vehicle\WeaponDpsAggregator;
use Octfx\ScDataDumper\Services\Vehicle\WeaponSystemAnalyzer;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    private readonly CargoGridResolver $cargoGridResolver;

    private readonly InventoryContainerResolver $inventoryContainerResolver;

    private readonly PortSummaryBuilder $portSummaryBuilder;

    private readonly StandardisedPartBuilder $standardisedPartBuilder;

    private readonly ?Vehicle $vehicle;

    private readonly ?Element $entityPorts;

    public function __construct(private readonly VehicleWrapper $vehicleWrapper)
    {
        parent::__construct($this->vehicleWrapper->entity);

        $this->vehicle = $this->vehicleWrapper->vehicle;

        $this->cargoGridResolver = new CargoGridResolver;
        $this->inventoryContainerResolver = new InventoryContainerResolver;
        $this->portSummaryBuilder = new PortSummaryBuilder(new PortFinder);
        $this->entityPorts = $this->vehicleWrapper->entity->get('Components/SItemPortContainerComponentParams/Ports');
        $this->standardisedPartBuilder = new StandardisedPartBuilder(
            ServiceFactory::getItemService(),
            new ItemClassifierService,
            new PortClassifierService,
            $this->entityPorts
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

        $manufacturer = $this->vehicleWrapper->entity->getManufacturer();
        if ($manufacturer === null) {
            // Some actor-based vehicles (e.g. power suits) don't carry a valid manufacturer on AttachDef
            // and lack VehicleComponentParams entirely. Fall back to the insurance display params
            // which still hold the canonical manufacturer reference.
            $manufacturerRef = $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/displayParams@manufacturer');
            $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturerRef);
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

        $vehicleName = $this->translateLocalizationValue(
            Arr::get($vehicleComponentData, 'vehicleName')
            ?? $vehicleComponent->get('@vehicleName')
            ?? $attach?->get('Localization@Name')
        );
        $vehicleDescription = $this->translateLocalizationValue(
            $vehicleComponent->get('@vehicleDescription')
            ?? $attach?->get('Localization@Description')
        );
        $vehicleCareerLabel = $this->translateLocalizationValue($vehicleComponent->get('@vehicleCareer'));
        $vehicleRoleLabel = $this->translateLocalizationValue($vehicleComponent->get('@vehicleRole'));

        $descriptionData = ItemDescriptionParser::parse($vehicleDescription);

        $physicsParams = $this->vehicleWrapper->entity->get('Components/SSCActorPhysicsControllerComponentParams/physType/SEntityActorPhysicsControllerParams');

        $data = [
            'UUID' => $this->item->getUuid(),
            'ClassName' => $this->item->getClassName(),
            'Name' => trim($vehicleName !== '' ? $vehicleName : $this->item->getClassName()),
            'Description' => $vehicleDescription,
            'DescriptionData' => $descriptionData['data'] ?? null,
            'DescriptionText' => $descriptionData['description'] ?? null,

            'Career' => $vehicleCareerLabel,
            'Role' => $vehicleRoleLabel,

            'Manufacturer' => $manufacturer ? [
                'UUID' => $manufacturer->getUuid(),
                'Code' => $manufacturer->getCode(),
                'Name' => $this->translateLocalizationValue($manufacturer->get('Localization@Name')),
            ] : [],

            'Size' => $attach?->get('@Size', 0) ?? 0,
            'Length' => $dimensions[0] ?? 0,
            'Width' => $dimensions[1] ?? 0,
            'Height' => $dimensions[2] ?? 0,
            'Crew' => $vehicleComponent->get('@crewSize', 1),

            'Insurance' => [
                'ExpeditedCost' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseExpeditingFee', 0),
                'ExpeditedClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@mandatoryWaitTimeMinutes', 0),
                'StandardClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseWaitTimeMinutes', 0),
            ],

            'IsVehicle' => $isVehicle,
            'IsGravlev' => $isGravlev,
            'IsSpaceship' => ! ($isVehicle || $isGravlev),

            'PenetrationMultiplier' => [
                'Fuse' => $vehicleComponent->get('@fusePenetrationDamageMultiplier'),
                'Components' => $vehicleComponent->get('@componentPenetrationDamageMultiplier', []),
            ],
        ];

        // Actors / ATLS
        if ($physicsParams) {
            $physicsData = $physicsParams->attributesToArray();

            $data['IsSpaceship'] = false;
            $data['IsVehicle'] = true;
            $data['Physics'] = [
                'Mass' => (float) ($physicsData['Mass'] ?? 0),
                'Inertia' => (float) ($physicsData['inertia'] ?? 0),
                'InertiaAccel' => (float) ($physicsData['inertiaAccel'] ?? 0),
                'AirResistance' => (float) ($physicsData['airResistance'] ?? 0),
                'AirControl' => (float) ($physicsData['airControl'] ?? 0),
                'MaxVelGround' => (float) ($physicsData['maxVelGround'] ?? 0),
                'MinSlideAngle' => (float) ($physicsData['minSlideAngle'] ?? 0),
                'MaxClimbAngle' => (float) ($physicsData['maxClimbAngle'] ?? 0),
                'MinFallAngle' => (float) ($physicsData['minFallAngle'] ?? 0),
                'MinColSpeedForExternalForceEvent' => (float) ($physicsData['minColSpeedForExternalForceEvent'] ?? 0),
                'MinSpeedForChargeCollisionDamage' => (float) ($physicsData['minSpeedForChargeCollisionDamage'] ?? 0),
                'ChargeAttackDamage' => (float) ($physicsData['chargeAttackDamage'] ?? 0),
            ];
        }

        // Build standardised parts with loadout
        $standardisedParts = $this->standardisedPartBuilder->buildPartList(
            $this->vehicle?->get('Parts')?->children() ?? [],
            $this->vehicleWrapper->loadout
        );
        if (! empty($standardisedParts)) {
            $data['Parts'] = $this->buildPartsTree($standardisedParts);
        }

        $walker = new StandardisedPartWalker;

        $data['Mass'] = 0.0;
        foreach ($walker->walkParts($standardisedParts) as $part) {
            $data['Mass'] += Arr::get($part, 'part.Mass', 0.0);
        }

        if ($data['Mass'] === 0.0 && $physicsParams) {
            $physicsData = $physicsParams->attributesToArray();
            $data['Mass'] = (float) ($physicsData['Mass'] ?? 0);
        }

        $portSummary = $this->portSummaryBuilder->build($standardisedParts);

        // Build turret control map: which turrets are bridge-controllable
        $turretControlMap = (new TurretControlMapper)->getBridgeControllableTurrets(
            $this->vehicle,
            $this->vehicleWrapper->entity,
        );

        $context = new VehicleDataContext(
            standardisedParts: $standardisedParts,
            portSummary: $portSummary,
            ifcsLoadoutEntry: Arr::get($portSummary, 'flightControllers.0.Port.InstalledItem.stdItem'),
            mass: $data['Mass'],
            loadoutMass: 0.0,
            isVehicle: $isVehicle,
            isGravlev: $isGravlev,
            isSpaceship: ! ($isVehicle || $isGravlev),
            turretControlMap: $turretControlMap,
            intermediateResults: [],
            entity: $this->vehicleWrapper->entity,
        );

        $calculators = [
            new EmissionAggregator($walker, $this->vehicleWrapper),
            new ResourceAggregator($walker),
            new PropulsionSystemAggregator,
            new QuantumTravelCalculator,
            new FlightCharacteristicsCalculator,
            new HealthAggregator($walker),
            new WeaponDpsAggregator($walker),
            new WeaponSystemAnalyzer,
            new SeatingAnalyzer(
                bedExtractor: new SocpakBedExtractor(ServiceFactory::getActiveScDataPath()),
                itemService: ServiceFactory::getItemService(),
            ),
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

        if (! empty($calculatedData['Seating'])) {
            $data['Seating'] = $calculatedData['Seating'];
            $data['Seats'] = Arr::get($calculatedData['Seating'], 'CrewStations');
        }

        $summary = [];

        $crossSectionValues = [];

        // Shield Face Type + Shield Resistance/Absorption
        $shields = [];
        foreach ($walker->walkItems($standardisedParts) as $entry) {
            if (Arr::has($entry, 'Item.stdItem.ShieldController')) {
                $data['ShieldController'] = Arr::get($entry, 'Item.stdItem.ShieldController');
            }

            // Collect shield generators for resistance/absorption rollup
            $item = $entry['Item'] ?? null;
            $itemType = $item['type'] ?? null;
            if ($itemType === 'Shield') {
                $stdItem = Arr::get($item, 'stdItem');
                if ($stdItem !== null) {
                    $shields[] = $stdItem;
                }
            }
        }

        // Shield Resistance/Absorption: average across active shields only
        $shieldResistance = null;
        $shieldAbsorption = null;
        if ($shields !== []) {
            $maxShields = $this->getShieldPoolMaxCount();
            $activeShields = array_slice($shields, 0, $maxShields);

            $resistance = $this->averageShieldMinMax($activeShields, 'Shield.Resistance');
            if ($resistance !== null) {
                $shieldResistance = $resistance;
            }

            $absorption = $this->averageShieldMinMax($activeShields, 'Shield.Absorption');
            if ($absorption !== null) {
                $shieldAbsorption = $absorption;
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
            $data['cross_section'] = $signatureParams->get('radarProperties/SSCRadarContactProperites/crossSectionParams/SSCSignatureSystemManualCrossSectionParams/crossSection')?->attributesToArray() ?? [];
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
        if (isset($shieldResistance)) {
            $data['shields_total']['Resistance'] = $shieldResistance;
        }
        if (isset($shieldAbsorption)) {
            $data['shields_total']['Absorption'] = $shieldAbsorption;
        }
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
        if (! empty($calculatedData['Agility'])) {
            $data['Agility'] = $calculatedData['Agility'];
        }
        if (! empty($calculatedData['MannedTurrets'])) {
            $summary['MannedTurrets'] = $calculatedData['MannedTurrets'];
        }
        if (! empty($calculatedData['PdcTurrets'])) {
            $summary['PdcTurrets'] = $calculatedData['PdcTurrets'];
        }
        if (! empty($calculatedData['RemoteTurrets'])) {
            $summary['RemoteTurrets'] = $calculatedData['RemoteTurrets'];
        }

        // Merge Weaponry.Turrets DPS data into typed turret arrays
        $weaponryTurrets = $calculatedData['Weaponry']['Turrets'] ?? [];
        if (! empty($weaponryTurrets)) {
            $turretDpsMap = [];
            foreach ($weaponryTurrets as $turret) {
                $turretDpsMap[$turret['HardpointName']] = $turret;
            }

            foreach (['MannedTurrets', 'PdcTurrets', 'RemoteTurrets'] as $key) {
                if (empty($calculatedData[$key])) {
                    continue;
                }
                foreach ($calculatedData[$key] as $i => $turret) {
                    $hp = $turret['HardpointName'] ?? null;
                    if ($hp !== null && isset($turretDpsMap[$hp])) {
                        $dps = $turretDpsMap[$hp];
                        $calculatedData[$key][$i]['DpsTotal'] = $dps['DpsTotal'] ?? null;
                        $calculatedData[$key][$i]['SustainedDpsTotal'] = $dps['SustainedDpsTotal'] ?? null;
                        $calculatedData[$key][$i]['AlphaTotal'] = $dps['AlphaTotal'] ?? null;
                        $calculatedData[$key][$i]['Weapons'] = $dps['Weapons'] ?? [];
                        $calculatedData[$key][$i]['IsPilotSlaveable'] = $dps['IsPilotSlaveable'] ?? false;
                    }
                }
            }

            // Re-assign merged turret arrays to summary
            if (! empty($calculatedData['MannedTurrets'])) {
                $summary['MannedTurrets'] = $calculatedData['MannedTurrets'];
            }
            if (! empty($calculatedData['PdcTurrets'])) {
                $summary['PdcTurrets'] = $calculatedData['PdcTurrets'];
            }
            if (! empty($calculatedData['RemoteTurrets'])) {
                $summary['RemoteTurrets'] = $calculatedData['RemoteTurrets'];
            }
        }
        if (! empty($calculatedData['Propulsion'])) {
            $summary['Propulsion'] = $calculatedData['Propulsion'];
        }
        if (! empty($calculatedData['Weaponry'])) {
            $weaponry = $calculatedData['Weaponry'];
            unset($weaponry['Turrets']);
            if (! empty($weaponry)) {
                $summary['Weaponry'] = $weaponry;
            }
        }
        if (! empty($calculatedData['QuantumTravel'])) {
            $summary['QuantumTravel'] = $calculatedData['QuantumTravel'];
        }
        if (! empty($calculatedData['DriveCharacteristics'])) {
            $summary['DriveCharacteristics'] = $calculatedData['DriveCharacteristics'];
        }

        $loadout = $this->buildLoadout($standardisedParts);
        if (! empty($loadout) && ! empty($turretControlMap)) {
            $this->markPilotSlaveable($loadout, $turretControlMap);
        }
        if (! empty($loadout)) {
            $data['loadout'] = $loadout;
        }

        if (! empty($crossSectionValues)) {
            $data['cross_section'] = array_map(
                static fn (float $value): float => $value * $crossSectionMultiplier,
                $crossSectionValues
            );
        }

        // Engineering boost (multi-crew weapon regen modifier)
        $engineeringBoost = $this->extractEngineeringBoost();
        if ($engineeringBoost !== null) {
            $data['engineering_boost'] = $engineeringBoost;
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

        $installedItemPayload = $port['InstalledItem'] ?? null;
        $installedItem = is_array($installedItemPayload) ? ($installedItemPayload['stdItem'] ?? null) : null;
        $semanticType = is_array($installedItem) ? ($installedItem['Type'] ?? null) : null;
        $uneditable = $port['Uneditable'] ?? false;

        $entry = [
            'HardpointName' => $portName,
        ];

        if ($installedItem) {
            $entry['ClassName'] = $installedItem['ClassName'] ?? null;
            $entry['UUID'] = $installedItem['UUID'] ?? null;
            $entry['Name'] = $installedItem['Name'] ?? null;
            $entry['ManufacturerName'] = $installedItem['Manufacturer']['Name'] ?? null;
            $entry['Type'] = $semanticType;
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

    /**
     * Read the max shield pool count from the ship entity.
     * This limits how many shields are active simultaneously.
     */
    private function getShieldPoolMaxCount(): int
    {
        $pools = $this->vehicleWrapper->entity->get('Components/SItemPortContainerComponentParams/resourceNetworkPowerPools/itemPools');

        foreach ($pools?->children() ?? [] as $pool) {
            $itemType = $pool->get('@itemType');
            if ($itemType === 'Shield') {
                return (int) ($pool->get('@maxItemCount') ?? 0);
            }
        }

        // Fallback: no pool constraint means all shields active
        return PHP_INT_MAX;
    }

    /**
     * Average Min/Max values across shields for a given key path.
     * Key path is like 'Shield.Resistance' which resolves to stdItem.Shield.Resistance.
     * The value at that path is expected to be [DamageType => [Minimum => float, Maximum => float], ...]
     *
     * @param  array<int, array>  $shields  stdItem arrays for active shields
     * @return array<string, array{Minimum: float, Maximum: float}>|null
     */
    private function averageShieldMinMax(array $shields, string $keyPath): ?array
    {
        if ($shields === []) {
            return null;
        }

        $damageTypes = ['Physical', 'Energy', 'Distortion', 'Thermal', 'Biochemical', 'Stun'];
        $result = [];
        $count = count($shields);

        foreach ($damageTypes as $type) {
            $minSum = 0.0;
            $maxSum = 0.0;
            $hasData = false;

            foreach ($shields as $stdItem) {
                $values = Arr::get($stdItem, "{$keyPath}.{$type}");
                if (is_array($values)) {
                    $minSum += (float) ($values['Minimum'] ?? 0.0);
                    $maxSum += (float) ($values['Maximum'] ?? 0.0);
                    $hasData = true;
                }
            }

            if ($hasData) {
                $result[$type] = [
                    'Minimum' => round($minSum / $count, 4),
                    'Maximum' => round($maxSum / $count, 4),
                ];
            }
        }

        return $result !== [] ? $result : null;
    }

    private function extractEngineeringBoost(): ?array
    {
        if ($this->entityPorts === null) {
            return null;
        }

        $engineeringBuffPort = null;
        foreach ($this->entityPorts->children() as $portDef) {
            $name = $portDef->get('@Name');
            if ($name === 'engineeringBuff') {
                $engineeringBuffPort = $portDef;
                break;
            }
        }

        if ($engineeringBuffPort === null) {
            return null;
        }

        $uuid = $engineeringBuffPort->get('defaultItem/@entityClass');
        if ($uuid === null) {
            return null;
        }

        $modifierEntity = ServiceFactory::getItemService()->getByReference($uuid);
        if ($modifierEntity === null) {
            return null;
        }

        return new EngineeringBoost($modifierEntity)->toArray();
    }

    /**
     * Recursively mark loadout entries whose hardpoint is bridge-controllable.
     */
    private function markPilotSlaveable(array &$loadout, array $turretControlMap): void
    {
        $slaveableSet = array_flip($turretControlMap);

        foreach ($loadout as &$entry) {
            $hardpointName = $entry['HardpointName'] ?? null;
            if ($hardpointName !== null && isset($slaveableSet[$hardpointName])) {
                $entry['IsPilotSlaveable'] = true;
            }

            if (isset($entry['Loadout']) && is_array($entry['Loadout'])) {
                $this->markPilotSlaveable($entry['Loadout'], $turretControlMap);
            }
        }
    }
}
