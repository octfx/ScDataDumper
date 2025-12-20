<?php

declare(strict_types=1);

namespace Tests\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\EquippedItemWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleMetricsAggregator;
use PHPUnit\Framework\TestCase;

final class VehicleMetricsAggregatorTest extends TestCase
{
    private function makeAggregator(bool $includeRequestedDefaults = true): VehicleMetricsAggregator
    {
        return new VehicleMetricsAggregator(new EquippedItemWalker, $includeRequestedDefaults);
    }

    public function test_aggregates_fuel_and_quantum_capacities(): void
    {
        $loadout = [
            [
                'portName' => 'fuel_tank',
                'Item' => [
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'FuelTank',
                            ],
                        ],
                        'ResourceContainer' => [
                            'capacity' => [
                                'SStandardCargoUnit' => [
                                    'standardCargoUnits' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'quantum_fuel_tank',
                'Item' => [
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'QuantumFuelTank',
                            ],
                        ],
                        'ResourceContainer' => [
                            'capacity' => [
                                'SStandardCargoUnit' => [
                                    'standardCargoUnits' => 1.5,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        self::assertSame(2000.0, $result['fuel_capacity']);
        self::assertSame(1500.0, $result['quantum_fuel_capacity']);
    }

    public function test_aggregates_fuel_intake_and_thruster_usage_per_type(): void
    {
        $loadout = [
            [
                'portName' => 'intake',
                'Item' => [
                    'Components' => [
                        'SCItemFuelIntakeParams' => [
                            'fuelPushRate' => 5,
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'main_thruster',
                'Item' => [
                    'Components' => [
                        'SCItemThrusterParams' => [
                            'thrusterType' => 'Main',
                            'fuelBurnRatePer10KNewton' => 0.05,
                            'thrustCapacity' => 1000,
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'vtol_thruster',
                'Item' => [
                    'Components' => [
                        'SCItemThrusterParams' => [
                            'thrusterType' => 'Vtol',
                            'fuelBurnRatePer10KNewton' => 0.02,
                            'thrustCapacity' => 500,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        self::assertSame(5.0, $result['fuel_intake_rate']);
        self::assertEqualsWithDelta(0.005, $result['fuel_usage']['Main'], 0.0001);
        self::assertEqualsWithDelta(0.001, $result['fuel_usage']['Vtol'], 0.0001);
        self::assertSame(0.0, $result['fuel_usage']['Retro']);
        self::assertSame(0.0, $result['fuel_usage']['Maneuvering']);
    }

    public function test_aggregates_ir_and_em_with_fallbacks(): void
    {
        $loadout = [
            [
                'portName' => 'power_plant',
                'Item' => [
                    'Components' => [
                        'EntityComponentPowerConnection' => [
                            'PowerBase' => 10,
                            'PowerDrawRequest' => 50,
                            'PowerToEM' => 2,
                        ],
                        'EntityComponentHeatConnection' => [
                            'ThermalEnergyBase' => 3,
                            'ThermalEnergyDraw' => 1,
                            'TemperatureToIR' => 4,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        self::assertSame(20.0, $result['emission']['em_min']); // 10 * 2
        self::assertSame(100.0, $result['emission']['em_max']); // 50 * 2 (draw request used)
        self::assertSame(12.0, $result['emission']['ir']); // (3 * 4) idle
    }

    public function test_em_includes_nominal_signature_and_armor_multiplier(): void
    {
        $loadout = [
            [
                'portName' => 'power_plant',
                'Item' => [
                    'Components' => [
                        'EntityComponentPowerConnection' => [
                            'PowerBase' => 10,
                            'PowerDraw' => 20,
                            'PowerToEM' => 2,
                        ],
                        'ResourceNetworkSimple' => [
                            'states' => [
                                [
                                    'signatureParams' => [
                                        'EMSignature' => [
                                            'nominalSignature' => 100,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'hardpoint_armor',
                'Item' => [
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'Armor',
                            ],
                        ],
                        'SCItemVehicleArmorParams' => [
                            'signalInfrared' => 1.1,
                            'signalElectromagnetic' => 1.1,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        // (10*2 + 100) * 1.1 = 132
        self::assertEqualsWithDelta(132.0, $result['emission']['em_min'], 0.001);
        // (20*2 + 100) * 1.1 = 154
        self::assertEqualsWithDelta(154.0, $result['emission']['em_max'], 0.001);
    }

    public function test_ir_uses_temperature_signature_and_armor(): void
    {
        $loadout = [
            [
                'portName' => 'cooling_item',
                'Item' => [
                    'Components' => [
                        'EntityComponentHeatConnection' => [
                            'ThermalEnergyBase' => 5,
                            'ThermalEnergyDraw' => 5,
                        ],
                        'Temperature' => [
                            'SignatureParams' => [
                                'TemperatureToIR' => 4,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'portName' => 'hardpoint_armor',
                'Item' => [
                    'Components' => [
                        'SAttachableComponentParams' => [
                            'AttachDef' => [
                                'Type' => 'Armor',
                            ],
                        ],
                        'SCItemVehicleArmorParams' => [
                            'signalInfrared' => 1.1,
                            'signalElectromagnetic' => 1.0,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        // IR idle = 5 * 4 * 1.1 = 22
        self::assertEqualsWithDelta(22.0, $result['emission']['ir'], 0.001);
        // Heat totals should reflect base/draw
        self::assertEqualsWithDelta(10.0, $result['heat']['max'], 0.001);
    }

    public function test_exclude_requested_defaults_when_disabled(): void
    {
        $loadout = [
            [
                'portName' => 'power_plant',
                'Item' => [
                    'Components' => [
                        'EntityComponentPowerConnection' => [
                            'PowerDrawRequest' => 50,
                            'PowerToEM' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator(includeRequestedDefaults: false)->aggregate($loadout);

        self::assertNull($result['emission']['em_max']);
    }
}
