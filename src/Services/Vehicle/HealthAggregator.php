<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\ValueObjects\HealthAggregationConfig;

/**
 * Aggregate health data for vehicles
 *
 * Calculates total health, damage before destruction, and damage before detach
 * with configurable filtering for structural vs non-structural parts.
 */
final class HealthAggregator
{
    private HealthAggregationConfig $config;

    public function __construct(?HealthAggregationConfig $config = null)
    {
        $this->config = $config ?? new HealthAggregationConfig;
    }

    /**
     * Aggregate health data from parts
     *
     * @param  Collection  $parts  Collection of ship parts
     * @return array Health aggregation data
     */
    public function aggregateHealth(Collection $parts): array
    {
        return [
            'Health' => $parts
                ->filter(fn ($x) => $this->shouldIncludeInHealth($x))
                ->sum(fn ($x) => $x['MaximumDamage']),
            'DamageBeforeDestruction' => $this->extractDestructionDamage($parts),
            'DamageBeforeDetach' => $this->extractDetachDamage($parts),
        ];
    }

    /**
     * Extract damage before destruction data
     */
    private function extractDestructionDamage(Collection $parts): array
    {
        return $parts
            ->filter(fn ($x) => ($x['ShipDestructionDamage'] ?? 0) > 0)
            ->mapWithKeys(fn ($x) => [$x['Name'] => $x['ShipDestructionDamage']])
            ->toArray();
    }

    /**
     * Extract damage before detach data
     */
    private function extractDetachDamage(Collection $parts): array
    {
        return $parts
            ->filter(fn ($x) => ($x['PartDetachDamage'] ?? 0) > 0 && $x['ShipDestructionDamage'] === null)
            ->mapWithKeys(fn ($x) => [$x['Name'] => $x['PartDetachDamage']])
            ->toArray();
    }

    /**
     * Decide whether a part should contribute to ship-level health
     *
     * We exclude small ItemPorts (thrusters, fuel intakes, etc.) when their
     * port flags mark them as non-structural (uneditable/invisible).
     */
    private function shouldIncludeInHealth(array $part): bool
    {
        $damage = $part['MaximumDamage'] ?? 0;
        if ($damage <= 0) {
            return false;
        }

        $port = $part['Port'] ?? null;
        if (! $port || ! is_array($port)) {
            return true;
        }

        $flags = array_map('strtolower', $port['Flags'] ?? []);
        $isItemPort = ! empty($port['PortName']) || ! empty($port['Types']);

        $hasExcludedFlag = ! empty(array_intersect($flags, $this->config->excludedPortFlags));

        return ! ($this->config->skipItemPorts && $isItemPort && $hasExcludedFlag);
    }
}
