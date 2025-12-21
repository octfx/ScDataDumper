<?php

declare(strict_types=1);

namespace Tests\Vehicle;

use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use PHPUnit\Framework\TestCase;

final class PortSummaryBuilderTest extends TestCase
{
    private function makeBuilder(): PortSummaryBuilder
    {
        return new PortSummaryBuilder(new PortFinder, new PortClassifierService);
    }

    public function test_prepare_parts_adds_installed_item_and_category(): void
    {
        $parts = [
            [
                'Name' => 'hardpoint_gun',
                'Port' => [
                    'PortName' => 'hardpoint_gun',
                    'Types' => ['WeaponGun'],
                    'Uneditable' => false,
                ],
                'Parts' => [],
                'MaximumDamage' => 10,
            ],
        ];

        $loadout = [
            [
                'portName' => 'hardpoint_gun',
                'Item' => [
                    'ClassName' => 'weapon_gun',
                    'Type' => 'WeaponGun',
                ],
            ],
        ];

        $prepared = $this->makeBuilder()->preparePartsWithClassification($parts, $loadout);

        self::assertSame('weapon_gun', $prepared[0]['InstalledItem']['ClassName']);
        self::assertSame('Weapon hardpoints', $prepared[0]['Category']);
    }

    public function test_flatten_parts_returns_collection(): void
    {
        $parts = [
            [
                'Name' => 'parent',
                'Port' => ['PortName' => 'parent'],
                'Parts' => [
                    ['Name' => 'child', 'Port' => ['PortName' => 'child']],
                ],
            ],
        ];

        $builder = $this->makeBuilder();
        $flat = $builder->flattenParts($parts);

        self::assertSame(['parent', 'child'], $flat->pluck('Name')->all());
    }

    public function test_attach_installed_items_enriches_summary(): void
    {
        $summary = [
            'mannedTurrets' => collect([
                ['Name' => 'hardpoint_gun'],
            ]),
        ];

        $loadout = [
            [
                'portName' => 'hardpoint_gun',
                'Item' => ['ClassName' => 'weapon_gun'],
            ],
        ];

        $enriched = $this->makeBuilder()->attachInstalledItems($summary, $loadout);

        self::assertSame('weapon_gun', $enriched['mannedTurrets']->first()['InstalledItem']['ClassName']);
    }
}
