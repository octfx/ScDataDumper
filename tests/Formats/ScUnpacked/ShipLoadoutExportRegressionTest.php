<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Services\Vehicle\SeatingAnalyzer;
use Octfx\ScDataDumper\Services\Vehicle\SocpakObject;
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

    public function test_build_seating_info_keeps_loadout_beds_when_socpak_beds_also_exist(): void
    {
        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'hardpoint_bed_loadout',
                    'InstalledItem' => [
                        'type' => 'Bed',
                        'stdItem' => [
                            'Type' => 'Bed.Captain',
                            'ClassName' => 'Bed_Single_Loadout_Source',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeSeatingAnalyzer(
            $standardisedParts,
            [new SocpakObject('Bed_Single_Socpak_Source', 'Bed_Instance_01', 'socpak_section', null, '/tmp/interior.socpak')],
        );

        self::assertCount(1, $result['Beds']);
        self::assertSame('hardpoint_bed_loadout', $result['Beds'][0]['HardpointName']);
        self::assertSame('Bed_Single_Loadout_Source', $result['Beds'][0]['ClassName']);
        self::assertSame(1, $result['TotalBeds']);
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

    public function test_loadout_entry_uses_port_required_tags_when_present(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_controller_flight',
            'Uneditable' => false,
            'Types' => ['FlightController'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => ['ANVL_Paladin_Blade'],
            'InstalledItem' => [
                'stdItem' => [
                    'ClassName' => 'Controller_Flight_ANVL_Paladin',
                    'UUID' => '00000000-0000-0000-0000-000000000002',
                    'Name' => 'Paladin Flight Controller',
                    'Manufacturer' => ['Name' => 'Anvil Aerospace'],
                    'Type' => 'FlightController.UNDEFINED',
                    'Grade' => 'C',
                    'RequiredTags' => ['ANVL_Paladin_Blade'],
                    'Ports' => [],
                ],
            ],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame(['ANVL_Paladin_Blade'], $entry['RequiredTags'] ?? null);
    }

    public function test_loadout_entry_falls_back_to_item_required_tags_when_port_has_none(): void
    {
        $ship = $this->newShipForInternalInvocation();

        // Avenger Stalker scenario: port has no RequiredTags, item does
        $port = [
            'PortName' => 'hardpoint_controller_flight',
            'Uneditable' => false,
            'Types' => ['FlightController'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
            'InstalledItem' => [
                'stdItem' => [
                    'ClassName' => 'Controller_Flight_AEGS_Avenger_Stalker',
                    'UUID' => '00000000-0000-0000-0000-000000000003',
                    'Name' => 'Avenger Stalker Standard Flight Blade',
                    'Manufacturer' => ['Name' => 'Aegis Dynamics'],
                    'Type' => 'FlightController.UNDEFINED',
                    'Grade' => 1,
                    'RequiredTags' => ['AEGS_Avenger_Stalker_Blade'],
                    'Ports' => [],
                ],
            ],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame(['AEGS_Avenger_Stalker_Blade'], $entry['RequiredTags'] ?? null);
    }

    public function test_loadout_entry_has_no_required_tags_when_neither_port_nor_item_have_them(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_cooler',
            'Uneditable' => false,
            'Types' => ['Cooler'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
            'InstalledItem' => [
                'stdItem' => [
                    'ClassName' => 'COOL_AEGS_SingleS1',
                    'UUID' => '00000000-0000-0000-0000-000000000004',
                    'Name' => 'Endurance Cooler',
                    'Manufacturer' => ['Name' => 'Aegis Dynamics'],
                    'Type' => 'Cooler.UNDEFINED',
                    'Grade' => 1,
                    'RequiredTags' => [],
                    'Ports' => [],
                ],
            ],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertArrayNotHasKey('RequiredTags', $entry);
    }

    public function test_loadout_entry_has_no_required_tags_when_no_item_installed(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_paint',
            'Uneditable' => false,
            'Types' => ['Paints'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertArrayNotHasKey('RequiredTags', $entry);
    }

    public function test_loadout_entry_falls_back_to_item_required_tags_with_multiple_tags(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_paint',
            'Uneditable' => false,
            'Types' => ['Paints'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
            'InstalledItem' => [
                'stdItem' => [
                    'ClassName' => 'Paint_AEGS_Avenger_Stalker_White',
                    'UUID' => '00000000-0000-0000-0000-000000000005',
                    'Name' => 'Avenger Stalker White Paint',
                    'Manufacturer' => ['Name' => 'Aegis Dynamics'],
                    'Type' => 'Paints.UNDEFINED',
                    'Grade' => 1,
                    'RequiredTags' => ['Paint_Avenger_Stalker', 'Paint_Base_Avenger'],
                    'Ports' => [],
                ],
            ],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame(['Paint_Avenger_Stalker', 'Paint_Base_Avenger'], $entry['RequiredTags'] ?? null);
    }

    public function test_compatible_types_groups_subtypes_by_major_type(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_weapon_left',
            'Uneditable' => false,
            'Types' => ['WeaponGun.Ballistic', 'WeaponGun.Energy', 'Turret.GunTurret'],
            'MinSize' => 1,
            'MaxSize' => 3,
            'RequiredTags' => [],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertArrayHasKey('CompatibleTypes', $entry);
        self::assertArrayNotHasKey('ItemTypes', $entry);

        $ct = $entry['CompatibleTypes'];
        self::assertCount(2, $ct);

        // First group: WeaponGun with two sub-types
        self::assertSame('WeaponGun', $ct[0]['Type']);
        self::assertSame(['Ballistic', 'Energy'], $ct[0]['SubTypes']);

        // Second group: Turret with one sub-type
        self::assertSame('Turret', $ct[1]['Type']);
        self::assertSame(['GunTurret'], $ct[1]['SubTypes']);
    }

    public function test_compatible_types_with_no_subtypes(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_cooler',
            'Uneditable' => false,
            'Types' => ['Cooler'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        $ct = $entry['CompatibleTypes'];
        self::assertCount(1, $ct);
        self::assertSame('Cooler', $ct[0]['Type']);
        self::assertArrayNotHasKey('SubTypes', $ct[0]);
    }

    public function test_compatible_types_deduplicates_subtypes(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_weapon',
            'Uneditable' => false,
            'Types' => ['WeaponGun.Ballistic', 'WeaponGun.Ballistic', 'WeaponGun.Energy'],
            'MinSize' => 1,
            'MaxSize' => 2,
            'RequiredTags' => [],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        $ct = $entry['CompatibleTypes'];
        self::assertCount(1, $ct);
        self::assertSame(['Ballistic', 'Energy'], $ct[0]['SubTypes']);
    }

    private function invokeSeatingAnalyzer(array $standardisedParts, array $socpakObjects = []): array
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
            socpakObjects: $socpakObjects,
        );

        $analyzer = new SeatingAnalyzer;
        $result = $analyzer->calculate($context)['Seating'];

        self::assertIsArray($result);

        return $result;
    }

    public function test_loadout_entry_exports_port_tags_when_present(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_weapon_class2_nose',
            'Uneditable' => false,
            'Types' => ['Turret.GunTurret'],
            'MinSize' => 3,
            'MaxSize' => 3,
            'RequiredTags' => [],
            'PortTags' => ['AEGS_Avenger_Base'],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame(['AEGS_Avenger_Base'], $entry['PortTags'] ?? null);
    }

    public function test_loadout_entry_exports_port_tags_with_multiple_per_port_tags(): void
    {
        $ship = $this->newShipForInternalInvocation();

        // Polaris PDC port: per-port "RSI_Polaris" + "PDC"
        $port = [
            'PortName' => 'hardpoint_pdc_top_01',
            'Uneditable' => false,
            'Types' => ['Turret.PDCTurret'],
            'MinSize' => 2,
            'MaxSize' => 2,
            'RequiredTags' => ['PDC'],
            'PortTags' => ['RSI_Polaris', 'PDC'],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertSame(['RSI_Polaris', 'PDC'], $entry['PortTags'] ?? null);
    }

    public function test_loadout_entry_omits_port_tags_when_empty(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $port = [
            'PortName' => 'hardpoint_cooler',
            'Uneditable' => false,
            'Types' => ['Cooler'],
            'MinSize' => 1,
            'MaxSize' => 1,
            'RequiredTags' => [],
            'PortTags' => [],
        ];

        $entry = $this->invokeBuildLoadoutEntry($ship, $port);

        self::assertIsArray($entry);
        self::assertArrayNotHasKey('PortTags', $entry);
    }
}
