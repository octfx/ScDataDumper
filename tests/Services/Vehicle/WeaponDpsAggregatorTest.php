<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\WeaponDpsAggregator;
use PHPUnit\Framework\TestCase;

final class WeaponDpsAggregatorTest extends TestCase
{
    public function test_empty_parts_returns_empty(): void
    {
        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($this->makeContext([]));

        self::assertSame([], $result);
    }

    public function test_fixed_weapon_dps_and_alpha_aggregated(): void
    {
        $result = $this->calculateForItems([
            $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
            $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertArrayHasKey('FixedWeapons', $weaponry);
        self::assertSame(840.0, $weaponry['FixedWeapons']['DpsTotal']);
        self::assertSame(1008.0, $weaponry['FixedWeapons']['AlphaTotal']);
        self::assertSame(600.0, $weaponry['FixedWeapons']['SustainedDpsTotal']);
        self::assertCount(2, $weaponry['FixedWeapons']['Weapons']);
        self::assertSame('Laser_S3_UUID', $weaponry['FixedWeapons']['Weapons'][0]['UUID']);
        self::assertSame('Laser_S3', $weaponry['FixedWeapons']['Weapons'][0]['ClassName']);

        self::assertSame(840.0, $weaponry['PilotDps']);
        self::assertSame(1008.0, $weaponry['PilotAlpha']);
        self::assertSame(600.0, $weaponry['PilotSustainedDps']);
    }

    public function test_turret_weapons_grouped_by_hardpoint(): void
    {
        $parts = [
            // Turret base
            [
                'Name' => 'hardpoint_remote_turret_top',
                'Port' => [
                    'PortName' => 'hardpoint_remote_turret_top',
                    'InstalledItem' => $this->makeItem('TurretBase.RemoteTurret'),
                ],
                'Parts' => [
                    // Gimbal left with weapon
                    [
                        'Name' => 'turret_left',
                        'Port' => [
                            'PortName' => 'turret_left',
                            'InstalledItem' => $this->makeItem(
                                'Turret.GunTurret',
                                [
                                    'Weapon' => null, // gimbal has no weapon damage
                                ]
                            ),
                            'InstalledItem' => array_merge(
                                $this->makeItem('Turret.GunTurret'),
                                [
                                    'stdItem' => [
                                        'Ports' => [
                                            [
                                                'PortName' => 'hardpoint_class_2',
                                                'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
                                            ],
                                        ],
                                    ],
                                ]
                            ),
                        ],
                    ],
                ],
            ],
        ];

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($this->makeContext($parts));
        $weaponry = $result['Weaponry'];

        // The weapon should be detected as being inside the turret
        self::assertArrayHasKey('Turrets', $weaponry);
        self::assertCount(1, $weaponry['Turrets']);
        self::assertSame('hardpoint_remote_turret_top', $weaponry['Turrets'][0]['HardpointName']);
        self::assertSame(420.0, $weaponry['Turrets'][0]['DpsTotal']);
        self::assertSame(504.0, $weaponry['Turrets'][0]['AlphaTotal']);
    }

    public function test_missile_damage_summed_per_type(): void
    {
        $result = $this->calculateForItems([
            $this->makeMissile('MISL_S1', ['Physical' => 750, 'Energy' => 0]),
            $this->makeMissile('MISL_S2', ['Physical' => 1500, 'Energy' => 100]),
            $this->makeMissile('MISL_S1', ['Physical' => 750, 'Energy' => 0]),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertSame(3, $weaponry['Missiles']['Count']);
        self::assertSame(3000.0, $weaponry['Missiles']['Damage']['Physical']);
        self::assertSame(100.0, $weaponry['Missiles']['Damage']['Energy']);
        self::assertSame(3100.0, $weaponry['Missiles']['Damage']['Total']);
        self::assertSame(3100.0, $weaponry['TotalMissiles']);
    }

    public function test_countermeasures_summed_by_type(): void
    {
        $result = $this->calculateForItems([
            $this->makeCountermeasure('CM_Flare', 'Flare', 48),
            $this->makeCountermeasure('CM_Flare_2', 'Flare', 24),
            $this->makeCountermeasure('CM_Noise', 'Noise', 10),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertSame(72, $weaponry['Countermeasures']['Flare']);
        self::assertSame(10, $weaponry['Countermeasures']['Noise']);
    }

    public function test_weapon_without_damage_skipped(): void
    {
        $result = $this->calculateForItems([
            $this->makeItem('WeaponGun.Gun', ['Weapon' => null]),
        ]);

        self::assertSame([], $result);
    }

    public function test_mixed_loadout(): void
    {
        $result = $this->calculateForItems([
            $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
            $this->makeMissile('MISL_S1', ['Physical' => 750]),
            $this->makeCountermeasure('CM_Flare', 'Flare', 48),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertArrayHasKey('FixedWeapons', $weaponry);
        self::assertSame(420.0, $weaponry['FixedWeapons']['DpsTotal']);
        self::assertSame(1, $weaponry['Missiles']['Count']);
        self::assertSame(750.0, $weaponry['TotalMissiles']);
        self::assertSame(48, $weaponry['Countermeasures']['Flare']);
    }

    public function test_sustained_dps_null_when_missing(): void
    {
        $result = $this->calculateForItems([
            $this->makeWeapon('WeaponGun.Gun', 'Ballistic_S3', 200.0, 150.0, null),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertNull($weaponry['FixedWeapons']['SustainedDpsTotal']);
        self::assertArrayNotHasKey('PilotSustainedDps', $weaponry);
    }

    public function test_turret_weapon_inherits_is_pilot_slaveable(): void
    {
        $parts = [
            [
                'Name' => 'hardpoint_remote_turret_top',
                'Port' => [
                    'PortName' => 'hardpoint_remote_turret_top',
                    'Category' => 'Remote turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('Turret.GunTurret'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    [
                                        'PortName' => 'hardpoint_class_2',
                                        'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'CF-447', 500.0, 100.0, 350.0),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $context = new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: ['hardpoint_remote_turret_top'],
        );

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($context);
        $weapons = $result['Weaponry']['Turrets'][0]['Weapons'];

        self::assertCount(1, $weapons);
        self::assertSame('CF-447_UUID', $weapons[0]['UUID']);
        self::assertSame('CF-447', $weapons[0]['ClassName']);
        self::assertTrue($weapons[0]['IsPilotSlaveable']);
    }

    public function test_turret_weapon_is_pilot_slaveable_false_when_not_bridge(): void
    {
        $parts = [
            [
                'Name' => 'hardpoint_remote_turret_top',
                'Port' => [
                    'PortName' => 'hardpoint_remote_turret_top',
                    'Category' => 'Remote turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('Turret.GunTurret'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    [
                                        'PortName' => 'hardpoint_class_2',
                                        'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'CF-447', 500.0, 100.0, 350.0),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $context = new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: [],
        );

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($context);
        $weapons = $result['Weaponry']['Turrets'][0]['Weapons'];

        self::assertFalse($weapons[0]['IsPilotSlaveable']);
    }



    public function test_autonomous_turret_detected_by_port_category(): void
    {
        // Asgard-style autonomous door turret: WeaponGun.Gun mount with port category 'Autonomous turrets'
        // The mount contains a WeaponController sub-port, which contains the actual weapon
        $parts = [
            [
                'Name' => 'hardpoint_door_left',
                'Port' => [
                    'PortName' => 'hardpoint_door_left',
                    'Category' => 'Autonomous turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('WeaponGun.Gun'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    // WeaponController sub-port containing the actual gun
                                    [
                                        'PortName' => 'weapon_controller',
                                        'InstalledItem' => array_merge(
                                            $this->makeItem('WeaponController.UnmannedTurret'),
                                            [
                                                'stdItem' => [
                                                    'Ports' => [
                                                        [
                                                            'PortName' => 'hardpoint_class_1',
                                                            'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'YellowJacket', 200.0, 50.0, null),
                                                        ],
                                                    ],
                                                ],
                                            ]
                                        ),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($this->makeContext($parts));
        $weaponry = $result['Weaponry'];

        self::assertArrayHasKey('Turrets', $weaponry);
        self::assertCount(1, $weaponry['Turrets']);
        self::assertSame('hardpoint_door_left', $weaponry['Turrets'][0]['HardpointName']);
        self::assertSame(200.0, $weaponry['Turrets'][0]['DpsTotal']);
        self::assertSame(50.0, $weaponry['Turrets'][0]['AlphaTotal']);
    }

    public function test_remote_turret_detected_by_port_category(): void
    {
        // A2-style remote turret: Turret.GunTurret item with port category 'Remote turrets'
        $parts = [
            [
                'Name' => 'hardpoint_forward_left_remote_turret',
                'Port' => [
                    'PortName' => 'hardpoint_forward_left_remote_turret',
                    'Category' => 'Remote turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('Turret.GunTurret'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    [
                                        'PortName' => 'hardpoint_class_2',
                                        'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'CF-447', 500.0, 100.0, 350.0),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);
        $result = $aggregator->calculate($this->makeContext($parts));
        $weaponry = $result['Weaponry'];

        self::assertArrayHasKey('Turrets', $weaponry);
        self::assertCount(1, $weaponry['Turrets']);
        self::assertSame('hardpoint_forward_left_remote_turret', $weaponry['Turrets'][0]['HardpointName']);
        self::assertSame(500.0, $weaponry['Turrets'][0]['DpsTotal']);
    }

    public function test_bomb_counted_as_missile(): void
    {
        $result = $this->calculateForItems([
            $this->makeBomb('Colossus_Bomb', ['Physical' => 568297.0]),
            $this->makeBomb('Colossus_Bomb', ['Physical' => 568297.0]),
        ]);

        $weaponry = $result['Weaponry'];
        self::assertSame(2, $weaponry['Missiles']['Count']);
        self::assertSame(1136594.0, $weaponry['Missiles']['Damage']['Physical']);
    }

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

        $aggregator = new WeaponDpsAggregator(new StandardisedPartWalker);

        return $aggregator->calculate($this->makeContext($parts));
    }

    private function makeContext(array $parts): VehicleDataContext
    {
        return new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        );
    }

    private function makeItem(string $type, array $stdItem = []): array
    {
        return [
            'type' => $type,
            'stdItem' => $stdItem,
        ];
    }

    private function makeWeapon(string $type, string $className, float $dps, float $alpha, ?float $sustained): array
    {
        $damage = [
            'DpsTotal' => $dps,
            'AlphaTotal' => $alpha,
        ];
        if ($sustained !== null) {
            $damage['Sustained'] = $sustained;
        }

        return $this->makeItem($type, [
            'UUID' => $className . '_UUID',
            'ClassName' => $className,
            'Weapon' => [
                'Damage' => $damage,
            ],
        ]);
    }

    private function makeMissile(string $className, array $damageByType): array
    {
        $damage = array_merge(
            ['Physical' => 0, 'Energy' => 0, 'Distortion' => 0, 'Thermal' => 0, 'Biochemical' => 0, 'Stun' => 0],
            $damageByType
        );

        return $this->makeItem('Missile.Missile', [
            'ClassName' => $className,
            'Missile' => [
                'Damage' => $damage,
            ],
        ]);
    }

    private function makeCountermeasure(string $className, string $type, int $capacity): array
    {
        return $this->makeItem('WeaponDefensive', [
            'ClassName' => $className,
            'WeaponDefensive' => [
                'Type' => $type,
                'Capacity' => $capacity,
            ],
        ]);
    }

    private function makeBomb(string $className, array $damageByType): array
    {
        $damage = array_merge(
            ['Physical' => 0, 'Energy' => 0, 'Distortion' => 0, 'Thermal' => 0, 'Biochemical' => 0, 'Stun' => 0],
            $damageByType
        );

        return $this->makeItem('Bomb.Utility', [
            'ClassName' => $className,
            'Missile' => [
                'Damage' => $damage,
            ],
        ]);
    }
}
