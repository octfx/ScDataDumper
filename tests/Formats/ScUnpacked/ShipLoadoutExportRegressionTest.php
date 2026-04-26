<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Services\Vehicle\SeatingAnalyzer;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ShipLoadoutExportRegressionTest extends TestCase
{
    public function test_loadout_entry_keeps_semantic_type_and_does_not_export_classifier_fields(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'S3TurretPort',
            'Uneditable' => false,
            'Types' => ['Turret'],
            'MinSize' => 1,
            'MaxSize' => 3,
            'InstalledItem' => [
                'classification' => 'ship.turret.gunturret',
                'Type' => 'Ship.Turret.GunTurret',
                'stdItem' => [
                    'ClassName' => 'TURR_S3_EXAMPLE',
                    'UUID' => '00000000-0000-0000-0000-000000000001',
                    'Name' => 'Example Turret',
                    'Manufacturer' => ['Name' => 'Example Manufacturer'],
                    'Type' => 'Turret.GunTurret',
                    'Grade' => 'A',
                    'Ports' => [],
                ],
            ],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame('Turret.GunTurret', $entry['Type'] ?? null);
        self::assertArrayNotHasKey('classification', $entry);
        self::assertArrayNotHasKey('Classification', $entry);
    }

    public function test_build_seating_info_classifies_seats_and_beds(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_seat_pilot',
                    'InstalledItem' => [
                        'stdItem' => [
                            'Type' => 'Seat.UNDEFINED',
                            'Name' => 'Pilot Seat',
                            'ClassName' => 'SEAT_PILOT',
                            'Seat' => [
                                'SeatType' => 'HOTAS_C_L',
                            ],
                        ],
                        'entity_tag_map' => [
                            ['name' => 'Seat'],
                            ['name' => 'Helmsman'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        self::assertCount(1, $result['Seats']);
        $seat = $result['Seats'][0];
        self::assertSame('hardpoint_seat_pilot', $seat['HardpointName']);
        self::assertSame('SEAT_PILOT', $seat['ClassName']);
        self::assertSame('Helmsman', $seat['Role']);
        self::assertSame('HOTAS_C_L', $seat['SeatType']);
        self::assertArrayNotHasKey('HasEjection', $seat);

        self::assertSame(1, $result['Summary']['Helmsman']);
        self::assertArrayNotHasKey('Unknown', $result['Summary']);
    }

    public function test_build_seating_info_counts_escape_pods_and_ejection_seats(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_seat_pilot',
                    'InstalledItem' => [
                        'stdItem' => [
                            'Type' => 'Seat.UNDEFINED',
                            'Name' => 'Pilot Seat',
                            'ClassName' => 'SEAT_PILOT',
                            'Seat' => [
                                'HasEjection' => true,
                                'Ejection' => ['MaxLinearVelocity' => 2000],
                            ],
                        ],
                        'entity_tag_map' => [
                            ['name' => 'Seat'],
                            ['name' => 'Helmsman'],
                        ],
                    ],
                ],
                'Parts' => [
                    [
                        'Name' => 'EscapePodPart',
                        'Port' => [
                            'PortName' => 'hardpoint_escape_pod_left',
                            'InstalledItem' => [
                                'stdItem' => [
                                    'Type' => 'Seat.UNDEFINED',
                                    'Name' => 'Bed',
                                    'ClassName' => 'ESCAPE_POD_LEFT',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        self::assertCount(1, $result['Seats']);
        $seat = $result['Seats'][0];
        self::assertSame('Helmsman', $seat['Role']);
        self::assertTrue($seat['HasEjection']);

        self::assertSame(1, $result['Summary']['Helmsman']);
        self::assertSame(1, $result['Summary']['EscapePods']);
        self::assertSame(1, $result['Summary']['EjectionSeats']);
    }

    public function test_build_seating_info_groups_stations(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_seat_bridge_l',
                    'InstalledItem' => [
                        'stdItem' => [
                            'Type' => 'Seat.UNDEFINED',
                            'ClassName' => 'BRIDGE_SEAT_L',
                        ],
                        'entity_tag_map' => [
                            ['name' => 'Seat'],
                            ['name' => 'Bridge'],
                        ],
                    ],
                ],
                'Parts' => [
                    [
                        'Name' => 'BridgePart',
                        'Port' => [
                            'PortName' => 'hardpoint_seat_bridge_r',
                            'InstalledItem' => [
                                'stdItem' => [
                                    'Type' => 'Seat.UNDEFINED',
                                    'ClassName' => 'BRIDGE_SEAT_R',
                                ],
                                'entity_tag_map' => [
                                    ['name' => 'Seat'],
                                    ['name' => 'Bridge'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'Name' => 'DronePart',
                        'Port' => [
                            'PortName' => 'hardpoint_seat_drone_l',
                            'InstalledItem' => [
                                'stdItem' => [
                                    'Type' => 'Seat.UNDEFINED',
                                    'ClassName' => 'DRONE_SEAT_L',
                                ],
                                'entity_tag_map' => [
                                    ['name' => 'Seat'],
                                    ['name' => 'Engineering'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'Name' => 'TurretPart',
                        'Port' => [
                            'PortName' => 'hardpoint_turret_top',
                            'InstalledItem' => [
                                'stdItem' => [
                                    'Type' => 'TurretBase.MannedTurret',
                                    'ClassName' => 'TURRET_MANNED_TOP',
                                    'Seat' => [
                                        'SeatType' => 'DUAL_STICK',
                                    ],
                                ],
                                'entity_tag_map' => [
                                    ['name' => 'Turret'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        self::assertCount(3, $result['Seats']);
        self::assertSame(1, $result['Summary']['TurretGunner']);
        self::assertSame(2, $result['Summary']['Stations']['Bridge']);
        self::assertSame(1, $result['Summary']['Stations']['Engineering']);
    }

    private function newShipForInternalInvocation(): Ship
    {
        return new ReflectionClass(Ship::class)->newInstanceWithoutConstructor();
    }

    private function invokeBuildLoadoutEntry(Ship $ship, array $port): ?array
    {
        $method = new ReflectionMethod(Ship::class, 'buildLoadoutEntry');

        $result = $method->invoke($ship, $port);

        return is_array($result) ? $result : null;
    }

    private function invokeSeatingAnalyzer(array $standardisedParts): array
    {
        $context = new VehicleDataContext(
            standardisedParts: $standardisedParts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        );

        $analyzer = new SeatingAnalyzer;
        $result = $analyzer->calculate($context)['Seating'];

        self::assertIsArray($result);

        return $result;
    }
}
