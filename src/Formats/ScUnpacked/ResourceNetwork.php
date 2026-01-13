<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class ResourceNetwork extends BaseFormat
{
    protected ?string $elementKey = 'Components/ItemResourceComponentParams';

    private const array UNIT_FACTORS = [
        'SStandardResourceUnit' => 1.0,
        'SPowerSegmentResourceUnit' => 1.0,
        'SCentiResourceUnit' => 0.01,
        'SMicroResourceUnit' => 0.000001,
    ];

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();

        $data = [
            'IsNetworked' => (bool) $component->get('isResourceNetworked'),
            'IsRelay' => (bool) $component->get('isRelay'),
            'DefaultPriority' => $component->get('defaultPriority'),
            'States' => $this->parseStates($component->get('/states')),
            'Repair' => $component->get('/selfRepair')?->attributesToArray(['__type'], pascalCase: true),
            'Usage' => $this->buildUsage(),
            'Generation' => $this->buildGeneration(),
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
            $parsed = $this->parseState($state);

            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    private function parseState(Element $state): ?array
    {
        $data = [
            'Name' => $state->get('name'),
            'Deltas' => $this->parseDeltas($state->get('/deltas')),
            'Signature' => $this->parseSignature($state->get('/signatureParams')),
            'LinkedInteractionStates' => $this->parseLinkedInteractionStates($state->get('/linkedInteractionStates')),
            'PowerRanges' => $this->parsePowerRanges(
                $state->get('/powerRanges') ?? $state->get('/rangeParams')
            ),
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
            $parsed = $this->parseDelta($delta);

            if ($parsed !== null) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    private function parseDelta(Element $delta): ?array
    {
        $parsed = match ($delta->nodeName) {
            'ItemResourceDeltaConsumption' => $this->buildConsumptionDelta($delta),
            'ItemResourceDeltaGeneration' => $this->buildGenerationDelta($delta),
            'ItemResourceDeltaConversion' => $this->buildConversionDelta($delta),
            'ItemResourceDeltaStorage' => $this->buildStorageDelta($delta),
            'ItemResourceDeltaNetworkReflection' => [
                'Type' => 'NetworkReflection',
                'Resource' => $delta->get('resource'),
                'BinaryEvaluation' => $delta->get('binaryEvaluation'),
            ],
            default => [
                'Type' => 'Other',
                'Attributes' => $this->transformArrayKeysToPascalCase($delta->attributesToArray()),
            ],
        };

        $parsed = $this->clean($parsed);

        return $parsed === [] ? null : $parsed;
    }

    private function buildConsumptionDelta(Element $delta): array
    {
        $consumption = $this->parseResourceAmountBlock($delta->get('/consumption'));

        return [
            'Type' => 'Consumption',
            'Resource' => $consumption['Resource'] ?? null,
            'Rate' => $consumption['Rate'] ?? null,
            'RawUnit' => $consumption['RawUnit'] ?? null,
            'MinimumFraction' => $delta->get('minimumConsumptionFraction'),
            'Composition' => $this->parseComposition($delta->get('/consumptionComposition')),
        ];
    }

    private function buildGenerationDelta(Element $delta): array
    {
        $generation = $this->parseResourceAmountBlock($delta->get('/generation'));

        return [
            'Type' => 'Generation',
            'Resource' => $generation['Resource'] ?? null,
            'Rate' => $generation['Rate'] ?? null,
            'RawUnit' => $generation['RawUnit'] ?? null,
            'NoOverGeneration' => $delta->get('noOverGeneration'),
            'Composition' => $this->parseComposition($delta->get('/composition')),
        ];
    }

    private function buildConversionDelta(Element $delta): array
    {
        $consumption = $this->parseResourceAmountBlock($delta->get('/consumption'));
        $generation = $this->parseResourceAmountBlock($delta->get('/generation'));

        return [
            'Type' => 'Conversion',
            'Resource' => $consumption['Resource'] ?? null,
            'Rate' => $consumption['Rate'] ?? null,
            'RawUnit' => $consumption['RawUnit'] ?? null,
            'GeneratedResource' => $generation['Resource'] ?? null,
            'GeneratedRate' => $generation['Rate'] ?? null,
            'GeneratedRawUnit' => $generation['RawUnit'] ?? null,
            'MinimumFraction' => $delta->get('minimumConsumptionFraction'),
            'NoOverGeneration' => $delta->get('noOverGeneration'),
            'ConsumptionComposition' => $this->parseComposition($delta->get('/consumptionComposition')),
            'GeneratedComposition' => $this->parseComposition($delta->get('/generatedComposition')),
        ];
    }

    private function buildStorageDelta(Element $delta): array
    {
        $consumption = $this->parseResourceAmountBlock($delta->get('/consumption'));
        $generation = $this->parseResourceAmountBlock($delta->get('/generation'));

        return [
            'Type' => 'Storage',
            'Resource' => $consumption['Resource'] ?? null,
            'Rate' => $consumption['Rate'] ?? null,
            'RawUnit' => $consumption['RawUnit'] ?? null,
            'GeneratedResource' => $generation['Resource'] ?? null,
            'GeneratedRate' => $generation['Rate'] ?? null,
            'GeneratedRawUnit' => $generation['RawUnit'] ?? null,
            'Discharge' => $delta->get('discharge'),
            'ConsumptionComposition' => $this->parseComposition($delta->get('/consumptionComposition')),
        ];
    }

    private function parseResourceAmountBlock(?Element $element): ?array
    {
        if (! $element) {
            return null;
        }

        $rate = $this->parseResourceRate($element->get('/resourceAmountPerSecond'));

        $data = [
            'Resource' => $element->get('resource'),
        ];

        if ($rate !== null) {
            $data += $rate;
        }

        return $this->clean($data);
    }

    private function parseResourceRate(?Element $element): ?array
    {
        if (! $element) {
            return null;
        }

        foreach ($element->children() as $unit) {
            $field = $this->getAmountField($unit->nodeName);

            if ($field === null) {
                continue;
            }

            $amount = $unit->get($field);

            if ($amount === null) {
                continue;
            }

            $factor = self::UNIT_FACTORS[$unit->nodeName] ?? null;

            if ($factor !== null) {
                return [
                    'Rate' => $amount * $factor,
                ];
            }

            return [
                'RawUnit' => $unit->nodeName,
                'AmountPerSecond' => $amount,
            ];
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
            $out[] = [
                'ContainerResource' => $value->get('containerResource'),
                'Ratio' => $value->get('ratio'),
            ];
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
            $out[] = $this->transformArrayKeysToPascalCase($state->attributesToArray());
        }

        return $out;
    }

    private function parsePowerRanges(?Element $powerRanges): array
    {
        if (! $powerRanges) {
            return [];
        }

        $out = [];

        foreach ($powerRanges->children() as $range) {
            $out[] = $this->transformArrayKeysToPascalCase($range->attributesToArray());
        }

        return $out;
    }

    private function parseSignature(?Element $signature): ?array
    {
        if (! $signature) {
            return null;
        }

        $data = [];

        $em = $signature->get('/EMSignature');
        $ir = $signature->get('/IRSignature');

        $emNominal = $em?->get('nominalSignature');
        $irNominal = $ir?->get('nominalSignature');

        if ($emNominal !== null && $emNominal !== 0.0) {
            $data['EM'] = $emNominal;
        }

        if ($irNominal !== null && $irNominal !== 0.0) {
            $data['IR'] = $irNominal;
        }

        return $data === [] ? null : $data;
    }

    private function clean($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $cleaned = $this->clean($item);

                $isEmptyArray = is_array($cleaned) && $cleaned === [];

                if ($cleaned === null || $isEmptyArray) {
                    unset($value[$key]);
                } else {
                    $value[$key] = $cleaned;
                }
            }

            return $value;
        }

        return $value;
    }

    private function buildUsage(): ?array
    {
        $onlineState = null;

        foreach ($this->item->get('Components/ItemResourceComponentParams/states')?->children() as $state) {
            if ($state->get('@name') === 'Online') {
                $onlineState = $state;
                break;
            }
        }

        if ($onlineState === null) {
            return null;
        }

        $isGenerator = $onlineState->has('/deltas/ItemResourceDeltaGeneration');
        $powerDelta = null;

        foreach ($onlineState->get('/deltas')?->children() as $delta) {
            if ($delta->get('/consumption@resource') === 'Power' || ($isGenerator && $delta->get('/generation@resource') === 'Power')) {
                $powerDelta = $delta;
                break;
            }
        }

        $key = 'SPowerSegmentResourceUnit@units';
        if ($powerDelta?->has('/consumption/resourceAmountPerSecond/SStandardResourceUnit')) {
            $key = 'SStandardResourceUnit@standardResourceUnits';
        }

        $minConsumptionFraction = (float) ($powerDelta?->get('@minimumConsumptionFraction', 1) ?? 1);

        $powerUsageMax = $isGenerator ?
            $powerDelta?->get('/generation/resourceAmountPerSecond/SPowerSegmentResourceUnit@units', 0) :
            $powerDelta?->get('/consumption/resourceAmountPerSecond/'.$key, 0);
        $powerUsageMin = $powerUsageMax * $minConsumptionFraction;

        $lowPowerRange = null;

        foreach ($onlineState->get('/powerRanges')?->children() as $range) {
            if ($range->getNode()->nodeName === 'low' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }

            if ($range->getNode()->nodeName === 'medium' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }

            if ($range->getNode()->nodeName === 'high' && ((int) $range->get('@registerRange')) === 1) {
                $lowPowerRange = (float) ($range->get('@modifier', 1));
                break;
            }
        }

        return [
            'Power' => [
                'Minimum' => $powerUsageMin,
                'Maximum' => $powerUsageMax,
            ],
            'Coolant' => [
                'Minimum' => round($powerUsageMin * $lowPowerRange),
                'Maximum' => $powerUsageMax,
            ],
        ];
    }

    private function buildGeneration(): ?array
    {
        $onlineState = null;

        foreach ($this->item->get('Components/ItemResourceComponentParams/states')?->children() as $state) {
            if ($state->get('@name') === 'Online') {
                $onlineState = $state;
                break;
            }
        }

        if ($onlineState === null) {
            return null;
        }

        $isGenerator = $onlineState->get('/deltas/ItemResourceDeltaGeneration/generation@resource') === 'Power';
        $isCooler = $onlineState->get('/deltas/ItemResourceDeltaConversion/generation@resource') === 'Coolant';

        if ($isCooler) {
            return [
                'Coolant' => $onlineState->get('/deltas/ItemResourceDeltaConversion/generation/resourceAmountPerSecond/SStandardResourceUnit@standardResourceUnits'),
            ];
        }

        if ($isGenerator) {
            return [
                'Power' => $onlineState->get('/deltas/ItemResourceDeltaGeneration/generation/resourceAmountPerSecond/SPowerSegmentResourceUnit@units', 0),
            ];
        }

        return null;

    }
}
