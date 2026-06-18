<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex;
use Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder
 */
final class SystemsBuilderTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Group 1: Empty loadout
    // ------------------------------------------------------------------ //

    public function test_build_returns_array(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        self::assertIsArray($result);
    }

    public function test_all_system_keys_present(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $result,
                "Systems must contain key '{$key}' from ALL_KEYS");
        }
    }

    public function test_exactly_all_system_keys_present(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        $expectedKeys = VehicleSystemKeys::ALL_KEYS;
        $actualKeys = array_keys($result);
        sort($expectedKeys);
        sort($actualKeys);

        self::assertSame($expectedKeys, $actualKeys,
            'Systems must contain exactly the keys from ALL_KEYS, no more, no less');
    }

    public function test_every_system_has_summary_key(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey('Summary', $result[$key],
                "System '{$key}' must have a 'Summary' key");
        }
    }

    public function test_every_system_has_ports_key(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey('Ports', $result[$key],
                "System '{$key}' must have a 'Ports' key");
        }
    }

    public function test_every_system_has_exactly_two_keys(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            $systemKeys = array_keys($result[$key]);
            sort($systemKeys);
            $expected = VehicleSystemKeys::BUCKET_KEYS;
            sort($expected);

            self::assertSame($expected, $systemKeys,
                "System '{$key}' must have exactly the keys ['Summary', 'Ports']");
        }
    }

    public function test_empty_systems_have_null_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertNull($result[$key]['Summary'],
                "Empty system '{$key}' must have Summary === null");
        }
    }

    public function test_empty_systems_have_empty_ports_array(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build([]);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertSame([], $result[$key]['Ports'],
                "Empty system '{$key}' must have Ports === []");
        }
    }

    // ------------------------------------------------------------------ //
    //  Group 2: Loadout with classified ports (shield + QD + nested JD)
    // ------------------------------------------------------------------ //

    /**
     * Annotated loadout with a shield, a quantum drive, and a nested jump drive.
     *
     * Structure:
     *   loadout.0  shield_1                (Shield.UNDEFINED)
     *   loadout.1  hardpoint_quantum_drive (QuantumDrive.UNDEFINED)
     *     loadout.1.loadout.0  hardpoint_Jump_Drive (JumpDrive.UNDEFINED)
     */
    private function annotatedClassificationFixture(): array
    {
        $raw = [
            [
                'HardpointName' => 'shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_Generic',
                'UUID' => 's1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_AEGS',
                'UUID' => 'qd1',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_Jump_Drive',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'ClassName' => 'JDRV_ACME',
                        'UUID' => 'jd1',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    public function test_shields_has_one_port(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        self::assertCount(1, $result['Shields']['Ports'],
            'Shields must have exactly 1 port from the fixture');
    }

    public function test_shields_port_has_reference_shape(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        $port = $result['Shields']['Ports'][0];
        foreach (VehicleSystemKeys::PORT_REF_KEYS as $key) {
            self::assertArrayHasKey($key, $port,
                "Shield port reference must contain key '{$key}' from PORT_REF_KEYS");
        }

        // Must have exactly the PORT_REF_KEYS fields
        $actualKeys = array_keys($port);
        $expectedKeys = VehicleSystemKeys::PORT_REF_KEYS;
        sort($actualKeys);
        sort($expectedKeys);
        self::assertSame($expectedKeys, $actualKeys,
            'Shield port reference must contain exactly the PORT_REF_KEYS fields');
    }

    public function test_quantum_drives_has_one_port(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        self::assertCount(1, $result['QuantumDrives']['Ports'],
            'QuantumDrives must have exactly 1 port from the fixture');
    }

    public function test_jump_drives_has_one_port(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        self::assertCount(1, $result['JumpDrives']['Ports'],
            'JumpDrives must have exactly 1 port (nested under quantum drive)');
    }

    public function test_jump_drive_port_is_not_in_quantum_drives(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        $qdPortIds = array_map(
            fn (array $p) => $p['PortId'],
            $result['QuantumDrives']['Ports'],
        );

        self::assertNotContains('loadout.1.loadout.0', $qdPortIds,
            'The nested jump drive PortId must not appear in QuantumDrives');
    }

    public function test_systems_with_no_ports_still_have_null_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        // Coolers has no ports in this fixture
        self::assertNull($result['Coolers']['Summary'],
            'System with no ports must still have Summary === null');
    }

    public function test_systems_with_no_ports_still_have_empty_array(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        // Coolers has no ports in this fixture
        self::assertSame([], $result['Coolers']['Ports'],
            'System with no ports must still have Ports === []');
    }

    public function test_all_system_keys_present_with_loadout(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedClassificationFixture());

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $result,
                "All 32 system keys must be present even with classified loadout; missing '{$key}'");
        }
    }

    // ------------------------------------------------------------------ //
    //  Group 3: Multiple ports in same system
    // ------------------------------------------------------------------ //

    /**
     * Two shields and two coolers.
     */
    private function annotatedMultiPortFixture(): array
    {
        $raw = [
            [
                'HardpointName' => 'shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'shield_2',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_2',
                'UUID' => 's2',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'cooler_1',
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => 'CLLR_1',
                'UUID' => 'c1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'cooler_2',
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => 'CLLR_2',
                'UUID' => 'c2',
                'Loadout' => [],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    public function test_multiple_shields_in_ports(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMultiPortFixture());

        self::assertCount(2, $result['Shields']['Ports'],
            'Shields must contain 2 ports from the multi-port fixture');
    }

    public function test_multiple_coolers_in_ports(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMultiPortFixture());

        self::assertCount(2, $result['Coolers']['Ports'],
            'Coolers must contain 2 ports from the multi-port fixture');
    }

    public function test_port_ids_are_unique_in_each_system(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMultiPortFixture());

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            $portIds = array_map(
                fn (array $p) => $p['PortId'],
                $result[$key]['Ports'],
            );

            $uniquePortIds = array_unique($portIds);
            self::assertCount(count($uniquePortIds), $portIds,
                "System '{$key}' must not have duplicate PortId values in Ports");
        }
    }

    // ------------------------------------------------------------------ //
    //  Group 4: Classifier integration -- all classified ports resolve
    // ------------------------------------------------------------------ //

    /**
     * Mixed fixture: shield, quantum drive, jump drive, cooler, power plant, weapon, missile rack, missile.
     */
    private function annotatedMixedFixture(): array
    {
        $raw = [
            [
                'HardpointName' => 'shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_1',
                'UUID' => 'qd1',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_Jump_Drive',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'ClassName' => 'JDRV_1',
                        'UUID' => 'jd1',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'cooler_1',
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => 'CLLR_1',
                'UUID' => 'c1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'powerplant_1',
                'Type' => 'PowerPlant.UNDEFINED',
                'ClassName' => 'POWR_1',
                'UUID' => 'pp1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_class_2',
                'Type' => 'WeaponGun.Gun',
                'ClassName' => 'KLWE_LaserRepeater_S3',
                'UUID' => 'w1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'missile_rack_1',
                'Type' => 'MissileLauncher.MissileRack',
                'ClassName' => 'MSSL_Rack_S2',
                'UUID' => 'mr1',
                'Loadout' => [
                    [
                        'HardpointName' => 'missile_1',
                        'Type' => 'Missile.Guided',
                        'ClassName' => 'MSSL_Lockon_S2',
                        'UUID' => 'm1',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    public function test_mixed_fixture_shields_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['Shields']['Ports']);
    }

    public function test_mixed_fixture_quantum_drives_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['QuantumDrives']['Ports']);
    }

    public function test_mixed_fixture_jump_drives_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['JumpDrives']['Ports']);
    }

    public function test_mixed_fixture_coolers_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['Coolers']['Ports']);
    }

    public function test_mixed_fixture_power_plants_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['PowerPlants']['Ports']);
    }

    public function test_mixed_fixture_weapons_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['Weapons']['Ports']);
    }

    public function test_mixed_fixture_missile_racks_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['MissileRacks']['Ports']);
    }

    public function test_mixed_fixture_missiles_count(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(1, $result['Missiles']['Ports']);
    }

    public function test_mixed_fixture_missile_not_in_missile_racks(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        $rackUuids = array_map(
            fn (array $p) => $p['UUID'],
            $result['MissileRacks']['Ports'],
        );

        self::assertNotContains('m1', $rackUuids,
            'Actual missile must not appear in MissileRacks');
    }

    public function test_mixed_fixture_all_system_keys_present(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        self::assertCount(count(VehicleSystemKeys::ALL_KEYS), $result,
            'Result must have exactly the 32 system keys');
    }

    public function test_mixed_fixture_classified_ports_resolve_to_loadout(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        // Build index for resolution check
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedMixedFixture());

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            foreach ($result[$key]['Ports'] as $portRef) {
                $resolved = $index->findByPortId($portRef['PortId']);
                self::assertNotNull($resolved,
                    "Port {$portRef['PortId']} in system '{$key}' must resolve to a loadout entry");
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  Group 5: Skeleton does not populate summaries
    // ------------------------------------------------------------------ //

    public function test_skeleton_summaries_are_all_null_even_with_loadout(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        // Count-based summaries (derived from port counts) are populated even
        // without external data. Everything else must be null.
        $countBasedKeys = ['MissileRacks', 'Missiles'];

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            if (in_array($key, $countBasedKeys, true)) {
                // Count-based: non-null when ports exist
                $portCount = count($result[$key]['Ports']);
                if ($portCount > 0) {
                    self::assertNotNull($result[$key]['Summary'],
                        "Count-based system '{$key}' should have a Summary when ports exist");
                    self::assertSame($portCount, $result[$key]['Summary']['Count']);
                } else {
                    self::assertNull($result[$key]['Summary'],
                        "Count-based system '{$key}' should have null Summary when no ports");
                }
            } else {
                self::assertNull($result[$key]['Summary'],
                    "SystemsBuilder must leave calculator/item-backed Summary null without external data, including '{$key}'");
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  Group 6: Port reference object values are correct
    // ------------------------------------------------------------------ //

    public function test_shield_port_reference_has_correct_values(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        $port = $result['Shields']['Ports'][0];

        self::assertSame('loadout.0', $port['PortId']);
        self::assertSame('shield_1', $port['HardpointName']);
        self::assertSame('Shield', $port['Type']);
        self::assertNull($port['SubType'], 'UNDEFINED subtype normalizes to null');
        self::assertSame('s1', $port['UUID']);
        self::assertSame('SHLD_1', $port['ClassName']);
        self::assertNull($port['ParentPortId']);
        self::assertSame('loadout.0', $port['RootPortId']);
        self::assertSame(['shield_1'], $port['Path']);
    }

    public function test_jump_drive_port_reference_has_correct_values(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        $port = $result['JumpDrives']['Ports'][0];

        self::assertSame('loadout.1.loadout.0', $port['PortId']);
        self::assertSame('hardpoint_Jump_Drive', $port['HardpointName']);
        self::assertSame('JumpDrive', $port['Type']);
        self::assertNull($port['SubType']);
        self::assertSame('jd1', $port['UUID']);
        self::assertSame('JDRV_1', $port['ClassName']);
        self::assertSame('loadout.1', $port['ParentPortId']);
        self::assertSame('loadout.1', $port['RootPortId']);
        self::assertSame(['hardpoint_quantum_drive', 'hardpoint_Jump_Drive'], $port['Path']);
    }

    public function test_missile_port_reference_has_correct_values(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedMixedFixture());

        $port = $result['Missiles']['Ports'][0];

        self::assertSame('loadout.5.loadout.0', $port['PortId']);
        self::assertSame('missile_1', $port['HardpointName']);
        self::assertSame('Missile', $port['Type']);
        self::assertSame('Guided', $port['SubType']);
        self::assertSame('m1', $port['UUID']);
        self::assertSame('MSSL_Lockon_S2', $port['ClassName']);
        self::assertSame('loadout.5', $port['ParentPortId']);
        self::assertSame('loadout.5', $port['RootPortId']);
        self::assertSame(['missile_rack_1', 'missile_1'], $port['Path']);
    }
}
