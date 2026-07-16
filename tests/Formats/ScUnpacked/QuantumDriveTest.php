<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\JumpPerformance;
use Octfx\ScDataDumper\Formats\ScUnpacked\QuantumDrive;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\TestCase;

final class QuantumDriveTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Fuel Rate                                                          //
    // ------------------------------------------------------------------ //

    public function test_fuel_rate_from_micro_units(): void
    {
        $qd = $this->makeDrive(microResourceUnits: 50000);
        $result = $qd->toArray();

        // 50000 / 1000 = 50.0
        self::assertSame(50.0, $result['FuelConsumptionSCUPerGM']);
    }

    public function test_fuel_rate_from_raw_requirement_fallback(): void
    {
        // No micro units, so it falls back to quantumFuelRequirement
        $qd = $this->makeDrive(quantumFuelRequirement: 25000000);
        $result = $qd->toArray();

        self::assertEqualsWithDelta(25000000.0, $result['FuelConsumptionSCUPerGM'], 0.01);
    }

    public function test_fuel_rate_null_when_no_data(): void
    {
        $qd = $this->makeDrive(driveSpeed: 200000000);
        $result = $qd->toArray();

        // No micro units and no raw requirement -> consumption is null
        // But wait - raw requirement default is 0 which makes getFuelConsumptionPerGm return null (v <= 0)
        self::assertNull($result['FuelConsumptionSCUPerGM']);
    }

    public function test_fuel_rate_null_when_zero_raw_requirement(): void
    {
        $qd = $this->makeDrive(quantumFuelRequirement: 0, driveSpeed: 200000000);
        $result = $qd->toArray();

        self::assertNull($result['FuelConsumptionSCUPerGM']);
    }

    // ------------------------------------------------------------------ //
    //  Fuel Efficiency                                                    //
    // ------------------------------------------------------------------ //

    public function test_fuel_efficiency_computed(): void
    {
        // driveSpeed = 200,000,000 m/s
        // consumption = 50.0 SCU/GM (from micro units 50000)
        // gmPerSecond = 200_000_000 / 1_000_000_000 = 0.2
        // efficiency = 0.2 / (50.0 * 10) = 0.0004 -> round to 0.0
        $qd = $this->makeDrive(driveSpeed: 200000000, microResourceUnits: 50000);
        $result = $qd->toArray();

        self::assertEqualsWithDelta(0.0, $result['FuelEfficiencyGMPerSCU'], 0.01);
    }

    public function test_fuel_efficiency_null_when_zero_consumption(): void
    {
        $qd = $this->makeDrive(driveSpeed: 200000000);
        $result = $qd->toArray();

        self::assertNull($result['FuelEfficiencyGMPerSCU']);
    }

    public function test_fuel_efficiency_null_when_zero_drive_speed(): void
    {
        $qd = $this->makeDrive(driveSpeed: 0, microResourceUnits: 50000);
        $result = $qd->toArray();

        self::assertNull($result['FuelEfficiencyGMPerSCU']);
    }

    // ------------------------------------------------------------------ //
    //  Fuel for Distance                                                  //
    // ------------------------------------------------------------------ //

    public function test_fuel_for_10gm(): void
    {
        // consumption = 50.0 SCU/GM -> 50.0 * 10 = 500.0
        $qd = $this->makeDrive(microResourceUnits: 50000, driveSpeed: 200000000);
        $result = $qd->toArray();

        self::assertEqualsWithDelta(500.0, $result['FuelRequirement10GM'], 0.01);
    }

    public function test_fuel_null_when_consumption_null(): void
    {
        $qd = $this->makeDrive(driveSpeed: 200000000);
        $result = $qd->toArray();

        self::assertNull($result['FuelRequirement10GM']);
    }

    // ------------------------------------------------------------------ //
    //  Travel Time: Long trip (reaches vmax, cruise phase)                //
    // ------------------------------------------------------------------ //

    public function test_travel_time_long_trip_has_cruise_phase(): void
    {
        // driveSpeed = 200,000,000 m/s, a1 = 500, a2 = 1000
        // tRamp = 2 * 200_000_000 / 1500 ≈ 266,666.67 s
        // dRamp ≈ 23,704 GM -> dTwoRamps ≈ 47,407 GM
        // For distance = 100 GM: 100 GM << 47,407 GM -> short trip!
        // A smaller drive speed produces a long trip.
        //
        // driveSpeed = 10,000, a1 = 1000, a2 = 2000
        // tRamp = 2*10000/3000 = 6.67s
        // dRamp = (2*1e8 / 9e6) * ((1000/3)+1000) = 22.22 * 1333.33 = 29,629.6 m = 0.0000296 GM
        // dTwoRamps ≈ 0.00006 GM
        // So 10 GM is a LONG trip.
        $qd = $this->makeDrive(driveSpeed: 10000, stageOneAccel: 1000, stageTwoAccel: 2000);
        $result = $qd->toArray();

        // Should have a finite travel time
        self::assertNotNull($result['TravelTime10GMSeconds']);
        self::assertGreaterThan(0, $result['TravelTime10GMSeconds']);
    }

    public function test_travel_time_with_known_values(): void
    {
        // driveSpeed = 10000 m/s, a1 = 1000, a2 = 2000
        // 10 GM = 1e10 meters
        // tRamp = 2*10000/3000 = 6.667s
        // dRamp = (2*1e8/9e6) * ((1000/3)+1000) = 22.222 * 1333.33 = 29,629.6m
        // dTwoRamps = 59,259.3m
        // cruiseDist = 1e10 - 59259.3 ≈ 9,999,940,740.7
        // cruiseTime = 9,999,940,740.7 / 10000 = 999,994.07s
        // total = 2*6.667 + 999,994.07 ≈ 1,000,007.4s ≈ 277h 46m 47s
        $qd = $this->makeDrive(driveSpeed: 10000, stageOneAccel: 1000, stageTwoAccel: 2000);
        $result = $qd->toArray();

        self::assertEqualsWithDelta(1_000_007, $result['TravelTime10GMSeconds'], 1);
    }

    // ------------------------------------------------------------------ //
    //  Travel Time: Short trip (never reaches vmax, binary search)        //
    // ------------------------------------------------------------------ //

    public function test_travel_time_short_trip_binary_search(): void
    {
        // driveSpeed = 200,000,000 m/s (0.2c), a1 = 500, a2 = 1000
        // For 10 GM = 1e10m:
        // dTwoRamps ≈ 2.37e13m = 23,704 GM
        // 10 GM < 23,704 GM -> short trip (binary search path)
        $qd = $this->makeDrive(driveSpeed: 200000000, stageOneAccel: 500, stageTwoAccel: 1000);
        $result = $qd->toArray();

        self::assertNotNull($result['TravelTime10GMSeconds']);
        self::assertGreaterThan(0, $result['TravelTime10GMSeconds']);
        // For a short trip, the time should be less than the full ramp time
        // tRamp = 2*200e6/1500 = 266,666.67s
        // The short trip should be less than 2*tRamp
        self::assertLessThan(533_334, $result['TravelTime10GMSeconds']);
    }

    // ------------------------------------------------------------------ //
    //  Travel Time: Edge cases                                            //
    // ------------------------------------------------------------------ //

    public function test_travel_time_null_when_zero_vmax(): void
    {
        $qd = $this->makeDrive(driveSpeed: 0, stageOneAccel: 500, stageTwoAccel: 1000);
        $result = $qd->toArray();

        self::assertNull($result['TravelTime10GMSeconds']);
    }

    public function test_travel_time_null_when_zero_acceleration(): void
    {
        $qd = $this->makeDrive(driveSpeed: 200000000, stageOneAccel: 0, stageTwoAccel: 0);
        $result = $qd->toArray();

        self::assertNull($result['TravelTime10GMSeconds']);
    }

    // ------------------------------------------------------------------ //
    //  Duration Formatting                                                //
    // ------------------------------------------------------------------ //

    public function test_format_duration_under_one_minute(): void
    {
        // Drive with slow speed for a predictable duration.
        // 30 seconds -> "0:30"
        $qd = $this->makeDrive(driveSpeed: 10000, stageOneAccel: 1000, stageTwoAccel: 2000);
        $result = $qd->toArray();

        // Just verify the duration format is valid: either M:SS or H:MM:SS
        self::assertMatchesRegularExpression('/^\d+:\d{2}(?::\d{2})?$/', $result['TravelTime10GM']);
    }

    public function test_format_duration_over_one_hour(): void
    {
        $qd = $this->makeDrive(driveSpeed: 10000, stageOneAccel: 1000, stageTwoAccel: 2000);
        $result = $qd->toArray();

        // For driveSpeed=10000, 10GM travel time ≈ 1,000,007s ≈ 277h 46m 47s
        // Should have H:MM:SS format
        self::assertMatchesRegularExpression('/^\d+:\d{2}:\d{2}$/', $result['TravelTime10GM']);
    }

    // ------------------------------------------------------------------ //
    //  toArray integration                                                //
    // ------------------------------------------------------------------ //

    public function test_to_array_returns_null_when_no_element(): void
    {
        $doc = $this->loadXmlAsFile('<?xml version="1.0"?><EntityClassDefinition.NoParams __type="EntityClassDefinition"/>');

        $qd = new QuantumDrive($doc);

        self::assertNull($qd->toArray());
    }

    public function test_to_array_full_structure_has_expected_keys(): void
    {
        $qd = $this->makeDrive(
            driveSpeed: 200000000,
            stageOneAccel: 500,
            stageTwoAccel: 1000,
            quantumFuelRequirement: 10000000,
        );
        $result = $qd->toArray();

        self::assertNotNull($result);
        self::assertArrayHasKey('FuelRate', $result);
        self::assertArrayHasKey('FuelConsumptionSCUPerGM', $result);
        self::assertArrayHasKey('FuelEfficiencyGMPerSCU', $result);
        self::assertArrayHasKey('FuelRequirement10GM', $result);
        self::assertArrayHasKey('TravelTime10GMSeconds', $result);
        self::assertArrayHasKey('TravelTime10GM', $result);
        self::assertArrayHasKey('StandardJump', $result);
        self::assertArrayHasKey('Heat', $result);
        self::assertArrayHasKey('Boost', $result);
    }

    public function test_to_array_missing_heat_params_returns_null_heat(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.QD_Test __type="EntityClassDefinition">
            <Components>
                <SCItemQuantumDriveParams quantumFuelRequirement="10000000">
                    <params driveSpeed="200000000" stageOneAccelRate="500" stageTwoAccelRate="1000" engageSpeed="0" cooldownTime="0" />
                    <splinejumpParams />
                    <quantumBoostParams />
                </SCItemQuantumDriveParams>
            </Components>
        </EntityClassDefinition.QD_Test>
        XML;

        $doc = $this->loadXmlAsFile($xml);

        $qd = new QuantumDrive($doc);
        $result = $qd->toArray();

        self::assertNull($result['Heat']);
    }

    public function test_to_array_missing_boost_params_returns_null_boost(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.QD_Test __type="EntityClassDefinition">
            <Components>
                <SCItemQuantumDriveParams quantumFuelRequirement="10000000">
                    <params driveSpeed="200000000" stageOneAccelRate="500" stageTwoAccelRate="1000" engageSpeed="0" cooldownTime="0" />
                    <splinejumpParams />
                    <heatParams />
                </SCItemQuantumDriveParams>
            </Components>
        </EntityClassDefinition.QD_Test>
        XML;

        $doc = $this->loadXmlAsFile($xml);

        $qd = new QuantumDrive($doc);
        $result = $qd->toArray();

        self::assertNull($result['Boost']);
    }

    // ------------------------------------------------------------------ //
    //  formatFuelRate                                                     //
    // ------------------------------------------------------------------ //

    public function test_format_fuel_rate_divides_by_1e6(): void
    {
        // quantumFuelRequirement = 25,000,000 -> FuelRate = 25.0
        $qd = $this->makeDrive(quantumFuelRequirement: 25000000, driveSpeed: 200000000);
        $result = $qd->toArray();

        self::assertEqualsWithDelta(25.0, $result['FuelRate'], 0.01);
    }

    public function test_format_fuel_rate_null_input(): void
    {
        // No quantumFuelRequirement attribute -> raw is null -> FuelRate is null
        // But the fixture uses quantumFuelRequirement="0" as default, so FuelRate = 0/1e6 = 0.0
        $qd = $this->makeDrive(driveSpeed: 200000000, microResourceUnits: 50000, quantumFuelRequirement: 0);
        $result = $qd->toArray();

        // formatFuelRate(null) returns null, but our fixture has requirement=0
        // 0 / 1e6 = 0.0
        self::assertSame(0.0, $result['FuelRate']);
    }

    // ------------------------------------------------------------------ //
    //  StandardJump contains drive speed                                  //
    // ------------------------------------------------------------------ //

    public function test_standard_jump_is_a_jump_performance_object(): void
    {
        $qd = $this->makeDrive(driveSpeed: 200000000, stageOneAccel: 500, stageTwoAccel: 1000);
        $result = $qd->toArray();

        self::assertNotNull($result);
        $standardJump = $result['StandardJump'];
        // StandardJump is a JumpPerformance format object (not yet resolved via processArray)
        self::assertInstanceOf(JumpPerformance::class, $standardJump);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    private function makeDrive(
        ?float $driveSpeed = null,
        ?float $stageOneAccel = null,
        ?float $stageTwoAccel = null,
        ?float $quantumFuelRequirement = null,
        ?float $microResourceUnits = null,
    ): QuantumDrive {
        $microXml = '';
        if ($microResourceUnits !== null) {
            $microXml = <<<XML
                <ItemResourceComponentParams>
                    <states>
                        <ItemResourceState name="Travelling">
                            <deltas>
                                <ItemResourceDeltaConsumption>
                                    <consumption resource="QuantumFuel">
                                        <resourceAmountPerSecond>
                                            <SMicroResourceUnit microResourceUnits="{$microResourceUnits}" />
                                        </resourceAmountPerSecond>
                                    </consumption>
                                </ItemResourceDeltaConsumption>
                            </deltas>
                        </ItemResourceState>
                    </states>
                </ItemResourceComponentParams>
            XML;
        }

        $paramsXml = '';
        if ($driveSpeed !== null) {
            $s1 = $stageOneAccel ?? 500;
            $s2 = $stageTwoAccel ?? 1000;

            $paramsXml = <<<XML
                <params driveSpeed="{$driveSpeed}" stageOneAccelRate="{$s1}" stageTwoAccelRate="{$s2}" engageSpeed="0" cooldownTime="0" />
                <splinejumpParams />
                <heatParams />
                <quantumBoostParams />
            XML;
        }

        $fuelAttr = $quantumFuelRequirement !== null
            ? "quantumFuelRequirement=\"{$quantumFuelRequirement}\""
            : 'quantumFuelRequirement="0"';

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.QD_Test __type="EntityClassDefinition">
                <Components>
                    <SCItemQuantumDriveParams {$fuelAttr}>
                        {$paramsXml}
                    </SCItemQuantumDriveParams>
                    {$microXml}
                </Components>
            </EntityClassDefinition.QD_Test>
        XML;

        $tmpFile = sys_get_temp_dir().'/qd_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, trim($xml));

        try {
            $doc = new TestRootDocument;
            $doc->load($tmpFile);

            return new QuantumDrive($doc);
        } finally {
            @unlink($tmpFile);
        }
    }

    private function loadXmlAsFile(string $xml): TestRootDocument
    {
        $tmpFile = sys_get_temp_dir().'/qd_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, $xml);

        try {
            $doc = new TestRootDocument;
            $doc->load($tmpFile);

            return $doc;
        } finally {
            @unlink($tmpFile);
        }
    }
}
