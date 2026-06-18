<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder;
use Octfx\ScDataDumper\Services\Vehicle\VehicleSystemKeys;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder
 */
final class SystemsBuilderSummaryTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Shared fixtures
    // ------------------------------------------------------------------ //

    /**
     * Annotated loadout with ports matching every system that gets a calculator-backed summary.
     *
     *   loadout.0  shield_1                (Shield.UNDEFINED)
     *   loadout.1  hardpoint_quantum_drive (QuantumDrive.UNDEFINED)
     *   loadout.2  cooler_1                (Cooler.UNDEFINED)
     *   loadout.3  powerplant_1            (PowerPlant.UNDEFINED)
     *   loadout.4  hardpoint_class_2       (WeaponGun.Gun)
     */
    private function annotatedSummaryFixture(): array
    {
        $raw = [
            [
                'HardpointName' => 'shield_1',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_1',
                'UUID' => 'qd1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'cooler_1',
                'Type' => 'Cooler.UNDEFINED',
                'ClassName' => 'CLLR_1',
                'UUID' => 'c1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'powerplant_1',
                'Type' => 'PowerPlant.UNDEFINED',
                'ClassName' => 'POWR_1',
                'UUID' => 'pp1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_class_2',
                'Type' => 'WeaponGun.Gun',
                'ClassName' => 'KLWE_LaserRepeater_S3',
                'UUID' => 'w1',
                'Loadout' => [],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($raw);
    }

    /**
     * Synthetic calculated data matching the orchestrator output format.
     */
    private function fullCalculatedData(): array
    {
        return [
            'shields_total' => [
                'hp' => 10000,
                'regen' => 500,
                'face_type' => 'FrontBack',
            ],
            'QuantumTravel' => [
                'Speed' => 319000000,
                'SpoolTime' => 7,
                'FuelCapacity' => 10600,
                'Range' => 50000000,
            ],
            'FlightCharacteristics' => [
                'ScmSpeed' => 200,
                'MaxSpeed' => 1000,
                'Ifcs' => [
                    'NoFuelParams' => [
                        'ScmSpeed' => 200,
                    ],
                ],
            ],
            'Propulsion' => [
                'FuelCapacity' => 360000,
                'FuelIntakeRate' => 800,
                'ThrustCapacity' => [
                    'Main' => 5000000,
                ],
            ],
            'cooling' => [
                'CoolingCapacity' => 50000,
            ],
            'power' => [
                'PowerCapacity' => 1000000,
                'PowerDraw' => 500000,
            ],
            'power_pools' => [
                ['PoolName' => 'Main', 'Capacity' => 500000],
            ],
            'Weaponry' => [
                'TotalDps' => 2000,
                'SustainedDps' => 1500,
                'Turrets' => [
                    ['HardpointName' => 'turret_1', 'DpsTotal' => 500],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------ //
    //  Shields summary
    // ------------------------------------------------------------------ //

    public function test_shields_summary_populated_from_shields_total(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $expected = $this->fullCalculatedData()['shields_total'];
        self::assertSame($expected, $result['Shields']['Summary'],
            'Shields.Summary must be populated from calculatedData[shields_total]');
    }

    public function test_shields_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['Shields']['Summary'],
            'Shields.Summary must be null when no calculatedData is provided');
    }

    // ------------------------------------------------------------------ //
    //  QuantumDrives summary
    // ------------------------------------------------------------------ //

    public function test_quantum_drives_summary_populated(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $expected = $this->fullCalculatedData()['QuantumTravel'];
        self::assertSame($expected, $result['QuantumDrives']['Summary'],
            'QuantumDrives.Summary must be populated from calculatedData[QuantumTravel]');
    }

    public function test_quantum_drives_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['QuantumDrives']['Summary'],
            'QuantumDrives.Summary must be null when no calculatedData is provided');
    }

    // ------------------------------------------------------------------ //
    //  FlightControllers summary
    // ------------------------------------------------------------------ //

    public function test_flight_controllers_summary_populated(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $expected = $this->fullCalculatedData()['FlightCharacteristics'];
        self::assertSame($expected, $result['FlightControllers']['Summary'],
            'FlightControllers.Summary must be populated from calculatedData[FlightCharacteristics]');
    }

    public function test_flight_controllers_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['FlightControllers']['Summary'],
            'FlightControllers.Summary must be null when no calculatedData is provided');
    }

    // ------------------------------------------------------------------ //
    //  Thrusters summary
    // ------------------------------------------------------------------ //

    public function test_thrusters_summary_populated(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $expected = $this->fullCalculatedData()['Propulsion'];
        self::assertSame($expected, $result['Thrusters']['Summary'],
            'Thrusters.Summary must be populated from calculatedData[Propulsion]');
    }

    public function test_thrusters_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['Thrusters']['Summary'],
            'Thrusters.Summary must be null when no calculatedData is provided');
    }

    // ------------------------------------------------------------------ //
    //  Coolers summary
    // ------------------------------------------------------------------ //

    public function test_coolers_summary_populated(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $expected = $this->fullCalculatedData()['cooling'];
        self::assertSame($expected, $result['Coolers']['Summary'],
            'Coolers.Summary must be populated from calculatedData[cooling]');
    }

    public function test_coolers_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['Coolers']['Summary'],
            'Coolers.Summary must be null when no calculatedData is provided');
    }

    // ------------------------------------------------------------------ //
    //  PowerPlants summary
    // ------------------------------------------------------------------ //

    public function test_power_plants_summary_populated_from_power_and_power_pools(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $summary = $result['PowerPlants']['Summary'];
        self::assertNotNull($summary, 'PowerPlants.Summary must not be null when power data is present');
        self::assertSame(1000000, $summary['PowerCapacity'],
            'PowerPlants.Summary must contain power data');
        self::assertSame(500000, $summary['PowerDraw']);
        self::assertArrayHasKey('PowerPools', $summary,
            'PowerPlants.Summary must contain merged PowerPools');
        self::assertSame(
            $this->fullCalculatedData()['power_pools'],
            $summary['PowerPools'],
            'PowerPlants.Summary.PowerPools must match calculatedData[power_pools]',
        );
    }

    public function test_power_plants_summary_only_power_no_pools(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            ['power' => ['PowerCapacity' => 500000]],
        );

        $summary = $result['PowerPlants']['Summary'];
        self::assertNotNull($summary);
        self::assertSame(500000, $summary['PowerCapacity']);
        self::assertArrayNotHasKey('PowerPools', $summary,
            'PowerPlants.Summary must not have PowerPools when power_pools is absent');
    }

    public function test_power_plants_summary_only_pools_no_power(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            ['power_pools' => [['PoolName' => 'Main']]],
        );

        $summary = $result['PowerPlants']['Summary'];
        self::assertNotNull($summary);
        self::assertArrayHasKey('PowerPools', $summary);
        self::assertArrayNotHasKey('PowerCapacity', $summary,
            'PowerPlants.Summary must not have PowerCapacity when power is absent');
    }

    public function test_power_plants_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['PowerPlants']['Summary'],
            'PowerPlants.Summary must be null when no power data is provided');
    }

    // ------------------------------------------------------------------ //
    //  Weapons summary (Weaponry without Turrets)
    // ------------------------------------------------------------------ //

    public function test_weapons_summary_populated_without_turrets(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $summary = $result['Weapons']['Summary'];
        self::assertNotNull($summary, 'Weapons.Summary must not be null when Weaponry data is present');
        self::assertArrayHasKey('TotalDps', $summary,
            'Weapons.Summary must contain TotalDps from Weaponry');
        self::assertSame(2000, $summary['TotalDps']);
        self::assertArrayHasKey('SustainedDps', $summary);
        self::assertArrayNotHasKey('Turrets', $summary,
            'Weapons.Summary must NOT contain the Turrets sub-key from Weaponry');
    }

    public function test_weapons_summary_null_when_no_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build($this->annotatedSummaryFixture(), []);

        self::assertNull($result['Weapons']['Summary'],
            'Weapons.Summary must be null when no Weaponry data is provided');
    }

    public function test_weapons_summary_null_when_only_turrets_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            ['Weaponry' => ['Turrets' => [['HardpointName' => 'turret_1']]]],
        );

        self::assertNull($result['Weapons']['Summary'],
            'Weapons.Summary must be null when Weaponry only contains Turrets (which is stripped)');
    }

    // ------------------------------------------------------------------ //
    //  Armors summary -- explicitly not populated yet
    // ------------------------------------------------------------------ //

    public function test_armors_summary_stays_null_for_now(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNull($result['Armors']['Summary'],
            'Armors.Summary must remain null -- armor summary comes from legacyPortSummary or installed item data, not calculatedData');
    }

    // ------------------------------------------------------------------ //
    //  Systems without calculator-backed summaries stay null
    // ------------------------------------------------------------------ //

    public function test_non_calculator_systems_stay_null_with_full_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        $nonCalculatorSystems = [
            'JumpDrives',
            'QuantumFuelTanks',
            'HydrogenFuelTanks',
            'FuelIntakes',
            'Armors',
            'WeaponMounts',
            'MissileRacks',
            'Missiles',
            'MannedTurrets',
            'RemoteTurrets',
            'PdcTurrets',
            'Radars',
            'LifeSupport',
            'CounterMeasures',
            'Paints',
            'Mining',
            'Salvage',
            'TractorBeams',
            'Emps',
            'Qeds',
            'Modules',
            'DockedVehicles',
            'AiModules',
            'CargoGrids',
            'WeaponLockers',
        ];

        foreach ($nonCalculatorSystems as $key) {
            self::assertNull($result[$key]['Summary'],
                "System '{$key}' must have Summary === null -- no calculator-backed summary mapped yet");
        }
    }

    // ------------------------------------------------------------------ //
    //  Ports still populated alongside summaries
    // ------------------------------------------------------------------ //

    public function test_shields_ports_still_populated_with_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNotNull($result['Shields']['Summary']);
        self::assertCount(1, $result['Shields']['Ports'],
            'Shields.Ports must still be populated when Summary is present');
    }

    public function test_quantum_drives_ports_still_populated_with_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNotNull($result['QuantumDrives']['Summary']);
        self::assertCount(1, $result['QuantumDrives']['Ports'],
            'QuantumDrives.Ports must still be populated when Summary is present');
    }

    public function test_coolers_ports_still_populated_with_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNotNull($result['Coolers']['Summary']);
        self::assertCount(1, $result['Coolers']['Ports'],
            'Coolers.Ports must still be populated when Summary is present');
    }

    public function test_power_plants_ports_still_populated_with_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNotNull($result['PowerPlants']['Summary']);
        self::assertCount(1, $result['PowerPlants']['Ports'],
            'PowerPlants.Ports must still be populated when Summary is present');
    }

    public function test_weapons_ports_still_populated_with_summary(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        self::assertNotNull($result['Weapons']['Summary']);
        self::assertCount(1, $result['Weapons']['Ports'],
            'Weapons.Ports must still be populated when Summary is present');
    }

    // ------------------------------------------------------------------ //
    //  All system keys still present
    // ------------------------------------------------------------------ //

    public function test_all_system_keys_present_with_calculated_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey($key, $result,
                "All 32 system keys must be present even with calculated data; missing '{$key}'");
        }
    }

    public function test_every_system_has_summary_and_ports_keys_with_data(): void
    {
        $builder = new SystemsBuilder;
        $result = $builder->build(
            $this->annotatedSummaryFixture(),
            $this->fullCalculatedData(),
        );

        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            self::assertArrayHasKey('Summary', $result[$key],
                "System '{$key}' must have a 'Summary' key");
            self::assertArrayHasKey('Ports', $result[$key],
                "System '{$key}' must have a 'Ports' key");
        }
    }
}
