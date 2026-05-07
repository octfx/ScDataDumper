<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\Vehicle\ItemTypeResolver;
use Octfx\ScDataDumper\Tests\Fixtures\BuildsTestItems;
use PHPUnit\Framework\TestCase;

final class PortClassifierServiceTest extends TestCase
{
    use BuildsTestItems;

    private PortClassifierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PortClassifierService(new ItemTypeResolver);
    }

    // ------------------------------------------------------------------ //
    //  Early returns                                                      //
    // ------------------------------------------------------------------ //

    public function test_null_port_returns_disabled(): void
    {
        self::assertSame(['DISABLED', 'DISABLED'], $this->service->classifyPort(null, null));
    }

    public function test_empty_types_and_no_installed_item_returns_disabled(): void
    {
        self::assertSame(
            ['DISABLED', 'DISABLED'],
            $this->service->classifyPort(['Types' => []], null),
        );
    }

    public function test_port_with_null_types_and_no_item_returns_disabled(): void
    {
        self::assertSame(
            ['DISABLED', 'DISABLED'],
            $this->service->classifyPort(['PortName' => 'hardpoint'], null),
        );
    }

    // ------------------------------------------------------------------ //
    //  Fuzzy name matching (early checks)                                 //
    // ------------------------------------------------------------------ //

    public function test_tractor_beam_by_port_name(): void
    {
        $port = $this->makePort(types: ['Turret.GunTurret'], portName: 'tractor_beam_mount');

        self::assertSame(['Utility', 'Utility hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_utility_hardpoint_by_port_name(): void
    {
        $port = $this->makePort(types: ['WeaponGun'], portName: 'utility_mount');

        self::assertSame(['Utility', 'Utility hardpoints'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Mining                                                             //
    // ------------------------------------------------------------------ //

    public function test_mining_gun_port(): void
    {
        $port = $this->makePort(types: ['WeaponMining.Gun']);

        self::assertSame(['Mining', 'Mining hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_mining_arm(): void
    {
        $port = $this->makePort(types: ['MiningArm']);

        self::assertSame(['Mining', 'Mining arm'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Turrets                                                            //
    // ------------------------------------------------------------------ //

    public function test_turret_generic(): void
    {
        $port = $this->makePort(types: ['Turret.GunTurret']);

        self::assertSame(['Weapons', 'Weapon hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_turret_remote(): void
    {
        $port = $this->makePort(types: ['Turret.GunTurret'], portName: 'remote_turret');

        self::assertSame(['Weapons', 'Remote turrets'], $this->service->classifyPort($port, null));
    }

    public function test_turret_pdc(): void
    {
        $port = $this->makePort(types: ['Turret.GunTurret'], portName: 'pdc_turret');

        self::assertSame(['Weapons', 'PDC turrets'], $this->service->classifyPort($port, null));
    }

    public function test_manned_turret(): void
    {
        $port = $this->makePort(types: ['TurretBase.MannedTurret']);

        self::assertSame(['Weapons', 'Manned turrets'], $this->service->classifyPort($port, null));
    }

    public function test_manned_turret_mining_argo_mole(): void
    {
        $installedItem = $this->makeInstalledItem(
            'TurretBase',
            'MannedTurret',
            ports: [
                ['type' => 'WeaponMining', 'subType' => 'Gun'],
            ],
        );

        $port = $this->makePort(types: ['TurretBase.MannedTurret'], installedItem: $installedItem);

        self::assertSame(['Mining', 'Mining turrets'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_manned_turret_tractor(): void
    {
        // Port name contains 'tractor' which matches the early fuzzyNameMatch check,
        // so it returns Utility hardpoints, not Utility turrets
        $installedItem = $this->makeInstalledItem('TurretBase', 'MannedTurret', className: 'TRACTOR_Beam');
        $port = $this->makePort(types: ['TurretBase.MannedTurret'], portName: 'tractor_manned', installedItem: $installedItem);

        self::assertSame(['Utility', 'Utility hardpoints'], $this->service->classifyPort($port, $installedItem));
    }

    // ------------------------------------------------------------------ //
    //  Weapons                                                            //
    // ------------------------------------------------------------------ //

    public function test_missile_rack(): void
    {
        $port = $this->makePort(types: ['MissileLauncher.MissileRack']);

        self::assertSame(['Weapons', 'Missile racks'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_mount(): void
    {
        $port = $this->makePort(types: ['WeaponMount']);

        self::assertSame(['Weapons', 'Weapon hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_mount_autonomous_turret(): void
    {
        $installedItem = $this->makeInstalledItem('WeaponGun', 'Gun', ports: [
            ['Types' => ['WeaponController.Gun']],
        ]);

        $port = $this->makePort(types: ['WeaponMount'], installedItem: $installedItem);

        self::assertSame(['Weapons', 'Autonomous turrets'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_weapon_gun(): void
    {
        $port = $this->makePort(types: ['WeaponGun']);

        self::assertSame(['Weapons', 'Weapon hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_gun_autonomous_turret(): void
    {
        $installedItem = $this->makeInstalledItem('WeaponGun', 'Gun', ports: [
            ['Types' => ['WeaponController.Gun']],
        ]);

        $port = $this->makePort(types: ['WeaponGun'], installedItem: $installedItem);

        self::assertSame(['Weapons', 'Autonomous turrets'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_missile(): void
    {
        $port = $this->makePort(types: ['Missile.Missile']);

        self::assertSame(['Weapons', 'Missiles'], $this->service->classifyPort($port, null));
    }

    public function test_emp(): void
    {
        $port = $this->makePort(types: ['EMP']);

        self::assertSame(['Weapons', 'EMP hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_countermeasure(): void
    {
        $port = $this->makePort(types: ['WeaponDefensive.CountermeasureLauncher']);

        self::assertSame(['Weapons', 'Countermeasures'], $this->service->classifyPort($port, null));
    }

    public function test_qig(): void
    {
        $port = $this->makePort(types: ['QuantumInterdictionGenerator']);

        self::assertSame(['Weapons', 'QIG hardpoints'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_defensive_generic(): void
    {
        $port = $this->makePort(types: ['WeaponDefensive']);

        self::assertSame(['Weapons', 'Defensive hardpoints'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Systems                                                            //
    // ------------------------------------------------------------------ //

    public function test_power_plant(): void
    {
        $port = $this->makePort(types: ['PowerPlant']);

        self::assertSame(['Systems', 'Power plants'], $this->service->classifyPort($port, null));
    }

    public function test_cooler(): void
    {
        $port = $this->makePort(types: ['Cooler']);

        self::assertSame(['Systems', 'Coolers'], $this->service->classifyPort($port, null));
    }

    public function test_shield(): void
    {
        $port = $this->makePort(types: ['Shield']);

        self::assertSame(['Systems', 'Shield generators'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_regen_pool(): void
    {
        $port = $this->makePort(types: ['WeaponRegenPool']);

        self::assertSame(['Systems', 'Weapon regen pool'], $this->service->classifyPort($port, null));
    }

    public function test_life_support_by_type(): void
    {
        $port = $this->makePort(types: ['LifeSupportGenerator']);

        self::assertSame(['Systems', 'Life support'], $this->service->classifyPort($port, null));
    }

    public function test_life_support_by_fuzzy_name(): void
    {
        $port = $this->makePort(types: ['SomeUnknownType'], portName: 'lifesupport_generator');

        self::assertSame(['Systems', 'Life support'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Propulsion                                                         //
    // ------------------------------------------------------------------ //

    public function test_fuel_intake(): void
    {
        $port = $this->makePort(types: ['FuelIntake']);

        self::assertSame(['Propulsion', 'Fuel intakes'], $this->service->classifyPort($port, null));
    }

    public function test_fuel_tank(): void
    {
        $port = $this->makePort(types: ['FuelTank']);

        self::assertSame(['Propulsion', 'Fuel tanks'], $this->service->classifyPort($port, null));
    }

    public function test_quantum_fuel_tank_with_sub(): void
    {
        $port = $this->makePort(types: ['QuantumFuelTank.QuantumFuel']);

        self::assertSame(['Propulsion', 'Quantum fuel tanks'], $this->service->classifyPort($port, null));
    }

    public function test_quantum_fuel_tank_generic(): void
    {
        $port = $this->makePort(types: ['QuantumFuelTank']);

        self::assertSame(['Propulsion', 'Quantum fuel tanks'], $this->service->classifyPort($port, null));
    }

    public function test_quantum_drive_with_sub(): void
    {
        $port = $this->makePort(types: ['QuantumDrive.QDrive']);

        self::assertSame(['Propulsion', 'Quantum drives'], $this->service->classifyPort($port, null));
    }

    public function test_quantum_drive_generic(): void
    {
        $port = $this->makePort(types: ['QuantumDrive']);

        self::assertSame(['Propulsion', 'Quantum drives'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Thrusters                                                          //
    // ------------------------------------------------------------------ //

    public function test_main_thruster(): void
    {
        $port = $this->makePort(types: ['MainThruster.Main']);

        self::assertSame(['Thrusters', 'Main thrusters'], $this->service->classifyPort($port, null));
    }

    public function test_main_thruster_retro(): void
    {
        $port = $this->makePort(types: ['MainThruster.Main'], portName: 'retro_thruster');

        self::assertSame(['Thrusters', 'Retro thrusters'], $this->service->classifyPort($port, null));
    }

    public function test_main_thruster_vtol(): void
    {
        $port = $this->makePort(types: ['MainThruster.Main'], portName: 'vtol_engine');

        self::assertSame(['Thrusters', 'VTOL thrusters'], $this->service->classifyPort($port, null));
    }

    public function test_maneuvering_thruster(): void
    {
        $port = $this->makePort(types: ['ManneuverThruster.Gimbal']);

        self::assertSame(['Thrusters', 'Maneuvering thrusters'], $this->service->classifyPort($port, null));
    }

    public function test_maneuvering_thruster_retro(): void
    {
        $port = $this->makePort(types: ['ManneuverThruster.Gimbal'], portName: 'retro_maneuver');

        self::assertSame(['Thrusters', 'Retro thrusters'], $this->service->classifyPort($port, null));
    }

    public function test_maneuvering_thruster_vtol(): void
    {
        $port = $this->makePort(types: ['ManneuverThruster.Gimbal'], portName: 'vtol_maneuver');

        self::assertSame(['Thrusters', 'VTOL thrusters'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Avionics                                                           //
    // ------------------------------------------------------------------ //

    public function test_avionics_motherboard(): void
    {
        $port = $this->makePort(types: ['Avionics.Motherboard']);

        self::assertSame(['Avionics', 'Computers'], $this->service->classifyPort($port, null));
    }

    public function test_radar(): void
    {
        $port = $this->makePort(types: ['Radar']);

        self::assertSame(['Avionics', 'Radars'], $this->service->classifyPort($port, null));
    }

    public function test_radar_short_range(): void
    {
        $port = $this->makePort(types: ['Radar.ShortRangeRadar']);

        self::assertSame(['Avionics', 'Radars'], $this->service->classifyPort($port, null));
    }

    public function test_radar_mid_range(): void
    {
        $port = $this->makePort(types: ['Radar.MidRangeRadar']);

        self::assertSame(['Avionics', 'Radars'], $this->service->classifyPort($port, null));
    }

    public function test_scanner(): void
    {
        $port = $this->makePort(types: ['Scanner']);

        self::assertSame(['Avionics', 'Scanners'], $this->service->classifyPort($port, null));
    }

    public function test_scanner_gun(): void
    {
        $port = $this->makePort(types: ['Scanner.Gun']);

        self::assertSame(['Avionics', 'Scanners'], $this->service->classifyPort($port, null));
    }

    public function test_ping(): void
    {
        $port = $this->makePort(types: ['Ping']);

        self::assertSame(['Avionics', 'Pings'], $this->service->classifyPort($port, null));
    }

    public function test_transponder(): void
    {
        $port = $this->makePort(types: ['Transponder']);

        self::assertSame(['Avionics', 'Transponders'], $this->service->classifyPort($port, null));
    }

    public function test_self_destruct(): void
    {
        $port = $this->makePort(types: ['SelfDestruct']);

        self::assertSame(['Avionics', 'Self destructs'], $this->service->classifyPort($port, null));
    }

    public function test_flight_controller(): void
    {
        $port = $this->makePort(types: ['FlightController']);

        self::assertSame(['Avionics', 'Flight controllers'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Cargo                                                              //
    // ------------------------------------------------------------------ //

    public function test_cargo(): void
    {
        $port = $this->makePort(types: ['Cargo']);

        self::assertSame(['Cargo', 'Cargo grids'], $this->service->classifyPort($port, null));
    }

    public function test_cargo_grid(): void
    {
        $port = $this->makePort(types: ['CargoGrid']);

        self::assertSame(['Cargo', 'Cargo grids'], $this->service->classifyPort($port, null));
    }

    public function test_cargo_container(): void
    {
        $port = $this->makePort(types: ['Container.Cargo']);

        self::assertSame(['Cargo', 'Cargo containers'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Armor                                                              //
    // ------------------------------------------------------------------ //

    public function test_armor(): void
    {
        $port = $this->makePort(types: ['Armor']);

        self::assertSame(['Armor', 'Armor'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Misc                                                               //
    // ------------------------------------------------------------------ //

    public function test_usable(): void
    {
        $port = $this->makePort(types: ['Usable']);

        self::assertSame(['Misc', 'Usables'], $this->service->classifyPort($port, null));
    }

    public function test_room(): void
    {
        $port = $this->makePort(types: ['Room']);

        self::assertSame(['Misc', 'Rooms'], $this->service->classifyPort($port, null));
    }

    public function test_door(): void
    {
        $port = $this->makePort(types: ['Door']);

        self::assertSame(['Misc', 'Doors'], $this->service->classifyPort($port, null));
    }

    public function test_paints(): void
    {
        $port = $this->makePort(types: ['Paints']);

        self::assertSame(['Misc', 'Paints'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Attachments                                                        //
    // ------------------------------------------------------------------ //

    public function test_battery_port(): void
    {
        $port = $this->makePort(types: ['SomeUnknownType'], portName: 'BatteryPort_main');

        self::assertSame(['Attachments', 'Batteries'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_attachment_barrel(): void
    {
        $port = $this->makePort(types: ['WeaponAttachment.Barrel']);

        self::assertSame(['Attachments', 'Weapon attachments'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_attachment_firing_mechanism(): void
    {
        $port = $this->makePort(types: ['WeaponAttachment.FiringMechanism']);

        self::assertSame(['Attachments', 'Weapon attachments'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_attachment_power_array(): void
    {
        $port = $this->makePort(types: ['WeaponAttachment.PowerArray']);

        self::assertSame(['Attachments', 'Weapon attachments'], $this->service->classifyPort($port, null));
    }

    public function test_weapon_attachment_ventilation(): void
    {
        $port = $this->makePort(types: ['WeaponAttachment.Ventilation']);

        self::assertSame(['Attachments', 'Weapon attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_control_panel(): void
    {
        $port = $this->makePort(types: ['ControlPanel.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_misc(): void
    {
        $port = $this->makePort(types: ['Misc.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_button(): void
    {
        $port = $this->makePort(types: ['Button.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_sensor(): void
    {
        $port = $this->makePort(types: ['Sensor.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_lightgroup(): void
    {
        $port = $this->makePort(types: ['Lightgroup.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    public function test_door_attachment_decal(): void
    {
        $port = $this->makePort(types: ['Decal.DoorPart']);

        self::assertSame(['Attachments', 'Door attachments'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Seating                                                            //
    // ------------------------------------------------------------------ //

    public function test_seat(): void
    {
        $port = $this->makePort(types: ['Seat']);

        self::assertSame(['Seating', 'Seats'], $this->service->classifyPort($port, null));
    }

    public function test_seat_access(): void
    {
        $port = $this->makePort(types: ['SeatAccess']);

        self::assertSame(['Seating', 'Seat access'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  Unknown                                                            //
    // ------------------------------------------------------------------ //

    public function test_unknown_returns_unknown(): void
    {
        $port = $this->makePort(types: ['CompletelyUnknownType']);

        self::assertSame(['UNKNOWN', 'UNKNOWN'], $this->service->classifyPort($port, null));
    }

    // ------------------------------------------------------------------ //
    //  guessByInstalledItem (uneditable + installed item path)             //
    // ------------------------------------------------------------------ //

    public function test_guess_life_support_from_installed_item(): void
    {
        $installedItem = $this->makeInstalledItem('LifeSupportGenerator', classification: 'Ship.LifeSupportGenerator');
        $port = $this->makePort(types: [], portName: 'hardpoint', flags: ['$uneditable'], installedItem: $installedItem, uneditable: true);

        self::assertSame(['Systems', 'Life support'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_guess_weapon_gun_from_installed_item(): void
    {
        $installedItem = $this->makeInstalledItem('WeaponGun', 'Gun', classification: 'Ship.WeaponGun.Gun');
        $port = $this->makePort(types: [], portName: 'hardpoint', flags: ['$uneditable'], installedItem: $installedItem, uneditable: true);

        self::assertSame(['Weapons', 'Weapon hardpoints'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_guess_cargo_container_from_installed_item(): void
    {
        $installedItem = $this->makeInstalledItem('Container', 'Cargo', classification: 'Ship.Container.Cargo');
        $port = $this->makePort(types: [], portName: 'hardpoint', flags: ['$uneditable'], installedItem: $installedItem, uneditable: true);

        self::assertSame(['Cargo', 'Cargo containers'], $this->service->classifyPort($port, $installedItem));
    }

    public function test_guess_returns_unknown_when_classifier_unknown(): void
    {
        // When uneditable + installed item but guessByInstalledItem returns null,
        // falls through all type checks and returns UNKNOWN
        $installedItem = $this->makeInstalledItem('UnknownType', classification: null);
        $port = $this->makePort(types: [], portName: 'hardpoint', flags: ['$uneditable'], installedItem: $installedItem, uneditable: true);

        self::assertSame(['UNKNOWN', 'UNKNOWN'], $this->service->classifyPort($port, $installedItem));
    }

    // ------------------------------------------------------------------ //
    //  fuzzyNameMatch                                                     //
    // ------------------------------------------------------------------ //

    public function test_fuzzy_name_match_port_name(): void
    {
        self::assertTrue(PortClassifierService::fuzzyNameMatch(
            ['PortName' => 'tractor_beam'],
            'tractor',
        ));
    }

    public function test_fuzzy_name_match_installed_item_class_name(): void
    {
        self::assertTrue(PortClassifierService::fuzzyNameMatch(
            ['PortName' => 'hardpoint'],
            'remote',
            ['ClassName' => 'RemoteTurret_S2'],
        ));
    }

    public function test_fuzzy_name_match_loadout(): void
    {
        self::assertTrue(PortClassifierService::fuzzyNameMatch(
            ['PortName' => 'hardpoint', 'Loadout' => 'VTOL_Loadout'],
            'vtol',
        ));
    }

    public function test_fuzzy_name_match_no_match(): void
    {
        self::assertFalse(PortClassifierService::fuzzyNameMatch(
            ['PortName' => 'hardpoint'],
            'mining',
        ));
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    /**
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private function makePort(
        array $types = [],
        string $portName = 'hardpoint',
        array $flags = [],
        ?array $installedItem = null,
        bool $uneditable = false,
    ): array {
        return [
            'PortName' => $portName,
            'Types' => $types,
            'Flags' => $flags,
            'Uneditable' => $uneditable,
            'InstalledItem' => $installedItem,
            'Loadout' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ports
     * @return array<string, mixed>
     */
    private function makeInstalledItem(
        string $type,
        ?string $subType = null,
        array $ports = [],
        string $className = '',
        ?string $classification = null,
    ): array {
        $fullType = $type.($subType ? ".$subType" : '');

        return [
            'type' => $type,
            'subType' => $subType,
            'ClassName' => $className,
            'Ports' => $ports,
            'stdItem' => array_filter([
                'Type' => $fullType,
                'Ports' => $ports,
                'ClassName' => $className,
            ]),
            'classification' => $classification,
        ];
    }
}
