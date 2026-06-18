<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\TurretDpsEnricher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\TurretDpsEnricher
 */
final class TurretDpsEnricherTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Fixture builders
    // ------------------------------------------------------------------ //

    /**
     * Annotated loadout with one turret root, a nested gimbal, a nested weapon,
     * plus one non-turret entry (shield).
     */
    private function singleTurretFixture(): array
    {
        return [
            [
                'PortId' => 'loadout.0',
                'ParentPortId' => null,
                'RootPortId' => 'loadout.0',
                'Path' => ['hardpoint_turret_back_rear'],
                'HardpointName' => 'hardpoint_turret_back_rear',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_Manned',
                'UUID' => 'turret-1',
                'Editable' => true,
                'Loadout' => [
                    [
                        'PortId' => 'loadout.0.loadout.0',
                        'ParentPortId' => 'loadout.0',
                        'RootPortId' => 'loadout.0',
                        'Path' => ['hardpoint_turret_back_rear', 'turret_left'],
                        'HardpointName' => 'turret_left',
                        'Type' => 'Turret.GunTurret',
                        'ClassName' => 'VariPuck_Gimbal_S4',
                        'UUID' => 'gimbal-1',
                        'Editable' => true,
                        'Loadout' => [
                            [
                                'PortId' => 'loadout.0.loadout.0.loadout.0',
                                'ParentPortId' => 'loadout.0.loadout.0',
                                'RootPortId' => 'loadout.0',
                                'Path' => ['hardpoint_turret_back_rear', 'turret_left', 'hardpoint_class_2'],
                                'HardpointName' => 'hardpoint_class_2',
                                'Type' => 'WeaponGun.Gun',
                                'ClassName' => 'KLWE_LaserRepeater_S4',
                                'UUID' => 'weapon-1',
                                'Editable' => true,
                                'Loadout' => [],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'PortId' => 'loadout.1',
                'ParentPortId' => null,
                'RootPortId' => 'loadout.1',
                'Path' => ['shield_1'],
                'HardpointName' => 'shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Editable' => true,
                'Loadout' => [],
            ],
        ];
    }

    /**
     * Calculated data with one turret DPS entry matching the turret root.
     */
    private function singleTurretCalculatedData(): array
    {
        return [
            'Weaponry' => [
                'Turrets' => [
                    [
                        'HardpointName' => 'hardpoint_turret_back_rear',
                        'DpsTotal' => 1635.8,
                        'SustainedDpsTotal' => 829.0,
                        'AlphaTotal' => 130.8,
                        'IsPilotSlaveable' => false,
                        'Weapons' => [
                            ['ClassName' => 'KLWE_LaserRepeater_S4', 'Dps' => 817.9, 'Alpha' => 65.4, 'IsPilotSlaveable' => false],
                            ['ClassName' => 'KLWE_LaserRepeater_S4', 'Dps' => 817.9, 'Alpha' => 65.4, 'IsPilotSlaveable' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Annotated loadout with two turret roots and one non-turret entry.
     */
    private function multiTurretFixture(): array
    {
        return [
            [
                'PortId' => 'loadout.0',
                'ParentPortId' => null,
                'RootPortId' => 'loadout.0',
                'Path' => ['hardpoint_turret_front'],
                'HardpointName' => 'hardpoint_turret_front',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_Manned_Front',
                'UUID' => 'turret-front',
                'Loadout' => [
                    [
                        'PortId' => 'loadout.0.loadout.0',
                        'ParentPortId' => 'loadout.0',
                        'RootPortId' => 'loadout.0',
                        'Path' => ['hardpoint_turret_front', 'hardpoint_class_2'],
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'Weapon_Front',
                        'UUID' => 'weapon-front',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'PortId' => 'loadout.1',
                'ParentPortId' => null,
                'RootPortId' => 'loadout.1',
                'Path' => ['hardpoint_turret_back'],
                'HardpointName' => 'hardpoint_turret_back',
                'Type' => 'TurretBase.RemoteTurret',
                'ClassName' => 'Turret_Remote_Back',
                'UUID' => 'turret-back',
                'Loadout' => [
                    [
                        'PortId' => 'loadout.1.loadout.0',
                        'ParentPortId' => 'loadout.1',
                        'RootPortId' => 'loadout.1',
                        'Path' => ['hardpoint_turret_back', 'hardpoint_class_2'],
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'Weapon_Back',
                        'UUID' => 'weapon-back',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'PortId' => 'loadout.2',
                'ParentPortId' => null,
                'RootPortId' => 'loadout.2',
                'Path' => ['hardpoint_quantum_drive'],
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QD_1',
                'UUID' => 'qd-1',
                'Loadout' => [],
            ],
        ];
    }

    /**
     * Calculated data with two turret DPS entries.
     */
    private function multiTurretCalculatedData(): array
    {
        return [
            'Weaponry' => [
                'Turrets' => [
                    [
                        'HardpointName' => 'hardpoint_turret_front',
                        'DpsTotal' => 500.0,
                        'SustainedDpsTotal' => 250.0,
                        'AlphaTotal' => 100.0,
                        'IsPilotSlaveable' => true,
                        'Weapons' => [
                            ['ClassName' => 'Weapon_Front', 'Dps' => 500.0, 'Alpha' => 100.0, 'IsPilotSlaveable' => true],
                        ],
                    ],
                    [
                        'HardpointName' => 'hardpoint_turret_back',
                        'DpsTotal' => 800.0,
                        'SustainedDpsTotal' => 400.0,
                        'AlphaTotal' => 200.0,
                        'IsPilotSlaveable' => false,
                        'Weapons' => [
                            ['ClassName' => 'Weapon_Back', 'Dps' => 800.0, 'Alpha' => 200.0, 'IsPilotSlaveable' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------ //
    //  Tests -- single turret enrichment
    // ------------------------------------------------------------------ //

    public function test_turret_root_gets_dps_total(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $this->assertSame(1635.8, $result[0]['DpsTotal']);
    }

    public function test_turret_root_gets_sustained_dps_total(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $this->assertSame(829.0, $result[0]['SustainedDpsTotal']);
    }

    public function test_turret_root_gets_alpha_total(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $this->assertSame(130.8, $result[0]['AlphaTotal']);
    }

    public function test_turret_root_gets_weapons_array(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $this->assertIsArray($result[0]['Weapons']);
        $this->assertCount(2, $result[0]['Weapons']);
    }

    public function test_turret_root_gets_is_pilot_slaveable(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $this->assertFalse($result[0]['IsPilotSlaveable']);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- nested entries must NOT get turret DPS
    // ------------------------------------------------------------------ //

    public function test_nested_gimbal_does_not_get_turret_dps(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $gimbal = $result[0]['Loadout'][0];

        $this->assertArrayNotHasKey('DpsTotal', $gimbal);
        $this->assertArrayNotHasKey('SustainedDpsTotal', $gimbal);
        $this->assertArrayNotHasKey('AlphaTotal', $gimbal);
    }

    public function test_nested_weapon_does_not_get_turret_dps(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $weapon = $result[0]['Loadout'][0]['Loadout'][0];

        $this->assertArrayNotHasKey('DpsTotal', $weapon);
        $this->assertArrayNotHasKey('AlphaTotal', $weapon);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- non-turret entries unchanged
    // ------------------------------------------------------------------ //

    public function test_non_turret_entry_unchanged(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $shield = $result[1];

        $this->assertArrayNotHasKey('DpsTotal', $shield);
        $this->assertArrayNotHasKey('Weapons', $shield);
        $this->assertSame('loadout.1', $shield['PortId']);
        $this->assertSame('Shield.UNDEFINED', $shield['Type']);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- original fields preserved
    // ------------------------------------------------------------------ //

    public function test_original_fields_preserved_on_turret_root(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $turret = $result[0];

        $this->assertSame('loadout.0', $turret['PortId']);
        $this->assertNull($turret['ParentPortId']);
        $this->assertSame('loadout.0', $turret['RootPortId']);
        $this->assertSame(['hardpoint_turret_back_rear'], $turret['Path']);
        $this->assertSame('hardpoint_turret_back_rear', $turret['HardpointName']);
        $this->assertSame('TurretBase.MannedTurret', $turret['Type']);
        $this->assertSame('Turret_Manned', $turret['ClassName']);
        $this->assertSame('turret-1', $turret['UUID']);
        $this->assertTrue($turret['Editable']);
    }

    public function test_original_fields_preserved_on_nested_gimbal(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->singleTurretFixture(), $this->singleTurretCalculatedData());

        $gimbal = $result[0]['Loadout'][0];

        $this->assertSame('loadout.0.loadout.0', $gimbal['PortId']);
        $this->assertSame('loadout.0', $gimbal['ParentPortId']);
        $this->assertSame('VariPuck_Gimbal_S4', $gimbal['ClassName']);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- empty/missing calculated data
    // ------------------------------------------------------------------ //

    public function test_empty_calculated_data_returns_unchanged_loadout(): void
    {
        $enricher = new TurretDpsEnricher;
        $loadout = $this->singleTurretFixture();

        $result = $enricher->enrich($loadout, []);

        // Loadout entries should have no DPS fields
        $this->assertArrayNotHasKey('DpsTotal', $result[0]);
        $this->assertArrayNotHasKey('DpsTotal', $result[1]);
    }

    public function test_empty_turrets_returns_unchanged_loadout(): void
    {
        $enricher = new TurretDpsEnricher;
        $loadout = $this->singleTurretFixture();

        $result = $enricher->enrich($loadout, ['Weaponry' => ['Turrets' => []]]);

        $this->assertArrayNotHasKey('DpsTotal', $result[0]);
    }

    public function test_weaponry_without_turrets_key_returns_unchanged_loadout(): void
    {
        $enricher = new TurretDpsEnricher;
        $loadout = $this->singleTurretFixture();

        $result = $enricher->enrich($loadout, ['Weaponry' => ['PilotDps' => 100.0]]);

        $this->assertArrayNotHasKey('DpsTotal', $result[0]);
    }

    public function test_empty_loadout_returns_empty_array(): void
    {
        $enricher = new TurretDpsEnricher;

        $result = $enricher->enrich([], $this->singleTurretCalculatedData());

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- multiple turrets
    // ------------------------------------------------------------------ //

    public function test_multiple_turrets_matched_correctly(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->multiTurretFixture(), $this->multiTurretCalculatedData());

        // First turret root (front)
        $this->assertSame(500.0, $result[0]['DpsTotal']);
        $this->assertSame(250.0, $result[0]['SustainedDpsTotal']);
        $this->assertSame(100.0, $result[0]['AlphaTotal']);
        $this->assertTrue($result[0]['IsPilotSlaveable']);
        $this->assertCount(1, $result[0]['Weapons']);

        // Second turret root (back)
        $this->assertSame(800.0, $result[1]['DpsTotal']);
        $this->assertSame(400.0, $result[1]['SustainedDpsTotal']);
        $this->assertSame(200.0, $result[1]['AlphaTotal']);
        $this->assertFalse($result[1]['IsPilotSlaveable']);
        $this->assertCount(1, $result[1]['Weapons']);

        // Non-turret entry unchanged
        $this->assertArrayNotHasKey('DpsTotal', $result[2]);
        $this->assertSame('hardpoint_quantum_drive', $result[2]['HardpointName']);
    }

    public function test_multiple_turrets_nested_weapons_unchanged(): void
    {
        $enricher = new TurretDpsEnricher;
        $result = $enricher->enrich($this->multiTurretFixture(), $this->multiTurretCalculatedData());

        // Nested weapon under first turret -- no DPS fields
        $this->assertArrayNotHasKey('DpsTotal', $result[0]['Loadout'][0]);

        // Nested weapon under second turret -- no DPS fields
        $this->assertArrayNotHasKey('DpsTotal', $result[1]['Loadout'][0]);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- non-mutating
    // ------------------------------------------------------------------ //

    public function test_non_mutating(): void
    {
        $enricher = new TurretDpsEnricher;
        $original = $this->singleTurretFixture();

        // Deep-clone the original for comparison
        $snapshot = unserialize(serialize($original));

        $enricher->enrich($original, $this->singleTurretCalculatedData());

        // Original must be completely unchanged
        $this->assertSame($snapshot, $original);
    }

    public function test_enrich_returns_new_array(): void
    {
        $enricher = new TurretDpsEnricher;
        $original = $this->singleTurretFixture();

        $result = $enricher->enrich($original, $this->singleTurretCalculatedData());

        // Top-level array is a different instance
        $this->assertNotSame($original, $result);

        // Nested turret entry is a different instance
        $this->assertNotSame($original[0], $result[0]);
    }

    // ------------------------------------------------------------------ //
    //  Tests -- turret with no matching DPS data
    // ------------------------------------------------------------------ //

    public function test_turret_root_without_matching_dps_data_unchanged(): void
    {
        $enricher = new TurretDpsEnricher;

        // Calculated data has a turret that doesn't match any loadout entry
        $calculatedData = [
            'Weaponry' => [
                'Turrets' => [
                    [
                        'HardpointName' => 'hardpoint_turret_nonexistent',
                        'DpsTotal' => 999.0,
                        'SustainedDpsTotal' => 500.0,
                        'AlphaTotal' => 200.0,
                        'IsPilotSlaveable' => false,
                        'Weapons' => [],
                    ],
                ],
            ],
        ];

        $result = $enricher->enrich($this->singleTurretFixture(), $calculatedData);

        // No entries should have DPS fields since no hardpoint matched
        $this->assertArrayNotHasKey('DpsTotal', $result[0]);
        $this->assertArrayNotHasKey('DpsTotal', $result[1]);
    }
}
