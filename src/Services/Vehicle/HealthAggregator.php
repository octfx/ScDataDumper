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
final class HealthAggregator implements VehicleDataCalculator
{
    private HealthAggregationConfig $config;

    public function __construct(
        private readonly StandardisedPartWalker $walker,
        ?HealthAggregationConfig $config = null
    ) {
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
        $health = 0.0;
        $destructionParts = [];
        $detachParts = [];

        foreach ($this->walker->walkParts($parts) as $entry) {
            $part = $entry['part'];

            if ($this->shouldIncludeInHealth($part)) {
                $health += $part['MaximumDamage'] ?? 0;
            }

            if (($part['ShipDestructionDamage'] ?? 0) > 0) {
                $destructionParts[] = [
                    'Name' => $part['Name'],
                    'HP' => $part['MaximumDamage'] ?? 0,
                    'DestructionDamage' => $part['ShipDestructionDamage'],
                ];
            }

            if (($part['PartDetachDamage'] ?? 0) > 0 && $part['ShipDestructionDamage'] === null) {
                $detachParts[] = [
                    'Name' => $part['Name'],
                    'HP' => $part['MaximumDamage'] ?? 0,
                    'DetachDamage' => $part['PartDetachDamage'],
                ];
            }
        }

        return [
            'Health' => $health,
            'DamageBeforeDestruction' => $destructionParts,
            'DamageBeforeDetach' => $detachParts,
        ];
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

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        return $this->aggregateHealth(collect($context->standardisedParts));
    }

    public function getPriority(): int
    {
        return 40;
    }
}
