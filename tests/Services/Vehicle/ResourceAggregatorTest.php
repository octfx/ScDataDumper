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

    public function test_empty_loadout_returns_empty_resource_shape(): void
    {
        $result = $this->calculateForParts([]);

        self::assertSame(0.0, $result['mass_loadout']);
        self::assertNull($result['shields_total']['hp']);
        self::assertNull($result['shields_total']['regen']);
        self::assertNull($result['shields_total']['regen_raw']);
        self::assertNull($result['shields_total']['regen_min_power']);
        self::assertNull($result['distortion']['pool']);
        self::assertNull($result['ammo']['missiles']);
        self::assertNull($result['ammo']['missile_rack_capacity']);
        self::assertNull($result['weapon_storage']['lockers']);
        self::assertNull($result['weapon_storage']['slots_total']);
        self::assertNull($result['weapon_storage']['slots_rifle']);
        self::assertNull($result['weapon_storage']['slots_pistol']);
        self::assertNull($result['weapon_storage']['by_locker']);
    }

    public function test_mass_distortion_and_ammunition_metrics_are_aggregated(): void
    {
        $missile = $this->makeItem([
            'Type' => 'Missile.Arrow',
            'Mass' => 5.0,
            'Distortion' => ['Maximum' => 12.0],
        ]);

        $missileLauncher = $this->makeItem([
            'Type' => 'MissileLauncher.MissileRack',
            'Mass' => 7.0,
            'Distortion' => ['Maximum' => 3.0],
            'MissileRack' => ['Count' => 6.0],
        ]);

        $genericComponent = $this->makeItem([
            'Type' => 'Radar.Standard',
            'Mass' => 4.0,
            'Distortion' => ['Maximum' => 10.0],
        ]);

        $result = $this->calculateForItems([$missile, $missileLauncher, $genericComponent]);

        self::assertSame(16.0, $result['mass_loadout']);
        self::assertSame(25.0, $result['distortion']['pool']);
        self::assertEquals(2.0, $result['ammo']['missiles']);
        self::assertSame(6.0, $result['ammo']['missile_rack_capacity']);
    }

    public function test_weapon_lockers_and_slots_are_counted_from_std_item_ports(): void
    {
        $weaponLocker = $this->makeItem([
            'Type' => 'Container.Storage',
            'ClassName' => 'crew_weapon_locker',
            'Name' => 'Crew Locker',
            'Ports' => [
                [
                    'PortName' => 'SidearmSlot',
                    'MaxSize' => 2.0,
                    'Types' => ['WeaponPersonal.Pistol'],
                ],
                [
                    'PortName' => 'RifleSlot',
                    'MaxSize' => 4.0,
                    'Types' => ['WeaponPersonal.Rifle'],
                ],
            ],
        ]);

        $result = $this->calculateForItems([$weaponLocker]);
        $weaponStorage = $result['weapon_storage'];

        self::assertSame(1, $weaponStorage['lockers']);
        self::assertSame(2, $weaponStorage['slots_total']);
        self::assertSame(1, $weaponStorage['slots_rifle']);
        self::assertSame(1, $weaponStorage['slots_pistol']);
        self::assertIsArray($weaponStorage['by_locker']);
        self::assertCount(1, $weaponStorage['by_locker']);
        self::assertSame('Crew Locker', $weaponStorage['by_locker'][0]['name']);
        self::assertSame('crew_weapon_locker', $weaponStorage['by_locker'][0]['class_name']);
        self::assertSame('Port 0', $weaponStorage['by_locker'][0]['port']);
        self::assertSame(2, $weaponStorage['by_locker'][0]['slots_total']);
        self::assertSame(1, $weaponStorage['by_locker'][0]['slots_rifle']);
        self::assertSame(1, $weaponStorage['by_locker'][0]['slots_pistol']);
    }

    public function test_weapon_storage_falls_back_to_raw_component_ports_when_std_item_ports_are_missing(): void
    {
        $rawPortLocker = $this->makeItem(
            [
                'Type' => 'Container.Storage',
                'ClassName' => 'raw_weapon_station',
                'Name' => 'Raw Locker',
            ],
            [
                'Components' => [
                    'SItemPortContainerComponentParams' => [
                        'Ports' => [
                            'SItemPortDef' => [
                                [
                                    '@Name' => 'SidearmSlot',
                                    '@MaxSize' => 2,
                                    'Types' => [
                                        'SItemPortDefTypes' => [
                                            '@Type' => 'WeaponPersonal',
                                            '@SubTypes' => 'Pistol',
                                        ],
                                    ],
                                ],
                                [
                                    'Name' => 'LongGunSlot',
                                    'MaxSize' => 4,
                                    'Types' => [
                                        'SItemPortDefTypes' => [
                                            'Type' => 'WeaponPersonal',
                                            'SubTypes' => [
                                                'SItemPortDefType' => [
                                                    ['value' => 'Rifle'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $result = $this->calculateForItems([$rawPortLocker]);
        $weaponStorage = $result['weapon_storage'];

        self::assertSame(1, $weaponStorage['lockers']);
        self::assertSame(2, $weaponStorage['slots_total']);
        self::assertSame(1, $weaponStorage['slots_rifle']);
        self::assertSame(1, $weaponStorage['slots_pistol']);
        self::assertIsArray($weaponStorage['by_locker']);
        self::assertCount(1, $weaponStorage['by_locker']);
        self::assertSame('Raw Locker', $weaponStorage['by_locker'][0]['name']);
        self::assertSame('raw_weapon_station', $weaponStorage['by_locker'][0]['class_name']);
        self::assertSame('Port 0', $weaponStorage['by_locker'][0]['port']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function calculateForParts(array $parts): array
    {
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

        return $this->calculateForParts($parts);
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

    /**
     * @param  array<string, mixed>  $stdItem
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function makeItem(array $stdItem, array $payload = []): array
    {
        return array_replace_recursive(
            [
                'stdItem' => $stdItem,
            ],
            $payload
        );
    }
}
