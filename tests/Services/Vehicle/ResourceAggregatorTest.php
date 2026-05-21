<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\Vehicle\ResourceAggregator;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Tests\Fixtures\BuildsTestItems;
use PHPUnit\Framework\TestCase;

final class ResourceAggregatorTest extends TestCase
{
    use BuildsTestItems;

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

    public function test_theoretical_regen_uses_raw_regen_when_power_pool_is_unavailable(): void
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

        self::assertEquals(100.0, $shieldsTotal['regen']);
        self::assertArrayHasKey('regen_raw', $shieldsTotal);
        self::assertArrayHasKey('regen_min_power', $shieldsTotal);
        self::assertEquals(100.0, $shieldsTotal['regen_raw']);
        self::assertEquals(75.0, $shieldsTotal['regen_min_power']);
    }

    public function test_theoretical_regen_is_normalized_by_all_installed_shield_power_at_high_shield_power(): void
    {
        $activeShield = $this->makeShieldItem(
            maxShieldRegen: 2006.0,
            states: [
                [
                    'Name' => 'Online',
                    'Deltas' => [
                        [
                            'Type' => 'Conversion',
                            'GeneratedResource' => 'Shield',
                            'GeneratedRate' => 2006.0,
                            'MinimumFraction' => 0.25,
                        ],
                    ],
                ],
            ],
            powerMaximum: 4.0,
        );

        $installedInactiveShields = array_fill(0, 5, $this->makeShieldItem(maxShieldRegen: 2006.0, powerMaximum: 4.0));

        $result = $this->calculateForItems(
            [$activeShield, ...$installedInactiveShields],
            entity: $this->makeVehicleWithShieldPoolMaxCount(1),
        );

        self::assertEquals(2006.0, $result['shields_total']['regen_raw']);
        self::assertEquals(334.33, $result['shields_total']['regen']);
        self::assertEquals(2.99, $result['shields_total']['regeneration_time']);
    }

    public function test_redeemer_like_regeneration_time_uses_all_installed_shield_power_without_reserve_ratio(): void
    {
        $shields = array_fill(
            0,
            6,
            $this->makeShieldItem(
                maxShieldRegen: 2006.0,
                health: 10560.0,
                powerMaximum: 4.0,
                reservePoolDrainRateRatio: 2.5,
            ),
        );

        $result = $this->calculateForItems(
            $shields,
            entity: $this->makeVehicleWithShieldPoolMaxCount(2),
        );

        self::assertEquals(4012.0, $result['shields_total']['regen_raw']);
        self::assertEquals(1337.33, $result['shields_total']['regen']);
        self::assertEquals(15.79, $result['shields_total']['regeneration_time']);
    }

    public function test_negative_shield_pool_max_count_means_unlimited_active_shields(): void
    {
        $shield = $this->makeShieldItem(maxShieldRegen: 51.0, health: 900.0, powerMaximum: 1.0);

        $result = $this->calculateForItems(
            [$shield],
            entity: $this->makeVehicleWithShieldPoolMaxCount(-1),
        );

        self::assertEquals(900.0, $result['shields_total']['hp']);
        self::assertEquals(51.0, $result['shields_total']['regen_raw']);
        self::assertEquals(51.0, $result['shields_total']['regen']);
        self::assertEquals(17.65, $result['shields_total']['regeneration_time']);
    }

    public function test_shield_totals_only_include_active_shields(): void
    {
        $shieldA = $this->makeShieldItem(maxShieldRegen: 100.0, health: 1000.0, powerMaximum: 2.0);
        $shieldB = $this->makeShieldItem(maxShieldRegen: 200.0, health: 2000.0, powerMaximum: 2.0);
        $inactiveShield = $this->makeShieldItem(maxShieldRegen: 300.0, health: 3000.0, powerMaximum: 2.0);

        $result = $this->calculateForItems(
            [$shieldA, $shieldB, $inactiveShield],
            entity: $this->makeVehicleWithShieldPoolMaxCount(2),
        );

        self::assertEquals(3000.0, $result['shields_total']['hp']);
        self::assertEquals(300.0, $result['shields_total']['regen_raw']);
        self::assertEquals(200.0, $result['shields_total']['regen']);
    }

    public function test_empty_loadout_returns_empty_resource_shape(): void
    {
        $result = $this->calculateForParts([]);

        self::assertSame(0.0, $result['mass_loadout']);
        self::assertNull($result['shields_total']['hp']);
        self::assertNull($result['shields_total']['regen']);
        self::assertNull($result['shields_total']['regeneration_time']);
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
        self::assertNull($result['suit_storage']['lockers']);
        self::assertNull($result['suit_storage']['slots_total']);
        self::assertNull($result['suit_storage']['by_locker']);
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

    public function test_suit_lockers_and_slots_are_counted_from_std_item_ports(): void
    {
        $suitLocker = $this->makeItem([
            'Type' => 'Usable',
            'ClassName' => 'Locker_Suit_DRAK_Cutter_Rambler',
            'Name' => 'Suit Locker',
            'Ports' => [
                [
                    'PortName' => 'armor_undersuit_itemport',
                    'MaxSize' => 1.0,
                    'Types' => ['Char_Armor_Undersuit'],
                ],
            ],
        ]);

        $result = $this->calculateForItems([$suitLocker]);
        $suitStorage = $result['suit_storage'];

        self::assertSame(1, $suitStorage['lockers']);
        self::assertSame(1, $suitStorage['slots_total']);
        self::assertIsArray($suitStorage['by_locker']);
        self::assertCount(1, $suitStorage['by_locker']);
        self::assertSame('Suit Locker', $suitStorage['by_locker'][0]['name']);
        self::assertSame('locker_suit_drak_cutter_rambler', $suitStorage['by_locker'][0]['class_name']);
        self::assertSame('Port 0', $suitStorage['by_locker'][0]['port']);
        self::assertSame(1, $suitStorage['by_locker'][0]['slots_total']);
    }

    public function test_suit_locker_detected_by_port_type_when_class_name_does_not_match(): void
    {
        $suitLocker = $this->makeItem([
            'Type' => 'Usable',
            'ClassName' => 'Custom_Suit_Cabinet',
            'Name' => 'Custom Cabinet',
            'Ports' => [
                [
                    'PortName' => 'armor_helmet_itemport',
                    'MaxSize' => 1.0,
                    'Types' => ['Char_Armor_Helmet'],
                ],
            ],
        ]);

        $result = $this->calculateForItems([$suitLocker]);
        $suitStorage = $result['suit_storage'];

        self::assertSame(1, $suitStorage['lockers']);
        self::assertSame(1, $suitStorage['slots_total']);
    }

    public function test_suit_locker_detected_by_suit_locker_class_name_pattern(): void
    {
        $suitLocker = $this->makeItem([
            'Type' => 'Usable',
            'ClassName' => 'Useable_suit_locker_TEMPLATE_01',
            'Name' => 'Suit Locker',
            'Ports' => [],
        ]);

        $result = $this->calculateForItems([$suitLocker]);
        $suitStorage = $result['suit_storage'];

        self::assertSame(1, $suitStorage['lockers']);
        self::assertNull($suitStorage['slots_total']);
    }

    public function test_suit_storage_counts_multiple_lockers_and_slots(): void
    {
        $locker1 = $this->makeItem([
            'Type' => 'Usable',
            'ClassName' => 'Locker_Suit_A',
            'Name' => 'Suit Locker A',
            'Ports' => [
                [
                    'PortName' => 'armor_undersuit_itemport',
                    'MaxSize' => 1.0,
                    'Types' => ['Char_Armor_Undersuit'],
                ],
            ],
        ]);

        $locker2 = $this->makeItem([
            'Type' => 'Usable',
            'ClassName' => 'Locker_Suit_B',
            'Name' => 'Suit Locker B',
            'Ports' => [
                [
                    'PortName' => 'armor_helmet_itemport',
                    'MaxSize' => 1.0,
                    'Types' => ['Char_Armor_Helmet'],
                ],
                [
                    'PortName' => 'armor_torso_itemport',
                    'MaxSize' => 1.0,
                    'Types' => ['Char_Armor_Torso'],
                ],
            ],
        ]);

        $result = $this->calculateForItems([$locker1, $locker2]);
        $suitStorage = $result['suit_storage'];

        self::assertSame(2, $suitStorage['lockers']);
        self::assertSame(3, $suitStorage['slots_total']);
        self::assertCount(2, $suitStorage['by_locker']);
        self::assertSame(1, $suitStorage['by_locker'][0]['slots_total']);
        self::assertSame(2, $suitStorage['by_locker'][1]['slots_total']);
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
    private function calculateForParts(array $parts, array $intermediateResults = [], ?VehicleDefinition $entity = null): array
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
            intermediateResults: $intermediateResults,
            entity: $entity,
        );

        return $this->aggregator->calculate($context);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function calculateForItems(array $items, array $intermediateResults = [], ?VehicleDefinition $entity = null): array
    {
        return $this->calculateForParts($this->wrapItemsAsParts($items), $intermediateResults, $entity);
    }

    /**
     * @param  array<int, array<string, mixed>>  $states
     * @return array<string, mixed>
     */
    private function makeShieldItem(
        float $maxShieldRegen,
        array $states = [],
        float $health = 1000.0,
        ?float $powerMaximum = null,
        float $reservePoolDrainRateRatio = 1.0,
    ): array {
        $stdItem = [
            'Shield' => [
                'MaxShieldHealth' => $health,
                'MaxShieldRegen' => $maxShieldRegen,
                'ReservePoolDrainRateRatio' => $reservePoolDrainRateRatio,
            ],
        ];

        if ($states !== [] || $powerMaximum !== null) {
            $stdItem['ResourceNetwork'] = [];
        }

        if ($states !== []) {
            $stdItem['ResourceNetwork']['States'] = $states;
        }

        if ($powerMaximum !== null) {
            $stdItem['ResourceNetwork']['Usage']['Power']['Maximum'] = $powerMaximum;
        }

        return [
            'stdItem' => $stdItem,
        ];
    }

    private function makeVehicleWithShieldPoolMaxCount(int $maxCount): VehicleDefinition
    {
        $vehicle = new VehicleDefinition;
        $vehicle->loadXML(<<<XML
<VehicleDefinition.Test>
    <Components>
        <SItemPortContainerComponentParams>
            <resourceNetworkPowerPools>
                <itemPools>
                    <Pool itemType="Shield" maxItemCount="{$maxCount}" />
                </itemPools>
            </resourceNetworkPowerPools>
        </SItemPortContainerComponentParams>
    </Components>
</VehicleDefinition.Test>
XML);

        return $vehicle;
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
