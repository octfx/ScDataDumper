<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Calculate drive characteristics for arcade wheeled vehicles
 * (Simple read from MovementParams)
 */
final class ArcadeWheeledCalculator implements DriveCalculatorStrategy
{
    public function supports(?Vehicle $vehicle): bool
    {
        return $vehicle?->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed') !== null;
    }

    public function calculate(Vehicle $vehicle, float $mass): array
    {
        $topSpeed = $vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@topSpeed');
        $reverseSpeed = $vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@reverseSpeed');
        $acceleration = $vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@acceleration');
        $decceleration = $vehicle->get('MovementParams/ArcadeWheeled/Handling/Power@decceleration');

        return [
            'TopSpeed' => $topSpeed,
            'ReverseSpeed' => $reverseSpeed,
            'Acceleration' => $acceleration,
            'Decceleration' => $decceleration,
            'ZeroToMax' => $acceleration > 0 ? $topSpeed / $acceleration : null,
            'ZeroToReverse' => $acceleration > 0 ? $reverseSpeed / $acceleration : null,
            'MaxToZero' => $decceleration > 0 ? $topSpeed / $decceleration : null,
            'ReverseToZero' => $decceleration > 0 ? $reverseSpeed / $decceleration : null,
        ];
    }
}
