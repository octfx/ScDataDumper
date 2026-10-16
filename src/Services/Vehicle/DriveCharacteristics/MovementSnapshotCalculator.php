<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Helper\Element;

/**
 * Capture a MovementParams snapshot for a wheeled movement type
 * (ArcadeWheeled, PhysicalWheeled, TrackWheeled)
 */
final class MovementSnapshotCalculator
{
    use NormalizesValues;

    public function __construct(private readonly string $movementKey) {}

    public function movementKey(): string
    {
        return $this->movementKey;
    }

    public function supports(?Vehicle $vehicle): bool
    {
        return $vehicle?->get('MovementParams/'.$this->movementKey) !== null;
    }

    public function calculate(Vehicle $vehicle, float $mass): array
    {
        $movement = $vehicle->get('MovementParams/'.$this->movementKey);

        if (! $movement instanceof Element) {
            return [];
        }

        return [
            'Movement' => [
                $this->movementKey => $this->elementToArray($movement),
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
