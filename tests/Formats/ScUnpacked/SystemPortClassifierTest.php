<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex;
use Octfx\ScDataDumper\Services\Vehicle\SystemPortClassifier;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\SystemPortClassifier
 */
final class SystemPortClassifierTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Helper: annotate -> index -> classify pipeline
    // ------------------------------------------------------------------ //

    /**
     * Runs the full pipeline: raw loadout -> annotate -> index -> classify.
     *
     * @param  list<array<string, mixed>>  $rawLoadout
     * @return array<string, list<array<string, mixed>>>
     */
    private function classify(array $rawLoadout): array
    {
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($rawLoadout);
        $index = (new RecursiveLoadoutPortIndex)->build($annotated);

        return (new SystemPortClassifier)->classify($index);
    }

    /**
     * Asserts that a classification result contains a system key with a port
     * matching the given UUID.
     */
    private function assertBucketContainsUuid(
        array $classification,
        string $systemKey,
        string $expectedUuid,
        string $message = '',
    ): void {
        $message = $message ?: "System '{$systemKey}' must contain a port with UUID '{$expectedUuid}'";
        self::assertArrayHasKey($systemKey, $classification, "System key '{$systemKey}' must exist in classification");
        $uuids = array_map(fn (array $ref) => $ref['UUID'] ?? null, $classification[$systemKey]);
        self::assertContains($expectedUuid, $uuids, $message);
    }

    /**
     * Asserts that a classification result's system key does NOT contain a port
     * matching the given UUID.
     */
    private function assertBucketDoesNotContainUuid(
        array $classification,
        string $systemKey,
        string $forbiddenUuid,
        string $message = '',
    ): void {
        $message = $message ?: "System '{$systemKey}' must NOT contain a port with UUID '{$forbiddenUuid}'";
        if (! isset($classification[$systemKey])) {
            return; // key doesn't exist, so it definitely doesn't contain the UUID
        }
        $uuids = array_map(fn (array $ref) => $ref['UUID'] ?? null, $classification[$systemKey]);
        self::assertNotContains($forbiddenUuid, $uuids, $message);
    }

    // ------------------------------------------------------------------ //
    //  Fixture 1: Quantum drive with nested jump drive
    // ------------------------------------------------------------------ //

    private function quantumDriveWithJumpDriveFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_AEGS_Spectral',
                'UUID' => 'qd-1',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_Jump_Drive',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'ClassName' => 'JDRV_ACME',
                        'UUID' => 'jd-1',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];
    }

    public function test_quantum_drive_in_quantum_drives_bucket(): void
    {
        $result = $this->classify($this->quantumDriveWithJumpDriveFixture());

        $this->assertBucketContainsUuid($result, 'QuantumDrives', 'qd-1');
    }

    public function test_nested_jump_drive_in_jump_drives_bucket(): void
    {
        $result = $this->classify($this->quantumDriveWithJumpDriveFixture());

        $this->assertBucketContainsUuid($result, 'JumpDrives', 'jd-1');
    }

    public function test_jump_drive_not_in_quantum_drives(): void
    {
        $result = $this->classify($this->quantumDriveWithJumpDriveFixture());

        $this->assertBucketDoesNotContainUuid($result, 'QuantumDrives', 'jd-1',
            'Jump drive must not appear in QuantumDrives');
    }

    public function test_quantum_fuel_tanks_empty_when_no_tanks(): void
    {
        $result = $this->classify($this->quantumDriveWithJumpDriveFixture());

        // No QuantumFuelTanks entries in this fixture
        self::assertFalse(
            isset($result['QuantumFuelTanks']) && ! empty($result['QuantumFuelTanks']),
            'QuantumFuelTanks should be empty or absent when no fuel tank ports exist'
        );
    }

    public function test_quantum_interdiction_generator_in_qeds_bucket(): void
    {
        $result = $this->classify([
            [
                'HardpointName' => 'hardpoint_qig',
                'Type' => 'QuantumInterdictionGenerator.UNDEFINED',
                'ClassName' => 'QIG_TEST',
                'UUID' => 'qig-1',
                'Loadout' => [],
            ],
        ]);

        $this->assertBucketContainsUuid($result, 'Qeds', 'qig-1');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 2: Turret with gimbal mount and weapon
    // ------------------------------------------------------------------ //

    private function turretWithGimbalAndWeaponFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_turret_back_rear',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_Manned',
                'UUID' => 'turret-1',
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_left',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => 'VariPuck_Gimbal_S4',
                        'UUID' => 'gimbal-1',
                        'Loadout' => [
                            [
                                'HardpointName' => 'hardpoint_class_2',
                                'Type' => 'WeaponGun.Gun',
                                'ClassName' => 'KLWE_LaserRepeater_S4',
                                'UUID' => 'weapon-1',
                                'Loadout' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_turret_root_in_manned_turrets(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketContainsUuid($result, 'MannedTurrets', 'turret-1');
    }

    public function test_gimbal_mount_in_weapon_mounts(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketContainsUuid($result, 'WeaponMounts', 'gimbal-1');
    }

    public function test_gimbal_not_in_manned_turrets(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketDoesNotContainUuid($result, 'MannedTurrets', 'gimbal-1',
            'Gimbal must not appear in MannedTurrets');
    }

    public function test_gimbal_not_in_weapons(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketDoesNotContainUuid($result, 'Weapons', 'gimbal-1',
            'Gimbal must not appear in Weapons');
    }

    public function test_actual_weapon_in_weapons(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketContainsUuid($result, 'Weapons', 'weapon-1');
    }

    public function test_weapon_not_in_weapon_mounts(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketDoesNotContainUuid($result, 'WeaponMounts', 'weapon-1',
            'Actual weapon must not appear in WeaponMounts');
    }

    public function test_weapon_not_in_manned_turrets(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        $this->assertBucketDoesNotContainUuid($result, 'MannedTurrets', 'weapon-1',
            'Actual weapon must not appear in MannedTurrets');
    }

    public function test_manned_turrets_has_exactly_one_entry(): void
    {
        $result = $this->classify($this->turretWithGimbalAndWeaponFixture());

        self::assertArrayHasKey('MannedTurrets', $result);
        self::assertCount(1, $result['MannedTurrets'], 'Only the turret root should be in MannedTurrets');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 3: Missile rack with missiles
    // ------------------------------------------------------------------ //

    private function missileRackWithMissilesFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_missile_rack',
                'Type' => 'MissileLauncher.MissileRack',
                'ClassName' => 'MSLI_Arctic_Mk2',
                'UUID' => 'rack-1',
                'Loadout' => [
                    [
                        'HardpointName' => 'missile_01',
                        'Type' => 'Missile.Guided',
                        'ClassName' => 'EMSD_Firebird',
                        'UUID' => 'missile-1',
                        'Loadout' => [],
                    ],
                    [
                        'HardpointName' => 'missile_02',
                        'Type' => 'Missile.Guided',
                        'ClassName' => 'EMSD_Firebird',
                        'UUID' => 'missile-2',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];
    }

    public function test_missile_rack_in_missile_racks_bucket(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        $this->assertBucketContainsUuid($result, 'MissileRacks', 'rack-1');
    }

    public function test_actual_missiles_in_missiles_bucket(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        $this->assertBucketContainsUuid($result, 'Missiles', 'missile-1');
        $this->assertBucketContainsUuid($result, 'Missiles', 'missile-2');
    }

    public function test_missile_racks_count_is_one(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        self::assertArrayHasKey('MissileRacks', $result);
        self::assertCount(1, $result['MissileRacks']);
    }

    public function test_missiles_count_is_two(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        self::assertArrayHasKey('Missiles', $result);
        self::assertCount(2, $result['Missiles']);
    }

    public function test_rack_not_in_missiles(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        $this->assertBucketDoesNotContainUuid($result, 'Missiles', 'rack-1',
            'Missile rack must not appear in Missiles');
    }

    public function test_missiles_not_in_missile_racks(): void
    {
        $result = $this->classify($this->missileRackWithMissilesFixture());

        $this->assertBucketDoesNotContainUuid($result, 'MissileRacks', 'missile-1',
            'Actual missile must not appear in MissileRacks');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 4: Multiple simple port types
    // ------------------------------------------------------------------ //

    private function multiplePortTypesFixture(): array
    {
        return [
            ['HardpointName' => 'shield_1', 'Type' => 'Shield.UNDEFINED', 'ClassName' => 'SHLD_Generic', 'UUID' => 's1', 'Loadout' => []],
            ['HardpointName' => 'shield_2', 'Type' => 'Shield.UNDEFINED', 'ClassName' => 'SHLD_Generic', 'UUID' => 's2', 'Loadout' => []],
            ['HardpointName' => 'cooler_1', 'Type' => 'Cooler.UNDEFINED', 'ClassName' => 'CLLR_Generic', 'UUID' => 'c1', 'Loadout' => []],
            ['HardpointName' => 'power_plant_1', 'Type' => 'PowerPlant.UNDEFINED', 'ClassName' => 'POWR_Generic', 'UUID' => 'pp1', 'Loadout' => []],
            ['HardpointName' => 'radar_1', 'Type' => 'Radar.UNDEFINED', 'ClassName' => 'RADR_Generic', 'UUID' => 'r1', 'Loadout' => []],
            ['HardpointName' => 'armor_1', 'Type' => 'Armor.UNDEFINED', 'ClassName' => 'ARMR_Generic', 'UUID' => 'a1', 'Loadout' => []],
            ['HardpointName' => 'fc_1', 'Type' => 'FlightController.UNDEFINED', 'ClassName' => 'FC_Generic', 'UUID' => 'fc1', 'Loadout' => []],
        ];
    }

    public function test_multiple_shields_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        self::assertArrayHasKey('Shields', $result);
        self::assertCount(2, $result['Shields']);
        $uuids = array_map(fn (array $r) => $r['UUID'], $result['Shields']);
        self::assertContains('s1', $uuids);
        self::assertContains('s2', $uuids);
    }

    public function test_cooler_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        $this->assertBucketContainsUuid($result, 'Coolers', 'c1');
    }

    public function test_power_plant_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        $this->assertBucketContainsUuid($result, 'PowerPlants', 'pp1');
    }

    public function test_radar_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        $this->assertBucketContainsUuid($result, 'Radars', 'r1');
    }

    public function test_armor_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        $this->assertBucketContainsUuid($result, 'Armors', 'a1');
    }

    public function test_flight_controller_classified(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        $this->assertBucketContainsUuid($result, 'FlightControllers', 'fc1');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 5: Fuel tanks and intakes
    // ------------------------------------------------------------------ //

    private function fuelSystemsFixture(): array
    {
        return [
            ['HardpointName' => 'qfuel_1', 'Type' => 'QuantumFuelTank.UNDEFINED', 'ClassName' => 'QFT_Generic', 'UUID' => 'qft1', 'Loadout' => []],
            ['HardpointName' => 'hfuel_1', 'Type' => 'FuelTank.UNDEFINED', 'ClassName' => 'FT_Generic', 'UUID' => 'ft1', 'Loadout' => []],
            ['HardpointName' => 'intake_1', 'Type' => 'FuelIntake.UNDEFINED', 'ClassName' => 'FI_Generic', 'UUID' => 'fi1', 'Loadout' => []],
        ];
    }

    public function test_quantum_fuel_tank_classified(): void
    {
        $result = $this->classify($this->fuelSystemsFixture());

        $this->assertBucketContainsUuid($result, 'QuantumFuelTanks', 'qft1');
    }

    public function test_hydrogen_fuel_tank_classified(): void
    {
        $result = $this->classify($this->fuelSystemsFixture());

        $this->assertBucketContainsUuid($result, 'HydrogenFuelTanks', 'ft1');
    }

    public function test_fuel_intake_classified(): void
    {
        $result = $this->classify($this->fuelSystemsFixture());

        $this->assertBucketContainsUuid($result, 'FuelIntakes', 'fi1');
    }

    public function test_quantum_fuel_tank_not_in_hydrogen(): void
    {
        $result = $this->classify($this->fuelSystemsFixture());

        $this->assertBucketDoesNotContainUuid($result, 'HydrogenFuelTanks', 'qft1',
            'QuantumFuelTank must not appear in HydrogenFuelTanks');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 6: Thrusters
    // ------------------------------------------------------------------ //

    private function thrustersFixture(): array
    {
        return [
            ['HardpointName' => 'main_thruster', 'Type' => 'MainThruster.Main', 'ClassName' => 'THR_Main', 'UUID' => 'mt1', 'Loadout' => []],
            ['HardpointName' => 'retro_thruster', 'Type' => 'RetroThruster.Retro', 'ClassName' => 'THR_Retro', 'UUID' => 'rt1', 'Loadout' => []],
            ['HardpointName' => 'vtol_thruster', 'Type' => 'VtolThruster.Vtol', 'ClassName' => 'THR_Vtol', 'UUID' => 'vt1', 'Loadout' => []],
            ['HardpointName' => 'maneuver_thruster', 'Type' => 'ManneuverThruster.Fixed', 'ClassName' => 'THR_Maneuver', 'UUID' => 'mnt1', 'Loadout' => []],
        ];
    }

    public function test_thrusters_all_classified(): void
    {
        $result = $this->classify($this->thrustersFixture());

        self::assertArrayHasKey('Thrusters', $result);
        self::assertCount(4, $result['Thrusters']);
        $uuids = array_map(fn (array $r) => $r['UUID'], $result['Thrusters']);
        self::assertContains('mt1', $uuids);
        self::assertContains('rt1', $uuids);
        self::assertContains('vt1', $uuids);
        self::assertContains('mnt1', $uuids);
    }

    // ------------------------------------------------------------------ //
    //  Fixture 7: Life support, countermeasures, paint
    // ------------------------------------------------------------------ //

    private function miscSystemsFixture(): array
    {
        return [
            ['HardpointName' => 'life_support', 'Type' => 'LifeSupportGenerator.UNDEFINED', 'ClassName' => 'LS_Generic', 'UUID' => 'ls1', 'Loadout' => []],
            ['HardpointName' => 'cm_1', 'Type' => 'WeaponDefensive.CounterMeasure', 'ClassName' => 'CM_Flare', 'UUID' => 'cm1', 'Loadout' => []],
            ['HardpointName' => 'paint_1', 'Type' => 'Paint.Default', 'ClassName' => 'PAINT_Red', 'UUID' => 'p1', 'Loadout' => []],
        ];
    }

    public function test_life_support_classified(): void
    {
        $result = $this->classify($this->miscSystemsFixture());

        $this->assertBucketContainsUuid($result, 'LifeSupport', 'ls1');
    }

    public function test_countermeasure_classified(): void
    {
        $result = $this->classify($this->miscSystemsFixture());

        $this->assertBucketContainsUuid($result, 'CounterMeasures', 'cm1');
    }

    public function test_paint_classified(): void
    {
        $result = $this->classify($this->miscSystemsFixture());

        $this->assertBucketContainsUuid($result, 'Paints', 'p1');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 8: Remote turret
    // ------------------------------------------------------------------ //

    private function remoteTurretFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_turret_remote',
                'Type' => 'TurretBase.RemoteTurret',
                'ClassName' => 'Turret_Remote',
                'UUID' => 'rturret-1',
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_right',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => 'VariPuck_Gimbal_S3',
                        'UUID' => 'rtg-1',
                        'Loadout' => [
                            [
                                'HardpointName' => 'hardpoint_class_3',
                                'Type' => 'WeaponGun.Gun',
                                'ClassName' => 'BADR_BallisticCannon_S3',
                                'UUID' => 'rtw-1',
                                'Loadout' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_remote_turret_root_in_remote_turrets(): void
    {
        $result = $this->classify($this->remoteTurretFixture());

        $this->assertBucketContainsUuid($result, 'RemoteTurrets', 'rturret-1');
    }

    public function test_remote_turret_not_in_manned_turrets(): void
    {
        $result = $this->classify($this->remoteTurretFixture());

        // If MannedTurrets exists, it must not contain the remote turret.
        // If it doesn't exist, that's also acceptable (no false positive).
        if (isset($result['MannedTurrets'])) {
            $this->assertBucketDoesNotContainUuid($result, 'MannedTurrets', 'rturret-1',
                'Remote turret must not appear in MannedTurrets');
        } else {
            // Explicitly assert the key absence to satisfy PHPUnit's assertion requirement
            self::assertArrayNotHasKey('MannedTurrets', $result,
                'Remote turret fixture should not produce MannedTurrets entries');
        }
    }

    public function test_remote_turret_gimbal_in_weapon_mounts(): void
    {
        $result = $this->classify($this->remoteTurretFixture());

        $this->assertBucketContainsUuid($result, 'WeaponMounts', 'rtg-1');
    }

    public function test_remote_turret_weapon_in_weapons(): void
    {
        $result = $this->classify($this->remoteTurretFixture());

        $this->assertBucketContainsUuid($result, 'Weapons', 'rtw-1');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 9: Duplicate nested hardpoint names
    // ------------------------------------------------------------------ //

    private function duplicateHardpointNamesFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_turret_a',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_A',
                'UUID' => 'ta-root',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'Gun_A',
                        'UUID' => 'ta-weapon',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'hardpoint_turret_b',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_B',
                'UUID' => 'tb-root',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'Gun_B',
                        'UUID' => 'tb-weapon',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];
    }

    public function test_duplicate_hardpoint_names_both_turrets_classified(): void
    {
        $result = $this->classify($this->duplicateHardpointNamesFixture());

        self::assertArrayHasKey('MannedTurrets', $result);
        self::assertCount(2, $result['MannedTurrets']);
        $uuids = array_map(fn (array $r) => $r['UUID'], $result['MannedTurrets']);
        self::assertContains('ta-root', $uuids);
        self::assertContains('tb-root', $uuids);
    }

    public function test_duplicate_hardpoint_names_both_weapons_classified(): void
    {
        $result = $this->classify($this->duplicateHardpointNamesFixture());

        self::assertArrayHasKey('Weapons', $result);
        self::assertCount(2, $result['Weapons']);
        $uuids = array_map(fn (array $r) => $r['UUID'], $result['Weapons']);
        self::assertContains('ta-weapon', $uuids);
        self::assertContains('tb-weapon', $uuids);
    }

    public function test_duplicate_hardpoint_names_have_unique_port_ids(): void
    {
        $result = $this->classify($this->duplicateHardpointNamesFixture());

        // Both weapons have the same HardpointName but different PortIds
        $weapons = $result['Weapons'];
        $portIds = array_map(fn (array $r) => $r['PortId'], $weapons);
        self::assertCount(2, array_unique($portIds), 'PortIds must be unique even with duplicate HardpointNames');
    }

    // ------------------------------------------------------------------ //
    //  Fixture 10: Complex mixed loadout
    // ------------------------------------------------------------------ //

    private function complexMixedFixture(): array
    {
        return [
            // Shields
            ['HardpointName' => 'shield_1', 'Type' => 'Shield.UNDEFINED', 'ClassName' => 'SHLD_1', 'UUID' => 'cs1', 'Loadout' => []],
            ['HardpointName' => 'shield_2', 'Type' => 'Shield.UNDEFINED', 'ClassName' => 'SHLD_2', 'UUID' => 'cs2', 'Loadout' => []],
            // Quantum drive with jump drive child
            [
                'HardpointName' => 'qd', 'Type' => 'QuantumDrive.UNDEFINED', 'ClassName' => 'QDRV', 'UUID' => 'cqd1', 'Loadout' => [
                    ['HardpointName' => 'jd', 'Type' => 'JumpDrive.UNDEFINED', 'ClassName' => 'JDRV', 'UUID' => 'cjd1', 'Loadout' => []],
                ],
            ],
            // Cooler
            ['HardpointName' => 'cooler', 'Type' => 'Cooler.UNDEFINED', 'ClassName' => 'CLLR', 'UUID' => 'cc1', 'Loadout' => []],
            // Power plant
            ['HardpointName' => 'power', 'Type' => 'PowerPlant.UNDEFINED', 'ClassName' => 'POWR', 'UUID' => 'cpp1', 'Loadout' => []],
            // Flight controller
            ['HardpointName' => 'fc', 'Type' => 'FlightController.UNDEFINED', 'ClassName' => 'FC', 'UUID' => 'cfc1', 'Loadout' => []],
            // Radar
            ['HardpointName' => 'radar', 'Type' => 'Radar.UNDEFINED', 'ClassName' => 'RADR', 'UUID' => 'cr1', 'Loadout' => []],
            // Armor
            ['HardpointName' => 'armor', 'Type' => 'Armor.UNDEFINED', 'ClassName' => 'ARMR', 'UUID' => 'ca1', 'Loadout' => []],
            // Quantum fuel tank
            ['HardpointName' => 'qft', 'Type' => 'QuantumFuelTank.UNDEFINED', 'ClassName' => 'QFT', 'UUID' => 'cqft1', 'Loadout' => []],
            // Hydrogen fuel tank
            ['HardpointName' => 'hft', 'Type' => 'FuelTank.UNDEFINED', 'ClassName' => 'FT', 'UUID' => 'cft1', 'Loadout' => []],
            // Fuel intake
            ['HardpointName' => 'fi', 'Type' => 'FuelIntake.UNDEFINED', 'ClassName' => 'FI', 'UUID' => 'cfi1', 'Loadout' => []],
            // Manned turret with gimbal + weapon
            [
                'HardpointName' => 'turret_m', 'Type' => 'TurretBase.MannedTurret', 'ClassName' => 'T_M', 'UUID' => 'ctm1', 'Loadout' => [
                    [
                        'HardpointName' => 'gimbal', 'Type' => 'Turret.GunTurret', 'ClassName' => 'VariPuck_S4', 'UUID' => 'ctmg1', 'Loadout' => [
                            ['HardpointName' => 'gun', 'Type' => 'WeaponGun.Gun', 'ClassName' => 'GUN_S4', 'UUID' => 'ctmw1', 'Loadout' => []],
                        ],
                    ],
                ],
            ],
            // Missile rack with missiles
            [
                'HardpointName' => 'rack', 'Type' => 'MissileLauncher.MissileRack', 'ClassName' => 'MSLI', 'UUID' => 'crack1', 'Loadout' => [
                    ['HardpointName' => 'm1', 'Type' => 'Missile.Guided', 'ClassName' => 'MSL_1', 'UUID' => 'cmsl1', 'Loadout' => []],
                    ['HardpointName' => 'm2', 'Type' => 'Missile.Guided', 'ClassName' => 'MSL_2', 'UUID' => 'cmsl2', 'Loadout' => []],
                ],
            ],
            // Thrusters
            ['HardpointName' => 'main_thrust', 'Type' => 'MainThruster.Main', 'ClassName' => 'THR', 'UUID' => 'cmt1', 'Loadout' => []],
            ['HardpointName' => 'retro_thrust', 'Type' => 'RetroThruster.Retro', 'ClassName' => 'THR', 'UUID' => 'crt1', 'Loadout' => []],
        ];
    }

    public function test_complex_shields_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(2, $result['Shields'] ?? []);
    }

    public function test_complex_quantum_drives_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['QuantumDrives'] ?? []);
    }

    public function test_complex_jump_drives_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['JumpDrives'] ?? []);
    }

    public function test_complex_manned_turrets_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['MannedTurrets'] ?? []);
    }

    public function test_complex_weapon_mounts_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['WeaponMounts'] ?? []);
    }

    public function test_complex_weapons_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['Weapons'] ?? []);
    }

    public function test_complex_missile_racks_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(1, $result['MissileRacks'] ?? []);
    }

    public function test_complex_missiles_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(2, $result['Missiles'] ?? []);
    }

    public function test_complex_thrusters_count(): void
    {
        $result = $this->classify($this->complexMixedFixture());
        self::assertCount(2, $result['Thrusters'] ?? []);
    }

    // ------------------------------------------------------------------ //
    //  Empty loadout
    // ------------------------------------------------------------------ //

    public function test_empty_loadout_returns_empty_classification(): void
    {
        $result = $this->classify([]);

        self::assertIsArray($result);
        self::assertEmpty($result, 'Empty loadout should produce empty classification');
    }

    // ------------------------------------------------------------------ //
    //  Reference object shape validation
    // ------------------------------------------------------------------ //

    public function test_classification_entries_have_reference_object_shape(): void
    {
        $result = $this->classify($this->multiplePortTypesFixture());

        foreach ($result as $systemKey => $ports) {
            foreach ($ports as $i => $ref) {
                foreach (VehicleSystemKeys::PORT_REF_KEYS as $key) {
                    self::assertArrayHasKey($key, $ref,
                        "System '{$systemKey}' port[{$i}] must have key '{$key}'");
                }
            }
        }
    }

    public function test_classification_port_ids_are_unique_across_all_systems(): void
    {
        $result = $this->classify($this->complexMixedFixture());

        $allPortIds = [];
        foreach ($result as $systemKey => $ports) {
            foreach ($ports as $ref) {
                $portId = $ref['PortId'];
                self::assertNotContains($portId, $allPortIds,
                    "PortId '{$portId}' appears in multiple system buckets -- each port should be in exactly one bucket");
                $allPortIds[] = $portId;
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  Unclassifiable ports are silently skipped
    // ------------------------------------------------------------------ //

    public function test_port_with_unknown_type_is_silently_skipped(): void
    {
        $loadout = [
            ['HardpointName' => 'seat', 'Type' => 'Seat.SomeSeat', 'ClassName' => 'Seat_Pilot', 'UUID' => 'seat-1', 'Loadout' => []],
            ['HardpointName' => 'shield_1', 'Type' => 'Shield.UNDEFINED', 'ClassName' => 'SHLD', 'UUID' => 's1', 'Loadout' => []],
        ];

        $result = $this->classify($loadout);

        // Seat should not appear in any system
        $allUuids = [];
        foreach ($result as $ports) {
            foreach ($ports as $ref) {
                $allUuids[] = $ref['UUID'];
            }
        }
        self::assertNotContains('seat-1', $allUuids, 'Unknown port types should be silently skipped');

        // Shield should still be classified
        $this->assertBucketContainsUuid($result, 'Shields', 's1');
    }
}
