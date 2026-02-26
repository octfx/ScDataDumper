<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\ResourceAggregator;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use PHPUnit\Framework\TestCase;

final class ResourceAggregatorTest extends TestCase
{
    private ResourceAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = new ResourceAggregator(new StandardisedPartWalker);
    }

    public function test_shield_regen_prefers_online_state_when_available(): void
    {
        $item = $this->makeShieldItem(
            maxShieldRegen: 120.0,
            states: [
                [
                    'Name' => 'Offline',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                            'GeneratedRate' => 20.0,
                            'MinimumFraction' => 0.2,
                        ],
                    ],
                ],
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'conversion',
                            'GeneratedResource' => 'sHiElD',
                            'GeneratedRate' => 100.0,
                            'MinimumFraction' => 0.4,
                        ],
                        [
                            'Type' => 'Consumption',
                            'Resource' => 'Power',
                            'Rate' => 3.0,
                        ],
                    ],
                ],
            ],
        );

        $result = $this->calculateForItems([$item]);

        self::assertEquals(100.0, $result['shields_total']['regen_raw']);
        self::assertEquals(40.0, $result['shields_total']['regen_min_power']);
    }

    public function test_shield_regen_falls_back_to_first_available_state_when_online_missing(): void
    {
        $item = $this->makeShieldItem(
            maxShieldRegen: 90.0,
            states: [
                [
                    'Name' => 'Standby',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                            'GeneratedRate' => 40.0,
                            'MinimumFraction' => 0.25,
                        ],
                    ],
                ],
            ],
        );

        $result = $this->calculateForItems([$item]);

        self::assertEquals(40.0, $result['shields_total']['regen_raw']);
        self::assertEquals(10.0, $result['shields_total']['regen_min_power']);
    }

    public function test_shield_regen_defaults_minimum_fraction_to_one_when_missing(): void
    {
        $item = $this->makeShieldItem(
            maxShieldRegen: 30.0,
            states: [
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                            'GeneratedRate' => 30.0,
                        ],
                    ],
                ],
            ],
        );

        $result = $this->calculateForItems([$item]);

        self::assertEquals(30.0, $result['shields_total']['regen_raw']);
        self::assertEquals(30.0, $result['shields_total']['regen_min_power']);
    }

    public function test_shield_regen_falls_back_to_max_shield_regen_when_conversion_data_is_missing(): void
    {
        $item = $this->makeShieldItem(
            maxShieldRegen: 77.0,
            states: [
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'Consumption',
                            'Resource' => 'Power',
                            'Rate' => 2.0,
                        ],
                    ],
                ],
            ],
        );

        $result = $this->calculateForItems([$item]);

        self::assertEquals(77.0, $result['shields_total']['regen_raw']);
        self::assertEquals(77.0, $result['shields_total']['regen_min_power']);
    }

    public function test_shield_regen_falls_back_to_max_shield_regen_when_conversion_data_is_partial(): void
    {
        $item = $this->makeShieldItem(
            maxShieldRegen: 88.0,
            states: [
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                        ],
                    ],
                ],
            ],
        );

        $result = $this->calculateForItems([$item]);

        self::assertEquals(88.0, $result['shields_total']['regen_raw']);
        self::assertEquals(88.0, $result['shields_total']['regen_min_power']);
    }

    public function test_legacy_regen_remains_unchanged_while_new_fields_are_present(): void
    {
        $itemWithConversion = $this->makeShieldItem(
            maxShieldRegen: 100.0,
            states: [
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                            'GeneratedRate' => 50.0,
                            'MinimumFraction' => 0.5,
                        ],
                    ],
                ],
            ],
        );

        $itemWithoutConversion = $this->makeShieldItem(maxShieldRegen: 50.0);

        $result = $this->calculateForItems([$itemWithConversion, $itemWithoutConversion]);
        $shieldsTotal = $result['shields_total'];

        self::assertSame(round((100.0 + 50.0) * 0.66), $shieldsTotal['regen']);
        self::assertArrayHasKey('regen_raw', $shieldsTotal);
        self::assertArrayHasKey('regen_min_power', $shieldsTotal);
        self::assertEquals(100.0, $shieldsTotal['regen_raw']);
        self::assertEquals(75.0, $shieldsTotal['regen_min_power']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function calculateForItems(array $items): array
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

        $context = new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        );

        return $this->aggregator->calculate($context);
    }

    /**
     * @param  array<int, array<string, mixed>>  $states
     * @return array<string, mixed>
     */
    private function makeShieldItem(float $maxShieldRegen, array $states = []): array
    {
        $stdItem = [
            'Shield' => [
                'MaxShieldHealth' => 1000.0,
                'MaxShieldRegen' => $maxShieldRegen,
            ],
        ];

        if ($states !== []) {
            $stdItem['ResourceNetwork'] = [
                'States' => $states,
            ];
        }

        return [
            'stdItem' => $stdItem,
        ];
    }
}
