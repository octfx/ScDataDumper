<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Capture MovementParams snapshot for arcade wheeled vehicles
 */
final class ArcadeWheeledCalculator implements DriveCalculatorStrategy
{
    use NormalizesValues;

    public function supports(?Vehicle $vehicle): bool
    {
        return $vehicle?->get('MovementParams/ArcadeWheeled') !== null;
    }

    public function calculate(Vehicle $vehicle, float $mass): array
    {
        $arcadeWheeled = $vehicle->get('MovementParams/ArcadeWheeled');

        if (! $arcadeWheeled instanceof Element) {
            return [];
        }

        return [
            'Movement' => [
                'ArcadeWheeled' => $this->elementToArray($arcadeWheeled),
            ],
        ];
    }

    /**
     * Convert Element to nested array with PascalCase keys
     */
    private function elementToArray(Element $element): array
    {
        $result = [];

        // Add attributes
        foreach ($element->attributes as $attr) {
            $result[$this->toPascalCase($attr->name)] = $this->convertValue($attr->value);
        }

        // Add child elements
        foreach ($element->children() as $child) {
            $childName = $this->toPascalCase($child->nodeName);
            $childArray = $this->elementToArray($child);

            // Handle multiple elements with same name
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

    private function convertValue(string $value): mixed
    {
        // Check for float
        if (is_numeric($value) && str_contains($value, '.')) {
            return (float) $value;
        }

        // Check for integer
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Check for boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        return $value;
    }
}
