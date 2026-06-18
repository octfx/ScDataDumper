<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use PHPUnit\Framework\TestCase;

final class PortSummaryBuilderCategoryTest extends TestCase
{
    private PortSummaryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PortSummaryBuilder(new PortFinder);
    }

    // ------------------------------------------------------------------ //
    //  Key existence tests
    // ------------------------------------------------------------------ //

    public function test_build_returns_emp_hardpoints_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('empHardpoints', $summary);
        self::assertInstanceOf(Collection::class, $summary['empHardpoints']);
    }

    public function test_build_returns_qed_hardpoints_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('qedHardpoints', $summary);
        self::assertInstanceOf(Collection::class, $summary['qedHardpoints']);
    }

    public function test_build_returns_interdiction_hardpoints_key_for_backward_compat(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('interdictionHardpoints', $summary);
        self::assertInstanceOf(Collection::class, $summary['interdictionHardpoints']);
    }

    public function test_build_returns_salvage_hardpoints_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('salvageHardpoints', $summary);
        self::assertInstanceOf(Collection::class, $summary['salvageHardpoints']);
    }

    public function test_build_returns_mining_hardpoints_key_for_backward_compat(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('miningHardpoints', $summary);
        self::assertInstanceOf(Collection::class, $summary['miningHardpoints']);
    }

    public function test_build_returns_tractor_beams_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('tractorBeams', $summary);
        self::assertInstanceOf(Collection::class, $summary['tractorBeams']);
    }

    public function test_build_returns_weapon_lockers_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('weaponLockers', $summary);
        self::assertInstanceOf(Collection::class, $summary['weaponLockers']);
    }

    public function test_build_returns_paints_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('paints', $summary);
        self::assertInstanceOf(Collection::class, $summary['paints']);
    }

    public function test_build_returns_ai_modules_key(): void
    {
        $summary = $this->builder->build([]);

        self::assertArrayHasKey('aiModules', $summary);
        self::assertInstanceOf(Collection::class, $summary['aiModules']);
    }

    // ------------------------------------------------------------------ //
    //  Classification tests
    // ------------------------------------------------------------------ //

    public function test_emp_ports_go_to_emp_hardpoints(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_emp', 'EMP hardpoints'),
            $this->makePort('hardpoint_qed', 'QIG hardpoints'),
        ]);

        self::assertSame(['hardpoint_emp'], $this->extractPortNames($summary['empHardpoints']));
    }

    public function test_qig_ports_go_to_qed_hardpoints(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_emp', 'EMP hardpoints'),
            $this->makePort('hardpoint_qed', 'QIG hardpoints'),
        ]);

        self::assertSame(['hardpoint_qed'], $this->extractPortNames($summary['qedHardpoints']));
    }

    public function test_interdiction_hardpoints_captures_both_emp_and_qig(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_emp', 'EMP hardpoints'),
            $this->makePort('hardpoint_qed', 'QIG hardpoints'),
        ]);

        $interdictionNames = $this->extractPortNames($summary['interdictionHardpoints']);
        self::assertContains('hardpoint_emp', $interdictionNames);
        self::assertContains('hardpoint_qed', $interdictionNames);
    }

    public function test_salvage_ports_go_to_salvage_hardpoints_not_mining(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_mining', 'Mining hardpoints'),
            $this->makePort('hardpoint_salvage', 'Salvage hardpoints'),
        ]);

        self::assertSame(['hardpoint_mining'], $this->extractPortNames($summary['miningHardpoints']));
        self::assertSame(['hardpoint_salvage'], $this->extractPortNames($summary['salvageHardpoints']));

        // Salvage should NOT appear in miningHardpoints
        self::assertNotContains('hardpoint_salvage', $this->extractPortNames($summary['miningHardpoints']));
        // Mining should NOT appear in salvageHardpoints
        self::assertNotContains('hardpoint_mining', $this->extractPortNames($summary['salvageHardpoints']));
    }

    public function test_tractor_beam_ports_go_to_tractor_beams(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_tractor', 'Tractor beams'),
        ]);

        self::assertSame(['hardpoint_tractor'], $this->extractPortNames($summary['tractorBeams']));
    }

    public function test_weapon_locker_ports_go_to_weapon_lockers(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('weapon_locker_1', 'Weapon lockers'),
        ]);

        self::assertSame(['weapon_locker_1'], $this->extractPortNames($summary['weaponLockers']));
    }

    public function test_paint_ports_go_to_paints(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_paint', 'Paint'),
        ]);

        self::assertSame(['hardpoint_paint'], $this->extractPortNames($summary['paints']));
    }

    public function test_ai_module_ports_go_to_ai_modules(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_ai_blade', 'AI modules'),
        ]);

        self::assertSame(['hardpoint_ai_blade'], $this->extractPortNames($summary['aiModules']));
    }

    // ------------------------------------------------------------------ //
    //  Backward compatibility
    // ------------------------------------------------------------------ //

    public function test_existing_category_keys_still_present(): void
    {
        $summary = $this->builder->build([]);

        $expectedExistingKeys = [
            'armor',
            'weaponHardpoints',
            'miningHardpoints',
            'utilityHardpoints',
            'miningTurrets',
            'mannedTurrets',
            'pdcTurrets',
            'remoteTurrets',
            'utilityTurrets',
            'interdictionHardpoints',
            'missileRacks',
            'powerPlants',
            'coolers',
            'shields',
            'cargoGrids',
            'countermeasures',
            'mainThrusters',
            'retroThrusters',
            'vtolThrusters',
            'maneuveringThrusters',
            'upThrusters',
            'downThrusters',
            'strafeThrusters',
            'hydrogenFuelIntakes',
            'hydrogenFuelTanks',
            'quantumDrives',
            'quantumFuelTanks',
            'flightControllers',
            'radars',
            'lifeSupport',
            'seats',
            'avionics',
        ];

        foreach ($expectedExistingKeys as $key) {
            self::assertArrayHasKey($key, $summary, "Existing category key '$key' is missing from PortSummaryBuilder output.");
        }
    }

    public function test_existing_categories_still_classify_correctly(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('hardpoint_shield', 'Shield generators'),
            $this->makePort('hardpoint_cooler', 'Coolers'),
            $this->makePort('hardpoint_pp', 'Power plants'),
            $this->makePort('hardpoint_missile', 'Missile racks'),
            $this->makePort('hardpoint_countermeasure', 'Countermeasures'),
            $this->makePort('hardpoint_radar', 'Radars'),
            $this->makePort('hardpoint_life', 'Life support'),
            $this->makePort('hardpoint_fc', 'Flight controllers'),
        ]);

        self::assertSame(['hardpoint_shield'], $this->extractPortNames($summary['shields']));
        self::assertSame(['hardpoint_cooler'], $this->extractPortNames($summary['coolers']));
        self::assertSame(['hardpoint_pp'], $this->extractPortNames($summary['powerPlants']));
        self::assertSame(['hardpoint_missile'], $this->extractPortNames($summary['missileRacks']));
        self::assertSame(['hardpoint_countermeasure'], $this->extractPortNames($summary['countermeasures']));
        self::assertSame(['hardpoint_radar'], $this->extractPortNames($summary['radars']));
        self::assertSame(['hardpoint_life'], $this->extractPortNames($summary['lifeSupport']));
        self::assertSame(['hardpoint_fc'], $this->extractPortNames($summary['flightControllers']));
    }

    // ------------------------------------------------------------------ //
    //  Edge cases
    // ------------------------------------------------------------------ //

    public function test_weapon_locker_predicate_does_not_match_generic_door(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('weapon_locker_1', 'Weapon lockers'),
            $this->makePort('generic_door', 'Doors'),
        ]);

        $lockerNames = $this->extractPortNames($summary['weaponLockers']);
        self::assertContains('weapon_locker_1', $lockerNames);
        self::assertNotContains('generic_door', $lockerNames);
    }

    public function test_empty_parts_produces_all_keys_with_empty_collections(): void
    {
        $summary = $this->builder->build([]);

        $newKeys = ['empHardpoints', 'qedHardpoints', 'salvageHardpoints', 'tractorBeams', 'weaponLockers', 'paints', 'aiModules'];

        foreach ($newKeys as $key) {
            self::assertArrayHasKey($key, $summary);
            self::assertInstanceOf(Collection::class, $summary[$key]);
            self::assertCount(0, $summary[$key], "New category '$key' should be empty for empty parts.");
        }
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    /**
     * @param  array<int, array<string, mixed>>  $ports
     * @return array<string, Collection>
     */
    private function buildSummaryFromPorts(array $ports): array
    {
        return $this->builder->build([
            [
                'Name' => 'Root',
                'Port' => [
                    'PortName' => 'root',
                    'Category' => 'Vehicle',
                    'InstalledItem' => $this->makeInstalledItemWithPorts($ports),
                ],
                'Parts' => [],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $installedItem
     * @return array<string, mixed>
     */
    private function makePort(string $portName, string $category, ?array $installedItem = null): array
    {
        $port = [
            'PortName' => $portName,
            'Category' => $category,
            'Port' => [
                'PortName' => $portName,
                'Category' => $category,
            ],
        ];

        if ($installedItem !== null) {
            $port['InstalledItem'] = $installedItem;
        }

        return $port;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ports
     * @return array<string, mixed>
     */
    private function makeInstalledItemWithPorts(array $ports): array
    {
        return [
            'stdItem' => [
                'Ports' => $ports,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ports
     * @return array<int, string>
     */
    private function extractPortNames(Collection $ports): array
    {
        return $ports
            ->map(static fn (array $entry): string => (string) ($entry['PortName'] ?? $entry['Port']['PortName'] ?? ''))
            ->values()
            ->all();
    }
}
