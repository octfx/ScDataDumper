<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Capture MovementParams snapshot for track wheeled vehicles
 */
final class TrackWheeledCalculator implements DriveCalculatorStrategy
{
    public function supports(?Vehicle $vehicle): bool
    {
        return $vehicle?->get('MovementParams/TrackWheeled') !== null;
    }

    public function calculate(Vehicle $vehicle, float $mass): array
    {
        $trackWheeled = $vehicle->get('MovementParams/TrackWheeled');

        if (! $trackWheeled instanceof Element) {
            return [];
        }

        return [
            'Movement' => [
                'TrackWheeled' => $this->elementToArray($trackWheeled),
            ],
        ];
    }

    /**
     * Convert Element to nested array with PascalCase keys
     */
    private function elementToArray(Element $element): array
    {
        $result = [];

        foreach ($element->attributes as $attr) {
            $result[$this->toPascalCase($attr->name)] = $this->convertValue($attr->value);
        }

        foreach ($element->children() as $child) {
            $childName = $this->toPascalCase($child->nodeName);
            $childArray = $this->elementToArray($child);

            if (isset($result[$childName])) {
                if (! isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $childArray;
            } else {
                $result[$childName] = $childArray;
            }
        }

        return $result;
    }

    /**
     * Convert kebab-case or camelCase attribute names to PascalCase
     */
    private function toPascalCase(string $name): string
    {
        $name = ltrim($name, '@');

        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        return $name;
    }

    /**
     * Convert string value to appropriate type
     */
    private function convertValue(string $value): mixed
    {
        if (is_numeric($value) && str_contains($value, '.')) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        return $value;
    }
}
