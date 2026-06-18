<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Services\Vehicle\LoadoutPortIdentityAnnotator;
use Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Octfx\ScDataDumper\Services\Vehicle\SystemsBuilder
 */
final class SystemsBuilderFuelSummaryTest extends TestCase
{
    // ------------------------------------------------------------------ //
    //  Shared fixtures
    // ------------------------------------------------------------------ //

    /**
     * Build a full fuel/missile fixture:
     *   - 1 quantum fuel tank
     *   - 2 hydrogen fuel tanks
     *   - 2 fuel intakes
     *   - 1 missile rack with 2 nested missiles
     *   - 1 armor port (for the armor-summary-stays-null test)
     *   - 1 quantum drive with nested jump drive
     */
    private function buildFuelFixture(): array
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_quantum_fuel_tank',
                'Type' => 'QuantumFuelTank.UNDEFINED',
                'ClassName' => 'QFT_1',
                'UUID' => 'qft1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_hydrogen_fuel_tank_1',
                'Type' => 'FuelTank.UNDEFINED',
                'ClassName' => 'FT_1',
                'UUID' => 'ft1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_hydrogen_fuel_tank_2',
                'Type' => 'FuelTank.UNDEFINED',
                'ClassName' => 'FT_2',
                'UUID' => 'ft2',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_fuel_intake_1',
                'Type' => 'FuelIntake.UNDEFINED',
                'ClassName' => 'FI_1',
                'UUID' => 'fi1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_fuel_intake_2',
                'Type' => 'FuelIntake.UNDEFINED',
                'ClassName' => 'FI_2',
                'UUID' => 'fi2',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_missile_rack_1',
                'Type' => 'MissileLauncher.MissileRack',
                'ClassName' => 'MSLI_1',
                'UUID' => 'mr1',
                'Loadout' => [
                    [
                        'HardpointName' => 'missile_01',
                        'Type' => 'Missile.Guided',
                        'ClassName' => 'EMSD_1',
                        'UUID' => 'm1',
                        'Loadout' => [],
                    ],
                    [
                        'HardpointName' => 'missile_02',
                        'Type' => 'Missile.Guided',
                        'ClassName' => 'EMSD_2',
                        'UUID' => 'm2',
                        'Loadout' => [],
                    ],
                ],
            ],
            [
                'HardpointName' => 'hardpoint_armor',
                'Type' => 'Armor.UNDEFINED',
                'ClassName' => 'ARMR_1',
                'UUID' => 'a1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'hardpoint_quantum_drive',
                'Type' => 'QuantumDrive.UNDEFINED',
                'ClassName' => 'QDRV_1',
                'UUID' => 'qd1',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_Jump_Drive',
                        'Type' => 'JumpDrive.UNDEFINED',
                        'ClassName' => 'JD_1',
                        'UUID' => 'jd1',
                        'Loadout' => [],
                    ],
                ],
            ],
        ];

        return (new LoadoutPortIdentityAnnotator)->annotate($loadout);
    }

    /**
     * Build systems from the fuel fixture with calculated data.
     */
    private function buildSystemsFromFixture(): array
    {
        $annotated = $this->buildFuelFixture();
        $builder = new SystemsBuilder;

        $calculatedData = [
            'QuantumTravel' => ['FuelCapacity' => 10600],
            'Propulsion' => ['FuelCapacity' => 360000, 'FuelIntakeRate' => 800],
        ];

        return $builder->build($annotated, $calculatedData, []);
    }

    // ------------------------------------------------------------------ //
    //  Quantum fuel tanks
    // ------------------------------------------------------------------ //

    public function test_quantum_fuel_tanks_summary_has_capacity(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotNull($systems['QuantumFuelTanks']['Summary']);
        $this->assertSame(10600.0, $systems['QuantumFuelTanks']['Summary']['Capacity']);
    }

    public function test_quantum_fuel_tanks_summary_null_when_no_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_quantum_fuel_tank',
                'Type' => 'QuantumFuelTank.UNDEFINED',
                'ClassName' => 'QFT_1',
                'UUID' => 'qft1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, [], []);

        $this->assertNull($systems['QuantumFuelTanks']['Summary']);
    }

    public function test_quantum_fuel_tanks_summary_null_when_no_fuel_capacity_in_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_quantum_fuel_tank',
                'Type' => 'QuantumFuelTank.UNDEFINED',
                'ClassName' => 'QFT_1',
                'UUID' => 'qft1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        // QuantumTravel exists but has no FuelCapacity
        $systems = $builder->build($annotated, ['QuantumTravel' => ['Speed' => 100]], []);

        $this->assertNull($systems['QuantumFuelTanks']['Summary']);
    }

    public function test_quantum_fuel_tanks_ports_still_populated(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotEmpty($systems['QuantumFuelTanks']['Ports']);
        $this->assertSame('loadout.0', $systems['QuantumFuelTanks']['Ports'][0]['PortId']);
    }

    // ------------------------------------------------------------------ //
    //  Hydrogen fuel tanks
    // ------------------------------------------------------------------ //

    public function test_hydrogen_fuel_tanks_summary_has_capacity(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotNull($systems['HydrogenFuelTanks']['Summary']);
        $this->assertSame(360000.0, $systems['HydrogenFuelTanks']['Summary']['Capacity']);
    }

    public function test_hydrogen_fuel_tanks_summary_null_when_no_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_fuel_tank',
                'Type' => 'FuelTank.UNDEFINED',
                'ClassName' => 'FT_1',
                'UUID' => 'ft1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, [], []);

        $this->assertNull($systems['HydrogenFuelTanks']['Summary']);
    }

    public function test_hydrogen_fuel_tanks_summary_null_when_no_capacity_in_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_fuel_tank',
                'Type' => 'FuelTank.UNDEFINED',
                'ClassName' => 'FT_1',
                'UUID' => 'ft1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, ['Propulsion' => ['FuelIntakeRate' => 100]], []);

        $this->assertNull($systems['HydrogenFuelTanks']['Summary']);
    }

    // ------------------------------------------------------------------ //
    //  Fuel intakes
    // ------------------------------------------------------------------ //

    public function test_fuel_intakes_summary_has_push_rate(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotNull($systems['FuelIntakes']['Summary']);
        $this->assertSame(800.0, $systems['FuelIntakes']['Summary']['FuelPushRate']);
    }

    public function test_fuel_intakes_summary_null_when_no_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_fuel_intake',
                'Type' => 'FuelIntake.UNDEFINED',
                'ClassName' => 'FI_1',
                'UUID' => 'fi1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, [], []);

        $this->assertNull($systems['FuelIntakes']['Summary']);
    }

    public function test_fuel_intakes_summary_null_when_no_push_rate_in_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_fuel_intake',
                'Type' => 'FuelIntake.UNDEFINED',
                'ClassName' => 'FI_1',
                'UUID' => 'fi1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, ['Propulsion' => ['FuelCapacity' => 1000]], []);

        $this->assertNull($systems['FuelIntakes']['Summary']);
    }

    // ------------------------------------------------------------------ //
    //  Jump drives -- intentionally stays null
    // ------------------------------------------------------------------ //

    public function test_jump_drives_summary_stays_null(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNull($systems['JumpDrives']['Summary']);
        $this->assertNotEmpty($systems['JumpDrives']['Ports']);
    }

    // ------------------------------------------------------------------ //
    //  Missile racks
    // ------------------------------------------------------------------ //

    public function test_missile_racks_summary_has_count(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotNull($systems['MissileRacks']['Summary']);
        $this->assertSame(1, $systems['MissileRacks']['Summary']['Count']);
    }

    public function test_missile_racks_summary_null_when_no_racks(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_shield',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, [], []);

        $this->assertNull($systems['MissileRacks']['Summary']);
    }

    // ------------------------------------------------------------------ //
    //  Missiles
    // ------------------------------------------------------------------ //

    public function test_missiles_summary_has_count(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotNull($systems['Missiles']['Summary']);
        $this->assertSame(2, $systems['Missiles']['Summary']['Count']);
    }

    public function test_missiles_summary_null_when_no_missiles(): void
    {
        $loadout = [
            [
                'HardpointName' => 'hardpoint_shield',
                'Type' => 'Shield.UNDEFINED',
                'ClassName' => 'SHLD_1',
                'UUID' => 's1',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, [], []);

        $this->assertNull($systems['Missiles']['Summary']);
    }

    // ------------------------------------------------------------------ //
    //  Armors -- intentionally stays null for now
    // ------------------------------------------------------------------ //

    public function test_armors_summary_stays_null_for_now(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNull($systems['Armors']['Summary']);
        $this->assertNotEmpty($systems['Armors']['Ports']);
    }

    // ------------------------------------------------------------------ //
    //  Ports still populated alongside summaries
    // ------------------------------------------------------------------ //

    public function test_missile_racks_and_missiles_ports_still_populated(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotEmpty($systems['MissileRacks']['Ports']);
        $rackPortId = $systems['MissileRacks']['Ports'][0]['PortId'];
        $this->assertSame('loadout.5', $rackPortId);

        $this->assertNotEmpty($systems['Missiles']['Ports']);
        $this->assertCount(2, $systems['Missiles']['Ports']);
    }

    public function test_fuel_intakes_ports_still_populated(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotEmpty($systems['FuelIntakes']['Ports']);
        $this->assertCount(2, $systems['FuelIntakes']['Ports']);
    }

    public function test_hydrogen_fuel_tanks_ports_still_populated(): void
    {
        $systems = $this->buildSystemsFromFixture();

        $this->assertNotEmpty($systems['HydrogenFuelTanks']['Ports']);
        $this->assertCount(2, $systems['HydrogenFuelTanks']['Ports']);
    }

    // ------------------------------------------------------------------ //
    //  Quantum fuel tanks capacity from calculated data
    // ------------------------------------------------------------------ //

    public function test_quantum_fuel_tanks_summary_from_calculated_data(): void
    {
        $loadout = [
            [
                'HardpointName' => 'qft_1',
                'Type' => 'QuantumFuelTank.UNDEFINED',
                'ClassName' => 'QFT_1',
                'UUID' => 'qft1',
                'Loadout' => [],
            ],
            [
                'HardpointName' => 'qft_2',
                'Type' => 'QuantumFuelTank.UNDEFINED',
                'ClassName' => 'QFT_2',
                'UUID' => 'qft2',
                'Loadout' => [],
            ],
        ];
        $annotated = (new LoadoutPortIdentityAnnotator)->annotate($loadout);

        $builder = new SystemsBuilder;
        $systems = $builder->build($annotated, ['QuantumTravel' => ['FuelCapacity' => 10600]], []);

        $this->assertNotNull($systems['QuantumFuelTanks']['Summary']);
        $this->assertSame(10600.0, $systems['QuantumFuelTanks']['Summary']['Capacity']);
    }

    // ------------------------------------------------------------------ //
    //  No $legacyPortSummary dependency
    // ------------------------------------------------------------------ //

    public function test_summaries_work_without_legacy_port_summary(): void
    {
        $annotated = $this->buildFuelFixture();
        $builder = new SystemsBuilder;
        $calculatedData = [
            'QuantumTravel' => ['FuelCapacity' => 10600],
            'Propulsion' => ['FuelCapacity' => 360000, 'FuelIntakeRate' => 800],
        ];
        $systems = $builder->build($annotated, $calculatedData, []);

        $this->assertSame(10600.0, $systems['QuantumFuelTanks']['Summary']['Capacity']);
        $this->assertSame(360000.0, $systems['HydrogenFuelTanks']['Summary']['Capacity']);
        $this->assertSame(800.0, $systems['FuelIntakes']['Summary']['FuelPushRate']);
    }
}
