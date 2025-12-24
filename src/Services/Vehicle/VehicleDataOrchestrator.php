<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Orchestrates execution of vehicle data calculators
 *
 * Coordinates calculator execution with priority-based ordering,
 * conditional execution, and dependency management through intermediate results.
 */
final class VehicleDataOrchestrator
{
    /** @var array<VehicleDataCalculator> */
    private array $calculators;

    /**
     * @param  array<VehicleDataCalculator>  $calculators  Array of calculator instances
     */
    public function __construct(array $calculators)
    {
        // Sort by priority (lower = runs first)
        usort($calculators, static fn (VehicleDataCalculator $a, VehicleDataCalculator $b) => $a->getPriority() <=> $b->getPriority());

        $this->calculators = $calculators;
    }

    /**
     * Execute all calculators and merge results
     *
     * @param  VehicleDataContext  $context  Input data for calculators
     * @return array Merged calculation results
     */
    public function calculate(VehicleDataContext $context): array
    {
        $results = [];
        $intermediateResults = [];

        foreach ($this->calculators as $calculator) {
            if (! $calculator->canCalculate($context)) {
                continue;
            }

            $enrichedContext = $context->withIntermediateResults($intermediateResults);

            $calculatorResult = $calculator->calculate($enrichedContext);

            $intermediateResults = array_merge($intermediateResults, $calculatorResult);

            $results = $this->mergeResults($results, $calculatorResult);
        }

        return $results;
    }

    /**
     * Merge calculator results
     *
     * @param  array  $existing  Existing merged results
     * @param  array  $new  New results to merge
     * @return array Merged results
     */
    private function mergeResults(array $existing, array $new): array
    {
        return array_merge($existing, $new);
    }
}
