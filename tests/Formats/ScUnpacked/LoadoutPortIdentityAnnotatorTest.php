<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator
 */
final class LoadoutPortIdentityAnnotatorTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Fixture builders
    // ------------------------------------------------------------------ //

    /**
     * Two turrets, each with one nested weapon sharing the same HardpointName.
     */
    private function duplicateHardpointFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_turret_a',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'TurretA',
                'UUID' => 'turret-a-uuid',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'WeaponA1',
                        'UUID' => 'weapon-a1-uuid',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'hardpoint_turret_b',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'TurretB',
                'UUID' => 'turret-b-uuid',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'WeaponB1',
                        'UUID' => 'weapon-b1-uuid',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Three-level deep nesting: turret -> mount -> weapon.
     */
    private function deepNestingFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_turret_back_rear',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'TurretRoot',
                'UUID' => 'turret-root-uuid',
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_left',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => 'VariPuck',
                        'UUID' => 'mount-uuid',
                        'Loadout' => [
                            [
                                'HardpointName' => 'hardpoint_class_2',
                                'Type' => 'WeaponGun.Gun',
                                'ClassName' => 'KLWE_LaserRepeater_S4',
                                'UUID' => 'weapon-uuid',
                                'Loadout' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * A port entry with no HardpointName (unnamed port).
     */
    private function unnamedPortFixture(): array
    {
        return [
            [
                'Type' => 'Generic',
                'ClassName' => 'UnnamedPort',
                'UUID' => 'unnamed-uuid',
                'Loadout' => [],
            ],
        ];
    }

    /**
     * Port with a nested child that also has no HardpointName.
     */
    private function deeplyUnnamedFixture(): array
    {
        return [
            [
                'Type' => 'Container',
                'ClassName' => 'Parent',
                'UUID' => 'parent-uuid',
                'Loadout' => [
                    [
                        'Type' => 'Generic',
                        'ClassName' => 'Child',
                        'UUID' => 'child-uuid',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Entry with an empty Loadout array (leaf node).
     */
    private function emptyLoadoutFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_TEST',
                'UUID' => 'shield-uuid',
                'Loadout' => [],
            ],
        ];
    }

    /**
     * Port with missing Loadout key entirely (no child key at all).
     */
    private function missingLoadoutKeyFixture(): array
    {
        return [
            [
                'HardpointName' => 'hardpoint_armor',
                'Type' => 'Armor.UNDEFINED',
                'ClassName' => 'ARMR_TEST',
                'UUID' => 'armor-uuid',
                // No 'Loadout' key at all
            ],
        ];
    }

    // ------------------------------------------------------------------ //
    //  1. Top-level identity fields
    // ------------------------------------------------------------------ //

    public function test_top_level_entries_get_port_id(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame('loadout.0', $result[0]['PortId']);
        self::assertSame('loadout.1', $result[1]['PortId']);
    }

    public function test_top_level_entries_have_null_parent_port_id(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertNull($result[0]['ParentPortId']);
        self::assertNull($result[1]['ParentPortId']);
    }

    public function test_top_level_entries_root_port_id_equals_own_port_id(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame('loadout.0', $result[0]['RootPortId']);
        self::assertSame('loadout.1', $result[1]['RootPortId']);
    }

    // ------------------------------------------------------------------ //
    //  2. Nested identity fields with duplicate hardpoint names
    // ------------------------------------------------------------------ //

    public function test_nested_entries_get_unique_port_id_despite_duplicate_hardpoint_name(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        // Both children share HardpointName 'hardpoint_class_2' but must differ
        $child0 = $result[0]['Loadout'][0];
        $child1 = $result[1]['Loadout'][0];

        self::assertSame('hardpoint_class_2', $child0['HardpointName']);
        self::assertSame('hardpoint_class_2', $child1['HardpointName']);
        self::assertNotSame($child0['PortId'], $child1['PortId'],
            'Nested entries with duplicate HardpointName must have unique PortId');
    }

    public function test_nested_entry_parent_port_id_points_to_parent(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame('loadout.0', $result[0]['Loadout'][0]['ParentPortId']);
        self::assertSame('loadout.1', $result[1]['Loadout'][0]['ParentPortId']);
    }

    public function test_nested_entry_root_port_id_points_to_top_level_ancestor(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame('loadout.0', $result[0]['Loadout'][0]['RootPortId']);
        self::assertSame('loadout.1', $result[1]['Loadout'][0]['RootPortId']);
    }

    // ------------------------------------------------------------------ //
    //  3. Path correctness
    // ------------------------------------------------------------------ //

    public function test_path_for_top_level_is_own_hardpoint_name(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame(['hardpoint_turret_a'], $result[0]['Path']);
        self::assertSame(['hardpoint_turret_b'], $result[1]['Path']);
    }

    public function test_path_for_nested_entries_includes_ancestry(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        self::assertSame(
            ['hardpoint_turret_a', 'hardpoint_class_2'],
            $result[0]['Loadout'][0]['Path']
        );
        self::assertSame(
            ['hardpoint_turret_b', 'hardpoint_class_2'],
            $result[1]['Loadout'][0]['Path']
        );
    }

    public function test_path_for_deeply_nested_entries_has_full_ancestry(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->deepNestingFixture());

        // Root: loadout.0
        self::assertSame(['hardpoint_turret_back_rear'], $result[0]['Path']);
        // Mount: loadout.0.loadout.0
        self::assertSame(
            ['hardpoint_turret_back_rear', 'turret_left'],
            $result[0]['Loadout'][0]['Path']
        );
        // Weapon: loadout.0.loadout.0.loadout.0
        self::assertSame(
            ['hardpoint_turret_back_rear', 'turret_left', 'hardpoint_class_2'],
            $result[0]['Loadout'][0]['Loadout'][0]['Path']
        );
    }

    public function test_path_contains_null_for_unnamed_ports(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->unnamedPortFixture());

        self::assertSame([null], $result[0]['Path']);
    }

    public function test_path_contains_null_for_deeply_unnamed_ports(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->deeplyUnnamedFixture());

        // Parent has no name -> [null]
        self::assertSame([null], $result[0]['Path']);
        // Child has no name -> [null, null]
        self::assertSame([null, null], $result[0]['Loadout'][0]['Path']);
    }

    // ------------------------------------------------------------------ //
    //  4. PortId format
    // ------------------------------------------------------------------ //

    public function test_port_id_format_is_loadout_dot_index(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->deepNestingFixture());

        self::assertSame('loadout.0', $result[0]['PortId']);
        self::assertSame('loadout.0.loadout.0', $result[0]['Loadout'][0]['PortId']);
        self::assertSame(
            'loadout.0.loadout.0.loadout.0',
            $result[0]['Loadout'][0]['Loadout'][0]['PortId']
        );
    }

    public function test_all_port_ids_are_unique(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        $allPortIds = $this->collectPortIds($result);

        self::assertCount(4, $allPortIds, 'Fixture should have 4 ports total');
        self::assertCount(4, array_unique($allPortIds), 'All PortIds must be unique');
    }

    // ------------------------------------------------------------------ //
    //  5. Original fields preserved
    // ------------------------------------------------------------------ //

    public function test_original_fields_are_preserved(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $fixture = $this->duplicateHardpointFixture();
        $result = $annotator->annotate($fixture);

        // Top-level
        self::assertSame('hardpoint_turret_a', $result[0]['HardpointName']);
        self::assertSame('TurretBase.MannedTurret', $result[0]['Type']);
        self::assertSame('TurretA', $result[0]['ClassName']);
        self::assertSame('turret-a-uuid', $result[0]['UUID']);

        // Nested
        $child = $result[0]['Loadout'][0];
        self::assertSame('hardpoint_class_2', $child['HardpointName']);
        self::assertSame('WeaponGun.Gun', $child['Type']);
        self::assertSame('WeaponA1', $child['ClassName']);
        self::assertSame('weapon-a1-uuid', $child['UUID']);
    }

    public function test_loadout_children_are_preserved(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->duplicateHardpointFixture());

        // Each top-level turret should still have exactly one child
        self::assertCount(1, $result[0]['Loadout']);
        self::assertCount(1, $result[1]['Loadout']);
    }

    // ------------------------------------------------------------------ //
    //  6. Edge cases
    // ------------------------------------------------------------------ //

    public function test_empty_loadout_returns_empty_array(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate([]);

        self::assertSame([], $result);
    }

    public function test_entries_with_empty_loadout_array_get_identity(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->emptyLoadoutFixture());

        self::assertSame('loadout.0', $result[0]['PortId']);
        self::assertNull($result[0]['ParentPortId']);
        self::assertSame('loadout.0', $result[0]['RootPortId']);
        self::assertSame(['hardpoint_shield_1'], $result[0]['Path']);
        self::assertSame([], $result[0]['Loadout']);
    }

    public function test_entries_without_loadout_key_get_identity(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->missingLoadoutKeyFixture());

        self::assertSame('loadout.0', $result[0]['PortId']);
        self::assertSame(['hardpoint_armor'], $result[0]['Path']);
        // No Loadout key should have been added (no children to recurse)
        self::assertArrayNotHasKey('Loadout', $result[0]);
    }

    public function test_unnamed_port_still_gets_port_id(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->unnamedPortFixture());

        self::assertSame('loadout.0', $result[0]['PortId']);
    }

    // ------------------------------------------------------------------ //
    //  7. Deep nesting (3+ levels)
    // ------------------------------------------------------------------ //

    public function test_deeply_nested_entry_has_correct_ancestry(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->deepNestingFixture());

        $root = $result[0];
        $mount = $root['Loadout'][0];
        $weapon = $mount['Loadout'][0];

        // Root
        self::assertSame('loadout.0', $root['PortId']);
        self::assertNull($root['ParentPortId']);
        self::assertSame('loadout.0', $root['RootPortId']);

        // Mount (1st child)
        self::assertSame('loadout.0.loadout.0', $mount['PortId']);
        self::assertSame('loadout.0', $mount['ParentPortId']);
        self::assertSame('loadout.0', $mount['RootPortId']);

        // Weapon (2nd child)
        self::assertSame('loadout.0.loadout.0.loadout.0', $weapon['PortId']);
        self::assertSame('loadout.0.loadout.0', $weapon['ParentPortId']);
        self::assertSame('loadout.0', $weapon['RootPortId']);
    }

    public function test_deeply_nested_preserves_original_fields(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($this->deepNestingFixture());

        $mount = $result[0]['Loadout'][0];
        $weapon = $mount['Loadout'][0];

        self::assertSame('turret_left', $mount['HardpointName']);
        self::assertSame('VariPuck', $mount['ClassName']);
        self::assertSame('hardpoint_class_2', $weapon['HardpointName']);
        self::assertSame('KLWE_LaserRepeater_S4', $weapon['ClassName']);
    }

    // ------------------------------------------------------------------ //
    //  8. Pure function / determinism
    // ------------------------------------------------------------------ //

    public function test_annotator_is_pure_and_deterministic(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $fixture = $this->duplicateHardpointFixture();

        $result1 = $annotator->annotate($fixture);
        $result2 = $annotator->annotate($fixture);

        self::assertSame($result1, $result2,
            'Annotator must be deterministic: same input always produces same output');
    }

    public function test_annotator_does_not_mutate_original(): void
    {
        $annotator = new LoadoutPortIdentityAnnotator;
        $fixture = $this->duplicateHardpointFixture();

        // Deep-clone the fixture to compare later
        $originalSnapshot = json_decode(json_encode($fixture), true);

        $annotator->annotate($fixture);

        // The original array should not have been modified
        self::assertSame($originalSnapshot, $fixture,
            'Annotator must not mutate the input array');
    }

    // ------------------------------------------------------------------ //
    //  10. Multiple top-level entries with multiple children each
    // ------------------------------------------------------------------ //

    public function test_multiple_children_under_same_parent(): void
    {
        $fixture = [
            [
                'HardpointName' => 'hardpoint_turret_front',
                'Type' => 'TurretBase.MannedTurret',
                'Loadout' => [
                    [
                        'HardpointName' => 'turret_left',
                        'Type' => 'Turret.GunTurret',
                        'Loadout' => [],
                    ],
                    [
                        'HardpointName' => 'turret_right',
                        'Type' => 'Turret.GunTurret',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];

        $annotator = new LoadoutPortIdentityAnnotator;
        $result = $annotator->annotate($fixture);

        self::assertSame('loadout.0', $result[0]['PortId']);
        self::assertSame('loadout.0.loadout.0', $result[0]['Loadout'][0]['PortId']);
        self::assertSame('loadout.0.loadout.1', $result[0]['Loadout'][1]['PortId']);

        self::assertSame(
            ['hardpoint_turret_front', 'turret_left'],
            $result[0]['Loadout'][0]['Path']
        );
        self::assertSame(
            ['hardpoint_turret_front', 'turret_right'],
            $result[0]['Loadout'][1]['Path']
        );
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    /**
     * Recursively collect all PortIds from an annotated loadout.
     *
     * @param  list<array<string, mixed>>  $loadout
     * @return list<string>
     */
    private function collectPortIds(array $loadout): array
    {
        $ids = [];
        foreach ($loadout as $entry) {
            $ids[] = $entry['PortId'];
            if (! empty($entry['Loadout']) && is_array($entry['Loadout'])) {
                $ids = array_merge($ids, $this->collectPortIds($entry['Loadout']));
            }
        }

        return $ids;
    }
}
