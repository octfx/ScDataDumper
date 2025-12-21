<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Definitions\Element;

/**
 * Extract compatible types from port definitions
 *
 * Handles both Element-based (DOMNode) and array-based port type extraction.
 */
final class CompatibleTypesExtractor
{
    /**
     * Extract compatible types from Element
     */
    public function fromElement(Element $port): array
    {
        $types = [];

        foreach ($port->get('/Types')?->children() ?? [] as $portType) {
            $major = $portType->get('@Type') ?? $portType->get('@type');

            if (empty($major)) {
                continue;
            }

            $subTypes = [];

            // Extract from SubTypes children
            foreach ($portType->get('/SubTypes')?->children() ?? [] as $subType) {
                $value = $subType->get('value');
                if (! empty($value)) {
                    $subTypes[] = $value;
                }
            }

            // Extract from SubTypes attribute
            $subTypeAttr = $portType->get('@SubTypes') ?? $portType->get('@subtypes');
            if (! empty($subTypeAttr)) {
                foreach (explode(',', $subTypeAttr) as $subType) {
                    $trimmed = trim($subType);
                    if (! empty($trimmed)) {
                        $subTypes[] = $trimmed;
                    }
                }
            }

            $types[] = [
                'type' => $major,
                'sub_types' => array_values(array_unique(array_filter($subTypes))),
            ];
        }

        return $types;
    }

    /**
     * Extract compatible types from array
     */
    public function fromArray(array $port): array
    {
        $types = Arr::get($port, 'Types', []);

        if (! is_array($types)) {
            return [];
        }

        // Normalize to array of types
        if (isset($types['SItemPortDefTypes'])) {
            $types = [$types['SItemPortDefTypes']];
        } elseif (isset($types['Type']) || isset($types['type'])) {
            $types = [$types];
        }

        $mapped = [];

        foreach ($types as $type) {
            $major = $type['Type'] ?? $type['type'] ?? null;

            if (! $major) {
                continue;
            }

            $subTypes = [];
            $subs = $type['SubTypes'] ?? $type['subTypes'] ?? $type['sub_types'] ?? null;

            if (is_array($subs)) {
                foreach ($subs as $subType) {
                    if (is_array($subType) && isset($subType['value'])) {
                        $subTypes[] = $subType['value'];
                    } elseif (is_string($subType)) {
                        $subTypes[] = $subType;
                    }
                }
            }

            $mapped[] = [
                'type' => $major,
                'sub_types' => array_values(array_filter(array_unique($subTypes))),
            ];
        }

        return $mapped;
    }
}
