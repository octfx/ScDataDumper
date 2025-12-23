<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridResolver;
use Octfx\ScDataDumper\Services\Vehicle\CompatibleTypesExtractor;
use Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics\DriveCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\EquippedItemWalker;
use Octfx\ScDataDumper\Services\Vehicle\FlightCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\HealthAggregator;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortMapper;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use Octfx\ScDataDumper\Services\Vehicle\PortSystemBuilder;
use Octfx\ScDataDumper\Services\Vehicle\PropulsionSystemAggregator;
use Octfx\ScDataDumper\Services\Vehicle\QuantumTravelCalculator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleMetricsAggregator;
use Octfx\ScDataDumper\Services\Vehicle\WeaponSystemAnalyzer;
use Octfx\ScDataDumper\ValueObjects\ScuCalculator;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    private readonly CargoGridResolver $cargoGridResolver;

    private readonly FlightCharacteristicsCalculator $flightCharacteristicsCalculator;

    private readonly PropulsionSystemAggregator $propulsionAggregator;

    private readonly DriveCharacteristicsCalculator $driveCharacteristicsCalculator;

    private readonly PortSystemBuilder $portSystemBuilder;

    private readonly PortSummaryBuilder $portSummaryBuilder;

    private readonly QuantumTravelCalculator $quantumTravelCalculator;

    private readonly WeaponSystemAnalyzer $weaponSystemAnalyzer;

    private readonly HealthAggregator $healthAggregator;

    private readonly VehicleMetricsAggregator $vehicleMetricsAggregator;

    private readonly ?Vehicle $vehicle;

    public function __construct(private readonly VehicleWrapper $vehicleWrapper)
    {
        parent::__construct($this->vehicleWrapper->entity);

        $this->vehicle = $this->vehicleWrapper->vehicle;

        $this->cargoGridResolver = new CargoGridResolver;
        $this->flightCharacteristicsCalculator = new FlightCharacteristicsCalculator;
        $this->propulsionAggregator = new PropulsionSystemAggregator;
        $this->driveCharacteristicsCalculator = new DriveCharacteristicsCalculator;
        $this->portSystemBuilder = new PortSystemBuilder(new PortMapper, new CompatibleTypesExtractor);
        $this->portSummaryBuilder = new PortSummaryBuilder(new PortFinder, new PortClassifierService);
        $this->quantumTravelCalculator = new QuantumTravelCalculator;
        $this->weaponSystemAnalyzer = new WeaponSystemAnalyzer;
        $this->healthAggregator = new HealthAggregator;
        $this->vehicleMetricsAggregator = new VehicleMetricsAggregator(
            new EquippedItemWalker,
            $this->vehicleWrapper
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

        $isVehicle = $this->item->get('Components/VehicleComponentParams@vehicleCareer') === '@vehicle_focus_ground' || $vehicleComponent->get('@SubType') === 'Vehicle_GroundVehicle';
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

        $vehicleParts = [];
        foreach ($this->vehicle?->get('//Parts')?->children() ?? [] as $part) {
            if ($part->get('skipPart') === '1') {
                continue;
            }

            $part = new Part($part)->toArray();
            $vehicleParts[] = $part;
        }

        $vehicleParts = $this->portSummaryBuilder->preparePartsWithClassification(
            $vehicleParts,
            $this->vehicleWrapper->loadout
        );

        $data['parts'] = $this->mapParts($vehicleParts);

        $portsElement = $this->vehicleWrapper->entity->get('Components/SItemPortContainerComponentParams/Ports');
        $ports = [];
        if ($portsElement) {
            $ports = $this->portSystemBuilder->buildFromElements(
                $portsElement->children(),
                collect($this->vehicleWrapper->loadout)
            );
        }

        // Extract ports from Vehicle Parts hierarchy that don't have loadout entries
        $partsPortDefs = $this->portSystemBuilder->extractFromParts($vehicleParts);
        $ports = $this->portSystemBuilder->mergeWithPartPorts($ports, $partsPortDefs);
        if (! empty($ports)) {
            $data['ports'] = $ports;
        }

        $parts = $this->portSummaryBuilder->flattenParts($vehicleParts);

        $mass = $parts->sum(fn ($x) => (float) ($x['Mass'] ?? 0));

        $data['Mass'] = $mass > 0 ? $mass : null;
        if ($data['Mass'] === null) {
            $data['Mass'] = $this->item->get('SSCActorPhysicsControllerComponentParams/physType/SEntityActorPhysicsControllerParams@Mass');
        }

        $portSummary = $this->portSummaryBuilder->build($vehicleParts);

        // Keep port collections intact but expose them as a plain array so services with array
        // type hints (e.g. PropulsionSystemAggregator) can consume them without type errors.
        $portSummary = $this->portSummaryBuilder->attachInstalledItems($portSummary, $this->vehicleWrapper->loadout);

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
        $walker = new EquippedItemWalker;
        foreach ($walker->walk($this->vehicleWrapper->loadout) as $entry) {
            $item = $entry['Item'];
            $type = $item['type'] ?? null;
            $name = strtolower($item['name'] ?? '');
            $className = strtolower($item['className'] ?? '');

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
            'QuantumTravel' => $this->quantumTravelCalculator->calculate($portSummary),
            'Propulsion' => $this->propulsionAggregator->aggregate($portSummary),
            'Thrusters' => $this->buildThrusterSummary($portSummary),
        ];

        // Aggregate emissions and fuel from installed items only
        $metrics = $this->vehicleMetricsAggregator->aggregate($this->vehicleWrapper->loadout);

        $crossSectionValues = [];

        if ($isGravlev || ! $isVehicle) {
            $ifcs = collect($this->vehicleWrapper->loadout)
                ->first(fn ($entry) => Arr::has($entry, 'Item.stdItem.Ifcs'));

            $flightCharacteristics = $this->flightCharacteristicsCalculator->calculate(
                $ifcs,
                $data['Mass'],
                $summary['Propulsion']['ThrustCapacity']
            );

            if ($flightCharacteristics) {
                $summary['FlightCharacteristics'] = $flightCharacteristics;
                $data['agility'] = $this->flightCharacteristicsCalculator->extractAgilityData($flightCharacteristics);
            }
        }

        if ($isVehicle) {
            unset($summary['Propulsion'], $summary['QuantumTravel']);

            $driveCharacteristics = $this->driveCharacteristicsCalculator->calculate(
                $this->vehicleWrapper->vehicle,
                $data['Mass']
            );

            if ($driveCharacteristics) {
                $summary['DriveCharacteristics'] = $driveCharacteristics;
            }
        }

        foreach ($this->vehicleWrapper->loadout as $loadoutEntry) {
            if (Arr::has($loadoutEntry, 'Item.Components.SCItemShieldEmitterParams.FaceType')) {
                $faceType = Arr::get($loadoutEntry, 'Item.Components.SCItemShieldEmitterParams.FaceType');
                $data['ShieldFaceType'] = $faceType;
            }
        }

        $armorEntry = collect($this->vehicleWrapper->loadout)
            ->first(fn ($x) => ($x['portName'] ?? null) === 'hardpoint_armor' && Arr::has($x, 'Item.stdItem.Armor'));

        $crossSectionMultiplier = 1;

        if ($armorEntry) {
            $armor = Arr::get($armorEntry, 'Item.stdItem.Armor', []);

            $data['armor'] = $armor;
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

        $personalInventory = collect($this->vehicleWrapper->loadout)
            ->filter(fn ($entry) => isset($entry['Item']['Components']['SCItemInventoryContainerComponentParams']))
            ->filter(fn ($entry) => str_contains(strtolower($entry['portName'] ?? ''), 'personal'))
            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0.);

        $data['personal_inventory'] = $personalInventory;

        $vehicleInventory = collect($this->vehicleWrapper->loadout)
            ->filter(fn ($entry) => isset($entry['Item']['Components']['SCItemInventoryContainerComponentParams']))
            ->reject(fn ($entry) => str_contains(strtolower($entry['portName'] ?? ''), 'personal'))
            ->reject(fn ($entry) => Arr::get($entry, 'Item.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid'
                || Arr::get($entry, 'Item.Type') === 'Ship.Container.Cargo')
            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0.);

        $data['vehicle_inventory'] = $vehicleInventory;

        $summary = array_merge($summary, $this->healthAggregator->aggregateHealth($parts));

        if (! empty($summary['Propulsion'])) {
            $data['fuel'] = [
                'capacity' => $metrics['fuel_capacity'] !== null ? $metrics['fuel_capacity'] / 1000 : null,
                'intake_rate' => $metrics['fuel_intake_rate'] ?? null,
                'usage' => [
                    'main' => $metrics['fuel_usage']['Main'] ?? null,
                    'retro' => $metrics['fuel_usage']['Retro'] ?? null,
                    'vtol' => $metrics['fuel_usage']['Vtol'] ?? null,
                    'maneuvering' => $metrics['fuel_usage']['Maneuvering'] ?? null,
                ],
            ];
        }

        if (! empty($summary['QuantumTravel'])) {
            $data['quantum'] = [
                'quantum_speed' => $summary['QuantumTravel']['Speed'] ?? null,
                'quantum_spool_time' => $summary['QuantumTravel']['SpoolTime'] ?? null,
                'quantum_fuel_capacity' => $metrics['quantum_fuel_capacity'] !== null ? $metrics['quantum_fuel_capacity'] / 1000 : null,
                'quantum_range' => isset($summary['QuantumTravel']['Range']) ? $summary['QuantumTravel']['Range'] / 1000 : null,
            ];
        }

        $data['emission'] = $data['emission'] ?? $metrics['emission'] ?? [];

        //        if ($metrics['emission']['ir'] !== null) {
        //            $data['emission']['ir'] = $metrics['emission']['ir'];
        //        }
        //
        //        if ($metrics['emission']['em'] !== null) {
        //            // EM Signature with quantum drive active
        //            $data['emission']['em_quantum'] = $metrics['emission']['em_with_quantum'];
        //            // EM Signature with shields active
        //            $data['emission']['em_shields'] = $metrics['emission']['em_with_shields'];
        //        }

        $data['cooling'] = $metrics['cooling'];
        $data['power'] = $metrics['power'];
        $data['power_pools'] = $metrics['power_pools'];
        $data['shields_total'] = $metrics['shields'];
        // deprecated, use shields_total.hp
        $data['shield_hp'] = $metrics['shields']['hp'] ?? 0;
        $data['distortion'] = $metrics['distortion'];
        $data['ammo'] = $metrics['ammo'];
        $data['weapon_storage'] = $metrics['weapon_storage'];

        // Debug
        // $data['metrics'] = $metrics;

        //        // Acceleration estimates based on thrust capacity and mass
        //        $totalMass = $data['Mass'] ?? null;
        //        if ($totalMass && ! empty($summary['Propulsion']['ThrustCapacity'])) {
        //            $thrust = $summary['Propulsion']['ThrustCapacity'];
        //            $data['acceleration'] = [
        //                'forward' => $thrust['Main'] > 0 ? $thrust['Main'] / $totalMass : null,
        //                'retro' => $thrust['Retro'] > 0 ? $thrust['Retro'] / $totalMass : null,
        //                'vtol' => $thrust['Vtol'] > 0 ? $thrust['Vtol'] / $totalMass : null,
        //                'maneuvering' => $thrust['Maneuvering'] > 0 ? $thrust['Maneuvering'] / $totalMass : null,
        //            ];
        //        }

        $summary['MannedTurrets'] = $this->weaponSystemAnalyzer->analyzeTurrets($portSummary['mannedTurrets']);
        $summary['RemoteTurrets'] = $this->weaponSystemAnalyzer->analyzeTurrets($portSummary['remoteTurrets']);

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

    private function mapParts(array $parts): array
    {
        return array_map(function ($part) {
            return [
                'name' => $part['Name'] ?? null,
                'display_name' => $part['Port']['DisplayName'] ?? $part['Name'] ?? null,
                'damage_max' => $part['MaximumDamage'] ?? null,
                'children' => isset($part['Parts']) ? $this->mapParts($part['Parts']) : [],
            ];
        }, $parts);
    }

    private function buildThrusterSummary(array $portSummary): array
    {
        $groups = [
            'Main' => $portSummary['mainThrusters'] ?? collect(),
            'Retro' => $portSummary['retroThrusters'] ?? collect(),
            'Vtol' => $portSummary['vtolThrusters'] ?? collect(),
            'Maneuvering' => $portSummary['maneuveringThrusters'] ?? collect(),
        ];

        $mapThruster = static function (array $entry): array {
            $installed = $entry['InstalledItem'] ?? [];

            $thrustCapacity = Arr::get($installed, 'stdItem.Thruster.ThrustCapacity');
            $thrustMn = $thrustCapacity !== null ? $thrustCapacity / 1_000_000 : null;

            $burnRatePerMn = Arr::get($installed, 'stdItem.Thruster.BurnRatePerMNMicroUnits');

            return [
                'Name' => Arr::get($installed, 'stdItem.Name'),
                'PortName' => Arr::get($entry, 'Port.PortName'),
                'Classification' => Arr::get($installed, 'classification'),
                'ClassNames' => Arr::get($installed, 'className'),
                'Size' => Arr::get($installed, 'stdItem.Size'),
                'ThrustMN' => $thrustMn,
                'BurnRatePerMN' => $burnRatePerMn,
            ];
        };

        return array_map(static function ($thrusters) use ($mapThruster) {
            return collect($thrusters)
                ->map($mapThruster)
                ->filter(static function (array $thruster): bool {
                    return array_any($thruster, fn ($value) => $value !== null && $value !== '');
                })
                ->values()
                ->all();
        }, $groups);
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
}
