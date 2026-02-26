<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Services\Vehicle\ItemTypeResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

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

    public function test_count_seats_and_beds_matches_semantic_major_type(): void
    {
        $ship = $this->newShipForInternalInvocation();

        $standardisedParts = [
            [
                'Name' => 'RootPart',
                'Port' => [
                    'PortName' => 'SeatPort',
                    'InstalledItem' => [
                        'stdItem' => [
                            'Type' => 'Seat.UNDEFINED',
                            'Name' => 'Pilot Seat',
                            'ClassName' => 'SEAT_PILOT',
                        ],
                    ],
                ],
                'Parts' => [
                    [
                        'Name' => 'CrewQuarterPart',
                        'Port' => [
                            'PortName' => 'BedPort',
                            'InstalledItem' => [
                                'stdItem' => [
                                    'Type' => 'Bed.Captain',
                                    'Name' => 'Captain Rest Unit',
                                    'ClassName' => 'CAPTAIN_BUNK',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeCountSeatsAndBeds($ship, $standardisedParts);

        self::assertSame(1, $result['seat_count']);
        self::assertSame(1, $result['bed_count']);
    }

    private function newShipForInternalInvocation(): Ship
    {
        $shipReflection = new ReflectionClass(Ship::class);
        $ship = $shipReflection->newInstanceWithoutConstructor();

        $resolverProperty = new ReflectionProperty(Ship::class, 'itemTypeResolver');
        $resolverProperty->setValue($ship, new ItemTypeResolver);

        return $ship;
    }

    private function invokeBuildLoadoutEntry(Ship $ship, array $port): ?array
    {
        $method = new ReflectionMethod(Ship::class, 'buildLoadoutEntry');

        $result = $method->invoke($ship, $port);

        return is_array($result) ? $result : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $standardisedParts
     * @return array{seat_count: int, bed_count: int}
     */
    private function invokeCountSeatsAndBeds(Ship $ship, array $standardisedParts): array
    {
        $method = new ReflectionMethod(Ship::class, 'countSeatsAndBeds');

        $result = $method->invoke($ship, $standardisedParts);

        self::assertIsArray($result);
        self::assertArrayHasKey('seat_count', $result);
        self::assertArrayHasKey('bed_count', $result);

        return $result;
    }
}
