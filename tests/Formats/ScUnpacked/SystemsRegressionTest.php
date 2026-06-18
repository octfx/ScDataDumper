<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex;
use Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Aurora-pattern and Carrack-pattern ship configurations.
 *
 * These tests exercise the full annotator -> index -> classifier -> builder pipeline
 * using synthetic annotated loadout arrays that mirror real Aurora Mk II and
 * Carrack loadout structures.
 *
 * The wiring into Ship::toArray() is verified separately in VehicleSystemsContractTest.
 * These tests focus on semantic invariants: correct system port counts, semantic splits,
 * port identity resolution, and duplicate hardpoint name handling.
 *
 * @see API/docs/examples/v4-systems/full-dumps/rsi_aurora_mk2.full-proposed-systems.json
 * @see API/docs/examples/v4-systems/full-dumps/anvl_carrack.full-proposed-systems.json
 */
final class SystemsRegressionTest extends TestCase
{
    // ================================================================== //
    //  Aurora-pattern tests
    // ================================================================== //

    public function test_aurora_systems_key_exists(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertIsArray($result);
    }

    public function test_aurora_all_32_system_keys_present(): void
    {
        $result = $this->buildAuroraSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $result,
                "Aurora Systems must contain key '{$key}'");
        }
    }

    public function test_aurora_exactly_32_system_keys(): void
    {
        $result = $this->buildAuroraSystems();
        $actualKeys = array_keys($result);
        $expectedKeys = VehicleSystemKeys::ALL_KEYS;
        sort($actualKeys);
        sort($expectedKeys);

        self::assertSame($expectedKeys, $actualKeys);
    }

    public function test_aurora_shields_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(2, $result['Shields']['Ports'],
            'Aurora must have 2 shield ports');
    }

    public function test_aurora_quantum_drives_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['QuantumDrives']['Ports'],
            'Aurora must have 1 quantum drive port');
    }

    public function test_aurora_jump_drives_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['JumpDrives']['Ports'],
            'Aurora must have 1 jump drive port (nested under quantum drive)');
    }

    public function test_aurora_flight_controllers_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['FlightControllers']['Ports'],
            'Aurora must have 1 flight controller port');
    }

    public function test_aurora_thrusters_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(18, $result['Thrusters']['Ports'],
            'Aurora must have 18 thruster ports');
    }

    public function test_aurora_quantum_fuel_tanks_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['QuantumFuelTanks']['Ports'],
            'Aurora must have 1 quantum fuel tank port');
    }

    public function test_aurora_hydrogen_fuel_tanks_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['HydrogenFuelTanks']['Ports'],
            'Aurora must have 1 hydrogen fuel tank port');
    }

    public function test_aurora_fuel_intakes_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(2, $result['FuelIntakes']['Ports'],
            'Aurora must have 2 fuel intake ports');
    }

    public function test_aurora_coolers_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(2, $result['Coolers']['Ports'],
            'Aurora must have 2 cooler ports');
    }

    public function test_aurora_power_plants_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['PowerPlants']['Ports'],
            'Aurora must have 1 power plant port');
    }

    public function test_aurora_armors_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['Armors']['Ports'],
            'Aurora must have 1 armor port');
    }

    public function test_aurora_weapons_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(4, $result['Weapons']['Ports'],
            'Aurora must have 4 weapon ports');
    }

    public function test_aurora_missile_racks_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(4, $result['MissileRacks']['Ports'],
            'Aurora must have 4 missile rack ports');
    }

    public function test_aurora_missiles_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(8, $result['Missiles']['Ports'],
            'Aurora must have 8 missile ports (2 per rack)');
    }

    public function test_aurora_radars_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['Radars']['Ports'],
            'Aurora must have 1 radar port');
    }

    public function test_aurora_life_support_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(1, $result['LifeSupport']['Ports'],
            'Aurora must have 1 life support port');
    }

    public function test_aurora_countermeasures_count(): void
    {
        $result = $this->buildAuroraSystems();
        self::assertCount(2, $result['CounterMeasures']['Ports'],
            'Aurora must have 2 countermeasure ports');
    }

    // ================================================================== //
    //  Aurora semantic split tests
    // ================================================================== //

    public function test_aurora_missile_racks_and_missiles_have_no_overlap(): void
    {
        $result = $this->buildAuroraSystems();

        $rackPortIds = array_column($result['MissileRacks']['Ports'], 'PortId');
        $missilePortIds = array_column($result['Missiles']['Ports'], 'PortId');

        $overlap = array_intersect($rackPortIds, $missilePortIds);
        self::assertEmpty($overlap,
            'MissileRacks and Missiles must have no overlapping PortIds');
    }

    public function test_aurora_jump_drive_not_in_quantum_drives(): void
    {
        $result = $this->buildAuroraSystems();

        $qdPortIds = array_column($result['QuantumDrives']['Ports'], 'PortId');
        $jdPortIds = array_column($result['JumpDrives']['Ports'], 'PortId');

        $overlap = array_intersect($qdPortIds, $jdPortIds);
        self::assertEmpty($overlap,
            'QuantumDrives and JumpDrives must have no overlapping PortIds');
    }

    public function test_aurora_weapons_not_in_missile_racks(): void
    {
        $result = $this->buildAuroraSystems();

        $weaponPortIds = array_column($result['Weapons']['Ports'], 'PortId');
        $rackPortIds = array_column($result['MissileRacks']['Ports'], 'PortId');

        $overlap = array_intersect($weaponPortIds, $rackPortIds);
        self::assertEmpty($overlap,
            'Weapons and MissileRacks must have no overlapping PortIds');
    }

    // ================================================================== //
    //  Carrack-pattern tests
    // ================================================================== //

    public function test_carrack_systems_key_exists(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertIsArray($result);
    }

    public function test_carrack_all_32_system_keys_present(): void
    {
        $result = $this->buildCarrackSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $result,
                "Carrack Systems must contain key '{$key}'");
        }
    }

    public function test_carrack_shields_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['Shields']['Ports'],
            'Carrack must have 2 shield ports');
    }

    public function test_carrack_quantum_drives_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['QuantumDrives']['Ports'],
            'Carrack must have 1 quantum drive port');
    }

    public function test_carrack_jump_drives_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['JumpDrives']['Ports'],
            'Carrack must have 1 jump drive port (nested under quantum drive)');
    }

    public function test_carrack_thrusters_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(22, $result['Thrusters']['Ports'],
            'Carrack must have 22 thruster ports');
    }

    public function test_carrack_quantum_fuel_tanks_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['QuantumFuelTanks']['Ports'],
            'Carrack must have 1 quantum fuel tank port');
    }

    public function test_carrack_hydrogen_fuel_tanks_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['HydrogenFuelTanks']['Ports'],
            'Carrack must have 2 hydrogen fuel tank ports');
    }

    public function test_carrack_fuel_intakes_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['FuelIntakes']['Ports'],
            'Carrack must have 2 fuel intake ports');
    }

    public function test_carrack_coolers_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['Coolers']['Ports'],
            'Carrack must have 2 cooler ports');
    }

    public function test_carrack_power_plants_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['PowerPlants']['Ports'],
            'Carrack must have 2 power plant ports');
    }

    public function test_carrack_armors_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['Armors']['Ports'],
            'Carrack must have 1 armor port');
    }

    public function test_carrack_weapons_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(8, $result['Weapons']['Ports'],
            'Carrack must have 8 weapon ports (actual guns, not mounts)');
    }

    public function test_carrack_weapon_mounts_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(8, $result['WeaponMounts']['Ports'],
            'Carrack must have 8 weapon mount ports (gimbals/VariPuck)');
    }

    public function test_carrack_manned_turrets_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(3, $result['MannedTurrets']['Ports'],
            'Carrack must have 3 manned turret root ports');
    }

    public function test_carrack_remote_turrets_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['RemoteTurrets']['Ports'],
            'Carrack must have 1 remote turret root port');
    }

    public function test_carrack_radars_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['Radars']['Ports'],
            'Carrack must have 1 radar port');
    }

    public function test_carrack_life_support_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(1, $result['LifeSupport']['Ports'],
            'Carrack must have 1 life support port');
    }

    public function test_carrack_countermeasures_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['CounterMeasures']['Ports'],
            'Carrack must have 2 countermeasure ports');
    }

    public function test_carrack_weapon_lockers_count(): void
    {
        $result = $this->buildCarrackSystems();
        self::assertCount(2, $result['WeaponLockers']['Ports'],
            'Carrack must have 2 weapon locker ports');
    }

    // ================================================================== //
    //  Carrack semantic split tests
    // ================================================================== //

    public function test_carrack_turret_roots_not_in_weapons_or_mounts(): void
    {
        $result = $this->buildCarrackSystems();

        $turretPortIds = array_merge(
            array_column($result['MannedTurrets']['Ports'], 'PortId'),
            array_column($result['RemoteTurrets']['Ports'], 'PortId'),
        );
        $weaponPortIds = array_column($result['Weapons']['Ports'], 'PortId');
        $mountPortIds = array_column($result['WeaponMounts']['Ports'], 'PortId');

        $overlap = array_intersect($turretPortIds, $weaponPortIds);
        self::assertEmpty($overlap,
            'Turret roots must not appear in Weapons');

        $overlap = array_intersect($turretPortIds, $mountPortIds);
        self::assertEmpty($overlap,
            'Turret roots must not appear in WeaponMounts');
    }

    public function test_carrack_gimbal_mounts_not_in_manned_turrets(): void
    {
        $result = $this->buildCarrackSystems();

        $mountPortIds = array_column($result['WeaponMounts']['Ports'], 'PortId');
        $turretPortIds = array_column($result['MannedTurrets']['Ports'], 'PortId');

        $overlap = array_intersect($mountPortIds, $turretPortIds);
        self::assertEmpty($overlap,
            'Gimbal/weapon mounts must not appear in MannedTurrets');
    }

    public function test_carrack_weapons_not_in_weapon_mounts(): void
    {
        $result = $this->buildCarrackSystems();

        $weaponPortIds = array_column($result['Weapons']['Ports'], 'PortId');
        $mountPortIds = array_column($result['WeaponMounts']['Ports'], 'PortId');

        $overlap = array_intersect($weaponPortIds, $mountPortIds);
        self::assertEmpty($overlap,
            'Actual weapons must not appear in WeaponMounts');
    }

    public function test_carrack_jump_drive_not_in_quantum_drives(): void
    {
        $result = $this->buildCarrackSystems();

        $qdPortIds = array_column($result['QuantumDrives']['Ports'], 'PortId');
        $jdPortIds = array_column($result['JumpDrives']['Ports'], 'PortId');

        $overlap = array_intersect($qdPortIds, $jdPortIds);
        self::assertEmpty($overlap,
            'JumpDrives must be separate from QuantumDrives');
    }

    // ================================================================== //
    //  Port identity and uniqueness tests
    // ================================================================== //

    public function test_aurora_all_port_ids_resolve_to_loadout(): void
    {
        $annotated = $this->buildAuroraAnnotatedLoadout();
        $result = $this->buildAuroraSystems();

        $loadoutPortIds = [];
        $this->collectLoadoutPortIds($annotated, $loadoutPortIds);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $portRef) {
                self::assertContains($portRef['PortId'], $loadoutPortIds,
                    "System {$systemKey} port {$portRef['PortId']} must resolve to a loadout entry");
            }
        }
    }

    public function test_carrack_all_port_ids_resolve_to_loadout(): void
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();
        $result = $this->buildCarrackSystems();

        $loadoutPortIds = [];
        $this->collectLoadoutPortIds($annotated, $loadoutPortIds);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $portRef) {
                self::assertContains($portRef['PortId'], $loadoutPortIds,
                    "System {$systemKey} port {$portRef['PortId']} must resolve to a loadout entry");
            }
        }
    }

    public function test_aurora_port_ids_unique_across_all_systems(): void
    {
        $result = $this->buildAuroraSystems();

        $allPortIds = [];
        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $portRef) {
                $portId = $portRef['PortId'];
                self::assertNotContains($portId, $allPortIds,
                    "PortId '{$portId}' must not appear in multiple system buckets");
                $allPortIds[] = $portId;
            }
        }
    }

    public function test_carrack_port_ids_unique_across_all_systems(): void
    {
        $result = $this->buildCarrackSystems();

        $allPortIds = [];
        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $portRef) {
                $portId = $portRef['PortId'];
                self::assertNotContains($portId, $allPortIds,
                    "PortId '{$portId}' must not appear in multiple system buckets");
                $allPortIds[] = $portId;
            }
        }
    }

    // ================================================================== //
    //  Duplicate hardpoint name handling
    // ================================================================== //

    public function test_carrack_duplicate_hardpoint_names_have_different_port_ids(): void
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();
        $result = $this->buildCarrackSystems();

        // Collect all port refs from Weapons (which has duplicate "hardpoint_class_2" entries)
        $weapons = $result['Weapons']['Ports'];
        self::assertGreaterThan(1, count($weapons), 'Carrack must have multiple weapons');

        // Group by HardpointName to find duplicates
        $byName = [];
        foreach ($weapons as $port) {
            $name = $port['HardpointName'];
            $byName[$name][] = $port['PortId'];
        }

        // Find at least one duplicate hardpoint name
        $foundDuplicate = false;
        foreach ($byName as $name => $portIds) {
            if (count($portIds) > 1) {
                $foundDuplicate = true;
                // All PortIds for the same HardpointName must be unique
                $uniquePortIds = array_unique($portIds);
                self::assertCount(count($portIds), $uniquePortIds,
                    "Duplicate hardpoint '{$name}' must have unique PortIds");
            }
        }

        self::assertTrue($foundDuplicate,
            'Carrack must have at least one duplicate hardpoint name across weapons');
    }

    public function test_carrack_turret_gimbal_chain_has_correct_ancestry(): void
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();
        $index = (new RecursiveLoadoutPortIndex)->build($annotated);
        $result = $this->buildCarrackSystems();

        // Pick a manned turret root
        $turretRoots = $result['MannedTurrets']['Ports'];
        self::assertNotEmpty($turretRoots);

        $turretRootPortId = $turretRoots[0]['PortId'];

        // Find the corresponding loadout entry
        $turretEntry = $index->findByPortId($turretRootPortId);
        self::assertNotNull($turretEntry);

        // The turret root should have children (gimbals -> weapons)
        self::assertNotEmpty($turretEntry['Loadout'] ?? [],
            'Turret root must have nested loadout (gimbals/weapons)');
    }

    // ================================================================== //
    //  Loadout annotation tests
    // ================================================================== //

    public function test_aurora_every_loadout_entry_has_port_id(): void
    {
        $annotated = $this->buildAuroraAnnotatedLoadout();
        $flat = $this->flattenLoadout($annotated);

        foreach ($flat as $entry) {
            self::assertArrayHasKey('PortId', $entry,
                sprintf('Entry "%s" must have PortId', $entry['HardpointName'] ?? '(unnamed)'));
        }
    }

    public function test_aurora_every_top_level_entry_has_root_port_id(): void
    {
        $annotated = $this->buildAuroraAnnotatedLoadout();

        foreach ($annotated as $entry) {
            self::assertArrayHasKey('RootPortId', $entry,
                sprintf('Top-level entry "%s" must have RootPortId', $entry['HardpointName'] ?? '(unnamed)'));
            self::assertSame($entry['PortId'], $entry['RootPortId'],
                'Top-level RootPortId must equal PortId');
        }
    }

    public function test_aurora_nested_entries_have_parent_port_id(): void
    {
        $annotated = $this->buildAuroraAnnotatedLoadout();
        $flat = $this->flattenLoadout($annotated);

        foreach ($flat as $entry) {
            if ($entry['ParentPortId'] !== null) {
                self::assertArrayHasKey('ParentPortId', $entry,
                    sprintf('Nested entry "%s" must have ParentPortId', $entry['HardpointName'] ?? '(unnamed)'));
            }
        }
    }

    public function test_carrack_every_loadout_entry_has_port_id(): void
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();
        $flat = $this->flattenLoadout($annotated);

        foreach ($flat as $entry) {
            self::assertArrayHasKey('PortId', $entry,
                sprintf('Entry "%s" must have PortId', $entry['HardpointName'] ?? '(unnamed)'));
        }
    }

    public function test_carrack_every_top_level_entry_has_root_port_id(): void
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();

        foreach ($annotated as $entry) {
            self::assertArrayHasKey('RootPortId', $entry,
                sprintf('Top-level entry "%s" must have RootPortId', $entry['HardpointName'] ?? '(unnamed)'));
            self::assertSame($entry['PortId'], $entry['RootPortId'],
                'Top-level RootPortId must equal PortId');
        }
    }

    // ================================================================== //
    //  Port reference shape tests
    // ================================================================== //

    public function test_aurora_system_port_refs_have_required_fields(): void
    {
        $result = $this->buildAuroraSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $i => $portRef) {
                self::assertArrayHasKey('PortId', $portRef,
                    "{$systemKey}[{$i}] must have PortId");
                self::assertArrayHasKey('HardpointName', $portRef,
                    "{$systemKey}[{$i}] must have HardpointName");
            }
        }
    }

    public function test_carrack_system_port_refs_have_required_fields(): void
    {
        $result = $this->buildCarrackSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($result[$systemKey]['Ports'] as $i => $portRef) {
                self::assertArrayHasKey('PortId', $portRef,
                    "{$systemKey}[{$i}] must have PortId");
                self::assertArrayHasKey('HardpointName', $portRef,
                    "{$systemKey}[{$i}] must have HardpointName");
            }
        }
    }

    // ================================================================== //
    //  Bucket shape tests
    // ================================================================== //

    public function test_aurora_each_system_has_summary_and_ports_keys(): void
    {
        $result = $this->buildAuroraSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey('Summary', $result[$key],
                "System '{$key}' must have Summary key");
            self::assertArrayHasKey('Ports', $result[$key],
                "System '{$key}' must have Ports key");
        }
    }

    public function test_carrack_each_system_has_summary_and_ports_keys(): void
    {
        $result = $this->buildCarrackSystems();

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey('Summary', $result[$key],
                "System '{$key}' must have Summary key");
            self::assertArrayHasKey('Ports', $result[$key],
                "System '{$key}' must have Ports key");
        }
    }

    public function test_aurora_empty_systems_have_empty_ports(): void
    {
        $result = $this->buildAuroraSystems();

        // Aurora has no turrets, no PDCs, no weapon mounts, no mining, etc.
        $expectedEmpty = ['MannedTurrets', 'RemoteTurrets', 'PdcTurrets', 'WeaponMounts',
            'Mining', 'Salvage', 'TractorBeams', 'Emps', 'Qeds', 'Modules',
            'DockedVehicles', 'AiModules', 'CargoGrids', 'WeaponLockers', 'Paints'];

        foreach ($expectedEmpty as $key) {
            self::assertSame([], $result[$key]['Ports'],
                "Aurora system '{$key}' must have empty Ports array");
        }
    }

    public function test_carrack_empty_systems_have_empty_ports(): void
    {
        $result = $this->buildCarrackSystems();

        // Carrack has no missiles, no missile racks, no mining, etc.
        $expectedEmpty = ['MissileRacks', 'Missiles', 'PdcTurrets', 'Paints',
            'Mining', 'Salvage', 'TractorBeams', 'Emps', 'Qeds', 'Modules',
            'DockedVehicles', 'AiModules', 'CargoGrids'];

        foreach ($expectedEmpty as $key) {
            self::assertSame([], $result[$key]['Ports'],
                "Carrack system '{$key}' must have empty Ports array");
        }
    }

    // ================================================================== //
    //  Fixture builders
    // ================================================================== //

    /**
     * Build Aurora-pattern Systems output.
     */
    private function buildAuroraSystems(): array
    {
        $annotated = $this->buildAuroraAnnotatedLoadout();
        $builder = new SystemsBuilder;

        return $builder->build($annotated);
    }

    /**
     * Build Carrack-pattern Systems output.
     */
    private function buildCarrackSystems(): array
    {
        $annotated = $this->buildCarrackAnnotatedLoadout();
        $builder = new SystemsBuilder;

        return $builder->build($annotated);
    }

    /**
     * Aurora Mk II-like annotated loadout.
     *
     * Structure:
     *   2 shields, 1 QD + 1 nested JD, 1 FC, 18 thrusters, 1 QFT, 1 HFT,
     *   2 intakes, 2 coolers, 1 PP, 1 armor, 4 weapons, 4 missile racks
     *   each with 2 nested missiles (8 total), 1 radar, 1 LS, 2 CMs
     */
    private function buildAuroraAnnotatedLoadout(): array
    {
        $raw = [];

        // 2 shields
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_shield_{$i}",
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => "SHLD_Aurora_{$i}",
                'UUID' => "shield-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 quantum drive with 1 nested jump drive
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_drive',
            'Type' => 'QuantumDrive.UNDEFINED',
            'ClassName' => 'QDRV_Aurora',

            'Loadout' => [
                [
                    'HardpointName' => 'hardpoint_Jump_Drive',
                    'Type' => 'JumpDrive.UNDEFINED',
                    'ClassName' => 'JDRV_Aurora',
                    'UUID' => 'jd-1',
                    'Loadout' => [],
                ],
            ],
        ];

        // 1 flight controller
        $raw[] = [
            'HardpointName' => 'hardpoint_flight_controller',
            'Type' => 'FlightController.UNDEFINED',
            'ClassName' => 'FC_Aurora',
            'UUID' => 'fc-1',
            'Loadout' => [],
        ];

        // 18 thrusters (1 main, 1 retro, 2 VTOL, 14 maneuvering)
        $thrusterTypes = ['MainThruster', 'RetroThruster', 'VtolThruster', 'VtolThruster'];
        for ($i = 0; $i < 14; $i++) {
            $thrusterTypes[] = 'ManeuverThruster';
        }
        foreach ($thrusterTypes as $i => $tType) {
            $raw[] = [
                'HardpointName' => "hardpoint_thruster_{$i}",
                'Type' => "{$tType}.UNDEFINED",
                'ClassName' => "THR_Aurora_{$i}",
                'UUID' => "thr-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 quantum fuel tank
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_fuel_tank',
            'Type' => 'QuantumFuelTank.UNDEFINED',
            'ClassName' => 'QFT_Aurora',

            'Loadout' => [],
        ];

        // 1 hydrogen fuel tank
        $raw[] = [
            'HardpointName' => 'hardpoint_hydrogen_fuel_tank',
            'Type' => 'FuelTank.UNDEFINED',
            'ClassName' => 'HFT_Aurora',

            'Loadout' => [],
        ];

        // 2 fuel intakes
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_fuel_intake_{$i}",
                'Type' => 'FuelIntake.UNDEFINED',

                'Loadout' => [],
            ];
        }

        // 2 coolers
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_cooler_{$i}",
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => "CLR_Aurora_{$i}",
                'UUID' => "clr-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 power plant
        $raw[] = [
            'HardpointName' => 'hardpoint_power_plant',
            'Type' => 'PowerPlant.UNDEFINED',
            'ClassName' => 'PP_Aurora',
            'UUID' => 'pp-1',
            'Loadout' => [],
        ];

        // 1 armor
        $raw[] = [
            'HardpointName' => 'hardpoint_armor',
            'Type' => 'Armor.UNDEFINED',
            'ClassName' => 'ARMR_Aurora',
            'UUID' => 'armor-1',
            'Loadout' => [],
        ];

        // 4 weapons (fixed)
        for ($i = 0; $i < 4; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_weapon_{$i}",
                'Type' => 'WeaponGun.Ballistic',
                'ClassName' => "WPN_Aurora_{$i}",
                'UUID' => "wpn-{$i}",
                'Loadout' => [],
            ];
        }

        // 4 missile racks, each with 2 nested missiles
        for ($i = 0; $i < 4; $i++) {
            $missiles = [];
            for ($j = 0; $j < 2; $j++) {
                $missiles[] = [
                    'HardpointName' => "hardpoint_missile_{$j}",
                    'Type' => 'Missile.UNDEFINED',
                    'ClassName' => "MSL_Aurora_{$i}_{$j}",
                    'UUID' => "msl-{$i}-{$j}",
                    'Loadout' => [],
                ];
            }
            $raw[] = [
                'HardpointName' => "hardpoint_missile_rack_{$i}",
                'Type' => 'MissileLauncher.MissileRack',
                'ClassName' => "MSLR_Aurora_{$i}",
                'UUID' => "mslr-{$i}",
                'Loadout' => $missiles,
            ];
        }

        // 1 radar
        $raw[] = [
            'HardpointName' => 'hardpoint_radar',
            'Type' => 'Radar.UNDEFINED',
            'ClassName' => 'RAD_Aurora',
            'UUID' => 'rad-1',
            'Loadout' => [],
        ];

        // 1 life support
        $raw[] = [
            'HardpointName' => 'hardpoint_life_support',
            'Type' => 'LifeSupportGenerator.UNDEFINED',
            'ClassName' => 'LS_Aurora',
            'UUID' => 'ls-1',
            'Loadout' => [],
        ];

        // 2 countermeasures
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_countermeasure_{$i}",
                'Type' => 'WeaponDefensive.CounterMeasure',
                'ClassName' => "CM_Aurora_{$i}",
                'UUID' => "cm-{$i}",
                'Loadout' => [],
            ];
        }

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    /**
     * Carrack-like annotated loadout.
     *
     * Structure:
     *   2 shields, 1 QD + 1 nested JD, 1 FC, 22 thrusters, 1 QFT, 2 HFTs,
     *   2 intakes, 2 coolers, 2 PPs, 1 armor,
     *   3 manned turrets (each: turret root -> gimbal/VariPuck -> weapon),
     *   1 remote turret (turret root -> gimbal -> weapon),
     *   plus 4 more weapons on turrets = 8 total weapons and 8 total mounts,
     *   1 radar, 1 LS, 2 CMs, 2 weapon lockers
     */
    private function buildCarrackAnnotatedLoadout(): array
    {
        $raw = [];

        // 2 shields
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_shield_{$i}",
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => "SHLD_Carrack_{$i}",
                'UUID' => "shield-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 quantum drive with 1 nested jump drive
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_drive',
            'Type' => 'QuantumDrive.UNDEFINED',
            'ClassName' => 'QDRV_Carrack',

            'Loadout' => [
                [
                    'HardpointName' => 'hardpoint_Jump_Drive',
                    'Type' => 'JumpDrive.UNDEFINED',
                    'ClassName' => 'JDRV_Carrack',
                    'UUID' => 'jd-1',
                    'Loadout' => [],
                ],
            ],
        ];

        // 1 flight controller
        $raw[] = [
            'HardpointName' => 'hardpoint_flight_controller',
            'Type' => 'FlightController.UNDEFINED',
            'ClassName' => 'FC_Carrack',
            'UUID' => 'fc-1',
            'Loadout' => [],
        ];

        // 22 thrusters (2 main, 2 retro, 4 VTOL, 14 maneuvering)
        $thrusterTypes = ['MainThruster', 'MainThruster', 'RetroThruster', 'RetroThruster',
            'VtolThruster', 'VtolThruster', 'VtolThruster', 'VtolThruster'];
        for ($i = 0; $i < 14; $i++) {
            $thrusterTypes[] = 'ManeuverThruster';
        }
        foreach ($thrusterTypes as $i => $tType) {
            $raw[] = [
                'HardpointName' => "hardpoint_thruster_{$i}",
                'Type' => "{$tType}.UNDEFINED",
                'ClassName' => "THR_Carrack_{$i}",
                'UUID' => "thr-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 quantum fuel tank
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_fuel_tank',
            'Type' => 'QuantumFuelTank.UNDEFINED',
            'ClassName' => 'QFT_Carrack',

            'Loadout' => [],
        ];

        // 2 hydrogen fuel tanks
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_hydrogen_fuel_tank_{$i}",
                'Type' => 'FuelTank.UNDEFINED',
                'ClassName' => "HFT_Carrack_{$i}",

                'Loadout' => [],
            ];
        }

        // 2 fuel intakes
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_fuel_intake_{$i}",
                'Type' => 'FuelIntake.UNDEFINED',

                'Loadout' => [],
            ];
        }

        // 2 coolers
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_cooler_{$i}",
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => "CLR_Carrack_{$i}",
                'UUID' => "clr-{$i}",
                'Loadout' => [],
            ];
        }

        // 2 power plants
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_power_plant_{$i}",
                'Type' => 'PowerPlant.UNDEFINED',
                'ClassName' => "PP_Carrack_{$i}",
                'UUID' => "pp-{$i}",
                'Loadout' => [],
            ];
        }

        // 1 armor
        $raw[] = [
            'HardpointName' => 'hardpoint_armor',
            'Type' => 'Armor.UNDEFINED',
            'ClassName' => 'ARMR_Carrack',
            'UUID' => 'armor-1',
            'Loadout' => [],
        ];

        // 3 manned turrets: each has turret root -> gimbal (VariPuck-like) -> weapon
        // This gives: 3 turret roots, 3 gimbals, 3 weapons from manned turrets
        $turretNames = ['hardpoint_turret_front', 'hardpoint_turret_back_rear', 'hardpoint_turret_bottom'];
        for ($i = 0; $i < 3; $i++) {
            $raw[] = [
                'HardpointName' => $turretNames[$i],
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => "TURR_Carrack_{$i}",
                'UUID' => "turr-m-{$i}",
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_left',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => "VariPuck_Carrack_{$i}",
                        'UUID' => "gimbal-m-{$i}",
                        'Loadout' => [
                            [
                                'HardpointName' => 'hardpoint_class_2',
                                'Type' => 'WeaponGun.Ballistic',
                                'ClassName' => "WPN_Carrack_M{$i}",
                                'UUID' => "wpn-m-{$i}",
                                'Loadout' => [],
                            ],
                        ],
                    ],
                    [
                        'HardpointName' => 'turret_right',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => "VariPuck_Carrack_R{$i}",
                        'UUID' => "gimbal-mr-{$i}",
                        'Loadout' => [
                            [
                                'HardpointName' => 'hardpoint_class_2',
                                'Type' => 'WeaponGun.Energy',
                                'ClassName' => "WPN_Carrack_MR{$i}",
                                'UUID' => "wpn-mr-{$i}",
                                'Loadout' => [],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // 1 remote turret: turret root -> gimbal -> weapon
        $raw[] = [
            'HardpointName' => 'hardpoint_turret_remote_top',
            'Type' => 'TurretBase.RemoteTurret',
            'ClassName' => 'TURR_Carrack_Remote',
            'UUID' => 'turr-r-0',
            'Loadout' => [
                [
                    'HardpointName' => 'turret_left',
                    'Type' => 'Turret.GunTurret',
                    'ClassName' => 'VariPuck_Carrack_Remote',
                    'UUID' => 'gimbal-r-0',
                    'Loadout' => [
                        [
                            'HardpointName' => 'hardpoint_class_2',
                            'Type' => 'WeaponGun.Energy',
                            'ClassName' => 'WPN_Carrack_R0',
                            'UUID' => 'wpn-r-0',
                            'Loadout' => [],
                        ],
                    ],
                ],
                [
                    'HardpointName' => 'turret_right',
                    'Type' => 'Turret.GunTurret',
                    'ClassName' => 'VariPuck_Carrack_Remote_R',
                    'UUID' => 'gimbal-rr-0',
                    'Loadout' => [
                        [
                            'HardpointName' => 'hardpoint_class_2',
                            'Type' => 'WeaponGun.Energy',
                            'ClassName' => 'WPN_Carrack_RR0',
                            'UUID' => 'wpn-rr-0',
                            'Loadout' => [],
                        ],
                    ],
                ],
            ],
        ];

        // 1 radar
        $raw[] = [
            'HardpointName' => 'hardpoint_radar',
            'Type' => 'Radar.UNDEFINED',
            'ClassName' => 'RAD_Carrack',
            'UUID' => 'rad-1',
            'Loadout' => [],
        ];

        // 1 life support
        $raw[] = [
            'HardpointName' => 'hardpoint_life_support',
            'Type' => 'LifeSupportGenerator.UNDEFINED',
            'ClassName' => 'LS_Carrack',
            'UUID' => 'ls-1',
            'Loadout' => [],
        ];

        // 2 countermeasures
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_countermeasure_{$i}",
                'Type' => 'WeaponDefensive.CounterMeasure',
                'ClassName' => "CM_Carrack_{$i}",
                'UUID' => "cm-{$i}",
                'Loadout' => [],
            ];
        }

        // 2 weapon lockers
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_weapon_locker_{$i}",
                'Type' => 'WeaponLocker.UNDEFINED',
                'ClassName' => "WL_Carrack_{$i}",
                'UUID' => "wl-{$i}",
                'Loadout' => [],
            ];
        }

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    /**
     * Recursively flatten a loadout tree into a flat list.
     *
     * @param  list<array<string, mixed>>  $loadout
     * @return list<array<string, mixed>>
     */
    private function flattenLoadout(array $loadout): array
    {
        $flat = [];
        foreach ($loadout as $entry) {
            $flat[] = $entry;
            if (! empty($entry['Loadout']) && is_array($entry['Loadout'])) {
                $flat = array_merge($flat, $this->flattenLoadout($entry['Loadout']));
            }
        }

        return $flat;
    }

    /**
     * Collect all PortIds from an annotated loadout tree.
     *
     * @param  list<array<string, mixed>>  $loadout
     * @param  list<string>  $portIds
     */
    private function collectLoadoutPortIds(array $loadout, array &$portIds): void
    {
        foreach ($loadout as $entry) {
            if (isset($entry['PortId'])) {
                $portIds[] = $entry['PortId'];
            }
            if (! empty($entry['Loadout']) && is_array($entry['Loadout'])) {
                $this->collectLoadoutPortIds($entry['Loadout'], $portIds);
            }
        }
    }
}
