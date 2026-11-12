<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridResolver;
use PHPUnit\Framework\TestCase;

final class CargoGridResolverTest extends TestCase
{
    public function test_calculates_size_limits_from_pascal_case_strategy_grids(): void
    {
        $grids = new Collection([
            // Shape produced by LoadoutCargoGridStrategy: PascalCase key, lowercase sub-keys
            [
                'uuid' => '7a98a97f-0af7-4f1a-859b-4c5ef57b5acd',
                'SCU' => 42.0,
                'MinSize' => ['x' => 1.25, 'y' => 1.25, 'z' => 1.25],
                'MaxSize' => ['x' => 2.5, 'y' => 5.0, 'z' => 2.5],
            ],
        ]);

        $limits = (new CargoGridResolver)->calculateCargoGridSizeLimits($grids);

        $this->assertSame(['x' => 1.25, 'y' => 1.25, 'z' => 1.25], $limits['MinSize']);
        $this->assertSame(['x' => 2.5, 'y' => 5.0, 'z' => 2.5], $limits['MaxSize']);
    }

    public function test_calculates_size_limits_from_fully_pascal_case_fallback_grids(): void
    {
        $grids = new Collection([
            // Shape produced by finalizeCargo via Formats\ScUnpacked\InventoryContainer
            [
                'UUID' => '7a98a97f-0af7-4f1a-859b-4c5ef57b5acd',
                'SCU' => 42.0,
                'MinSize' => ['X' => 1.25, 'Y' => 1.25, 'Z' => 1.25],
                'MaxSize' => ['X' => 2.5, 'Y' => 5.0, 'Z' => 2.5],
            ],
        ]);

        $limits = (new CargoGridResolver)->calculateCargoGridSizeLimits($grids);

        $this->assertSame(['x' => 1.25, 'y' => 1.25, 'z' => 1.25], $limits['MinSize']);
        $this->assertSame(['x' => 2.5, 'y' => 5.0, 'z' => 2.5], $limits['MaxSize']);
    }

    public function test_picks_smallest_min_and_largest_max_across_grids(): void
    {
        $grids = new Collection([
            [
                'MinSize' => ['x' => 2.0, 'y' => 2.0, 'z' => 2.0],
                'MaxSize' => ['x' => 2.0, 'y' => 2.0, 'z' => 2.0],
            ],
            [
                'MinSize' => ['x' => 1.0, 'y' => 1.0, 'z' => 1.0],
                'MaxSize' => ['x' => 3.0, 'y' => 3.0, 'z' => 3.0],
            ],
        ]);

        $limits = (new CargoGridResolver)->calculateCargoGridSizeLimits($grids);

        $this->assertSame(['x' => 1.0, 'y' => 1.0, 'z' => 1.0], $limits['MinSize']);
        $this->assertSame(['x' => 3.0, 'y' => 3.0, 'z' => 3.0], $limits['MaxSize']);
    }

    public function test_returns_nulls_when_no_grid_has_sizes(): void
    {
        $grids = new Collection([
            ['SCU' => 42.0],
            ['MinSize' => null, 'MaxSize' => null],
        ]);

        $limits = (new CargoGridResolver)->calculateCargoGridSizeLimits($grids);

        $this->assertNull($limits['MinSize']);
        $this->assertNull($limits['MaxSize']);
    }
}
