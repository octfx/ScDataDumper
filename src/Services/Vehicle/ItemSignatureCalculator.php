<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Helper\Arr;

final class ItemSignatureCalculator
{
    /**
     * @param  array  $components  Item component block (expects ItemResourceComponentParams / HeatController)
     * @param  array|null  $state  Selected ItemResourceState
     * @param  array  $deltas  Normalised list of deltas belonging to the state
     * @param  string|null  $portName  Port name (used to scale cooler IR)
     * @param  float|null  $powerValue  Current power value for range lookup
     * @param  float  $powerRatio  User-selected power ratio (r in SPViewer); defaults to 1.0
     * @return array{em_nominal: float, em_scaled: float, ir_nominal: float, ir_total: float, power_range_modifier: float}
     */
    public function calculate(array $components, ?array $state, array $deltas, ?string $portName = null, ?float $powerValue = null, float $powerRatio = 1.0): array
    {
        if ($state === null) {
            return [
                'em_nominal' => 0.0,
                'em_scaled' => 0.0,
                'ir_nominal' => 0.0,
                'ir_total' => 0.0,
                'power_range_modifier' => 1.0,
            ];
        }

        $sig = $state['signatureParams'] ?? [];
        $emNom = (float) Arr::get($sig, 'EMSignature.nominalSignature', 0.0);
        $irNom = (float) Arr::get($sig, 'IRSignature.nominalSignature', 0.0);

        $powerRangeModifier = $this->powerRangeModifier($powerValue, $state['rangeParams'] ?? []);

        $irScale = 1.0;
        if ($portName !== null && str_contains(strtolower($portName), 'cooler')) {
            $irScale = $powerRatio;
        }

        $startIr = $this->startIrIfEnabled($components);

        $irTotal = ($irNom * $irScale) + $startIr;

        return [
            'em_nominal' => $emNom,
            'em_scaled' => $emNom * $powerRangeModifier * $powerRatio,
            'ir_nominal' => $irNom,
            'ir_total' => $irTotal,
            'power_range_modifier' => $powerRangeModifier,
        ];
    }

    private function powerRangeModifier(?float $value, array $ranges): float
    {
        $registerRanges = array_values(array_filter(
            $ranges,
            static fn ($range) => isset($range['RegisterRange'])
        ));

        usort($registerRanges, static fn ($a, $b) => ($a['Start'] ?? 0) <=> ($b['Start'] ?? 0));

        if ($registerRanges === [] || $value === null) {
            return 1.0;
        }

        foreach ($registerRanges as $idx => $range) {
            $start = $range['Start'] ?? 0.0;
            $modifier = (float) ($range['Modifier'] ?? 1.0);
            $nextStart = $registerRanges[$idx + 1]['Start'] ?? null;

            if ($value >= $start && ($nextStart === null || $value < $nextStart)) {
                return $modifier;
            }
        }

        return 1.0;
    }

    private function startIrIfEnabled(array $components): float
    {
        $enableHeat = Arr::get($components, 'HeatController.EnableHeat');
        $enableSignature = Arr::get($components, 'HeatController.Signature.EnableSignature');

        if (! $enableHeat || ! $enableSignature) {
            return 0.0;
        }

        return (float) Arr::get($components, 'HeatController.Signature.StartIREmission', 0.0);
    }
}
