<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Generator;
use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\ServiceFactory;

const G = 9.80665;

final class Ship extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    private readonly PortClassifierService $portClassifierService;

    private readonly ?Vehicle $vehicle;

    public function __construct(private readonly VehicleWrapper $vehicleWrapper)
    {
        parent::__construct($this->vehicleWrapper->entity);

        $this->vehicle = $this->vehicleWrapper->vehicle;

        $this->portClassifierService = new PortClassifierService;
    }

    public function toArray(): array
    {
        $attach = $this->vehicleWrapper->entity->getAttachDef();
        $vehicleComponent = $this->get('Components/VehicleComponentParams');

        $vehicleComponentData = [];
        if ($vehicleComponent) {
            $vehicleComponentData = (new Element($vehicleComponent->getNode()))->attributesToArray();
        } else {
            $vehicleComponent = $this->vehicleWrapper->entity->getAttachDef();
        }

        $manufacturer = $vehicleComponent->get('manufacturer');

        $manufacturer = ServiceFactory::getManufacturerService()->getByReference($manufacturer);
        $itemService = ServiceFactory::getItemService();

        $isVehicle = $this->item->get('Components/VehicleComponentParams@vehicleCareer') === '@vehicle_focus_ground' || $vehicleComponent->get('@SubType') === 'Vehicle_GroundVehicle';
        $isGravlev = $this->item->get('Components/VehicleComponentParams@isGravlevVehicle') === '1';

        $data = [
            'UUID' => $this->item->getUuid(),
            'ClassName' => $this->item->getClassName(),
            'Name' => trim(Arr::get($vehicleComponentData, 'vehicleName') ?? $vehicleComponent->get('/Localization/English@Name') ?? $this->item->getClassName()),
            'Description' => $vehicleComponent->get('/English@vehicleDescription') ?? $vehicleComponent->get('/Localization/English@Description', ''),

            'Career' => $vehicleComponent->get('/English@vehicleCareer', ''),
            'Role' => $vehicleComponent->get('/English@vehicleRole', ''),

            'Manufacturer' => $manufacturer ? [
                'Code' => $manufacturer->getCode(),
                'Name' => $manufacturer->get('Localization/English@Name'),
            ] : [],

            'Size' => $attach->get('Size', 0),
            'Width' => $vehicleComponent->get('maxBoundingBoxSize@x', 0),
            'Length' => $vehicleComponent->get('maxBoundingBoxSize@y', 0),
            'Height' => $vehicleComponent->get('maxBoundingBoxSize@z', 0),
            'Crew' => $vehicleComponent->get('crewSize', 1),

            //            'Parts' => [],

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

        //        $loadoutEntries = [];
        //
        //        foreach ($this->item->get('/SEntityComponentDefaultLoadoutParams/loadout')?->children() ?? [] as $loadout) {
        //            $loadoutEntries[] = (new Loadout($loadout))->toArray();
        //        }
        //
        //        $data['LoadoutEntries'] = $loadoutEntries;

        $parts = collect();

        foreach ($this->partList($vehicleParts) as $part) {
            $parts->push($part);
        }

        $mass = $parts->sum(fn ($x) => (float) ($x['Mass'] ?? 0));

        $data['Mass'] = $mass > 0 ? $mass : null;
        if ($data['Mass'] === null) {
            $data['Mass'] = $this->item->get('SSCActorPhysicsControllerComponentParams/physType/SEntityActorPhysicsControllerParams@Mass');
        }

        $portSummary = $this->buildPortSummary($parts->toArray());
        $portSummary = $this->installItems($portSummary);

        $quantumDrive = $portSummary['quantumDrives']->first(fn ($x) => isset($x['InstalledItem']));

        $quantumFuelCapacity = $portSummary['quantumFuelTanks']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits') * 1000);
        $quantumFuelRate = Arr::get($quantumDrive, 'InstalledItem.Components.SCItemQuantumDriveParams.quantumFuelRequirement', 0) / 1e6;
        $quantumDriveSpeed = Arr::get($quantumDrive, 'InstalledItem.Components.SCItemQuantumDriveParams.params.driveSpeed');
        $distanceBetweenPOandArcCorp = 41927351070;

        $summary = [
            'QuantumTravel' => [
                'FuelCapacity' => ($quantumFuelCapacity ?? 0) > 0 ? ($quantumFuelCapacity) : null,
                'Range' => $quantumFuelRate > 0 ? ($quantumFuelCapacity / $quantumFuelRate) : null,
                'Speed' => Arr::get($quantumDrive, 'InstalledItem.Components.SCItemQuantumDriveParams.params.driveSpeed'),
                'SpoolTime' => Arr::get($quantumDrive, 'InstalledItem.Components.SCItemQuantumDriveParams.params.spoolUpTime'),
                'PortOlisarToArcCorpTime' => ! empty($quantumDriveSpeed) ? ($distanceBetweenPOandArcCorp / Arr::get($quantumDrive, 'InstalledItem.Components.SCItemQuantumDriveParams.params.driveSpeed')) : null,
                'PortOlisarToArcCorpFuel' => ($distanceBetweenPOandArcCorp * $quantumFuelRate) > 0 ? ($distanceBetweenPOandArcCorp * $quantumFuelRate) : null,
                'PortOlisarToArcCorpAndBack' => $quantumFuelRate > 0 ? (($quantumFuelCapacity / $quantumFuelRate) / (2 * $distanceBetweenPOandArcCorp)) : null,
            ],

            'Propulsion' => [
                'FuelCapacity' => $portSummary['hydrogenFuelTanks']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0) * 1000),
                'FuelIntakeRate' => $portSummary['hydrogenFuelIntakes']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemFuelIntakeParams.fuelPushRate', 0)),
                'FuelUsage' => [
                    'Main' => $portSummary['mainThrusters']->sum(fn ($x) => (Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.fuelBurnRatePer10KNewton', 0) / 1e4) * Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Retro' => $portSummary['retroThrusters']->sum(fn ($x) => (Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.fuelBurnRatePer10KNewton', 0) / 1e4) * Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Vtol' => $portSummary['vtolThrusters']->sum(fn ($x) => (Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.fuelBurnRatePer10KNewton', 0) / 1e4) * Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Maneuvering' => $portSummary['maneuveringThrusters']->sum(fn ($x) => (Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.fuelBurnRatePer10KNewton', 0) / 1e4) * Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                ],
                'ThrustCapacity' => [
                    'Main' => $portSummary['mainThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Retro' => $portSummary['retroThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Vtol' => $portSummary['vtolThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                    'Maneuvering' => $portSummary['maneuveringThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
                ],
            ],
        ];

        $summary['Propulsion']['IntakeToMainFuelRatio'] = $summary['Propulsion']['FuelUsage']['Main'] > 0 ? $summary['Propulsion']['FuelIntakeRate'] / $summary['Propulsion']['FuelUsage']['Main'] : null;
        $summary['Propulsion']['IntakeToTankCapacityRatio'] = $summary['Propulsion']['FuelCapacity'] > 0 ? $summary['Propulsion']['FuelIntakeRate'] / $summary['Propulsion']['FuelCapacity'] : null;
        $summary['Propulsion']['TimeForIntakesToFillTank'] = $summary['Propulsion']['FuelIntakeRate'] > 0 ? $summary['Propulsion']['FuelCapacity'] / $summary['Propulsion']['FuelIntakeRate'] : null;
        $summary['Propulsion']['ManeuveringTimeTillEmpty'] = ($summary['Propulsion']['FuelUsage']['Main'] > 0 && $summary['Propulsion']['FuelUsage']['Maneuvering'] > 0) ? $summary['Propulsion']['FuelCapacity'] / ($summary['Propulsion']['FuelUsage']['Main'] + $summary['Propulsion']['FuelUsage']['Maneuvering'] / 2 - $summary['Propulsion']['FuelIntakeRate']) : null;

        if ($isGravlev || ! $isVehicle) {
            $ifcs = collect($this->vehicleWrapper->loadout)
                ->first(fn ($x) => isset($x['Item']['Components']['IFCSParams']));

            if ($ifcs) {
                $summary['FlightCharacteristics'] = [
                    'ScmSpeed' => Arr::get($ifcs, 'Item.Components.IFCSParams.scmSpeed'),
                    'MaxSpeed' => Arr::get($ifcs, 'Item.Components.IFCSParams.maxSpeed'),
                    'BoostSpeedForward' => Arr::get($ifcs, 'Item.Components.IFCSParams.boostSpeedForward'),
                    'BoostSpeedBackward' => Arr::get($ifcs, 'Item.Components.IFCSParams.boostSpeedBackward'),
                    'Acceleration' => [
                        'Main' => $summary['Propulsion']['ThrustCapacity']['Main'] / $data['Mass'],
                        'Retro' => $summary['Propulsion']['ThrustCapacity']['Retro'] / $data['Mass'],
                        'Vtol' => $summary['Propulsion']['ThrustCapacity']['Vtol'] / $data['Mass'],
                        'Maneuvering' => $summary['Propulsion']['ThrustCapacity']['Maneuvering'] / $data['Mass'],
                    ],
                    'AccelerationG' => [
                        'Main' => $summary['Propulsion']['ThrustCapacity']['Main'] / $data['Mass'] / G,
                        'Retro' => $summary['Propulsion']['ThrustCapacity']['Retro'] / $data['Mass'] / G,
                        'Vtol' => $summary['Propulsion']['ThrustCapacity']['Vtol'] / $data['Mass'] / G,
                        'Maneuvering' => $summary['Propulsion']['ThrustCapacity']['Maneuvering'] / $data['Mass'] / G,
                    ],
                    'Pitch' => Arr::get($ifcs, 'Item.Components.IFCSParams.maxAngularVelocity.x'),
                    'Yaw' => Arr::get($ifcs, 'Item.Components.IFCSParams.maxAngularVelocity.z'),
                    'Roll' => Arr::get($ifcs, 'Item.Components.IFCSParams.maxAngularVelocity.y'),
                    'PitchBoostMultiplier' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnAngVelocityMultiplier.x'),
                    'YawBoostMultiplier' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnAngVelocityMultiplier.z'),
                    'RollBoostMultiplier' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnAngVelocityMultiplier.y'),
                    'Afterburner' => [
                        'PreDelayTime' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnerPreDelayTime'),
                        'RampUpTime' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnerRampUpTime'),
                        'RampDownTime' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.afterburnerRampDownTime'),
                        'Capacitor' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorMax'),
                        'IdleCost' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorAfterburnerIdleCost'),
                        'LinearCost' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorAfterburnerLinearCost'),
                        'AngularCost' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorAfterburnerAngularCost'),
                        'RegenPerSec' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorRegenPerSec'),
                        'RegenDelayAfterUse' => Arr::get($ifcs, 'Item.Components.IFCSParams.afterburner.capacitorRegenDelayAfterUse'),
                    ],
                ];

                $summary['FlightCharacteristics']['ZeroToScm'] = $summary['FlightCharacteristics']['Acceleration']['Main'] > 0 ? $summary['FlightCharacteristics']['ScmSpeed'] / $summary['FlightCharacteristics']['Acceleration']['Main'] : null;
                $summary['FlightCharacteristics']['ZeroToMax'] = $summary['FlightCharacteristics']['Acceleration']['Main'] > 0 ? $summary['FlightCharacteristics']['MaxSpeed'] / $summary['FlightCharacteristics']['Acceleration']['Main'] : null;
                $summary['FlightCharacteristics']['ScmToZero'] = $summary['FlightCharacteristics']['Acceleration']['Retro'] > 0 ? $summary['FlightCharacteristics']['ScmSpeed'] / $summary['FlightCharacteristics']['Acceleration']['Retro'] : null;
                $summary['FlightCharacteristics']['MaxToZero'] = $summary['FlightCharacteristics']['Acceleration']['Retro'] > 0 ? $summary['FlightCharacteristics']['MaxSpeed'] / $summary['FlightCharacteristics']['Acceleration']['Retro'] : null;
            }
        }

        if ($isVehicle) {
            unset($summary['Propulsion'], $summary['QuantumTravel']);

            if ($this->vehicleWrapper->vehicle?->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed')) {
                $summary['DriveCharacteristics'] = [
                    'TopSpeed' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed'),
                    'ReverseSpeed' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@reverseSpeed'),
                    'Acceleration' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@acceleration'),
                    'Decceleration' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@decceleration'),

                    'ZeroToMax' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed') / $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@acceleration'),
                    'ZeroToReverse' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@reverseSpeed') / $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@acceleration'),

                    'MaxToZero' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed') / $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@decceleration'),
                    'ReverseToZero' => $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@reverseSpeed') / $this->vehicleWrapper->vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@decceleration'),
                ];
                // @TODO: This whole thing needs to be validated
            } elseif ($this->vehicleWrapper->vehicle?->get('MovementParams/PhysicalWheeled/PhysicsParams@wWheelsMax')) {
                $wWheelsMax = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams@wWheelsMax');
                $brakeTorque = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams@brakeTorque');
                $torqueScale = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Engine@torqueScale');
                $gearFirst = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Gears@first');
                $gearReverse = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Gears@reverse');
                $mass = $data['Mass'];

                // Spartan fix?
                if ($wWheelsMax === 26000000.0) {
                    $wWheelsMax = 26;
                }

                $wheelRadius = $this->vehicleWrapper->vehicle->get('//SubPartWheel@rimRadius');

                $peakTorque = null;
                $torqueTable = $this->vehicleWrapper->vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Engine/RPMTorqueTable')?->children() ?? [];
                foreach ($torqueTable as $entry) {
                    /** @var $entry Element */
                    $torque = $entry->get('torque', 0);
                    $peakTorque = max($peakTorque ?? $torque, $torque);
                }

                if ($wheelRadius && $wWheelsMax && $mass && $brakeTorque && $peakTorque && $torqueScale && $gearFirst && $gearReverse) {
                    $topSpeed = $wWheelsMax * $wheelRadius; // m/s
                    $topSpeedKph = $topSpeed * 3.6;

                    $reverseSpeed = $wWheelsMax * $wheelRadius; // m/s
                    $reverseSpeedKph = $reverseSpeed * 3.6;

                    // This does not seem correct, but ohwell
                    $wheelTorque = $peakTorque * $torqueScale * $gearFirst;
                    $force = $wheelTorque / $wheelRadius;
                    $acceleration = $force / $mass;

                    $brakeForce = $brakeTorque / $wheelRadius;
                    $deceleration = $brakeForce / $mass;

                    $summary['DriveCharacteristics'] = [
                        'TopSpeed' => round($topSpeedKph, 2),
                        'ReverseSpeed' => round($reverseSpeedKph, 2),
                        'Acceleration' => round($acceleration, 4),
                        'Decceleration' => round($deceleration, 4),
                        'ZeroToMax' => round($topSpeed / $acceleration, 2),
                        'ZeroToReverse' => round($reverseSpeed / $acceleration, 2),
                        'MaxToZero' => round($topSpeed / $deceleration, 2),
                        'ReverseToZero' => round($reverseSpeed / $deceleration, 2),
                    ];
                } else {
                    $summary['DriveCharacteristics'] = [
                        'TopSpeed' => null,
                        'ReverseSpeed' => null,
                        'Acceleration' => null,
                        'Decceleration' => null,
                        'ZeroToMax' => null,
                        'ZeroToReverse' => null,
                        'MaxToZero' => null,
                        'ReverseToZero' => null,
                    ];
                }
            }
        }

        foreach ($this->vehicleWrapper->loadout as $loadoutEntry) {
            if (Arr::has($loadoutEntry, 'Item.Components.SCItemShieldEmitterParams.FaceType')) {
                $data['ShieldFaceType'] = Arr::get($loadoutEntry, 'Item.Components.SCItemShieldEmitterParams.FaceType');
            }
        }
        $cargoGrids = collect($this->vehicleWrapper->loadout)
            ->flatMap(function ($entry) {
                return $this->extractCargoGrids($entry);
            });

        $cargoCapacity = $cargoGrids->sum(function ($item) {
            $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x', 0);
            $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y', 0);
            $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z', 0);

            return ($dimX * $dimY * $dimZ) / M_TO_SCU_UNIT;
        });

        $standardisedCargoGrids = $cargoGrids
            ->map(function ($item) use ($itemService) {
                return $itemService->getByReference($item['__ref']);
            })
            ->filter(fn ($x) => $x !== null)
            ->map(function ($item) {
                return (new InventoryContainer($item))->toArray();
            })
            ->filter(fn ($x) => $x !== null);

        // Add ResourceContainer-based cargo
        $cargoCapacity += collect($this->vehicleWrapper->loadout)
            ->filter(fn ($x) => (isset($x['Item']['Components']['ResourceContainer']) && $x['Item']['Type'] === 'Ship.Container.Cargo'))
            ->sum(fn ($x) => Arr::get($x, 'Item.Components.ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0));

        $summary['Cargo'] = $cargoCapacity;
        $summary['CargoGrids'] = $standardisedCargoGrids;
        $summary['CargoSizeLimits'] = $this->calculateCargoGridSizeLimits($standardisedCargoGrids);

        $summary['Health'] = $parts->filter(fn ($x) => ($x['MaximumDamage'] ?? 0) > 0)->sum(fn ($x) => $x['MaximumDamage']);
        $summary['DamageBeforeDestruction'] = $parts->filter(fn ($x) => ($x['ShipDestructionDamage'] ?? 0) > 0)->mapWithKeys(fn ($x) => [$x['Name'] => $x['ShipDestructionDamage']]);
        $summary['DamageBeforeDetach'] = $parts->filter(fn ($x) => ($x['PartDetachDamage'] ?? 0) > 0 && $x['ShipDestructionDamage'] === null)->mapWithKeys(fn ($x) => [$x['Name'] => $x['PartDetachDamage']]);

        $summary['MannedTurrets'] = $portSummary['mannedTurrets']->map(fn ($x) => $this->calculateWeaponFitting($x['Port']))->toArray();
        $summary['RemoteTurrets'] = $portSummary['remoteTurrets']->map(fn ($x) => $this->calculateWeaponFitting($x['Port']))->toArray();

        $data = array_merge($data, $summary);

        $this->processArray($data);

        return $this->removeNullValues($data);
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

    private function removeNullValues($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);
            }
            if ($value === null || (is_array($value) && empty($value))) {
                unset($array[$key]);
            }
        }

        return $array;
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

    /**
     * Builds a summary of all port types on the ship
     *
     * @param  array  $parts  The ship parts to analyze
     * @return array Port summary with categorized ports
     */
    private function buildPortSummary(array $parts): array
    {
        $portSummary = [];

        // @TODO: Pilot / Mining / Utility
        // Player controlled hardpoints (those not in a turret)
        $portSummary['pilotHardpoints'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Weapon hardpoints', true,
            fn ($x) => in_array(($x['Category'] ?? ''), ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets']))
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['miningHardpoints'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Mining hardpoints', true,
            fn ($x) => in_array(($x['Category'] ?? ''), ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets']))
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['utilityHardpoints'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Utility hardpoints', true,
            fn ($x) => in_array(($x['Category'] ?? ''), ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets']))
            ->map(fn ($x) => $x[0])
            ->toArray();

        // Turrets
        $portSummary['miningTurrets'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Mining turrets', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['mannedTurrets'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Manned turrets', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['remoteTurrets'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Remote turrets', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['utilityTurrets'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Utility turrets', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        // Other hardpoints
        $portSummary['interdictionHardpoints'] = $this->findItemPorts($parts,
            fn ($x) => in_array(($x['Category'] ?? ''), ['EMP hardpoints', 'QIG hardpoints']), true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['missileRacks'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Missile racks', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['powerPlants'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Power plants', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['coolers'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Coolers', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['shields'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Shield generators', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['cargoGrids'] = $this->findItemPorts($parts,
            fn ($x) => isset($x['InstalledItem']['InventoryContainer']), true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['countermeasures'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Countermeasures', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['mainThrusters'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Main thrusters', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['retroThrusters'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Retro thrusters', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['vtolThrusters'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'VTOL thrusters', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['maneuveringThrusters'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Maneuvering thrusters', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['hydrogenFuelIntakes'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Fuel intakes', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['hydrogenFuelTanks'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Fuel tanks', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['quantumDrives'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Quantum drives', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['quantumFuelTanks'] = $this->findItemPorts($parts, fn ($x) => ($x['Category'] ?? '') === 'Quantum fuel tanks', true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        $portSummary['avionics'] = $this->findItemPorts($parts,
            fn ($x) => in_array(($x['Category'] ?? ''), ['Scanners', 'Pings', 'Radars', 'Transponders', 'FlightControllers']), true)
            ->map(fn ($x) => $x[0])
            ->toArray();

        return $portSummary;
    }

    /**
     * Finds item ports in the parts list that match the given predicate
     *
     * @param  array  $parts  List of parts to search through
     * @param  callable  $predicate  Function to determine if an item port matches
     * @param  bool  $stopOnFind  Whether to stop searching once a match is found
     * @param  callable|null  $stopPredicate  Optional function to determine if search should stop
     * @param  int  $depth  Current recursion depth
     * @return Collection Collection of matched item ports with their depth
     */
    private function findItemPorts(array $parts, callable $predicate, bool $stopOnFind = false, ?callable $stopPredicate = null, int $depth = 0): Collection
    {
        $results = collect();

        foreach ($parts as $part) {
            if (isset($part['Port'])) {
                if ($stopPredicate !== null && $stopPredicate($part)) {
                    continue;
                }

                if ($predicate($part)) {
                    $results->push([$part, $depth]);
                    if ($stopOnFind) {
                        continue;
                    }
                }

                if (isset($part['Port']['InstalledItem'])) {
                    $itemMatches = $this->findItemPortsInItem($part['Port']['InstalledItem'], $predicate, $stopOnFind, $stopPredicate, $depth + 1);
                    $results = $results->merge($itemMatches);
                }
            }

            if (isset($part['Parts'])) {
                $partMatches = $this->findItemPorts($part['Parts'], $predicate, $stopOnFind, $stopPredicate, $depth + 1);
                $results = $results->merge($partMatches);
            }
        }

        return $results;
    }

    /**
     * Finds item ports in an installed item that match the given predicate
     *
     * @param  array  $item  The item to search through
     * @param  callable  $predicate  Function to determine if an item port matches
     * @param  bool  $stopOnFind  Whether to stop searching once a match is found
     * @param  callable|null  $stopPredicate  Optional function to determine if search should stop
     * @param  int  $depth  Current recursion depth
     * @return Collection Collection of matched item ports with their depth
     */
    private function findItemPortsInItem(array $item, callable $predicate, bool $stopOnFind, ?callable $stopPredicate, int $depth): Collection
    {
        $results = collect();

        if (isset($item['Ports'])) {
            foreach ($item['Ports'] as $port) {
                if ($stopPredicate !== null && $stopPredicate($port)) {
                    continue;
                }

                if ($predicate($port)) {
                    $results->push([$port, $depth]);
                    if ($stopOnFind) {
                        continue;
                    }
                }

                if (isset($port['InstalledItem'])) {
                    $matches = $this->findItemPortsInItem($port['InstalledItem'], $predicate, $stopOnFind, $stopPredicate, $depth + 1);
                    $results = $results->merge($matches);
                }
            }
        }

        return $results;
    }

    private function calculateWeaponFitting(array $port): array
    {
        if (
            (! empty($port['Uneditable']) || ! $this->acceptsWeapon($port)) &&
            ($this->isTurret($port) || $this->isGimbal($port))
        ) {
            return [
                'Size' => $port['Size'],
                'Gimballed' => $this->isGimbal($port),
                'Turret' => $this->isTurret($port),
                'WeaponSizes' => $this->listTurretPortSizes($port),
            ];
        }

        return [
            'Size' => $port['Size'],
            'Fixed' => true,
            'WeaponSizes' => [$port['Size']],
        ];
    }

    private function isGimbal(array $port): bool
    {
        return isset($port['InstalledItem']['Type']) &&
            $port['InstalledItem']['Type'] === 'Turret.GunTurret';
    }

    private function isTurret(array $port): bool
    {
        $types = [
            'Turret.BallTurret',
            'Turret.CanardTurret',
            'Turret.MissileTurret',
            'Turret.NoseMounted',
            'TurretBase.MannedTurret',
            'TurretBase.Unmanned',
        ];

        return isset($port['InstalledItem']['Type']) &&
            in_array($port['InstalledItem']['Type'], $types, true);
    }

    private function acceptsWeapon(array $port): bool
    {
        if (! isset($port['Types']) || ! is_array($port['Types'])) {
            return false;
        }

        $acceptedTypes = ['WeaponGun', 'WeaponGun.Gun', 'WeaponMining.Gun'];
        foreach ($acceptedTypes as $type) {
            if (in_array($type, $port['Types'], true)) {
                return true;
            }
        }

        return false;
    }

    private function listTurretPortSizes(array $port): array
    {
        $sizes = [];
        if (! isset($port['InstalledItem']['Ports']) || ! is_array($port['InstalledItem']['Ports'])) {
            return $sizes;
        }

        foreach ($port['InstalledItem']['Ports'] as $subPort) {
            if ($this->acceptsWeapon($subPort)) {
                $sizes[] = $subPort['Size'];
            }
        }

        return $sizes;
    }

    /**
     * Recursively calculates cargo capacity for a top-level load-out entry.
     */
    private function cargoFromLoadout(array $loadout): float
    {
        $capacity = 0.0;

        // 1) process the item mounted in this port
        if (isset($loadout['Item']) && is_array($loadout['Item'])) {
            $capacity += $this->cargoFromItem($loadout['Item']);
        }

        // 2) some load-out entries already expose nested entries at this level
        if (isset($loadout['entries']) && is_array($loadout['entries'])) {
            foreach ($loadout['entries'] as $entry) {
                $capacity += $this->cargoFromLoadout($entry);
            }
        }

        return $capacity;
    }

    /**
     * Recursively calculates cargo capacity inside an item.
     *
     * Only items whose attach-def type is **CargoGrid**
     * (Components.SAttachableComponentParams.AttachDef.Type === 'CargoGrid')
     * contribute to the total. The method still drills into nested default
     * load-outs so that descendant items that *are* cargo-grids are included.
     */
    private function cargoFromItem(array $item): float
    {
        $capacity = 0.0;

        // Check whether THIS item is a cargo-grid
        $isCargoGrid = Arr::get(
            $item,
            'Components.SAttachableComponentParams.AttachDef.Type'
        ) === 'CargoGrid';

        // If it is, read its interior dimensions and convert to SCU
        if (
            $isCargoGrid &&
            isset($item['Components']['SCItemInventoryContainerComponentParams'])
        ) {
            $dimX = Arr::get(
                $item,
                'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x',
                0
            );
            $dimY = Arr::get(
                $item,
                'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y',
                0
            );
            $dimZ = Arr::get(
                $item,
                'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z',
                0
            );

            $capacity += ($dimX * $dimY * $dimZ) / M_TO_SCU_UNIT;
        }

        $manualEntries = Arr::get(
            $item,
            'Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries',
            []
        );

        foreach ($manualEntries as $entry) {
            // InstalledItem wrapper one or more actual items
            if (isset($entry['InstalledItem']) && is_array($entry['InstalledItem'])) {
                $capacity += $this->cargoFromItem($entry['InstalledItem']);
            }

            // Some entries expose another "entries" array directly
            if (isset($entry['entries']) && is_array($entry['entries'])) {
                foreach ($entry['entries'] as $subEntry) {
                    $capacity += $this->cargoFromLoadout($subEntry);
                }
            }
        }

        return $capacity;
    }

    private function extractCargoGrids(array $loadout): Collection
    {
        $grids = collect();

        if (
            Arr::get($loadout, 'Item.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid' &&
            isset($loadout['Item']['Components']['SCItemInventoryContainerComponentParams'])
        ) {
            $grids->push($loadout['Item']);
        }

        if (! empty($loadout['entries']) && is_array($loadout['entries'])) {
            foreach ($loadout['entries'] as $entry) {
                $grids = $grids->merge($this->extractCargoGrids($entry));
            }
        }

        $manualEntries = Arr::get($loadout, 'Item.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
        foreach ($manualEntries as $entry) {
            if (isset($entry['InstalledItem'])) {
                $grids = $grids->merge($this->extractCargoGrids(['Item' => $entry['InstalledItem']]));
            }

            if (! empty($entry['entries']) && is_array($entry['entries'])) {
                foreach ($entry['entries'] as $subEntry) {
                    $grids = $grids->merge($this->extractCargoGrids($subEntry));
                }
            }
        }

        return $grids;
    }

    private function calculateCargoGridSizeLimits(Collection $cargoGrids): array
    {
        $minVolumeGrid = $cargoGrids
            ->filter(fn ($grid) => isset($grid['minSize']['x'], $grid['minSize']['y'], $grid['minSize']['z']))
            ->sortBy(fn ($grid) => $grid['minSize']['x'] * $grid['minSize']['y'] * $grid['minSize']['z'])
            ->first();

        $maxVolumeGrid = $cargoGrids
            ->filter(fn ($grid) => isset($grid['maxSize']['x'], $grid['maxSize']['y'], $grid['maxSize']['z']))
            ->sortByDesc(fn ($grid) => $grid['maxSize']['x'] * $grid['maxSize']['y'] * $grid['maxSize']['z'])
            ->first();

        return [
            'MinSize' => $minVolumeGrid['minSize'] ?? null,
            'MaxSize' => $maxVolumeGrid['maxSize'] ?? null,
        ];
    }
}
