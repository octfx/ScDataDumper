<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class ResourceNetwork extends BaseFormat
{
    protected ?string $elementKey = 'Components/ItemResourceComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();

        $data = [
            'IsResourceNetworked' => (bool) $component->get('isResourceNetworked'),
            'IsRelay' => (bool) $component->get('isRelay'),
            'IsConnectedToRoom' => (bool) $component->get('isConnectedToRoom'),
            'WirelessConnection' => (bool) $component->get('wirelessConnection'),
            'DefaultPriority' => $component->get('defaultPriority'),
            'States' => $this->parseStates($component->get('/states')),
        ];

        $data = $this->clean($data);

        return $data === [] ? null : $data;
    }

    private function parseStates(?Element $states): array
    {
        if (! $states) {
            return [];
        }

        $out = [];

        foreach ($states->children() as $state) {
            $parsedState = $this->parseState($state);

            if ($parsedState !== null) {
                $out[] = $parsedState;
            }
        }

        return $out;
    }

    private function parseState(Element $state): ?array
    {
        $data = [
            'Name' => $state->get('name'),
            'Deltas' => $this->parseDeltas($state->get('/deltas')),
            'LinkedInteractionStates' => $this->parseLinkedInteractionStates($state->get('/linkedInteractionStates')),
            'Signature' => $this->parseSignature($state->get('/signatureParams')),
            'RangeParams' => $this->parseRangeParams($state->get('/rangeParams')),
        ];

        $data = $this->clean($data);

        return $data === [] ? null : $data;
    }

    private function parseDeltas(?Element $deltas): array
    {
        if (! $deltas) {
            return [];
        }

        $out = [];

        foreach ($deltas->children() as $delta) {
            $parsedDelta = $this->parseDelta($delta);

            if ($parsedDelta !== null) {
                $out[] = $parsedDelta;
            }
        }

        return $out;
    }

    private function parseDelta(Element $delta): ?array
    {
        $parsed = match ($delta->nodeName) {
            'ItemResourceDeltaConsumption' => [
                'Type' => 'Consumption',
                'MinimumConsumptionFraction' => $delta->get('minimumConsumptionFraction'),
                'Consumption' => $this->parseResourceAmountBlock($delta->get('/consumption')),
                'ConsumptionComposition' => $this->parseComposition($delta->get('/consumptionComposition')),
                'DynamicAmountOverride' => $this->parseGenericList($delta->get('/dynamicAmountOverride')),
            ],
            'ItemResourceDeltaGeneration' => [
                'Type' => 'Generation',
                'NoOverGeneration' => $delta->get('noOverGeneration'),
                'Generation' => $this->parseResourceAmountBlock($delta->get('/generation')),
                'Composition' => $this->parseComposition($delta->get('/composition')),
                'DynamicAmountOverride' => $this->parseGenericList($delta->get('/dynamicAmountOverride')),
                'DynamicCompositionOverride' => $this->parseComposition($delta->get('/dynamicCompositionOverride')),
                'GenerationModifiers' => $this->parseGenericList($delta->get('/generationModifiers')),
            ],
            'ItemResourceDeltaConversion' => [
                'Type' => 'Conversion',
                'MinimumConsumptionFraction' => $delta->get('minimumConsumptionFraction'),
                'NoOverGeneration' => $delta->get('noOverGeneration'),
                'Consumption' => $this->parseResourceAmountBlock($delta->get('/consumption')),
                'Generation' => $this->parseResourceAmountBlock($delta->get('/generation')),
                'GeneratedComposition' => $this->parseComposition($delta->get('/generatedComposition')),
                'ConsumptionComposition' => $this->parseComposition($delta->get('/consumptionComposition')),
                'DynamicConversionModifier' => $this->parseGenericList($delta->get('/dynamicConversionModifier')),
                'DynamicAmountOverride' => $this->parseGenericList($delta->get('/dynamicAmountOverride')),
                'GenerationModifiers' => $this->parseGenericList($delta->get('/generationModifiers')),
            ],
            'ItemResourceDeltaNetworkReflection' => [
                'Type' => 'NetworkReflection',
                'Resource' => $delta->get('resource'),
                'BinaryEvaluation' => $delta->get('binaryEvaluation'),
            ],
            'ItemResourceDeltaStorage' => [
                'Type' => 'Storage',
                'Discharge' => $delta->get('discharge'),
                'Consumption' => $this->parseResourceAmountBlock($delta->get('/consumption')),
                'Generation' => $this->parseResourceAmountBlock($delta->get('/generation')),
                'ConsumptionComposition' => $this->parseComposition($delta->get('/consumptionComposition')),
                'DynamicResourceOverride' => $this->parseGenericList($delta->get('/dynamicResourceOverride')),
                'TransferModifiers' => $this->parseGenericList($delta->get('/transferModifiers')),
            ],
            default => [
                'Type' => $delta->nodeName,
                'Attributes' => $delta->attributesToArray(),
            ],
        };

        $parsed = $this->clean($parsed);

        return $parsed === [] ? null : $parsed;
    }

    private function parseResourceAmountBlock(?Element $element): ?array
    {
        if (! $element) {
            return null;
        }

        $data = [
            'Resource' => $element->get('resource'),
            'Rate' => $this->parseResourceAmount($element->get('/resourceAmountPerSecond')),
        ];

        $data = $this->clean($data);

        return $data === [] ? null : $data;
    }

    private function parseResourceAmount(?Element $element): ?array
    {
        if (! $element) {
            return null;
        }

        foreach ($element->children() as $unit) {
            $field = $this->getAmountField($unit->nodeName);

            if ($field === null) {
                continue;
            }

            $value = $unit->get($field);

            if ($value === null) {
                continue;
            }

            $amount = [
                'Unit' => $unit->nodeName,
                'AmountPerSecond' => $value,
            ];

            $standardised = $this->normalizeAmount($unit->nodeName, $value);

            if ($standardised !== null) {
                $amount['StandardAmountPerSecond'] = $standardised;
            }

            return $amount;
        }

        return null;
    }

    private function getAmountField(string $unit): ?string
    {
        return match ($unit) {
            'SStandardResourceUnit' => 'standardResourceUnits',
            'SCentiResourceUnit' => 'centiResourceUnits',
            'SMicroResourceUnit' => 'microResourceUnits',
            'SPowerSegmentResourceUnit' => 'units',
            default => null,
        };
    }

    private function normalizeAmount(string $unit, float $value): ?float
    {
        return match ($unit) {
            'SStandardResourceUnit', 'SPowerSegmentResourceUnit' => $value,
            'SCentiResourceUnit' => $value / 100,
            'SMicroResourceUnit' => $value / 1_000_000,
            default => null,
        };
    }

    private function parseComposition(?Element $composition): array
    {
        if (! $composition) {
            return [];
        }

        $values = $composition->get('/values');

        if (! $values instanceof Element) {
            return [];
        }

        $out = [];

        foreach ($values->children() as $value) {
            $out[] = $value->attributesToArray();
        }

        return $out;
    }

    private function parseLinkedInteractionStates(?Element $states): array
    {
        if (! $states) {
            return [];
        }

        $out = [];

        foreach ($states->children() as $state) {
            $out[] = $state->attributesToArray();
        }

        return $out;
    }

    private function parseSignature(?Element $signature): ?array
    {
        if (! $signature) {
            return null;
        }

        $data = [
            'EM' => $signature->get('/EMSignature')?->attributesToArray(),
            'IR' => $signature->get('/IRSignature')?->attributesToArray(),
        ];

        $data = $this->clean($data);

        return $data === [] ? null : $data;
    }

    private function parseRangeParams(?Element $rangeParams): array
    {
        if (! $rangeParams) {
            return [];
        }

        $out = [];

        foreach ($rangeParams->children() as $range) {
            $out[] = $range->attributesToArray();
        }

        return $out;
    }

    private function parseGenericList(?Element $element): array
    {
        if (! $element) {
            return [];
        }

        $out = [];

        foreach ($element->children() as $child) {
            $out[] = $child->attributesToArray();
        }

        return $out;
    }

    private function clean($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $cleaned = $this->clean($item);

                if ($cleaned === null || (is_array($cleaned) && $cleaned === [])) {
                    unset($value[$key]);
                } else {
                    $value[$key] = $cleaned;
                }
            }

            return $value;
        }

        return $value;
    }
}
