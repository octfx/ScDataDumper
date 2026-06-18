<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Builds the Systems object from an annotated loadout.
 *
 * Produces a semantic index keyed by system category. Every vehicle gets all
 * system keys (see VehicleSystemKeys), each with { Summary, Ports }.
 */
final class SystemsBuilder
{
    /**
     * Build the complete Systems object from an annotated loadout.
     *
     * @param  list<array<string, mixed>>  $annotatedLoadout  Already-annotated loadout (from LoadoutPortIdentityAnnotator)
     * @param  array<string, mixed>  $calculatedData  Calculated data from the orchestrator (for summaries)
     * @param  array<string, mixed>  $legacyPortSummary  @deprecated No longer used. Kept for signature compatibility.
     * @return array<string, array{Summary: array|null, Ports: list<array<string, mixed>>}>
     */
    public function build(array $annotatedLoadout, array $calculatedData = [], array $legacyPortSummary = []): array
    {
        $index = (new RecursiveLoadoutPortIndex)->build($annotatedLoadout);
        $classified = (new SystemPortClassifier)->classify($index);

        $systems = [];
        foreach (VehicleSystemKeys::ALL_KEYS as $key) {
            $systems[$key] = [
                'Summary' => null,
                'Ports' => [],
            ];
        }

        foreach ($classified as $systemKey => $ports) {
            if (isset($systems[$systemKey])) {
                $systems[$systemKey]['Ports'] = $ports;
            }
        }

        $this->populateSummaries($systems, $calculatedData);
        $this->populateItemBasedSummaries($systems, $calculatedData);
        $this->populateCountBasedSummaries($systems);

        return $systems;
    }

    /**
     * Populate Summary for systems with calculator-backed aggregates.
     *
     * @param  array<string, array{Summary: array|null, Ports: list<array<string, mixed>>}>  $systems
     * @param  array<string, mixed>  $calculatedData
     */
    private function populateSummaries(array &$systems, array $calculatedData): void
    {
        if (! empty($calculatedData['shields_total'])) {
            $filtered = $this->filterNullValues($calculatedData['shields_total']);
            if (! empty($filtered)) {
                $systems['Shields']['Summary'] = $calculatedData['shields_total'];
            }
        }

        if (! empty($calculatedData['QuantumTravel'])) {
            $filtered = $this->filterNullValues($calculatedData['QuantumTravel']);
            if (! empty($filtered)) {
                $systems['QuantumDrives']['Summary'] = $calculatedData['QuantumTravel'];
            }
        }

        if (! empty($calculatedData['FlightCharacteristics'])) {
            $systems['FlightControllers']['Summary'] = $calculatedData['FlightCharacteristics'];
        }

        if (! empty($calculatedData['Propulsion'])) {
            $systems['Thrusters']['Summary'] = $calculatedData['Propulsion'];
        }

        if (! empty($calculatedData['cooling'])) {
            $systems['Coolers']['Summary'] = $calculatedData['cooling'];
        }

        // PowerPlants merges power + power_pools.
        $powerSummary = null;
        if (! empty($calculatedData['power'])) {
            $powerSummary = $calculatedData['power'];
        }
        if (! empty($calculatedData['power_pools'])) {
            $powerSummary = ($powerSummary ?? []) + ['PowerPools' => $calculatedData['power_pools']];
        }
        if ($powerSummary !== null) {
            $systems['PowerPlants']['Summary'] = $powerSummary;
        }

        // Weapons excludes the Turrets sub-key.
        if (! empty($calculatedData['Weaponry'])) {
            $weaponry = $calculatedData['Weaponry'];
            unset($weaponry['Turrets']);
            if (! empty($weaponry)) {
                $systems['Weapons']['Summary'] = $weaponry;
            }
        }
    }

    /**
     * Populate fuel/intake summaries from already-calculated Propulsion & QuantumTravel data:
     *   QuantumTravel.FuelCapacity -> QuantumFuelTanks.Capacity
     *   Propulsion.FuelCapacity    -> HydrogenFuelTanks.Capacity
     *   Propulsion.FuelIntakeRate  -> FuelIntakes.FuelPushRate
     *
     * @param  array<string, array{Summary: array|null, Ports: list<array<string, mixed>>}>  $systems
     * @param  array<string, mixed>  $calculatedData
     */
    private function populateItemBasedSummaries(array &$systems, array $calculatedData): void
    {
        $qftCapacity = $calculatedData['QuantumTravel']['FuelCapacity'] ?? null;
        if ($qftCapacity !== null && ! empty($systems['QuantumFuelTanks']['Ports'])) {
            $systems['QuantumFuelTanks']['Summary'] = ['Capacity' => (float) $qftCapacity];
        }

        $hftCapacity = $calculatedData['Propulsion']['FuelCapacity'] ?? null;
        if ($hftCapacity !== null && ! empty($systems['HydrogenFuelTanks']['Ports'])) {
            $systems['HydrogenFuelTanks']['Summary'] = ['Capacity' => (float) $hftCapacity];
        }

        $fiRate = $calculatedData['Propulsion']['FuelIntakeRate'] ?? null;
        if ($fiRate !== null && ! empty($systems['FuelIntakes']['Ports'])) {
            $systems['FuelIntakes']['Summary'] = ['FuelPushRate' => (float) $fiRate];
        }
    }

    /**
     * @param  array<string, array{Summary: array|null, Ports: list<array<string, mixed>>}>  $systems
     */
    private function populateCountBasedSummaries(array &$systems): void
    {
        $countBased = [
            'MissileRacks' => 'Count',
            'Missiles' => 'Count',
        ];

        foreach ($countBased as $systemKey => $field) {
            $portCount = count($systems[$systemKey]['Ports']);
            if ($portCount > 0) {
                $systems[$systemKey]['Summary'] = [$field => $portCount];
            }
        }
    }

    /**
     * Recursively strip null values, preserving structure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterNullValues(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                $filtered = $this->filterNullValues($value);
                if (! empty($filtered)) {
                    $result[$key] = $filtered;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
