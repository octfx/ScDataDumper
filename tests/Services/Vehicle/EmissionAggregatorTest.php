<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\EmissionAggregator;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use PHPUnit\Framework\TestCase;

final class EmissionAggregatorTest extends TestCase
{
    public function test_multi_powerplant_generation_uses_segment_sharing_formula(): void
    {
        $result = $this->calculateForItems(
            [
                $this->makeItem(
                    'PowerPlant',
                    [
                        'Emission' => ['Em' => ['Maximum' => 40.0]],
                        'ResourceNetwork' => [
                            'Generation' => ['Power' => 10.0],
                        ],
                    ],
                    ['size' => 2]
                ),
                $this->makeItem(
                    'PowerPlant',
                    [
                        'Emission' => ['Em' => ['Maximum' => 20.0]],
                        'ResourceNetwork' => [
                            'Generation' => ['Power' => 10.0],
                        ],
                    ],
                    ['size' => 3]
                ),
            ]
        );

        self::assertSame(15, $result['power']['generation_segments']);
        self::assertEquals(4.0, $result['emission']['em_per_segment']);
    }

    public function test_shield_pool_cap_limits_shield_emission_and_usage_to_max_item_count(): void
    {
        $result = $this->calculateForItems(
            [
                $this->makeItem(
                    'Shield',
                    [
                        'Emission' => ['Em' => ['Maximum' => 10.0], 'Ir' => 4.0],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Minimum' => 2.0, 'Maximum' => 4.0],
                                'Coolant' => ['Minimum' => 1.0, 'Maximum' => 2.0],
                            ],
                        ],
                    ]
                ),
                $this->makeItem(
                    'Shield',
                    [
                        'Emission' => ['Em' => ['Maximum' => 15.0], 'Ir' => 6.0],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Minimum' => 1.0, 'Maximum' => 3.0],
                                'Coolant' => ['Minimum' => 0.5, 'Maximum' => 1.0],
                            ],
                        ],
                    ]
                ),
            ],
            [
                ['itemType' => 'Shield', 'maxItemCount' => 1, '__polymorphicType' => 'ShieldPool'],
            ]
        );

        self::assertSame(10.0, $result['emission']['em_groups_shields']['Shield']);
        self::assertSame(4.0, $result['power']['used_segments_grouped']['Shield']);
        self::assertSame(1.0, $result['power_pools']['Shield']['size']);
    }

    public function test_weapon_pool_cap_reduces_weapon_segments_and_emission(): void
    {
        $result = $this->calculateForItems(
            [
                $this->makeItem(
                    'PowerPlant',
                    [
                        'Emission' => ['Em' => ['Maximum' => 12.0]],
                        'ResourceNetwork' => [
                            'Generation' => ['Power' => 20.0],
                        ],
                    ]
                ),
                $this->makeItem(
                    'WeaponGun',
                    [
                        'Emission' => ['Em' => ['Maximum' => 40.0]],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Maximum' => 4.0],
                                'Coolant' => ['Maximum' => 1.0],
                            ],
                        ],
                    ]
                ),
                $this->makeItem(
                    'WeaponGun',
                    [
                        'Emission' => ['Em' => ['Maximum' => 40.0]],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Maximum' => 4.0],
                                'Coolant' => ['Maximum' => 1.0],
                            ],
                        ],
                    ]
                ),
            ],
            [
                ['itemType' => 'WeaponGun', 'poolSize' => 5, '__polymorphicType' => 'WeaponPool'],
            ]
        );

        self::assertSame(5.0, $result['power']['used_segments_grouped']['WeaponGun']);
        self::assertSame(50.0, $result['emission']['em_groups_shields']['WeaponGun']);
        self::assertSame(5.0, $result['power_pools']['WeaponGun']['size']);
    }

    public function test_armor_signal_multipliers_affect_em_and_ir_totals(): void
    {
        $result = $this->calculateForItems(
            [
                $this->makeItem(
                    'Cooler',
                    [
                        'Emission' => ['Em' => ['Maximum' => 0.0], 'Ir' => 0.0],
                        'ResourceNetwork' => [
                            'Generation' => ['Coolant' => 4.0],
                        ],
                    ]
                ),
                $this->makeItem(
                    'Radar',
                    [
                        'Emission' => ['Em' => ['Maximum' => 5.0], 'Ir' => 8.0],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Maximum' => 2.0],
                            ],
                        ],
                    ]
                ),
                $this->makeItem(
                    'Armor',
                    [
                        'Armor' => [
                            'SignalMultipliers' => [
                                'Infrared' => 0.5,
                                'Electromagnetic' => 2.0,
                            ],
                        ],
                    ]
                ),
            ]
        );

        self::assertSame(10.0, $result['emission']['em_groups_shields']['Radar']);
        self::assertSame(4.0, $result['emission']['ir_shields']);
    }

    public function test_power_budgeting_preserves_minimum_shield_power_when_over_budget(): void
    {
        $result = $this->calculateForItems(
            [
                $this->makeItem(
                    'PowerPlant',
                    [
                        'Emission' => ['Em' => ['Maximum' => 10.0]],
                        'ResourceNetwork' => [
                            'Generation' => ['Power' => 5.0],
                        ],
                    ]
                ),
                $this->makeItem(
                    'Cooler',
                    [
                        'ResourceNetwork' => [
                            'Generation' => ['Coolant' => 10.0],
                        ],
                    ]
                ),
                $this->makeItem(
                    'FlightController',
                    [
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Maximum' => 4.0],
                            ],
                        ],
                    ]
                ),
                $this->makeItem(
                    'Shield',
                    [
                        'Emission' => ['Ir' => 10.0],
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Minimum' => 2.0, 'Maximum' => 3.0],
                                'Coolant' => ['Minimum' => 1.0, 'Maximum' => 2.0],
                            ],
                        ],
                    ]
                ),
                $this->makeItem(
                    'WeaponGun',
                    [
                        'ResourceNetwork' => [
                            'Usage' => [
                                'Power' => ['Maximum' => 4.0],
                                'Coolant' => ['Maximum' => 1.0],
                            ],
                        ],
                    ]
                ),
            ],
            [
                ['itemType' => 'Shield', 'maxItemCount' => 1, '__polymorphicType' => 'ShieldPool'],
            ]
        );

        self::assertSame(5, $result['power']['generation_segments']);
        self::assertNotNull($result['power_budgeting']);
        self::assertSame(6.0, $result['power_budgeting']['shields']['over_budget_segments']);
        self::assertSame(2.0, $result['power_budgeting']['shields']['budgeted_usage_by_type']['Shield']);
        self::assertSame(1.0, $result['power_budgeting']['shields']['budgeted_usage_by_type']['FlightController']);
        self::assertSame(2.0, $result['power_budgeting']['shields']['budgeted_usage_by_type']['WeaponGun']);
        self::assertSame(0.0, $result['power_budgeting']['shields']['remaining_over_budget_segments']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $pools
     * @return array<string, mixed>
     */
    private function calculateForItems(array $items, array $pools = []): array
    {
        $parts = array_map(
            fn (array $item, int $index): array => [
                'Name' => "Part {$index}",
                'Port' => [
                    'PortName' => "Port {$index}",
                    'InstalledItem' => $item,
                ],
            ],
            $items,
            array_keys($items)
        );

        $aggregator = new EmissionAggregator(
            new StandardisedPartWalker,
            new VehicleWrapper(null, $this->makeEntity($pools), [])
        );

        return $aggregator->calculate(new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $pools
     */
    private function makeEntity(array $pools): VehicleDefinition
    {
        $poolXml = implode('', array_map(
            static function (array $pool): string {
                $attributes = [];

                foreach ($pool as $name => $value) {
                    $attributes[] = sprintf('%s="%s"', $name, htmlspecialchars((string) $value, ENT_QUOTES));
                }

                return sprintf('<pool %s />', implode(' ', $attributes));
            },
            $pools
        ));

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST __type="VehicleDefinition" __ref="vehicle-test" __path="test.xml">
                <Components>
                    <SItemPortContainerComponentParams>
                        <resourceNetworkPowerPools>
                            <itemPools>{$poolXml}</itemPools>
                        </resourceNetworkPowerPools>
                    </SItemPortContainerComponentParams>
                </Components>
            </VehicleDefinition.TEST>
            XML;

        $entity = new VehicleDefinition;
        $entity->loadXML($xml);
        $entity->initXPath();

        return $entity;
    }

    /**
     * @param  array<string, mixed>  $stdItem
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function makeItem(string $type, array $stdItem = [], array $extra = []): array
    {
        return array_replace_recursive(
            [
                'type' => $type,
                'stdItem' => $stdItem,
            ],
            $extra
        );
    }
}
