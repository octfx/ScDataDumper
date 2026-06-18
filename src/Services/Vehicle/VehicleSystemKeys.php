<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

final class VehicleSystemKeys
{
    public const array ALL_KEYS = [
        'Shields',
        'QuantumDrives',
        'JumpDrives',
        'FlightControllers',
        'Thrusters',
        'QuantumFuelTanks',
        'HydrogenFuelTanks',
        'FuelIntakes',
        'Coolers',
        'PowerPlants',
        'Armors',
        'Weapons',
        'WeaponMounts',
        'MissileRacks',
        'Missiles',
        'MannedTurrets',
        'RemoteTurrets',
        'PdcTurrets',
        'Radars',
        'LifeSupport',
        'CounterMeasures',
        'Paints',
        'Mining',
        'Salvage',
        'TractorBeams',
        'Emps',
        'Qeds',
        'Modules',
        'DockedVehicles',
        'AiModules',
        'CargoGrids',
        'WeaponLockers',
    ];

    /**
     * Required fields on every system bucket: Summary and Ports.
     *
     * @var list<string>
     */
    public const array BUCKET_KEYS = ['Summary', 'Ports'];

    /**
     * Required fields on every system port reference object.
     *
     * @var list<string>
     */
    public const array PORT_REF_KEYS = [
        'PortId',
        'HardpointName',
        'Type',
        'SubType',
        'UUID',
        'ClassName',
        'ParentPortId',
        'RootPortId',
        'Path',

    ];

    /**
     * Required identity fields on every Loadout entry (including nested).
     *
     * @var list<string>
     */
    public const array LOADOUT_IDENTITY_KEYS = [
        'PortId',
        'ParentPortId',
        'RootPortId',
        'Path',
    ];
}
