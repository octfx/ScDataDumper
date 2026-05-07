<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Services\Vehicle\SeatingAnalyzer;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use ReflectionClass;

final class ShipLoadoutExportRegressionTest extends ScDataTestCase
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

        self::assertSame(1, $result['CrewStations']);
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

        self::assertSame(1, $result['CrewStations']);
        self::assertSame(1, $result['EscapePods']);
        self::assertSame(1, $result['EjectionSeats']);
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
                                    'Type' => 'Seat.UNDEFINED',
                                    'ClassName' => 'TURRET_SEAT',
                                ],
                                'entity_tag_map' => [
                                    ['name' => 'Seat'],
                                    ['name' => 'Turret'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        self::assertCount(4, $result['Seats']);
        self::assertSame(4, $result['CrewStations']);
    }

    public function test_build_seating_info_discovers_loadout_beds(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_seat_pilot',
                    'InstalledItem' => [
                        'stdItem' => [
                            'Type' => 'Seat.UNDEFINED',
                            'ClassName' => 'SEAT_PILOT',
                            'Ports' => [[
                                'PortName' => 'BedPort',
                                'InstalledItem' => [
                                    'type' => 'Bed',
                                    'stdItem' => [
                                        'Type' => 'Bed.Captain',
                                        'ClassName' => 'Bed_Single_RSI_Phoenix',
                                    ],
                                ],
                            ]],
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
        self::assertCount(1, $result['Beds']);

        $bed = $result['Beds'][0];
        self::assertSame('BedPort', $bed['HardpointName']);
        self::assertSame('Bed_Single_RSI_Phoenix', $bed['ClassName']);
        self::assertSame('Single', $bed['BedType']);
        self::assertFalse($bed['IsMedical']);

        self::assertSame(1, $result['CrewStations']);
        self::assertSame(1, $result['TotalBeds']);
        self::assertArrayNotHasKey('MedicalBeds', $result);
    }

    public function test_build_seating_info_classifies_medical_beds(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_bed_medical',
                    'InstalledItem' => [
                        'type' => 'Bed',
                        'stdItem' => [
                            'Type' => 'Usable.UNDEFINED',
                            'ClassName' => 'Bed_Single_Medical_Carrack',
                        ],
                    ],
                ],
            ],
            [
                'Name' => 'BedPart2',
                'Port' => [
                    'PortName' => 'hardpoint_bed_bunk',
                    'InstalledItem' => [
                        'type' => 'Bed',
                        'stdItem' => [
                            'Type' => 'Usable.UNDEFINED',
                            'ClassName' => 'Bed_Bunk_ANVL_Carrack',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        self::assertArrayNotHasKey('Seats', $result);
        self::assertCount(2, $result['Beds']);

        $medBed = $result['Beds'][0];
        self::assertSame('Bed_Single_Medical_Carrack', $medBed['ClassName']);
        self::assertSame('Medical', $medBed['BedType']);
        self::assertFalse($medBed['IsMedical']);
        self::assertNull($medBed['MedicalTier']);

        $bunkBed = $result['Beds'][1];
        self::assertSame('Bed_Bunk_ANVL_Carrack', $bunkBed['ClassName']);
        self::assertSame('Bunk', $bunkBed['BedType']);
        self::assertFalse($bunkBed['IsMedical']);

        self::assertSame(2, $result['TotalBeds']);
        self::assertArrayNotHasKey('MedicalBeds', $result);
    }

    public function test_build_seating_info_detects_medical_tier_from_classname_pattern(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_bed_medical',
                    'InstalledItem' => [
                        'type' => 'Bed',
                        'stdItem' => [
                            'Type' => 'Usable.UNDEFINED',
                            'ClassName' => 'Bed_Single_Medical_Ship_Canister_T2_Template',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer($standardisedParts);

        $medBed = $result['Beds'][0];
        self::assertSame('Bed_Single_Medical_Ship_Canister_T2_Template', $medBed['ClassName']);
        self::assertSame('Medical', $medBed['BedType']);
        self::assertTrue($medBed['IsMedical']);
        self::assertSame('T2', $medBed['MedicalTier']);

        self::assertSame([['tier' => 'T2', 'count' => 1]], $result['MedicalBeds']);
    }

    private function newShipForInternalInvocation(): Ship
    {
        return new ReflectionClass(Ship::class)->newInstanceWithoutConstructor();
    }

    private function invokeBuildLoadoutEntry(Ship $ship, array $port): ?array
    {
        $result = $this->invokeMethod($ship, 'buildLoadoutEntry', $port);

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
