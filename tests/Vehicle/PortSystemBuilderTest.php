<?php

declare(strict_types=1);

namespace Tests\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\CompatibleTypesExtractor;
use Octfx\ScDataDumper\Services\Vehicle\PortMapper;
use Octfx\ScDataDumper\Services\Vehicle\PortSystemBuilder;
use PHPUnit\Framework\TestCase;

final class PortSystemBuilderTest extends TestCase
{
    private function makeBuilder(): PortSystemBuilder
    {
        return new PortSystemBuilder(new PortMapper, new CompatibleTypesExtractor);
    }

    public function test_merge_backfills_sizes_and_compatible_types(): void
    {
        $ports = [
            [
                'name' => 'turret',
                'sizes' => ['min' => null, 'max' => null],
                'compatible_types' => [],
            ],
        ];

        $partPorts = [
            [
                'name' => 'turret',
                'sizes' => ['min' => 1, 'max' => 3],
                'compatible_types' => [['type' => 'Weapon', 'sub_types' => ['Gun']]],
            ],
        ];

        $merged = $this->makeBuilder()->mergeWithPartPorts($ports, $partPorts);

        self::assertSame(1, $merged[0]['sizes']['min']);
        self::assertSame(3, $merged[0]['sizes']['max']);
        self::assertSame($partPorts[0]['compatible_types'], $merged[0]['compatible_types']);
    }

    public function test_merge_adds_missing_part_ports(): void
    {
        $ports = [];

        $partPorts = [
            [
                'name' => 'cargo_grid',
                'sizes' => ['min' => null, 'max' => 8],
                'compatible_types' => [],
            ],
        ];

        $merged = $this->makeBuilder()->mergeWithPartPorts($ports, $partPorts);

        self::assertCount(1, $merged);
        self::assertSame('cargo_grid', $merged[0]['name']);
    }
}
