<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Calculates flight characteristics for spacecraft
 *
 * Processes IFCS (Intelligent Flight Control System) parameters to calculate:
 * - Authoritative speed and angular rate values from IFCS
 * - Raw and boosted linear acceleration (derived from thrust + afterburner)
 * - Afterburner-derived angular boosts
 * - Timing calculations using IFCS speeds and thrust-derived acceleration
 * - Agility metrics
 */
final class FlightCharacteristicsCalculator implements VehicleDataCalculator
{
    /**
     * Calculate complete flight characteristics from IFCS data
     *
     * @param  array|null  $ifcsLoadoutEntry  Loadout entry containing IFCSParams
     * @param  float  $mass  Ship mass in kg
     * @param  array  $thrustCapacity  Thrust capacity array with Main, Retro, Vtol, Maneuvering keys
     * @return array|null Flight characteristics array, or null if no IFCS data
     */
    public function calculateCharacteristics(?array $ifcsLoadoutEntry, float $mass, array $thrustCapacity): ?array
    {
        if (! $ifcsLoadoutEntry || ! Arr::has($ifcsLoadoutEntry, 'Ifcs')) {
            return null;
        }

        $ifcs = $ifcsLoadoutEntry['Ifcs'];
        [$afterburnerMode, $afterburner, $afterburnerLegacy, $afterburnerNew] = $this->resolveAfterburner($ifcs);

        $speeds = [
            'Scm' => Arr::get($ifcs, 'ScmSpeed'),
            'Max' => Arr::get($ifcs, 'MaxSpeed'),
            'BoostForward' => Arr::get($ifcs, 'BoostSpeedForward'),
            'BoostBackward' => Arr::get($ifcs, 'BoostSpeedBackward'),
        ];

        $angularRates = [
            'Pitch' => Arr::get($ifcs, 'Pitch'),
            'Yaw' => Arr::get($ifcs, 'Yaw'),
            'Roll' => Arr::get($ifcs, 'Roll'),
        ];

        $angularMultipliers = $this->extractAngularMultipliers($afterburner);

        $angularBoosted = [
            'Pitch' => $angularRates['Pitch'] !== null ? round($angularRates['Pitch'] * $angularMultipliers['Pitch'], 2) : null,
            'Yaw' => $angularRates['Yaw'] !== null ? round($angularRates['Yaw'] * $angularMultipliers['Yaw'], 2) : null,
            'Roll' => $angularRates['Roll'] !== null ? round($angularRates['Roll'] * $angularMultipliers['Roll'], 2) : null,
        ];

        $accelerationRaw = $this->calculateDirectionalAccelerations($thrustCapacity, $mass);
        $linearMultipliers = $this->extractLinearMultipliers($afterburner);
        $accelerationBoosted = $this->applyMultipliers($accelerationRaw, $linearMultipliers);
        $flightCharacteristics = [
            'Ifcs' => $ifcs,
            //            'AfterburnerMode' => $afterburnerMode,
            'Afterburner' => $afterburner,
            //            'AfterburnerLegacy' => $afterburnerLegacy,
            //            'AfterburnerNew' => $afterburnerNew,
            'Speeds' => $speeds,
            'AngularRates' => $angularRates,
            'AngularRatesBoosted' => $angularBoosted,
            'AngularRateMultipliers' => $angularMultipliers,
            'Acceleration' => [
                'Raw' => $accelerationRaw,
                'Boosted' => $accelerationBoosted,
                'BoostMultipliers' => $linearMultipliers,
            ],
        ];

        return array_merge(
            $flightCharacteristics,
            ['Timing' => $this->calculateTimings($speeds, $accelerationRaw, $accelerationBoosted)]
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

        $angular = Arr::get($flightCharacteristics, 'AngularRates', []);
        $angularBoosted = Arr::get($flightCharacteristics, 'AngularRatesBoosted', []);
        $accelRaw = Arr::get($flightCharacteristics, 'Acceleration.Raw', []);
        $accelBoosted = Arr::get($flightCharacteristics, 'Acceleration.Boosted', []);

        return [
            'pitch' => $round3($angular['Pitch'] ?? null),
            'yaw' => $round3($angular['Yaw'] ?? null),
            'roll' => $round3($angular['Roll'] ?? null),
            'pitch_boosted' => $round3($angularBoosted['Pitch'] ?? null),
            'yaw_boosted' => $round3($angularBoosted['Yaw'] ?? null),
            'roll_boosted' => $round3($angularBoosted['Roll'] ?? null),
            'acceleration' => [
                'main' => $round3($accelRaw['Main'] ?? null),
                'retro' => $round3($accelRaw['Retro'] ?? null),
                'vtol' => $round3($accelRaw['Vtol'] ?? null),
                'maneuver' => $round3($accelRaw['Maneuver'] ?? null),
                'main_boosted' => $round3($accelBoosted['Main'] ?? null),
                'retro_boosted' => $round3($accelBoosted['Retro'] ?? null),
                'vtol_boosted' => $round3($accelBoosted['Vtol'] ?? null),
                'maneuver_boosted' => $round3($accelBoosted['Maneuver'] ?? null),
            ],
        ];
    }

    /**
     * Calculate directional accelerations from thrust and mass
     *
     * @param  array  $thrustCapacity  Thrust capacity in Newtons
     * @param  float  $mass  Ship mass in kg
     * @return array Acceleration in m/s²
     */
    private function calculateDirectionalAccelerations(array $thrustCapacity, float $mass): array
    {
        if ($mass <= 0) {
            return [
                'Main' => 0,
                'Retro' => 0,
                'Vtol' => 0,
                'Maneuver' => 0,
            ];
        }

        return [
            'Main' => round(($thrustCapacity['Main'] ?? 0) / $mass, 2),
            'Retro' => round(($thrustCapacity['Retro'] ?? 0) / $mass, 2),
            'Vtol' => round(($thrustCapacity['Vtol'] ?? 0) / $mass, 2),
            'Maneuver' => round(($thrustCapacity['Maneuvering'] ?? 0) / $mass, 2),
        ];
    }


    /**
     * Calculate timing values (zero-to-speed transitions)
     *
     * @param  array  $speeds  Speed values from IFCS
     * @param  array  $accelerationRaw  Raw acceleration values
     * @param  array  $accelerationBoosted  Boosted acceleration values
     * @return array Timing calculations
     */
    private function calculateTimings(array $speeds, array $accelerationRaw, array $accelerationBoosted): array
    {
        $scmSpeed = $speeds['Scm'] ?? 0;
        $maxSpeed = $speeds['Max'] ?? 0;
        $boostForward = $speeds['BoostForward'] ?? null;
        $boostBackward = $speeds['BoostBackward'] ?? null;
        $forwardAccel = $accelerationRaw['Main'] ?? 0;
        $backwardAccel = $accelerationRaw['Retro'] ?? 0;
        $forwardAccelBoosted = $accelerationBoosted['Main'] ?? 0;
        $backwardAccelBoosted = $accelerationBoosted['Retro'] ?? 0;

        return [
            'ZeroToScm' => $forwardAccel > 0 ? round($scmSpeed / $forwardAccel, 2) : null,
            'ZeroToMax' => $forwardAccel > 0 ? round($maxSpeed / $forwardAccel, 2) : null,
            'ScmToZero' => $backwardAccel > 0 ? round($scmSpeed / $backwardAccel, 2) : null,
            'MaxToZero' => $backwardAccel > 0 ? round($maxSpeed / $backwardAccel, 2) : null,
            'ZeroToBoostForward' => ($boostForward !== null && $forwardAccelBoosted > 0) ? round($boostForward / $forwardAccelBoosted, 2) : null,
            'ZeroToBoostBackward' => ($boostBackward !== null && $backwardAccelBoosted > 0) ? round($boostBackward / $backwardAccelBoosted, 2) : null,
            'BoostForwardToZero' => ($boostForward !== null && $backwardAccelBoosted > 0) ? round($boostForward / $backwardAccelBoosted, 2) : null,
            'BoostBackwardToZero' => ($boostBackward !== null && $forwardAccelBoosted > 0) ? round($boostBackward / $forwardAccelBoosted, 2) : null,
        ];
    }

    /**
     * Resolve afterburner data
     *
     * @return array{0: string|null, 1: array, 2: array|null, 3: array|null}
     */
    private function resolveAfterburner(array $ifcs): array
    {
        $afterburnerNew = Arr::get($ifcs, 'AfterburnerNew');
        $afterburner = Arr::get($ifcs, 'Afterburner');

        //        if (is_array($afterburnerNew) && $afterburnerNew !== []) {
        //            return ['AfterburnerNew', $afterburnerNew, $afterburner, $afterburnerNew];
        //        }

        if (is_array($afterburner) && $afterburner !== []) {
            return ['Afterburner', $afterburner, $afterburner, $afterburnerNew];
        }

        return [null, [], $afterburner, $afterburnerNew];
    }

    /**
     * Extract angular multipliers from afterburner data
     *
     * @return array{Pitch: float, Yaw: float, Roll: float}
     */
    private function extractAngularMultipliers(array $afterburner): array
    {
        return [
            'Pitch' => $this->coerceFloat(Arr::get($afterburner, 'AngularAccelerationMultiplier.Pitch', 1), 1),
            'Yaw' => $this->coerceFloat(Arr::get($afterburner, 'AngularAccelerationMultiplier.Yaw', 1), 1),
            'Roll' => $this->coerceFloat(Arr::get($afterburner, 'AngularAccelerationMultiplier.Roll', 1), 1),
        ];
    }

    /**
     * Extract linear acceleration multipliers from afterburner data
     */
    private function extractLinearMultipliers(array $afterburner): array
    {
        $pos = Arr::get($afterburner, 'AccelerationMultiplierPositive', []);
        $neg = Arr::get($afterburner, 'AccelerationMultiplierNegative', []);

        $forward = $this->coerceFloat(Arr::get($pos, 'y', 1), 1);
        $backward = $this->coerceFloat(Arr::get($neg, 'y', $forward), $forward);
        $up = $this->coerceFloat(Arr::get($pos, 'z', 1), 1);
        $down = $this->coerceFloat(Arr::get($neg, 'z', $up), $up);
        $strafePos = $this->coerceFloat(Arr::get($pos, 'x', 1), 1);
        $strafeNeg = $this->coerceFloat(Arr::get($neg, 'x', $strafePos), $strafePos);
        $strafe = max($strafePos, $strafeNeg);

        return [
            'Main' => $forward,
            'Retro' => $backward,
            'Vtol' => $up,
            'Maneuver' => max($up, $down, $strafe),
        ];
    }

    private function applyMultipliers(array $acceleration, array $multipliers): array
    {
        $out = [];
        foreach ($acceleration as $key => $value) {
            $multiplier = $multipliers[$key] ?? 1;
            $out[$key] = $value !== null ? $value * $multiplier : null;
        }

        return $out;
    }

    private function coerceFloat(mixed $value, float $default): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public function canCalculate(VehicleDataContext $context): bool
    {
        return $context->isSpaceship || $context->isGravlev;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $thrustCapacity = $context->intermediateResults['Propulsion']['ThrustCapacity'] ?? [];

        $flightCharacteristics = $this->calculateCharacteristics(
            $context->ifcsLoadoutEntry,
            $context->mass,
            $thrustCapacity
        );

        if ($flightCharacteristics === null) {
            return [];
        }

        $agility = $this->extractAgilityData($flightCharacteristics);

        return [
            'FlightCharacteristics' => $flightCharacteristics,
            'Agility' => $agility,
        ];
    }

    public function getPriority(): int
    {
        return 30;
    }
}
