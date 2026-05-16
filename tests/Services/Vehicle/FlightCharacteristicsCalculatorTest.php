<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\FlightCharacteristicsCalculator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use PHPUnit\Framework\TestCase;

final class FlightCharacteristicsCalculatorTest extends TestCase
{
    private FlightCharacteristicsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new FlightCharacteristicsCalculator;
    }

    // ------------------------------------------------------------------ //
    //  canCalculate                                                       //
    // ------------------------------------------------------------------ //

    public function test_can_calculate_returns_true_for_spaceship(): void
    {
        $context = $this->makeContext(isSpaceship: true);

        self::assertTrue($this->calculator->canCalculate($context));
    }

    public function test_can_calculate_returns_true_for_gravlev(): void
    {
        $context = $this->makeContext(isGravlev: true);

        self::assertTrue($this->calculator->canCalculate($context));
    }

    public function test_can_calculate_returns_false_for_ground_vehicle(): void
    {
        $context = $this->makeContext();

        self::assertFalse($this->calculator->canCalculate($context));
    }

    // ------------------------------------------------------------------ //
    //  calculateCharacteristics: null / missing IFCS                      //
    // ------------------------------------------------------------------ //

    public function test_returns_null_when_ifcs_entry_is_null(): void
    {
        $result = $this->calculator->calculateCharacteristics(null, 50000, $this->makeThrust());

        self::assertNull($result);
    }

    public function test_returns_null_when_ifcs_entry_has_no_ifcs_key(): void
    {
        $result = $this->calculator->calculateCharacteristics(['foo' => 'bar'], 50000, $this->makeThrust());

        self::assertNull($result);
    }

    // ------------------------------------------------------------------ //
    //  Speed extraction                                                   //
    // ------------------------------------------------------------------ //

    public function test_extracts_scm_and_max_speeds(): void
    {
        $result = $this->calculateDefault();

        self::assertSame(220.0, $result['Speeds']['Scm']);
        self::assertSame(1100.0, $result['Speeds']['Max']);
    }

    public function test_extracts_boost_speeds(): void
    {
        $result = $this->calculateDefault();

        self::assertSame(600.0, $result['Speeds']['BoostForward']);
        self::assertSame(200.0, $result['Speeds']['BoostBackward']);
    }

    // ------------------------------------------------------------------ //
    //  Angular rates                                                      //
    // ------------------------------------------------------------------ //

    public function test_extracts_angular_rates(): void
    {
        $result = $this->calculateDefault();

        self::assertSame(45.0, $result['AngularRates']['Pitch']);
        self::assertSame(45.0, $result['AngularRates']['Yaw']);
        self::assertSame(90.0, $result['AngularRates']['Roll']);
    }

    public function test_angular_rates_boosted_applies_multipliers(): void
    {
        $result = $this->calculateDefault();

        // Pitch 45 * 1.5 = 67.5, Yaw 45 * 1.5 = 67.5, Roll 90 * 2.0 = 180.0
        self::assertEquals(67.5, $result['AngularRatesBoosted']['Pitch']);
        self::assertEquals(67.5, $result['AngularRatesBoosted']['Yaw']);
        self::assertEquals(180.0, $result['AngularRatesBoosted']['Roll']);
    }

    public function test_angular_rates_boosted_null_stays_null(): void
    {
        $ifcs = $this->makeIfcs(pitch: null, yaw: null, roll: null);
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, $this->makeThrust());

        self::assertNull($result['AngularRatesBoosted']['Pitch']);
        self::assertNull($result['AngularRatesBoosted']['Yaw']);
        self::assertNull($result['AngularRatesBoosted']['Roll']);
    }

    // ------------------------------------------------------------------ //
    //  Directional acceleration                                           //
    // ------------------------------------------------------------------ //

    public function test_acceleration_main_equals_thrust_over_mass(): void
    {
        // 500,000 N / 50,000 kg = 10.0 m/s²
        $result = $this->calculateDefault();

        self::assertEquals(10.0, $result['Acceleration']['Raw']['Main']);
    }

    public function test_acceleration_retro_equals_retro_thrust_over_mass(): void
    {
        // 250,000 N / 50,000 kg = 5.0 m/s²
        $result = $this->calculateDefault();

        self::assertEquals(5.0, $result['Acceleration']['Raw']['Retro']);
    }

    public function test_acceleration_maneuver_uses_maneuvering_key(): void
    {
        // 100,000 N / 50,000 kg = 2.0 m/s²
        $result = $this->calculateDefault();

        self::assertEquals(2.0, $result['Acceleration']['Raw']['Maneuver']);
    }

    public function test_acceleration_vtol_when_present(): void
    {
        $thrust = $this->makeThrust(vtol: 75000);
        $result = $this->calculator->calculateCharacteristics($this->makeIfcs(), 50000, $thrust);

        // 75,000 / 50,000 = 1.5
        self::assertEquals(1.5, $result['Acceleration']['Raw']['Vtol']);
    }

    public function test_acceleration_zero_when_mass_is_zero(): void
    {
        $result = $this->calculator->calculateCharacteristics($this->makeIfcs(), 0, $this->makeThrust());

        self::assertSame(0, $result['Acceleration']['Raw']['Main']);
        self::assertSame(0, $result['Acceleration']['Raw']['Retro']);
        self::assertSame(0, $result['Acceleration']['Raw']['Vtol']);
        self::assertSame(0, $result['Acceleration']['Raw']['Maneuver']);
    }

    public function test_acceleration_zero_when_thrust_is_missing(): void
    {
        $result = $this->calculator->calculateCharacteristics($this->makeIfcs(), 50000, []);

        // round(0/50000) returns 0.0 (float)
        self::assertSame(0.0, $result['Acceleration']['Raw']['Main']);
        self::assertSame(0.0, $result['Acceleration']['Raw']['Retro']);
    }

    // ------------------------------------------------------------------ //
    //  Afterburner resolution                                             //
    // ------------------------------------------------------------------ //

    public function test_afterburner_legacy_preferred_when_present(): void
    {
        $ifcs = $this->makeIfcs(afterburner: [
            'AngularAccelerationMultiplier' => ['Pitch' => 2.0, 'Yaw' => 2.0, 'Roll' => 3.0],
        ]);
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, $this->makeThrust());

        // AngularRatesBoosted should reflect the multiplier
        self::assertEquals(90.0, $result['AngularRatesBoosted']['Pitch']); // 45 * 2.0
    }

    public function test_afterburner_empty_when_neither_present(): void
    {
        // Explicitly set Afterburner to null in the IFCs data to test no-afterburner path
        $ifcs = [
            'Ifcs' => [
                'ScmSpeed' => 220,
                'MaxSpeed' => 1100,
                'BoostSpeedForward' => 600,
                'BoostSpeedBackward' => 200,
                'Pitch' => 45.0,
                'Yaw' => 45.0,
                'Roll' => 90.0,
                'Afterburner' => null,
            ],
        ];
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, $this->makeThrust());

        // With no afterburner (null), resolveAfterburner returns [null, [], null, null]
        // Multipliers default to 1.0, so boosted = raw
        self::assertEquals(45.0, $result['AngularRatesBoosted']['Pitch']); // 45 * 1.0
    }

    // ------------------------------------------------------------------ //
    //  Boosted acceleration                                               //
    // ------------------------------------------------------------------ //

    public function test_boosted_acceleration_applies_linear_multipliers(): void
    {
        $result = $this->calculateDefault();

        // Raw Main = 10.0, multiplier positive.y = 2.0 -> Boosted Main = 20.0
        self::assertEquals(20.0, $result['Acceleration']['Boosted']['Main']);
        // Raw Retro = 5.0, multiplier negative.y = 1.8 -> Boosted Retro = 9.0
        self::assertEquals(9.0, $result['Acceleration']['Boosted']['Retro']);
    }

    // ------------------------------------------------------------------ //
    //  Timing                                                             //
    // ------------------------------------------------------------------ //

    public function test_timing_zero_to_scm(): void
    {
        $result = $this->calculateDefault();

        // 220 / 10.0 = 22.0
        self::assertEquals(22.0, $result['Timing']['ZeroToScm']);
    }

    public function test_timing_zero_to_max(): void
    {
        $result = $this->calculateDefault();

        // 1100 / 10.0 = 110.0
        self::assertEquals(110.0, $result['Timing']['ZeroToMax']);
    }

    public function test_timing_scm_to_zero(): void
    {
        $result = $this->calculateDefault();

        // 220 / 5.0 = 44.0
        self::assertEquals(44.0, $result['Timing']['ScmToZero']);
    }

    public function test_timing_max_to_zero(): void
    {
        $result = $this->calculateDefault();

        // 1100 / 5.0 = 220.0
        self::assertEquals(220.0, $result['Timing']['MaxToZero']);
    }

    public function test_timing_zero_to_boost_forward(): void
    {
        $result = $this->calculateDefault();

        // 600 / 20.0 (boosted main) = 30.0
        self::assertEquals(30.0, $result['Timing']['ZeroToBoostForward']);
    }

    public function test_timing_null_when_accel_zero(): void
    {
        $ifcs = $this->makeIfcs();
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, ['Main' => 0, 'Retro' => 0, 'Vtol' => 0, 'Maneuvering' => 0]);

        self::assertNull($result['Timing']['ZeroToScm']);
        self::assertNull($result['Timing']['ZeroToMax']);
    }

    public function test_timing_null_when_boost_speed_null(): void
    {
        $ifcs = $this->makeIfcs(boostForward: null, boostBackward: null);
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, $this->makeThrust());

        self::assertNull($result['Timing']['ZeroToBoostForward']);
        self::assertNull($result['Timing']['ZeroToBoostBackward']);
    }

    // ------------------------------------------------------------------ //
    //  Agility extraction                                                 //
    // ------------------------------------------------------------------ //

    public function test_extract_agility_rounds_to_3_decimals(): void
    {
        $flightChar = $this->calculateDefault();
        $agility = $this->calculator->extractAgilityData($flightChar);

        self::assertEquals(45.000, $agility['pitch']);
        self::assertEquals(10.000, $agility['acceleration']['main']);
    }

    public function test_extract_agility_handles_null_values(): void
    {
        $ifcs = $this->makeIfcs(pitch: null, yaw: null, roll: null);
        $result = $this->calculator->calculateCharacteristics($ifcs, 50000, $this->makeThrust());
        $agility = $this->calculator->extractAgilityData($result);

        self::assertNull($agility['pitch']);
        self::assertNull($agility['yaw']);
        self::assertNull($agility['roll']);
        self::assertNull($agility['pitch_boosted']);
    }

    // ------------------------------------------------------------------ //
    //  Full integration                                                   //
    // ------------------------------------------------------------------ //

    public function test_full_calculation_with_known_values(): void
    {
        $result = $this->calculateDefault();

        self::assertNotNull($result);
        // Speeds
        self::assertSame(220.0, $result['Speeds']['Scm']);
        self::assertSame(1100.0, $result['Speeds']['Max']);
        // Raw acceleration: thrust/mass
        self::assertEquals(10.0, $result['Acceleration']['Raw']['Main']);
        self::assertEquals(5.0, $result['Acceleration']['Raw']['Retro']);
        self::assertEquals(2.0, $result['Acceleration']['Raw']['Maneuver']);
        // Boosted: raw * multiplier
        self::assertEquals(20.0, $result['Acceleration']['Boosted']['Main']);
        self::assertEquals(9.0, $result['Acceleration']['Boosted']['Retro']);
        // Angular boosted
        self::assertEquals(67.5, $result['AngularRatesBoosted']['Pitch']);
        self::assertEquals(180.0, $result['AngularRatesBoosted']['Roll']);
        // Timing
        self::assertEquals(22.0, $result['Timing']['ZeroToScm']);
        self::assertEquals(110.0, $result['Timing']['ZeroToMax']);
    }

    // ------------------------------------------------------------------ //
    //  calculate (VehicleDataContext)                                     //
    // ------------------------------------------------------------------ //

    public function test_calculate_returns_empty_for_non_spaceship(): void
    {
        $context = $this->makeContext(isSpaceship: false, isGravlev: false);

        $result = $this->calculator->calculate($context);

        self::assertSame([], $result);
    }

    public function test_calculate_returns_flight_characteristics_and_agility(): void
    {
        $context = $this->makeContext(
            isSpaceship: true,
            ifcsLoadoutEntry: $this->makeIfcs(),
            mass: 50000,
            thrustCapacity: $this->makeThrust(),
        );

        $result = $this->calculator->calculate($context);

        self::assertArrayHasKey('FlightCharacteristics', $result);
        self::assertArrayHasKey('Agility', $result);
        self::assertSame(220.0, $result['FlightCharacteristics']['Speeds']['Scm']);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    private function calculateDefault(): array
    {
        return $this->calculator->calculateCharacteristics(
            $this->makeIfcs(),
            50000,
            $this->makeThrust(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeIfcs(
        float $scmSpeed = 220,
        float $maxSpeed = 1100,
        ?float $boostForward = 600,
        ?float $boostBackward = 200,
        ?float $pitch = 45.0,
        ?float $yaw = 45.0,
        ?float $roll = 90.0,
        ?array $afterburner = null,
    ): array {
        $defaultAfterburner = [
            'AngularAccelerationMultiplier' => ['Pitch' => 1.5, 'Yaw' => 1.5, 'Roll' => 2.0],
            'AccelerationMultiplierPositive' => ['y' => 2.0, 'z' => 1.5, 'x' => 1.0],
            'AccelerationMultiplierNegative' => ['y' => 1.8, 'z' => 1.2, 'x' => 0.8],
        ];

        return [
            'Ifcs' => array_filter([
                'ScmSpeed' => $scmSpeed,
                'MaxSpeed' => $maxSpeed,
                'BoostSpeedForward' => $boostForward,
                'BoostSpeedBackward' => $boostBackward,
                'Pitch' => $pitch,
                'Yaw' => $yaw,
                'Roll' => $roll,
                'Afterburner' => $afterburner ?? $defaultAfterburner,
            ], static fn ($v) => $v !== null),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function makeThrust(
        float $main = 500000,
        float $retro = 250000,
        float $vtol = 0,
        float $maneuvering = 100000,
    ): array {
        return [
            'Main' => $main,
            'Retro' => $retro,
            'Vtol' => $vtol,
            'Maneuvering' => $maneuvering,
        ];
    }

    private function makeContext(
        bool $isSpaceship = false,
        bool $isGravlev = false,
        ?array $ifcsLoadoutEntry = null,
        float $mass = 0.0,
        array $thrustCapacity = [],
    ): VehicleDataContext {
        $intermediateResults = [];
        if (! empty($thrustCapacity)) {
            $intermediateResults['Propulsion']['ThrustCapacity'] = $thrustCapacity;
        }

        return new VehicleDataContext(
            standardisedParts: [],
            portSummary: [],
            ifcsLoadoutEntry: $ifcsLoadoutEntry,
            mass: $mass,
            loadoutMass: 0.0,
            isVehicle: true,
            isGravlev: $isGravlev,
            isSpaceship: $isSpaceship,
            intermediateResults: $intermediateResults,
        );
    }
}
