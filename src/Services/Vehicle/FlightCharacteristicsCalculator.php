<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Helper\Arr;

/**
 * Calculates flight characteristics for spacecraft
 *
 * Processes IFCS (Intelligent Flight Control System) parameters to calculate:
 * - Speed values (SCM, Max, Boost)
 * - Acceleration (linear and in G-forces)
 * - Angular velocity (Pitch, Yaw, Roll)
 * - Afterburner parameters
 * - Timing calculations (zero-to-speed transitions)
 * - Agility metrics
 */
final class FlightCharacteristicsCalculator
{
    /** Gravitational constant for G-force calculations */
    private const G = 9.80665;

    /**
     * Calculate complete flight characteristics from IFCS data
     *
     * @param  array|null  $ifcsLoadoutEntry  Loadout entry containing IFCSParams
     * @param  float  $mass  Ship mass in kg
     * @param  array  $thrustCapacity  Thrust capacity array with Main, Retro, Vtol, Maneuvering keys
     * @return array|null Flight characteristics array, or null if no IFCS data
     */
    public function calculate(?array $ifcsLoadoutEntry, float $mass, array $thrustCapacity): ?array
    {
        if (! $ifcsLoadoutEntry || ! isset($ifcsLoadoutEntry['Item']['Components']['IFCSParams'])) {
            return null;
        }

        $ifcsParams = $ifcsLoadoutEntry['Item']['Components']['IFCSParams'];

        // Calculate accelerations
        $acceleration = $this->calculateAccelerations($thrustCapacity, $mass);
        $accelerationG = $this->calculateAccelerationsInG($thrustCapacity, $mass);

        // Build base flight characteristics
        $flightCharacteristics = [
            'ScmSpeed' => Arr::get($ifcsParams, 'scmSpeed'),
            'MaxSpeed' => Arr::get($ifcsParams, 'maxSpeed'),
            'BoostSpeedForward' => Arr::get($ifcsParams, 'boostSpeedForward'),
            'BoostSpeedBackward' => Arr::get($ifcsParams, 'boostSpeedBackward'),
            'Acceleration' => $acceleration,
            'AccelerationG' => $accelerationG,
            'Pitch' => Arr::get($ifcsParams, 'maxAngularVelocity.x'),
            'Yaw' => Arr::get($ifcsParams, 'maxAngularVelocity.z'),
            'Roll' => Arr::get($ifcsParams, 'maxAngularVelocity.y'),
            'PitchBoostMultiplier' => Arr::get($ifcsParams, 'afterburner.afterburnAngVelocityMultiplier.x'),
            'YawBoostMultiplier' => Arr::get($ifcsParams, 'afterburner.afterburnAngVelocityMultiplier.z'),
            'RollBoostMultiplier' => Arr::get($ifcsParams, 'afterburner.afterburnAngVelocityMultiplier.y'),
            'Afterburner' => [
                'PreDelayTime' => Arr::get($ifcsParams, 'afterburner.afterburnerPreDelayTime'),
                'RampUpTime' => Arr::get($ifcsParams, 'afterburner.afterburnerRampUpTime'),
                'RampDownTime' => Arr::get($ifcsParams, 'afterburner.afterburnerRampDownTime'),
                'Capacitor' => Arr::get($ifcsParams, 'afterburner.capacitorMax'),
                'IdleCost' => Arr::get($ifcsParams, 'afterburner.capacitorAfterburnerIdleCost'),
                'LinearCost' => Arr::get($ifcsParams, 'afterburner.capacitorAfterburnerLinearCost'),
                'AngularCost' => Arr::get($ifcsParams, 'afterburner.capacitorAfterburnerAngularCost'),
                'RegenPerSec' => Arr::get($ifcsParams, 'afterburner.capacitorRegenPerSec'),
                'RegenDelayAfterUse' => Arr::get($ifcsParams, 'afterburner.capacitorRegenDelayAfterUse'),
            ],
        ];

        return array_merge(
            $flightCharacteristics,
            $this->calculateTimings($flightCharacteristics)
        );
    }

    /**
     * Extract agility data from flight characteristics
     *
     * Creates a separate agility structure with rounded values
     *
     * @param  array  $flightCharacteristics  Flight characteristics array
     * @return array Agility data array
     */
    public function extractAgilityData(array $flightCharacteristics): array
    {
        $round3 = static fn ($value) => $value !== null ? round($value, 3) : null;

        $agilityAcceleration = [
            'main' => $flightCharacteristics['Acceleration']['Main'] ?? null,
            'retro' => $flightCharacteristics['Acceleration']['Retro'] ?? null,
            'vtol' => $flightCharacteristics['Acceleration']['Vtol'] ?? null,
            'maneuvering' => $flightCharacteristics['Acceleration']['Maneuvering'] ?? null,
        ];

        return [
            'pitch' => $round3($flightCharacteristics['Pitch'] ?? null),
            'yaw' => $round3($flightCharacteristics['Yaw'] ?? null),
            'roll' => $round3($flightCharacteristics['Roll'] ?? null),
            'acceleration' => [
                'main' => $round3($agilityAcceleration['main']),
                'retro' => $round3($agilityAcceleration['retro']),
                'vtol' => $round3($agilityAcceleration['vtol']),
                'maneuvering' => $round3($agilityAcceleration['maneuvering']),
                'main_g' => $round3($agilityAcceleration['main'] !== null ? $agilityAcceleration['main'] / self::G : null),
                'retro_g' => $round3($agilityAcceleration['retro'] !== null ? $agilityAcceleration['retro'] / self::G : null),
                'vtol_g' => $round3($agilityAcceleration['vtol'] !== null ? $agilityAcceleration['vtol'] / self::G : null),
                'maneuvering_g' => $round3($agilityAcceleration['maneuvering'] !== null ? $agilityAcceleration['maneuvering'] / self::G : null),
            ],
        ];
    }

    /**
     * Calculate linear accelerations from thrust and mass
     *
     * @param  array  $thrustCapacity  Thrust capacity in Newtons
     * @param  float  $mass  Ship mass in kg
     * @return array Acceleration in m/s²
     */
    private function calculateAccelerations(array $thrustCapacity, float $mass): array
    {
        if ($mass <= 0) {
            return [
                'Main' => 0,
                'Retro' => 0,
                'Vtol' => 0,
                'Maneuvering' => 0,
            ];
        }

        return [
            'Main' => ($thrustCapacity['Main'] ?? 0) / $mass,
            'Retro' => ($thrustCapacity['Retro'] ?? 0) / $mass,
            'Vtol' => ($thrustCapacity['Vtol'] ?? 0) / $mass,
            'Maneuvering' => ($thrustCapacity['Maneuvering'] ?? 0) / $mass,
        ];
    }

    /**
     * Calculate accelerations in G-forces
     *
     * @param  array  $thrustCapacity  Thrust capacity in Newtons
     * @param  float  $mass  Ship mass in kg
     * @return array Acceleration in G
     */
    private function calculateAccelerationsInG(array $thrustCapacity, float $mass): array
    {
        if ($mass <= 0) {
            return [
                'Main' => 0,
                'Retro' => 0,
                'Vtol' => 0,
                'Maneuvering' => 0,
            ];
        }

        return [
            'Main' => ($thrustCapacity['Main'] ?? 0) / $mass / self::G,
            'Retro' => ($thrustCapacity['Retro'] ?? 0) / $mass / self::G,
            'Vtol' => ($thrustCapacity['Vtol'] ?? 0) / $mass / self::G,
            'Maneuvering' => ($thrustCapacity['Maneuvering'] ?? 0) / $mass / self::G,
        ];
    }

    /**
     * Calculate timing values (zero-to-speed transitions)
     *
     * @param  array  $flightCharacteristics  Flight characteristics with speed and acceleration data
     * @return array Timing calculations
     */
    private function calculateTimings(array $flightCharacteristics): array
    {
        $scmSpeed = $flightCharacteristics['ScmSpeed'] ?? 0;
        $maxSpeed = $flightCharacteristics['MaxSpeed'] ?? 0;
        $mainAccel = $flightCharacteristics['Acceleration']['Main'] ?? 0;
        $retroAccel = $flightCharacteristics['Acceleration']['Retro'] ?? 0;

        return [
            'ZeroToScm' => $mainAccel > 0 ? $scmSpeed / $mainAccel : null,
            'ZeroToMax' => $mainAccel > 0 ? $maxSpeed / $mainAccel : null,
            'ScmToZero' => $retroAccel > 0 ? $scmSpeed / $retroAccel : null,
            'MaxToZero' => $retroAccel > 0 ? $maxSpeed / $retroAccel : null,
        ];
    }
}
