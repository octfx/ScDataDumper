<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use PHPUnit\Framework\TestCase;

final class PortSummaryBuilderTest extends TestCase
{
    private PortSummaryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PortSummaryBuilder(new PortFinder);
    }

    public function test_build_excludes_child_weapon_ports_of_manned_turrets_from_weapon_hardpoints(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('pilot_gun', 'Weapon hardpoints'),
            $this->makePort(
                'manned_turret',
                'Manned turrets',
                $this->makeInstalledItemWithPorts([
                    $this->makePort('turret_gun_left', 'Weapon hardpoints'),
                    $this->makePort('turret_gun_right', 'Weapon hardpoints'),
                ])
            ),
        ]);

        self::assertSame(['pilot_gun'], $this->extractPortNames($summary['weaponHardpoints']));
        self::assertSame(['manned_turret'], $this->extractPortNames($summary['mannedTurrets']));
        self::assertNotContains('turret_gun_left', $this->extractPortNames($summary['weaponHardpoints']));
        self::assertNotContains('turret_gun_right', $this->extractPortNames($summary['weaponHardpoints']));
    }

    public function test_build_identifies_directional_maneuvering_thrusters_from_port_names(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('thruster_bottom_left', 'Maneuvering thrusters'),
            $this->makePort('thruster_upper_right', 'Maneuvering thrusters'),
            $this->makePort('thruster_side_left', 'Maneuvering thrusters'),
            $this->makePort('thruster_vtol_lower', 'Maneuvering thrusters'),
        ]);

        self::assertSame(['thruster_bottom_left'], $this->extractPortNames($summary['upThrusters']));
        self::assertSame(['thruster_upper_right'], $this->extractPortNames($summary['downThrusters']));
        self::assertSame(['thruster_side_left'], $this->extractPortNames($summary['strafeThrusters']));
        self::assertNotContains('thruster_vtol_lower', $this->extractPortNames($summary['upThrusters']));
        self::assertNotContains('thruster_vtol_lower', $this->extractPortNames($summary['downThrusters']));
        self::assertNotContains('thruster_vtol_lower', $this->extractPortNames($summary['strafeThrusters']));
    }

    public function test_build_uses_inventory_container_presence_for_cargo_grid_predicate(): void
    {
        $summary = $this->buildSummaryFromPorts([
            $this->makePort('cargo_grid_main', 'Cargo grids', [
                'InventoryContainer' => [
                    'SCU' => 16,
                ],
            ]),
            $this->makePort('category_only_cargo', 'Cargo grids', [
                'Name' => 'Missing inventory container',
            ]),
            $this->makePort('pilot_gun', 'Weapon hardpoints'),
        ]);

        self::assertSame(['cargo_grid_main'], $this->extractPortNames($summary['cargoGrids']));
        self::assertNotContains('category_only_cargo', $this->extractPortNames($summary['cargoGrids']));
    }

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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function makePort(string $portName, string $category, ?array $installedItem = null, array $extra = []): array
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

        return array_replace_recursive($port, $extra);
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
