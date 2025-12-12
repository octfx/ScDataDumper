<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Generator;
use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridResolver;
use Octfx\ScDataDumper\Services\Vehicle\CompatibleTypesExtractor;
use Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics\DriveCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\FlightCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\HealthAggregator;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortMapper;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use Octfx\ScDataDumper\Services\Vehicle\PortSystemBuilder;
use Octfx\ScDataDumper\Services\Vehicle\PropulsionSystemAggregator;
use Octfx\ScDataDumper\Services\Vehicle\QuantumTravelCalculator;
use Octfx\ScDataDumper\Services\Vehicle\WeaponSystemAnalyzer;
use Octfx\ScDataDumper\ValueObjects\ScuCalculator;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    private readonly PortClassifierService $portClassifierService;

    private readonly CargoGridResolver $cargoGridResolver;

    private readonly FlightCharacteristicsCalculator $flightCharacteristicsCalculator;

    private readonly PropulsionSystemAggregator $propulsionAggregator;

    private readonly DriveCharacteristicsCalculator $driveCharacteristicsCalculator;

    private readonly CompatibleTypesExtractor $compatibleTypesExtractor;

    private readonly PortMapper $portMapper;

    private readonly PortSystemBuilder $portSystemBuilder;

    private readonly PortFinder $portFinder;

    private readonly PortSummaryBuilder $portSummaryBuilder;

    private readonly QuantumTravelCalculator $quantumTravelCalculator;

    private readonly WeaponSystemAnalyzer $weaponSystemAnalyzer;

    private readonly HealthAggregator $healthAggregator;

    private readonly ?Vehicle $vehicle;

    public function __construct(private readonly VehicleWrapper $vehicleWrapper)
    {
        parent::__construct($this->vehicleWrapper->entity);

        $this->vehicle = $this->vehicleWrapper->vehicle;

        $this->portClassifierService = new PortClassifierService;
        $this->cargoGridResolver = new CargoGridResolver;
        $this->flightCharacteristicsCalculator = new FlightCharacteristicsCalculator;
        $this->propulsionAggregator = new PropulsionSystemAggregator;
        $this->driveCharacteristicsCalculator = new DriveCharacteristicsCalculator;
        $this->compatibleTypesExtractor = new CompatibleTypesExtractor;
        $this->portMapper = new PortMapper;
        $this->portSystemBuilder = new PortSystemBuilder($this->portMapper, $this->compatibleTypesExtractor);
        $this->portFinder = new PortFinder;
        $this->portSummaryBuilder = new PortSummaryBuilder($this->portFinder);
        $this->quantumTravelCalculator = new QuantumTravelCalculator;
        $this->weaponSystemAnalyzer = new WeaponSystemAnalyzer;
        $this->healthAggregator = new HealthAggregator;
    }

    public function toArray(): array
    {
        $attach = $this->vehicleWrapper->entity->getAttachDef();
        $vehicleComponent = $this->get('Components/VehicleComponentParams');

        $vehicleComponentData = [];
        if ($vehicleComponent) {
            $attributes = (new Element($vehicleComponent->getNode()))->attributesToArray();
            $vehicleComponentData = $attributes ? $this->transformArrayKeysToPascalCase($attributes) : [];
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

            // WeaponCrew = portSummary.MannedTurrets.Count + portSummary.RemoteTurrets.Count,
            // OperationsCrew = Math.Max(portSummary.MiningTurrets.Count, portSummary.UtilityTurrets.Count),

            'Insurance' => [
                'ExpeditedCost' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseExpeditingFee', 0),
                'ExpeditedClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@mandatoryWaitTimeMinutes', 0),
                'StandardClaimTime' => $this->item->get('StaticEntityClassData/SEntityInsuranceProperties/shipInsuranceParams@baseWaitTimeMinutes', 0),
            ],

            'IsVehicle' => $isVehicle,
            'IsGravlev' => $isGravlev,
            'IsSpaceship' => ! ($isVehicle || $isGravlev),
        ];

        $vehicleParts = [];
        foreach ($this->vehicle?->get('//Parts')?->children() ?? [] as $part) {
            if ($part->get('skipPart') === '1') {
                continue;
            }

            $part = (new Part($part))->toArray();
            $vehicleParts[] = $part;
        }

        $data['parts'] = $this->mapParts($vehicleParts);

        $portsElement = $this->vehicleWrapper->entity->get('Components/SItemPortContainerComponentParams/Ports');
        if ($portsElement) {
            $data['ports'] = $this->portSystemBuilder->buildFromElements($portsElement->children(), collect($this->vehicleWrapper->loadout));
        }

        // Extract ports from Vehicle Parts hierarchy that don't have loadout entries
        $partsPortDefs = $this->portSystemBuilder->extractFromParts($vehicleParts);
        if (! empty($partsPortDefs)) {
            $existingPortNames = array_map(
                static fn ($p) => strtolower($p['name'] ?? ''),
                $data['ports'] ?? []
            );

            // Add ports from parts that aren't already in the ports array
            foreach ($partsPortDefs as $partPort) {
                $portName = strtolower($partPort['name'] ?? '');
                if ($portName && ! in_array($portName, $existingPortNames, true)) {
                    $data['ports'][] = $partPort;
                    $existingPortNames[] = $portName;
                }
            }
        }

        $parts = collect();

        foreach ($this->partList($vehicleParts) as $part) {
            $parts->push($part);
        }

        $mass = $parts->sum(fn ($x) => (float) ($x['Mass'] ?? 0));

        $data['Mass'] = $mass > 0 ? $mass : null;
        if ($data['Mass'] === null) {
            $data['Mass'] = $this->item->get('SSCActorPhysicsControllerComponentParams/physType/SEntityActorPhysicsControllerParams@Mass');
        }

        $extractSignatureValues = static function (?Element $signatures): array {
            if (! $signatures) {
                return [];
            }

            $values = [];
            foreach ($signatures->children() as $child) {
                $value = $child->get('value', null);

                if ($value === null) {
                    $nodeValue = trim((string) $child->nodeValue);
                    $value = is_numeric($nodeValue) ? (float) $nodeValue : null;
                }

                if ($value !== null) {
                    $values[] = (float) $value;
                }
            }

            return $values;
        };

        $signatureParams = $this->vehicleWrapper->entity->get('Components/SSCSignatureSystemParams');

        if ($signatureParams) {
            $signatureValues = $extractSignatureValues($signatureParams->get('/baseSignatureParams/SSCSignatureSystemBaseSignatureParams/signatures'));
            $maxSignatureValues = $extractSignatureValues($signatureParams->get('/baseSignatureParams/SSCSignatureSystemBaseSignatureParams/maxSignatures'));
            $radarProps = $signatureParams->get('/radarProperties/SSCRadarContactProperites')?->attributesToArray() ?? [];

            $ir = $signatureValues[0] ?? null;
            $emIdle = $signatureValues[1] ?? null;

            $emMax = $maxSignatureValues[1]
                ?? Arr::get($radarProps, 'emMax')
                ?? Arr::get($radarProps, 'maxEm')
                ?? Arr::get($radarProps, 'maxSignatureEm');

            if ($emMax === null && $emIdle !== null) {
                $emMax = $emIdle;
            }

            if ($ir !== null || $emIdle !== null || $emMax !== null) {
                $data['emission'] = [
                    'ir' => $ir,
                    'em_idle' => $emIdle,
                    'em_max' => $emMax,
                ];
            }
        }

        $portSummary = $this->portSummaryBuilder->build($parts->toArray());
        // Keep port collections intact but expose them as a plain array so services with array
        // type hints (e.g. PropulsionSystemAggregator) can consume them without type errors.
        $portSummary = $this->installItems($portSummary)->all();

        $summary = [
            'QuantumTravel' => $this->quantumTravelCalculator->calculate($portSummary),
            'Propulsion' => $this->propulsionAggregator->aggregate($portSummary),
        ];

        if ($isGravlev || ! $isVehicle) {
            $ifcs = collect($this->vehicleWrapper->loadout)
                ->first(fn ($x) => isset($x['Item']['Components']['IFCSParams']));

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

            if (($loadoutEntry['portName'] ?? '') && str_contains(strtolower($loadoutEntry['portName']), 'shield')) {
                $shieldHp = Arr::get($loadoutEntry, 'Item.Components.SDistortionParams.Maximum');
                $shieldHp ??= Arr::get($loadoutEntry, 'Item.Components.SHealthComponentParams.Health');

                if ($shieldHp !== null) {
                    $data['shield_hp'] = $shieldHp;
                }
            }
        }

        $armorEntry = collect($this->vehicleWrapper->loadout)
            ->first(fn ($x) => ($x['portName'] ?? null) === 'hardpoint_armor' && Arr::has($x, 'Item.Components.SCItemVehicleArmorParams'));

        if ($armorEntry) {
            $armor = Arr::get($armorEntry, 'Item.Components.SCItemVehicleArmorParams', []);

            $data['armor'] = [
                'signal_infrared' => Arr::get($armor, 'signalInfrared'),
                'signal_electromagnetic' => Arr::get($armor, 'signalElectromagnetic'),
                'signal_cross_section' => Arr::get($armor, 'signalCrossSection'),
                'damage_physical' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamagePhysical'),
                'damage_energy' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamageEnergy'),
                'damage_distortion' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamageDistortion'),
                'damage_thermal' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamageThermal'),
                'damage_biochemical' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamageBiochemical'),
                'damage_stun' => Arr::get($armor, 'damageMultiplier.DamageInfo.DamageStun'),
            ];
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
            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0);

        $data['personal_inventory'] = $personalInventory;

        $vehicleInventory = collect($this->vehicleWrapper->loadout)
            ->filter(fn ($entry) => isset($entry['Item']['Components']['SCItemInventoryContainerComponentParams']))
            ->reject(fn ($entry) => str_contains(strtolower($entry['portName'] ?? ''), 'personal'))
            ->reject(fn ($entry) => Arr::get($entry, 'Item.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid'
                || Arr::get($entry, 'Item.Type') === 'Ship.Container.Cargo')
            ->sum(fn ($entry) => ScuCalculator::fromItem($entry['Item']) ?? 0);

        $data['vehicle_inventory'] = $vehicleInventory;

        $summary = array_merge($summary, $this->healthAggregator->aggregateHealth($parts));

        if (! empty($summary['Propulsion'])) {
            $data['fuel'] = [
                'capacity' => isset($summary['Propulsion']['FuelCapacity']) ? $summary['Propulsion']['FuelCapacity'] / 1000 : null,
                'intake_rate' => $summary['Propulsion']['FuelIntakeRate'] ?? null,
                'usage' => [
                    'main' => $summary['Propulsion']['FuelUsage']['Main'] ?? null,
                    'retro' => $summary['Propulsion']['FuelUsage']['Retro'] ?? null,
                    'vtol' => $summary['Propulsion']['FuelUsage']['Vtol'] ?? null,
                    'maneuvering' => $summary['Propulsion']['FuelUsage']['Maneuvering'] ?? null,
                ],
            ];
        }

        if (! empty($summary['QuantumTravel'])) {
            $data['quantum'] = [
                'quantum_speed' => $summary['QuantumTravel']['Speed'] ?? null,
                'quantum_spool_time' => $summary['QuantumTravel']['SpoolTime'] ?? null,
                'quantum_fuel_capacity' => isset($summary['QuantumTravel']['FuelCapacity']) ? $summary['QuantumTravel']['FuelCapacity'] / 1000 : null,
                'quantum_range' => isset($summary['QuantumTravel']['Range']) ? $summary['QuantumTravel']['Range'] / 1000 : null,
            ];
        }

        $summary['MannedTurrets'] = $this->weaponSystemAnalyzer->analyzeTurrets($portSummary['mannedTurrets']);
        $summary['RemoteTurrets'] = $this->weaponSystemAnalyzer->analyzeTurrets($portSummary['remoteTurrets']);

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

    private function installItems(array $portSummary): Collection
    {
        $loadouts = collect($this->vehicleWrapper->loadout);

        return collect($portSummary)->mapWithKeys(function ($items, $portName) use ($loadouts) {
            return [
                $portName => collect($items)->map(function ($item) use ($loadouts) {
                    $loadout = $loadouts->first(fn ($x) => $x['portName'] === $item['Name']);

                    if ($loadout && isset($loadout['Item'])) {
                        $item['InstalledItem'] = $loadout['Item'];
                    }

                    return $item;
                }),
            ];
        });
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
     * @param  array[]  $parts
     */
    private function partList(array $parts): Generator
    {
        $loadouts = collect($this->vehicleWrapper->loadout);

        foreach ($parts as $part) {
            if (isset($part['Parts'])) {
                yield from $this->partList($part['Parts']);
            }

            $loadout = $loadouts->first(fn ($x) => $x['portName'] === $part['Name']);

            if ($loadout && isset($loadout['Item'])) {
                $part['InstalledItem'] = $loadout['Item'];
            }

            $part['Category'] = $this->portClassifierService->classifyPort($part['Port'], $part['InstalledItem'] ?? null)[1];

            yield $part;
        }
    }
}
