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
final class PropulsionSystemAggregator
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
            'IntakeToMainFuelRatio' => $fuelUsage['Main'] > 0 ? $fuelIntakeRate / $fuelUsage['Main'] : null,
            'IntakeToTankCapacityRatio' => $fuelCapacity > 0 ? $fuelIntakeRate / $fuelCapacity : null,
            'TimeForIntakesToFillTank' => $fuelIntakeRate > 0 ? $fuelCapacity / $fuelIntakeRate : null,
            'ManeuveringTimeTillEmpty' => ($fuelUsage['Main'] > 0 && $fuelUsage['Maneuvering'] > 0)
                ? $fuelCapacity / ($fuelUsage['Main'] + $fuelUsage['Maneuvering'] / 2 - $fuelIntakeRate)
                : null,
        ];
    }

    private function calculateFuelCapacity(Collection $fuelTanks): float
    {
        return $fuelTanks->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0) * 1000);
    }

    private function calculateFuelIntakeRate(Collection $fuelIntakes): float
    {
        return $fuelIntakes->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemFuelIntakeParams.fuelPushRate', 0));
    }

    private function calculateFuelUsage(array $portSummary): array
    {
        return [
            'Main' => $this->sumThrusterFuelUsage($portSummary['mainThrusters']),
            'Retro' => $this->sumThrusterFuelUsage($portSummary['retroThrusters']),
            'Vtol' => $this->sumThrusterFuelUsage($portSummary['vtolThrusters']),
            'Maneuvering' => $this->sumThrusterFuelUsage($portSummary['maneuveringThrusters']),
        ];
    }

    private function calculateThrustCapacity(array $portSummary): array
    {
        return [
            'Main' => $portSummary['mainThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
            'Retro' => $portSummary['retroThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
            'Vtol' => $portSummary['vtolThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
            'Maneuvering' => $portSummary['maneuveringThrusters']->sum(fn ($x) => Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)),
        ];
    }

    private function sumThrusterFuelUsage(Collection $thrusters): float
    {
        return $thrusters->sum(fn ($x) => (Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.fuelBurnRatePer10KNewton', 0) / 1e4) *
            Arr::get($x, 'InstalledItem.Components.SCItemThrusterParams.thrustCapacity', 0)
        );
    }
}
