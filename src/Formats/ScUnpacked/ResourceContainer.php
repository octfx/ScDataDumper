<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class ResourceContainer extends BaseFormat
{
    protected ?string $elementKey = 'Components/ResourceContainer';

    private const UNIT_NAMES = [
        'SStandardCargoUnit' => 'SCU',
        'SCentiCargoUnit' => 'cSCU',
        'SMicroCargoUnit' => 'µSCU',
    ];

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();

        $data = [
            'Mass' => $component->get('mass'),
            'Immutable' => (bool) $component->get('immutable'),
            'DefaultFillFraction' => $component->get('defaultCompositionFillFactor'),
            'Capacity' => $this->parseCapacity($component->get('/capacity')),
            'InclusiveResources' => $this->parseReferences($component->get('/inclusiveResources')),
            'ExclusiveResources' => $this->parseReferences($component->get('/exclusiveResources')),
            'InclusiveGroups' => $this->parseReferences($component->get('/inclusiveGroups')),
            'ExclusiveGroups' => $this->parseReferences($component->get('/exclusiveGroups')),
            'DefaultComposition' => $this->parseDefaultComposition($component->get('/defaultComposition')),
        ];

        $data = $this->clean($data);

        return $data === [] ? null : $data;
    }

    private function parseCapacity(?Element $capacity): ?array
    {
        if (! $capacity) {
            return null;
        }

        foreach ($capacity->children() as $unit) {
            $value = $unit->get('standardCargoUnits')
                ?? $unit->get('centiSCU')
                ?? $unit->get('microSCU');

            return $this->clean([
                'Unit' => $unit->nodeName,
                'Value' => $value,
                'UnitName' => self::UNIT_NAMES[$unit->nodeName] ?? null,
                'SCU' => Item::convertToScu($capacity),
            ]);
        }

        return null;
    }

    private function parseReferences(?Element $element): array
    {
        if (! $element) {
            return [];
        }

        $out = [];

        foreach ($element->children() as $reference) {
            $value = $reference->get('value');

            if ($value !== null) {
                $out[] = $value;
            }
        }

        return $out;
    }

    private function parseDefaultComposition(?Element $composition): array
    {
        if (! $composition) {
            return [];
        }

        $out = [];

        foreach ($composition->children() as $entry) {
            $out[] = $this->clean([
                'Entry' => $entry->get('entry'),
                'Weight' => $entry->get('weight'),
            ]);
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
