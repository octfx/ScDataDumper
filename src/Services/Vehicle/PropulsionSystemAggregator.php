<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Aggregates propulsion system data for spacecraft
 *
 * Calculates fuel capacity, intake rates, fuel usage, thrust capacity,
 * and derived metrics for the propulsion system.
 */
final class PropulsionSystemAggregator implements VehicleDataCalculator
{
    /**
     * Aggregate propulsion system data from port summary
     *
     * @param  array  $portSummary  Port summary with thruster and fuel tank collections
     * @return array Propulsion system data
     */
    public function aggregate(array $portSummary): array
    {
        $fuelCapacity = $this->calculateFuelCapacity($portSummary['hydrogenFuelTanks']);
        $fuelIntakeRate = $this->calculateFuelIntakeRate($portSummary['hydrogenFuelIntakes']);
        $fuelUsage = $this->calculateFuelUsage($portSummary);
        $thrustCapacity = $this->calculateThrustCapacity($portSummary);

        return [
            'FuelCapacity' => $fuelCapacity,
            'FuelIntakeRate' => $fuelIntakeRate,
            'FuelUsage' => $fuelUsage,
            'ThrustCapacity' => $thrustCapacity,
            'IntakeToMainFuelRatio' => $fuelUsage['Main'] > 0 ? round($fuelIntakeRate / $fuelUsage['Main'], 2) : null,
            'IntakeToTankCapacityRatio' => $fuelCapacity > 0 ? round($fuelIntakeRate / $fuelCapacity, 2) : null,
            'TimeForIntakesToFillTank' => $fuelIntakeRate > 0 ? round($fuelCapacity / $fuelIntakeRate, 2) : null,
            'ManeuveringTimeTillEmpty' => ($fuelUsage['Main'] > 0 && $fuelUsage['Maneuvering'] > 0)
                ? round($fuelCapacity / ($fuelUsage['Main'] + $fuelUsage['Maneuvering'] / 2), 2)
                : null,
        ];
    }

    private function calculateFuelCapacity(Collection $fuelTanks): float
    {
        return $fuelTanks->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.FuelTank.Capacity', 0) * 1000);
    }

    private function calculateFuelIntakeRate(Collection $fuelIntakes): float
    {
        return $fuelIntakes->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.FuelIntake.FuelPushRate', 0));
    }

    private function calculateFuelUsage(array $portSummary): array
    {
        return [
            'Main' => round($this->sumThrusterFuelUsage($portSummary['mainThrusters']), 2),
            'Retro' => round($this->sumThrusterFuelUsage($portSummary['retroThrusters']), 2),
            'Vtol' => round($this->sumThrusterFuelUsage($portSummary['vtolThrusters']), 2),
            'Maneuvering' => round($this->sumThrusterFuelUsage($portSummary['maneuveringThrusters']), 2),
        ];
    }

    private function calculateThrustCapacity(array $portSummary): array
    {
        return [
            'Main' => $portSummary['mainThrusters']->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.ThrustCapacity', 0)),
            'Retro' => $portSummary['retroThrusters']->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.ThrustCapacity', 0)),
            'Vtol' => $portSummary['vtolThrusters']->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.ThrustCapacity', 0)),
            'Maneuvering' => $portSummary['maneuveringThrusters']->sum(fn ($x) => Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.ThrustCapacity', 0)),
        ];
    }

    private function sumThrusterFuelUsage(Collection $thrusters): float
    {
        return $thrusters->sum(fn ($x) => (Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.FuelBurnRatePer10KNewton', 0) / 1e4) *
            Arr::get($x, 'Port.InstalledItem.stdItem.Thruster.ThrustCapacity', 0)
        );
    }

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $propulsion = $this->aggregate($context->portSummary);

        $thrusters = $this->buildThrustersSummary(
            $context->portSummary,
            $propulsion['ThrustCapacity'],
            $context->mass
        );

        if ($thrusters !== []) {
            $propulsion['Thrusters'] = $thrusters;
        }

        return [
            'Propulsion' => $propulsion,
        ];
    }

    public function getPriority(): int
    {
        return 20;
    }

    private const G = 9.80665;

    /**
     * Build thruster summary with count, capacity (MN), and G-force
     *
     * @param  array<string, Collection>  $portSummary
     * @param  array<string, float>  $thrustCapacity  Thrust capacity in Newtons per direction
     * @param  float  $mass  Ship mass in kg
     * @return array<int, array{Type: string, Count: int, Capacity: float, G?: float}>
     */
    private function buildThrustersSummary(array $portSummary, array $thrustCapacity, float $mass): array
    {
        $groups = [
            ['Type' => 'Main', 'ThrustKey' => 'Main', 'PortKey' => 'mainThrusters', 'ComputeG' => true],
            ['Type' => 'Maneuver', 'ThrustKey' => 'Maneuvering', 'PortKey' => 'maneuveringThrusters', 'ComputeG' => true],
            ['Type' => 'Retro', 'ThrustKey' => 'Retro', 'PortKey' => 'retroThrusters', 'ComputeG' => true],
            ['Type' => 'Vtol', 'ThrustKey' => 'Vtol', 'PortKey' => 'vtolThrusters', 'ComputeG' => true],
        ];

        $thrusters = [];
        foreach ($groups as $group) {
            $count = ($portSummary[$group['PortKey']] ?? collect())->count();
            if ($count === 0) {
                continue;
            }

            $capacity = (float) ($thrustCapacity[$group['ThrustKey']] ?? 0);

            $entry = [
                'Type' => $group['Type'],
                'Count' => $count,
                'Capacity' => round($capacity / 1_000_000, 2),
            ];

            if ($group['ComputeG'] && $mass > 0) {
                $entry['G'] = round($capacity / $mass / self::G, 2);
            }

            $thrusters[] = $entry;
        }

        return $thrusters;
    }
}
