<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Calculate drive characteristics for physical wheeled vehicles
 * (Complex torque/mass calculations)
 */
final class PhysicalWheeledCalculator implements DriveCalculatorStrategy
{
    public function supports(?Vehicle $vehicle): bool
    {
        return $vehicle?->get('MovementParams/PhysicalWheeled/PhysicsParams@wWheelsMax') !== null;
    }

    public function calculate(Vehicle $vehicle, float $mass): array
    {
        $wWheelsMax = $vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams@wWheelsMax');
        $brakeTorque = $vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams@brakeTorque');
        $torqueScale = $vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Engine@torqueScale');
        $gearFirst = $vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Gears@first');

        // Spartan fix
        if ($wWheelsMax === 26000000.0) {
            $wWheelsMax = 26;
        }

        $wheelRadius = $vehicle->get('//SubPartWheel@rimRadius');
        $peakTorque = $this->extractPeakTorque($vehicle);

        if (! $wheelRadius || ! $wWheelsMax || ! $mass || ! $brakeTorque || ! $peakTorque || ! $torqueScale || ! $gearFirst) {
            return $this->nullResult();
        }

        $topSpeed = $wWheelsMax * $wheelRadius; // m/s
        $topSpeedKph = $topSpeed * 3.6;

        $reverseSpeed = $wWheelsMax * $wheelRadius; // m/s
        $reverseSpeedKph = $reverseSpeed * 3.6;

        // This does not seem correct, but ohwell
        $wheelTorque = $peakTorque * $torqueScale * $gearFirst;
        $force = $wheelTorque / $wheelRadius;
        $acceleration = $force / $mass;

        $brakeForce = $brakeTorque / $wheelRadius;
        $deceleration = $brakeForce / $mass;

        return [
            'TopSpeed' => round($topSpeedKph, 2),
            'ReverseSpeed' => round($reverseSpeedKph, 2),
            'Acceleration' => round($acceleration, 4),
            'Decceleration' => round($deceleration, 4),
            'ZeroToMax' => round($topSpeed / $acceleration, 2),
            'ZeroToReverse' => round($topSpeed / $acceleration, 2),
            'MaxToZero' => round($topSpeed / $deceleration, 2),
            'ReverseToZero' => round($topSpeed / $deceleration, 2),
        ];
    }

    private function extractPeakTorque(Vehicle $vehicle): ?float
    {
        $peakTorque = null;
        $torqueTable = $vehicle->get('MovementParams/PhysicalWheeled/PhysicsParams/Engine/RPMTorqueTable')?->children() ?? [];

        foreach ($torqueTable as $entry) {
            /** @var $entry Element */
            $torque = $entry->get('torque', 0);
            $peakTorque = max($peakTorque ?? $torque, $torque);
        }

        return $peakTorque;
    }

    private function nullResult(): array
    {
        return [
            'TopSpeed' => null,
            'ReverseSpeed' => null,
            'Acceleration' => null,
            'Decceleration' => null,
            'ZeroToMax' => null,
            'ZeroToReverse' => null,
            'MaxToZero' => null,
            'ReverseToZero' => null,
        ];
    }
}
