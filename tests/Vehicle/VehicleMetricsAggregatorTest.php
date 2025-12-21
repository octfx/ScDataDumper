<?php

declare(strict_types=1);

namespace Tests\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\EquippedItemWalker;
use Octfx\ScDataDumper\Services\Vehicle\ItemSignatureCalculator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleMetricsAggregator;
use PHPUnit\Framework\TestCase;

final class VehicleMetricsAggregatorTest extends TestCase
{
    private function makeAggregator(): VehicleMetricsAggregator
    {
        return new VehicleMetricsAggregator(new EquippedItemWalker, new ItemSignatureCalculator);
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
                        'ItemResourceComponentParams' => [
                            'states' => [
                                'ItemResourceState' => [
                                    'signatureParams' => [
                                        'EMSignature' => ['nominalSignature' => 2],
                                        'IRSignature' => ['nominalSignature' => 3],
                                    ],
                                    'deltas' => [
                                        'ItemResourceDeltaConsumption' => [
                                            'consumption' => [
                                                'resource' => 'Power',
                                                'resourceAmountPerSecond' => [
                                                    'SPowerSegmentResourceUnit' => ['units' => 10],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        self::assertSame(2.0, $result['emission']['em']); // nominal EM only
        self::assertSame(3.0, $result['emission']['ir']);  // nominal IR
    }

    public function test_em_includes_nominal_signature_and_armor_multiplier(): void
    {
        $loadout = [
            [
                'portName' => 'power_plant',
                'Item' => [
                    'Components' => [
                        'ItemResourceComponentParams' => [
                            'states' => [
                                'ItemResourceState' => [
                                    'signatureParams' => [
                                        'EMSignature' => ['nominalSignature' => 10],
                                    ],
                                    'deltas' => [
                                        'ItemResourceDeltaConsumption' => [
                                            'consumption' => [
                                                'resource' => 'Power',
                                                'resourceAmountPerSecond' => [
                                                    'SPowerSegmentResourceUnit' => ['units' => 10],
                                                ],
                                            ],
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

        // 10 * 1.1 = 11
        self::assertEqualsWithDelta(11.0, $result['emission']['em'], 0.001);
    }

    public function test_ir_uses_temperature_signature_and_armor(): void
    {
        $loadout = [
            [
                'portName' => 'cooling_item',
                'Item' => [
                    'Components' => [
                        'ItemResourceComponentParams' => [
                            'states' => [
                                'ItemResourceState' => [
                                    'signatureParams' => [
                                        'IRSignature' => ['nominalSignature' => 5],
                                    ],
                                    'deltas' => [
                                        'ItemResourceDeltaConsumption' => [
                                            'consumption' => [
                                                'resource' => 'Power',
                                                'resourceAmountPerSecond' => [
                                                    'SPowerSegmentResourceUnit' => ['units' => 1],
                                                ],
                                            ],
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
                            'signalElectromagnetic' => 1.0,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        // IR = 5 * 1.1 = 5.5
        self::assertEqualsWithDelta(5.5, $result['emission']['ir'], 0.001);
    }

    public function test_exclude_requested_defaults_when_disabled(): void
    {
        $loadout = [
            [
                'portName' => 'power_plant',
                'Item' => [
                    'Components' => [
                        'ItemResourceComponentParams' => [
                            'states' => [
                                'ItemResourceState' => [
                                    'signatureParams' => [
                                        'EMSignature' => ['nominalSignature' => 5],
                                    ],
                                    'deltas' => [
                                        'ItemResourceDeltaConsumption' => [
                                            'consumption' => [
                                                'resource' => 'Power',
                                                'resourceAmountPerSecond' => [
                                                    'SPowerSegmentResourceUnit' => ['units' => 1],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->makeAggregator()->aggregate($loadout);

        self::assertNotNull($result['emission']['em']);
    }
}
