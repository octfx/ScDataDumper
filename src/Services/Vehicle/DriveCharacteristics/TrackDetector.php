<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Detect tracked vehicles and extract track information
 */
final class TrackDetector
{
    /**
     * Detect tracks for a vehicle
     *
     * @param  Vehicle|null  $vehicle  The vehicle to check
     * @return array Track information with IsTracked and TrackCount
     */
    public function detect(?Vehicle $vehicle): array
    {
        if (! $vehicle) {
            return $this->emptyResult();
        }

        $trackWheeled = $vehicle->get('MovementParams/TrackWheeled');

        if ($trackWheeled === null) {
            return $this->emptyResult();
        }

        $trackCount = $vehicle->get('MovementParams/TrackWheeled/Tracks@count');

        if ($trackCount === null) {
            $trackCount = 2;
        }

        return [
            'IsTracked' => true,
            'TrackCount' => (int) $trackCount,
        ];
    }

    /**
     * Return empty result for non-tracked vehicles
     *
     * @return array Empty track result
     */
    private function emptyResult(): array
    {
        return [
            'IsTracked' => false,
            'TrackCount' => null,
        ];
    }
}
