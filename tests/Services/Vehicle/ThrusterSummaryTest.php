<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\Vehicle\PropulsionSystemAggregator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use PHPUnit\Framework\TestCase;

final class ThrusterSummaryTest extends TestCase
{
    private PropulsionSystemAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new PropulsionSystemAggregator;
    }

    public function test_thrusters_summary_included_in_propulsion_output(): void
    {
        $context = $this->makeContext(
            mass: 50_000.0,
            mainCount: 2,
            mainThrustPerThruster: 5_000_000,
            maneuverCount: 8,
            maneuverThrustPerThruster: 1_000_000,
            retroCount: 2,
            retroThrustPerThruster: 2_000_000,
        );

        $result = $this->aggregator->calculate($context);
        $propulsion = $result['Propulsion'];

        self::assertArrayHasKey('Thrusters', $propulsion);
        self::assertCount(3, $propulsion['Thrusters']);

        self::assertSame('Main', $propulsion['Thrusters'][0]['Type']);
        self::assertSame(2, $propulsion['Thrusters'][0]['Count']);
        self::assertSame(10.0, $propulsion['Thrusters'][0]['Capacity']);
        self::assertSame(20.39, $propulsion['Thrusters'][0]['G']);

        self::assertSame('Maneuver', $propulsion['Thrusters'][1]['Type']);
        self::assertSame(8, $propulsion['Thrusters'][1]['Count']);
        self::assertSame(8.0, $propulsion['Thrusters'][1]['Capacity']);
        self::assertSame(16.32, $propulsion['Thrusters'][1]['G']);

        self::assertSame('Retro', $propulsion['Thrusters'][2]['Type']);
        self::assertSame(2, $propulsion['Thrusters'][2]['Count']);
        self::assertSame(4.0, $propulsion['Thrusters'][2]['Capacity']);
        self::assertSame(8.16, $propulsion['Thrusters'][2]['G']);
    }

    public function test_thrusters_omitted_when_no_thrusters(): void
    {
        $context = $this->makeContext(mass: 1000.0);

        $result = $this->aggregator->calculate($context);

        self::assertArrayNotHasKey('Thrusters', $result['Propulsion']);
    }

    public function test_retro_omitted_when_count_zero(): void
    {
        $context = $this->makeContext(
            mass: 100_000.0,
            mainCount: 4,
            mainThrustPerThruster: 12_500_000,
            maneuverCount: 12,
            maneuverThrustPerThruster: 2_500_000,
        );

        $result = $this->aggregator->calculate($context);
        $thrusters = $result['Propulsion']['Thrusters'];

        self::assertCount(2, $thrusters);
        $types = array_column($thrusters, 'Type');
        self::assertNotContains('Retro', $types);
    }

    public function test_capacity_rounds_to_two_decimals(): void
    {
        $context = $this->makeContext(
            mass: 1000.0,
            mainCount: 1,
            mainThrustPerThruster: 1_234_567,
        );

        $result = $this->aggregator->calculate($context);
        self::assertSame(1.23, $result['Propulsion']['Thrusters'][0]['Capacity']);
    }

    public function test_g_rounds_to_two_decimals(): void
    {
        // 1,000,000 N / 10,000 kg / 9.80665 = 10.19716... → 10.2
        $context = $this->makeContext(
            mass: 10_000.0,
            mainCount: 1,
            mainThrustPerThruster: 1_000_000,
            retroCount: 1,
            retroThrustPerThruster: 500_000,
        );

        $result = $this->aggregator->calculate($context);
        self::assertSame(10.2, $result['Propulsion']['Thrusters'][0]['G']);
    }

    public function test_zero_mass_omits_g(): void
    {
        $context = $this->makeContext(
            mass: 0.0,
            mainCount: 1,
            mainThrustPerThruster: 1_000_000,
            retroCount: 1,
            retroThrustPerThruster: 100_000,
        );

        $result = $this->aggregator->calculate($context);
        $thrusters = $result['Propulsion']['Thrusters'];

        self::assertArrayNotHasKey('G', $thrusters[0]);
        self::assertArrayNotHasKey('G', $thrusters[1]);
    }

    public function test_thrust_capacity_shape_unchanged(): void
    {
        $context = $this->makeContext(
            mainCount: 1,
            mainThrustPerThruster: 5_000_000,
            retroCount: 1,
            retroThrustPerThruster: 2_000_000,
        );

        $result = $this->aggregator->calculate($context);
        $tc = $result['Propulsion']['ThrustCapacity'];

        // Still a flat dict of floats — Main, Retro, Vtol, Maneuvering
        self::assertSame(5_000_000.0, $tc['Main']);
        self::assertSame(2_000_000.0, $tc['Retro']);
        self::assertEquals(0.0, $tc['Vtol']);
        self::assertEquals(0.0, $tc['Maneuvering']);
        self::assertIsFloat($tc['Main']);
    }

    /**
     * @param  array<string, Collection>  $extraPorts  Additional port summary entries
     */
    private function makeContext(
        float $mass = 1000.0,
        int $mainCount = 0,
        float $mainThrustPerThruster = 0.0,
        int $maneuverCount = 0,
        float $maneuverThrustPerThruster = 0.0,
        int $retroCount = 0,
        float $retroThrustPerThruster = 0.0,
    ): VehicleDataContext {
        $makeThrusterCollection = static fn (int $count, float $thrust): Collection => collect(array_fill(0, $count, [
            'Port' => ['InstalledItem' => ['stdItem' => ['Thruster' => ['ThrustCapacity' => $thrust]]]],
        ]));

        return new VehicleDataContext(
            standardisedParts: [],
            portSummary: [
                'mainThrusters' => $makeThrusterCollection($mainCount, $mainThrustPerThruster),
                'maneuveringThrusters' => $makeThrusterCollection($maneuverCount, $maneuverThrustPerThruster),
                'retroThrusters' => $makeThrusterCollection($retroCount, $retroThrustPerThruster),
                'vtolThrusters' => collect(),
                'upThrusters' => collect(),
                'downThrusters' => collect(),
                'strafeThrusters' => collect(),
                'hydrogenFuelTanks' => collect(),
                'hydrogenFuelIntakes' => collect(),
            ],
            ifcsLoadoutEntry: null,
            mass: $mass,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            intermediateResults: [],
        );
    }
}
