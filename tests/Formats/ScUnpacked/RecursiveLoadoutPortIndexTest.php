<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\RecursiveLoadoutPortIndex
 */
final class RecursiveLoadoutPortIndexTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Fixture
    // ------------------------------------------------------------------ //

    /**
     * Returns an annotated loadout fixture with known structure.
     *
     * Structure:
     *   loadout.0  hardpoint_quantum_drive  (QuantumDrive.UNDEFINED)
     *     loadout.0.loadout.0  hardpoint_Jump_Drive  (JumpDrive.UNDEFINED)
     *   loadout.1  hardpoint_turret_a  (TurretBase.MannedTurret)
     *     loadout.1.loadout.0  hardpoint_class_2  (WeaponGun.Gun)
     *   loadout.2  hardpoint_class_2  (WeaponGun.Gun)
     */
    private function annotatedFixture(): array
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_AEGS_Spectral',
                'UUID' => 'aaa-111',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_Jump_Drive',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'ClassName' => 'JDRV_ACME',
                        'UUID' => 'bbb-222',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'hardpoint_turret_a',
                'Type' => 'TurretBase.MannedTurret',
                'ClassName' => 'Turret_Base',
                'UUID' => 'ccc-333',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_class_2',
                        'Type' => 'WeaponGun.Gun',
                        'ClassName' => 'KLWE_LaserRepeater_S4',
                        'UUID' => 'ddd-444',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'hardpoint_class_2',
                'Type' => 'WeaponGun.Gun',
                'ClassName' => 'KLWE_LaserRepeater_S3',
                'UUID' => 'eee-555',
                'Loadout' => [],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($loadout);
    }

    /**
     * Returns an annotated fixture with a port that has no Type.
     */
    private function annotatedFixtureWithNullType(): array
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_empty',
                'ClassName' => 'EmptyPort',
                'UUID' => 'fff-666',
                'Loadout' => [],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($loadout);
    }

    /**
     * Returns an annotated fixture with a port that has a simple type (no dot).
     */
    private function annotatedFixtureWithSimpleType(): array
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_armor',
                'Type' => 'Armor',
                'ClassName' => 'ARMR_TEST',
                'UUID' => 'ggg-777',
                'Loadout' => [],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($loadout);
    }

    // ------------------------------------------------------------------ //
    //  1. Build and count
    // ------------------------------------------------------------------ //

    public function test_build_returns_self(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $result = $index->build($this->annotatedFixture());

        self::assertSame($index, $result, 'build() must return the same instance for chaining');
    }

    public function test_count_returns_total_port_count(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        self::assertCount(5, $index, 'Fixture has 5 ports total (3 top-level + 2 nested)');
    }

    // ------------------------------------------------------------------ //
    //  2. all() -- flat ordered list
    // ------------------------------------------------------------------ //

    public function test_all_returns_flat_list_of_all_ports(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        $all = $index->all();

        self::assertIsArray($all);
        self::assertCount(5, $all);
    }

    public function test_all_preserves_order(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        $all = $index->all();
        $portIds = array_map(fn (array $port) => $port['PortId'], $all);

        // Top-level first, then children depth-first
        self::assertSame([
            'loadout.0',
            'loadout.0.loadout.0',
            'loadout.1',
            'loadout.1.loadout.0',
            'loadout.2',
        ], $portIds);
    }

    // ------------------------------------------------------------------ //
    //  3. findByPortId()
    // ------------------------------------------------------------------ //

    public function test_find_by_port_id_returns_correct_port(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        $port = $index->findByPortId('loadout.0.loadout.0');

        self::assertNotNull($port);
        self::assertSame('hardpoint_Jump_Drive', $port['HardpointName']);
        self::assertSame('loadout.0.loadout.0', $port['PortId']);
    }

    public function test_find_by_port_id_returns_null_for_missing(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        self::assertNull($index->findByPortId('loadout.99'));
    }

    public function test_find_by_port_id_handles_duplicate_hardpoint_names(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        // Both ports are named hardpoint_class_2 but have different PortIds
        $nested = $index->findByPortId('loadout.1.loadout.0');
        $topLevel = $index->findByPortId('loadout.2');

        self::assertNotNull($nested);
        self::assertNotNull($topLevel);
        self::assertSame('hardpoint_class_2', $nested['HardpointName']);
        self::assertSame('hardpoint_class_2', $topLevel['HardpointName']);
        self::assertNotSame($nested['PortId'], $topLevel['PortId']);
    }

    // ------------------------------------------------------------------ //
    //  4. getReferenceObject() -- field presence
    // ------------------------------------------------------------------ //

    public function test_get_reference_object_has_all_required_fields(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        $ref = $index->getReferenceObject('loadout.0.loadout.0');
        self::assertNotNull($ref, 'getReferenceObject must return a reference for a valid PortId');

        foreach (VehicleSystemKeys::PORT_REF_KEYS as $key) {
            self::assertArrayHasKey($key, $ref,
                "Reference object must contain key '{$key}' from PORT_REF_KEYS");
        }

        // Must have exactly the expected keys (no extra fields)
        $expectedKeys = VehicleSystemKeys::PORT_REF_KEYS;
        $actualKeys = array_keys($ref);
        sort($expectedKeys);
        sort($actualKeys);
        self::assertSame(
            $expectedKeys,
            $actualKeys,
            'Reference object must contain exactly the PORT_REF_KEYS fields'
        );
    }

    // ------------------------------------------------------------------ //
    //  5. Type/SubType normalization
    // ------------------------------------------------------------------ //

    public function test_get_reference_object_normalizes_type_with_subtype(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        // loadout.1.loadout.0 has Type "WeaponGun.Gun"
        $ref = $index->getReferenceObject('loadout.1.loadout.0');
        self::assertNotNull($ref);
        self::assertSame('WeaponGun', $ref['Type']);
        self::assertSame('Gun', $ref['SubType']);
    }

    public function test_get_reference_object_normalizes_type_undefined_subtype(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        // loadout.0 has Type "QuantumDrive.UNDEFINED"
        $ref = $index->getReferenceObject('loadout.0');
        self::assertNotNull($ref);
        self::assertSame('QuantumDrive', $ref['Type']);
        self::assertNull($ref['SubType'], 'UNDEFINED subtypes must normalize to null');
    }

    public function test_get_reference_object_handles_type_without_subtype(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixtureWithSimpleType());

        // Type is "Armor" (no dot)
        $ref = $index->getReferenceObject('loadout.0');
        self::assertNotNull($ref);
        self::assertSame('Armor', $ref['Type']);
        self::assertNull($ref['SubType']);
    }

    public function test_get_reference_object_handles_null_type(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixtureWithNullType());

        // No Type field on the entry
        $ref = $index->getReferenceObject('loadout.0');
        self::assertNotNull($ref);
        self::assertNull($ref['Type']);
        self::assertNull($ref['SubType']);
    }

    // ------------------------------------------------------------------ //
    //  6. Identity field copying
    // ------------------------------------------------------------------ //

    public function test_get_reference_object_copies_identity_fields(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        // loadout.0.loadout.0 is the jump drive child
        $ref = $index->getReferenceObject('loadout.0.loadout.0');
        self::assertNotNull($ref);

        self::assertSame('loadout.0.loadout.0', $ref['PortId']);
        self::assertSame('loadout.0', $ref['ParentPortId']);
        self::assertSame('loadout.0', $ref['RootPortId']);
        self::assertSame(
            ['hardpoint_quantum_drive', 'hardpoint_Jump_Drive'],
            $ref['Path']
        );
    }

    public function test_get_reference_object_includes_classname_and_uuid(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build($this->annotatedFixture());

        $ref = $index->getReferenceObject('loadout.0.loadout.0');
        self::assertNotNull($ref);

        self::assertSame('JDRV_ACME', $ref['ClassName']);
        self::assertSame('bbb-222', $ref['UUID']);
    }

    // ------------------------------------------------------------------ //
    //  7. Edge cases
    // ------------------------------------------------------------------ //

    public function test_empty_loadout_returns_empty_index(): void
    {
        $index = new RecursiveLoadoutPortIndex;
        $index->build([]);

        self::assertCount(0, $index);
        self::assertSame([], $index->all());
        self::assertNull($index->findByPortId('loadout.0'));
        self::assertNull($index->getReferenceObject('loadout.0'));
    }

    public function test_port_without_installed_item(): void
    {
        // Port with no ClassName or UUID
        $loadout = [
            [
                'HardpointName' => 'hardpoint_empty',
                'Type' => 'Shield.UNDEFINED',
                'Loadout' => [],
            ],
        ];

        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);
        $index = new RecursiveLoadoutPortIndex;
        $index->build($annotated);

        $ref = $index->getReferenceObject('loadout.0');
        self::assertNotNull($ref);
        self::assertNull($ref['ClassName']);
        self::assertNull($ref['UUID']);
        self::assertSame('Shield', $ref['Type']);
        self::assertNull($ref['SubType']);
    }
}
