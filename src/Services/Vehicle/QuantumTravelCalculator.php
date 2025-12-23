<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Calculate quantum travel characteristics
 *
 * Calculates fuel capacity, range, speed, spool time, and travel times
 * for quantum drives.
 */
final class QuantumTravelCalculator
{
    /** Distance between Port Olisar and ArcCorp in meters */
    private const int DIST_PO_TO_ARCCORP = 41927351070;

    /**
     * Calculate quantum travel data from port summary
     *
     * @param  array  $portSummary  Port summary with quantum drives and fuel tanks
     * @return array Quantum travel data
     */
    public function calculate(array $portSummary): array
    {
        $quantumDrive = collect($portSummary['quantumDrives'])->first(fn ($x) => isset($x['InstalledItem']));

        $quantumFuelCapacity = collect($portSummary['quantumFuelTanks'])->sum(
            fn ($x) => Arr::get($x, 'InstalledItem.stdItem.ResourceContainer.Capacity.SCU') * 1000
        );

        $quantumFuelRate = Arr::get($quantumDrive, 'InstalledItem.stdItem.QuantumDrive.QuantumFuelRequirement', 0) / 1e6;
        $quantumDriveSpeed = Arr::get($quantumDrive, 'InstalledItem.stdItem.QuantumDrive.StandardJump.DriveSpeed');

        return [
            'FuelCapacity' => ($quantumFuelCapacity ?? 0) > 0 ? $quantumFuelCapacity : null,
            'Range' => $quantumFuelRate > 0 ? ($quantumFuelCapacity / $quantumFuelRate) : null,
            'Speed' => $quantumDriveSpeed,
            'SpoolTime' => Arr::get($quantumDrive, 'InstalledItem.stdItem.QuantumDrive.StandardJump.SpoolUpTime'),
            'PortOlisarToArcCorpTime' => ! empty($quantumDriveSpeed)
                ? (self::DIST_PO_TO_ARCCORP / $quantumDriveSpeed)
                : null,
            'PortOlisarToArcCorpFuel' => (self::DIST_PO_TO_ARCCORP * $quantumFuelRate) > 0
                ? (self::DIST_PO_TO_ARCCORP * $quantumFuelRate)
                : null,
            'PortOlisarToArcCorpAndBack' => $quantumFuelRate > 0
                ? (($quantumFuelCapacity / $quantumFuelRate) / (2 * self::DIST_PO_TO_ARCCORP))
                : null,
        ];
    }
}
