<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataCalculator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;

/**
 * Adapter for DriveCharacteristicsCalculator to work with VehicleDataOrchestrator
 */
final readonly class DriveCharacteristicsVehicleCalculator implements VehicleDataCalculator
{
    public function __construct(
        private DriveCharacteristicsCalculator $calculator,
        private VehicleWrapper $wrapper,
    ) {}

    public function canCalculate(VehicleDataContext $context): bool
    {
        return $context->isVehicle && $this->wrapper->vehicle !== null;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $loadoutMass = $context->intermediateResults['mass_loadout'] ?? 0.0;
        $totalMass = $context->mass + $loadoutMass;

        $result = $this->calculator->calculate(
            $this->wrapper->vehicle,
            $totalMass
        );

        return $result !== null
            ? ['DriveCharacteristics' => $result]
            : [];
    }

    public function getPriority(): int
    {
        return 50;
    }
}
