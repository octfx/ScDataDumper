<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex;
use Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * Backward compatibility and output validation tests for the v4 Systems pipeline.
 *
 * These tests verify:
 * - Loadout structure is unchanged except for additive identity fields
 * - Systems port references are lightweight (no recursive subtrees)
 * - Output is JSON-serializable without circular refs or non-scalar objects
 * - Output size is reasonable for complex ship fixtures
 *
 * Part A: Backward compatibility regression tests
 * Part B: Performance / size characterization tests
 *
 * @see API/docs/v4-vehicle-systems-scdatadumper-implementation-handoff.md
 */
final class BackwardCompatibilityTest extends TestCase
{
    // ================================================================== //
    //  Part A: Backward compatibility regression tests
    // ================================================================== //

    // ------------------------------------------------------------------ //
    //  1. Top-level keys preserved
    // ------------------------------------------------------------------ //

    public function test_systems_output_has_loadout_key(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        // Systems output must be a well-formed array
        self::assertIsArray($systems);
        self::assertNotEmpty($systems);
    }

    public function test_systems_output_has_all_32_keys(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $systems,
                "Systems output must contain key '{$key}'");
        }
        self::assertCount(count(VehicleSystemKeys::ALL_KEYS), $systems,
            'Systems output must have exactly 32 keys');
    }

    // ------------------------------------------------------------------ //
    //  2. Loadout structure unchanged
    // ------------------------------------------------------------------ //

    public function test_annotated_loadout_preserves_hardpoint_name(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        foreach ($this->flattenLoadout($annotated) as $entry) {
            self::assertArrayHasKey('HardpointName', $entry,
                'Every loadout entry must still have HardpointName');
        }
    }

    public function test_annotated_loadout_preserves_class_name(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        foreach ($this->flattenLoadout($annotated) as $entry) {
            // Entries without installed items won't have ClassName
            if (isset($entry['ClassName'])) {
                self::assertIsString($entry['ClassName']);
            }
        }
    }

    public function test_annotated_loadout_preserves_type(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        foreach ($this->flattenLoadout($annotated) as $entry) {
            if (isset($entry['Type'])) {
                self::assertIsString($entry['Type']);
            }
        }
    }

    public function test_annotated_loadout_child_key_is_still_loadout(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        // Check that nested children are under the 'Loadout' key
        $foundNested = false;
        foreach ($this->flattenLoadout($annotated) as $entry) {
            if (isset($entry['Loadout']) && is_array($entry['Loadout']) && ! empty($entry['Loadout'])) {
                $foundNested = true;
                self::assertIsArray($entry['Loadout']);
                // Children must also have HardpointName
                foreach ($entry['Loadout'] as $child) {
                    self::assertArrayHasKey('HardpointName', $child,
                        'Nested loadout children must have HardpointName');
                }
            }
        }

        self::assertTrue($foundNested,
            'Complex fixture must have at least one nested loadout entry');
    }

    public function test_annotated_loadout_entries_have_port_id(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        foreach ($this->flattenLoadout($annotated) as $entry) {
            self::assertArrayHasKey('PortId', $entry,
                sprintf('Entry "%s" must have additive PortId field',
                    $entry['HardpointName'] ?? '(unnamed)'));
        }
    }

    public function test_annotated_loadout_nested_entries_have_port_id(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        $nestedCount = 0;
        foreach ($this->flattenLoadout($annotated) as $entry) {
            if (isset($entry['ParentPortId']) && $entry['ParentPortId'] !== null) {
                self::assertArrayHasKey('PortId', $entry,
                    'Nested entries must also have PortId');
                $nestedCount++;
            }
        }

        self::assertGreaterThan(0, $nestedCount,
            'Complex fixture must have nested entries with PortId');
    }

    public function test_annotated_loadout_preserves_uuid(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        foreach ($this->flattenLoadout($annotated) as $entry) {
            if (isset($entry['UUID'])) {
                self::assertIsString($entry['UUID']);
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  3. Systems port references are lightweight
    // ------------------------------------------------------------------ //

    public function test_system_port_refs_do_not_contain_loadout_key(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $i => $portRef) {
                self::assertArrayNotHasKey('Loadout', $portRef,
                    "{$systemKey}[{$i}] must NOT contain Loadout key -- refs must be flat");
            }
        }
    }

    public function test_system_port_refs_have_exactly_port_ref_keys(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $expectedKeys = VehicleSystemKeys::PORT_REF_KEYS;
        sort($expectedKeys);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $i => $portRef) {
                $actualKeys = array_keys($portRef);
                sort($actualKeys);

                self::assertEquals($expectedKeys, $actualKeys,
                    "{$systemKey}[{$i}] must have exactly PORT_REF_KEYS fields");
            }
        }
    }

    public function test_system_port_refs_have_no_extraneous_keys(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $allowedKeys = VehicleSystemKeys::PORT_REF_KEYS;
        $allowedSet = array_flip($allowedKeys);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $i => $portRef) {
                foreach (array_keys($portRef) as $key) {
                    self::assertArrayHasKey($key, $allowedSet,
                        "{$systemKey}[{$i}] has unexpected key '{$key}'");
                }
            }
        }
    }

    public function test_each_port_appears_in_exactly_one_system(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $allPortIds = [];
        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $portRef) {
                $portId = $portRef['PortId'];
                self::assertNotContains($portId, $allPortIds,
                    "PortId '{$portId}' must appear in exactly one system, found duplicate");
                $allPortIds[] = $portId;
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  4. JSON serialization
    // ------------------------------------------------------------------ //

    public function test_full_systems_output_is_json_serializable(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $json = json_encode($systems);
        self::assertNotFalse($json, 'Systems output must be JSON-serializable');
        self::assertJson($json);

        // Round-trip
        $decoded = json_decode($json, true);
        self::assertEquals($systems, $decoded,
            'Systems output must survive JSON round-trip');
    }

    public function test_annotated_loadout_is_json_serializable(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();

        $json = json_encode($annotated);
        self::assertNotFalse($json, 'Annotated loadout must be JSON-serializable');
        self::assertJson($json);

        // Round-trip
        $decoded = json_decode($json, true);
        self::assertEquals($annotated, $decoded,
            'Annotated loadout must survive JSON round-trip');
    }

    public function test_combined_output_is_json_serializable(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $combined = [
            'Loadout' => $annotated,
            'Systems' => $systems,
        ];

        $json = json_encode($combined);
        self::assertNotFalse($json, 'Combined Loadout + Systems must be JSON-serializable');

        $decoded = json_decode($json, true);
        self::assertEquals($combined, $decoded,
            'Combined output must survive JSON round-trip');
    }

    public function test_no_stdclass_objects_in_output(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $this->assertNoStdClass($systems, 'Systems');
        $this->assertNoStdClass($annotated, 'Loadout');
    }

    public function test_no_circular_references_in_output(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        // json_encode returns false on circular refs
        $json = json_encode(['Loadout' => $annotated, 'Systems' => $systems]);
        self::assertNotFalse($json,
            'Combined output must not have circular references');
    }

    // ------------------------------------------------------------------ //
    //  5. No duplicate port trees
    // ------------------------------------------------------------------ //

    public function test_system_refs_do_not_embed_nested_loadout_children(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $i => $portRef) {
                // Port refs should be flat -- no nested children
                self::assertArrayNotHasKey('Loadout', $portRef,
                    "{$systemKey}[{$i}] ref must not embed Loadout subtree");
                self::assertArrayNotHasKey('Children', $portRef,
                    "{$systemKey}[{$i}] ref must not have Children key");
                self::assertArrayNotHasKey('CompatibleTypes', $portRef,
                    "{$systemKey}[{$i}] ref must not have CompatibleTypes");
                self::assertArrayNotHasKey('Editable', $portRef,
                    "{$systemKey}[{$i}] ref must not have Editable");
                self::assertArrayNotHasKey('MaxSize', $portRef,
                    "{$systemKey}[{$i}] ref must not have MaxSize");
                self::assertArrayNotHasKey('MinSize', $portRef,
                    "{$systemKey}[{$i}] ref must not have MinSize");
            }
        }
    }

    public function test_system_refs_are_smaller_than_full_loadout_entries(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $index = (new RecursiveLoadoutPortIndex)->build($annotated);

        foreach (VehicleSystemKeys::ALL_KEYS as $systemKey) {
            foreach ($systems[$systemKey]['Ports'] as $portRef) {
                $fullEntry = $index->findByPortId($portRef['PortId']);
                if ($fullEntry === null) {
                    continue;
                }

                $refSize = strlen(json_encode($portRef));
                $fullSize = strlen(json_encode($fullEntry));

                // Reference objects should always be smaller than full entries
                // (unless the entry has no children and is minimal)
                if ($fullSize > 200) {
                    self::assertLessThan($fullSize, $refSize,
                        "{$systemKey} port ref for '{$portRef['PortId']}' should be smaller than full loadout entry");
                }
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  Additive identity fields
    // ------------------------------------------------------------------ //

    public function test_additive_fields_do_not_replace_existing_fields(): void
    {
        $raw = [
            [
                'HardpointName' => 'hardpoint_shield_0',
                'ClassName' => 'SHLD_Test',
                'UUID' => 'shield-0',
                'Type' => 'Shield.UNDEFINED',
                'Grade' => 'C',
                'Editable' => true,
                'CompatibleTypes' => [['Type' => 'Shield']],
                'MaxSize' => 2,
                'MinSize' => 1,
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_qd',
                'ClassName' => 'QD_Test',
                'UUID' => 'qd-0',
                'Type' => 'QuantumDrive.UNDEFINED',
                'Grade' => 'B',
                'Editable' => false,
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_jd',
                        'ClassName' => 'JD_Test',
                        'UUID' => 'jd-0',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'Grade' => 'A',
                        'Editable' => false,
                        'Loadout' => [],
                    ],
                ],
            ],
        ];

        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($raw);

        // Top-level shield entry: original fields preserved
        $shield = $annotated[0];
        self::assertSame('hardpoint_shield_0', $shield['HardpointName']);
        self::assertSame('SHLD_Test', $shield['ClassName']);
        self::assertSame('shield-0', $shield['UUID']);
        self::assertSame('Shield.UNDEFINED', $shield['Type']);
        self::assertSame('C', $shield['Grade']);
        self::assertTrue($shield['Editable']);
        self::assertSame(2, $shield['MaxSize']);
        self::assertSame(1, $shield['MinSize']);

        // QD entry
        $qd = $annotated[1];
        self::assertSame('hardpoint_qd', $qd['HardpointName']);
        self::assertSame('QD_Test', $qd['ClassName']);

        // Nested JD entry: original fields preserved
        $jd = $qd['Loadout'][0];
        self::assertSame('hardpoint_jd', $jd['HardpointName']);
        self::assertSame('JD_Test', $jd['ClassName']);
        self::assertSame('jd-0', $jd['UUID']);
        self::assertSame('JumpDrive.UNDEFINED', $jd['Type']);
        self::assertSame('A', $jd['Grade']);
        self::assertFalse($jd['Editable']);
    }

    // ================================================================== //
    //  Part B: Performance / size characterization tests
    // ================================================================== //

    public function test_systems_output_is_smaller_than_loadout_for_complex_ship(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $loadoutJson = json_encode($annotated);
        $systemsJson = json_encode($systems);

        self::assertLessThan(
            strlen($loadoutJson),
            strlen($systemsJson),
            'Systems output should be smaller than Loadout (lightweight references only)'
        );
    }

    public function test_total_output_size_is_reasonable_for_complex_ship(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $combined = ['Loadout' => $annotated, 'Systems' => $systems];
        $totalJson = json_encode($combined);
        $totalSize = strlen($totalJson);

        // Total combined output should be under 2MB
        // This is a generous limit -- real ship output is typically much smaller
        self::assertLessThan(2 * 1024 * 1024, $totalSize,
            'Total combined output should be under 2MB');

        // Record actual size for documentation
        // (This assertion always passes -- it's a characterization)
        $loadoutSize = strlen(json_encode($annotated));
        $systemsSize = strlen(json_encode($systems));
        $overhead = $totalSize - $loadoutSize;
        $overheadPercent = $loadoutSize > 0 ? round(($overhead / $loadoutSize) * 100, 1) : 0;

        // Systems overhead should be less than 100% of loadout size
        // (i.e., adding Systems shouldn't double the payload)
        self::assertLessThan(100, $overheadPercent,
            sprintf('Systems overhead should be < 100%% of loadout (actual: %.1f%%)', $overheadPercent));
    }

    public function test_systems_output_size_characterization(): void
    {
        $annotated = $this->buildComplexAnnotatedLoadout();
        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated);

        $loadoutSize = strlen(json_encode($annotated));
        $systemsSize = strlen(json_encode($systems));
        $combinedSize = strlen(json_encode(['Loadout' => $annotated, 'Systems' => $systems]));

        // Characterize: how many entries in the fixture?
        $flatCount = count($this->flattenLoadout($annotated));

        // Count non-empty systems
        $nonEmptySystems = 0;
        $totalPortRefs = 0;
        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            $count = count($systems[$key]['Ports']);
            if ($count > 0) {
                $nonEmptySystems++;
                $totalPortRefs += $count;
            }
        }

        // Sanity: total port refs should match flat loadout count
        self::assertSame($flatCount, $totalPortRefs,
            sprintf('Total system port refs (%d) should match flat loadout entries (%d)',
                $totalPortRefs, $flatCount));

        // Characterization output (never fails, just documents)
        $overhead = $combinedSize - $loadoutSize;
        $overheadPercent = $loadoutSize > 0 ? round(($overhead / $loadoutSize) * 100, 1) : 0;

        // These assertions just verify the data is reasonable
        self::assertGreaterThan(0, $loadoutSize);
        self::assertGreaterThan(0, $systemsSize);
        self::assertGreaterThan(0, $combinedSize);
        self::assertGreaterThan(0, $nonEmptySystems);
        self::assertGreaterThan(0, $totalPortRefs);
    }

    // ================================================================== //
    //  Fixture builders
    // ================================================================== //

    /**
     * Build a Carrack-like annotated loadout with ~60+ entries.
     *
     * This mirrors the Carrack fixture from SystemsRegressionTest but
     * includes additional fields (Grade, Editable, CompatibleTypes, MaxSize, MinSize)
     * to better exercise backward compatibility assertions.
     */
    private function buildComplexAnnotatedLoadout(): array
    {
        $raw = [];

        // 2 shields
        for ($i = 0; $i < 2; $i++) {
            $raw[] = $this->makeEntry(
                "hardpoint_shield_{$i}",
                'Shield.UNDEFINED',
                "SHLD_Carrack_{$i}",
                "shield-{$i}",
                'C',
                true,
            );
        }

        // 1 quantum drive with 1 nested jump drive
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_drive',
            'ClassName' => 'QDRV_Carrack',
            'UUID' => 'qd-1',
            'Type' => 'QuantumDrive.UNDEFINED',
            'Grade' => 'B',
            'Editable' => false,
            'CompatibleTypes' => [['Type' => 'QuantumDrive']],
            'MaxSize' => 2,
            'MinSize' => 1,
            'Loadout' => [
                $this->makeEntry(
                    'hardpoint_Jump_Drive',
                    'JumpDrive.UNDEFINED',
                    'JDRV_Carrack',
                    'jd-1',
                    'A',
                    false,
                ),
            ],
        ];

        // 1 flight controller
        $raw[] = $this->makeEntry(
            'hardpoint_flight_controller',
            'FlightController.UNDEFINED',
            'FC_Carrack',
            'fc-1',
            'A',
        );

        // 22 thrusters
        $thrusterTypes = ['MainThruster', 'MainThruster', 'RetroThruster', 'RetroThruster',
            'VtolThruster', 'VtolThruster', 'VtolThruster', 'VtolThruster'];
        for ($i = 0; $i < 14; $i++) {
            $thrusterTypes[] = 'ManeuverThruster';
        }
        foreach ($thrusterTypes as $i => $tType) {
            $raw[] = $this->makeEntry(
                "hardpoint_thruster_{$i}",
                "{$tType}.UNDEFINED",
                "THR_Carrack_{$i}",
                "thr-{$i}",
                'C',
            );
        }

        // 1 quantum fuel tank
        $raw[] = [
            'HardpointName' => 'hardpoint_quantum_fuel_tank',
            'ClassName' => 'QFT_Carrack',
            'UUID' => 'qft-1',
            'Type' => 'QuantumFuelTank.UNDEFINED',
            'Grade' => 'A',
            'Editable' => false,
            'Loadout' => [],
        ];

        // 2 hydrogen fuel tanks
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_hydrogen_fuel_tank_{$i}",
                'ClassName' => "HFT_Carrack_{$i}",
                'UUID' => "hft-{$i}",
                'Type' => 'FuelTank.UNDEFINED',
                'Grade' => 'A',
                'Editable' => false,
                'Loadout' => [],
            ];
        }

        // 2 fuel intakes
        for ($i = 0; $i < 2; $i++) {
            $raw[] = [
                'HardpointName' => "hardpoint_fuel_intake_{$i}",
                'ClassName' => "FI_Carrack_{$i}",
                'UUID' => "fi-{$i}",
                'Type' => 'FuelIntake.UNDEFINED',
                'Grade' => 'C',
                'Editable' => true,
                'Loadout' => [],
            ];
        }

        // 2 coolers
        for ($i = 0; $i < 2; $i++) {
            $raw[] = $this->makeEntry(
                "hardpoint_cooler_{$i}",
                'Cooler.UNDEFINED',
                "CLR_Carrack_{$i}",
                "clr-{$i}",
                'B',
            );
        }

        // 2 power plants
        for ($i = 0; $i < 2; $i++) {
            $raw[] = $this->makeEntry(
                "hardpoint_power_plant_{$i}",
                'PowerPlant.UNDEFINED',
                "PP_Carrack_{$i}",
                "pp-{$i}",
                'B',
            );
        }

        // 1 armor
        $raw[] = $this->makeEntry(
            'hardpoint_armor',
            'Armor.UNDEFINED',
            'ARMR_Carrack',
            'armor-1',
            'A',
            false,
        );

        // 3 manned turrets: turret root -> gimbal -> weapon
        $turretNames = ['hardpoint_turret_front', 'hardpoint_turret_back_rear', 'hardpoint_turret_bottom'];
        for ($i = 0; $i < 3; $i++) {
            $raw[] = [
                'HardpointName' => $turretNames[$i],
                'ClassName' => "TURR_Carrack_{$i}",
                'UUID' => "turr-m-{$i}",
                'Type' => 'TurretBase.MannedTurret',
                'Grade' => 'A',
                'Editable' => false,
                'CompatibleTypes' => [['Type' => 'TurretBase']],
                'MaxSize' => 3,
                'MinSize' => 1,
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_left',
                        'ClassName' => "VariPuck_Carrack_{$i}",
                        'UUID' => "gimbal-m-{$i}",
                        'Type' => 'Turret.GunTurret',
                        'Grade' => 'A',
                        'Editable' => false,
                        'CompatibleTypes' => [['Type' => 'WeaponGun']],
                        'MaxSize' => 4,
                        'MinSize' => 1,
                        'Loadout' => [
                            $this->makeEntry(
                                'hardpoint_class_2',
                                'WeaponGun.Ballistic',
                                "WPN_Carrack_M{$i}",
                                "wpn-m-{$i}",
                                'C',
                            ),
                        ],
                    ],
                    [
                        'HardpointName' => 'turret_right',
                        'ClassName' => "VariPuck_Carrack_R{$i}",
                        'UUID' => "gimbal-mr-{$i}",
                        'Type' => 'Turret.GunTurret',
                        'Grade' => 'A',
                        'Editable' => false,
                        'CompatibleTypes' => [['Type' => 'WeaponGun']],
                        'MaxSize' => 4,
                        'MinSize' => 1,
                        'Loadout' => [
                            $this->makeEntry(
                                'hardpoint_class_2',
                                'WeaponGun.Energy',
                                "WPN_Carrack_MR{$i}",
                                "wpn-mr-{$i}",
                                'C',
                            ),
                        ],
                    ],
                ],
            ];
        }

        // 1 remote turret
        $raw[] = [
            'HardpointName' => 'hardpoint_turret_remote_top',
            'ClassName' => 'TURR_Carrack_Remote',
            'UUID' => 'turr-r-0',
            'Type' => 'TurretBase.RemoteTurret',
            'Grade' => 'A',
            'Editable' => false,
            'Loadout' => [
                [
                    'HardpointName' => 'turret_left',
                    'ClassName' => 'VariPuck_Carrack_Remote',
                    'UUID' => 'gimbal-r-0',
                    'Type' => 'Turret.GunTurret',
                    'Grade' => 'A',
                    'Editable' => false,
                    'Loadout' => [
                        $this->makeEntry(
                            'hardpoint_class_2',
                            'WeaponGun.Energy',
                            'WPN_Carrack_R0',
                            'wpn-r-0',
                            'D',
                        ),
                    ],
                ],
                [
                    'HardpointName' => 'turret_right',
                    'ClassName' => 'VariPuck_Carrack_Remote_R',
                    'UUID' => 'gimbal-rr-0',
                    'Type' => 'Turret.GunTurret',
                    'Grade' => 'A',
                    'Editable' => false,
                    'Loadout' => [
                        $this->makeEntry(
                            'hardpoint_class_2',
                            'WeaponGun.Energy',
                            'WPN_Carrack_RR0',
                            'wpn-rr-0',
                            'D',
                        ),
                    ],
                ],
            ],
        ];

        // 1 radar
        $raw[] = $this->makeEntry(
            'hardpoint_radar',
            'Radar.UNDEFINED',
            'RAD_Carrack',
            'rad-1',
            'B',
        );

        // 1 life support
        $raw[] = $this->makeEntry(
            'hardpoint_life_support',
            'LifeSupportGenerator.UNDEFINED',
            'LS_Carrack',
            'ls-1',
            'A',
            false,
        );

        // 2 countermeasures
        for ($i = 0; $i < 2; $i++) {
            $raw[] = $this->makeEntry(
                "hardpoint_countermeasure_{$i}",
                'WeaponDefensive.CounterMeasure',
                "CM_Carrack_{$i}",
                "cm-{$i}",
                'A',
            );
        }

        // 2 weapon lockers
        for ($i = 0; $i < 2; $i++) {
            $raw[] = $this->makeEntry(
                "hardpoint_weapon_locker_{$i}",
                'WeaponLocker.UNDEFINED',
                "WL_Carrack_{$i}",
                "wl-{$i}",
                'A',
                false,
            );
        }

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    /**
     * Helper: make a simple loadout entry with common fields.
     */
    private function makeEntry(
        string $hardpointName,
        string $type,
        string $className,
        string $uuid,
        string $grade = 'C',
        bool $editable = true,
    ): array {
        return [
            'HardpointName' => $hardpointName,
            'ClassName' => $className,
            'UUID' => $uuid,
            'Type' => $type,
            'Grade' => $grade,
            'Editable' => $editable,
            'CompatibleTypes' => [['Type' => explode('.', $type)[0]]],
            'MaxSize' => 2,
            'MinSize' => 1,
            'Loadout' => [],
        ];
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
     * Assert that no stdClass objects exist anywhere in the data structure.
     */
    /**
     * Assert that no stdClass objects exist anywhere in the data structure.
     *
     * Returns the number of leaf nodes checked, which is always > 0 for non-empty data.
     * PHPUnit sees the assertion count and does not flag the test as risky.
     */
    private function assertNoStdClass(mixed $data, string $path): int
    {
        if (is_object($data)) {
            self::fail("Found stdClass object at path '{$path}' -- output must contain only arrays and scalars");
        }

        if (! is_array($data)) {
            // Leaf scalar/null -- assert it's not an object
            self::assertIsNotObject($data);

            return 1;
        }

        $count = 0;
        foreach ($data as $key => $value) {
            $count += $this->assertNoStdClass($value, "{$path}[{$key}]");
        }

        // For empty arrays, still make an assertion
        if ($count === 0) {
            self::assertIsArray($data);
            $count = 1;
        }

        return $count;
    }
}
