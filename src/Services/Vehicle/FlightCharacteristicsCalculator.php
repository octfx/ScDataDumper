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
final class FlightCharacteristicsCalculator
{
    /** Gravitational constant for G-force calculations */
    private const float G = 9.80665;

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
        if (! $ifcsLoadoutEntry || ! isset($ifcsLoadoutEntry['Item']['stdItem']['Ifcs'])) {
            return null;
        }

        $ifcs = $ifcsLoadoutEntry['Item']['stdItem']['Ifcs'];
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
            'Pitch' => $angularRates['Pitch'] !== null ? $angularRates['Pitch'] * $angularMultipliers['Pitch'] : null,
            'Yaw' => $angularRates['Yaw'] !== null ? $angularRates['Yaw'] * $angularMultipliers['Yaw'] : null,
            'Roll' => $angularRates['Roll'] !== null ? $angularRates['Roll'] * $angularMultipliers['Roll'] : null,
        ];

        $accelerationRaw = $this->calculateDirectionalAccelerations($thrustCapacity, $mass);
        $linearMultipliers = $this->extractLinearMultipliers($afterburner);
        $accelerationBoosted = $this->applyMultipliers($accelerationRaw, $linearMultipliers);

        $accelerationRawG = $this->convertToG($accelerationRaw);
        $accelerationBoostedG = $this->convertToG($accelerationBoosted);

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
                'RawG' => $accelerationRawG,
                'BoostedG' => $accelerationBoostedG,
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
        $accelRawG = Arr::get($flightCharacteristics, 'Acceleration.RawG', []);
        $accelBoosted = Arr::get($flightCharacteristics, 'Acceleration.Boosted', []);
        $accelBoostedG = Arr::get($flightCharacteristics, 'Acceleration.BoostedG', []);

        return [
            'pitch' => $round3($angular['Pitch'] ?? null),
            'yaw' => $round3($angular['Yaw'] ?? null),
            'roll' => $round3($angular['Roll'] ?? null),
            'pitch_boosted' => $round3($angularBoosted['Pitch'] ?? null),
            'yaw_boosted' => $round3($angularBoosted['Yaw'] ?? null),
            'roll_boosted' => $round3($angularBoosted['Roll'] ?? null),
            'acceleration' => [
                'forward' => $round3($accelRaw['Forward'] ?? null),
                'backward' => $round3($accelRaw['Backward'] ?? null),
                'up' => $round3($accelRaw['Up'] ?? null),
                'down' => $round3($accelRaw['Down'] ?? null),
                'strafe' => $round3($accelRaw['Strafe'] ?? null),
                'vtol' => $round3($accelRaw['Vtol'] ?? null),
                'forward_boosted' => $round3($accelBoosted['Forward'] ?? null),
                'backward_boosted' => $round3($accelBoosted['Backward'] ?? null),
                'up_boosted' => $round3($accelBoosted['Up'] ?? null),
                'down_boosted' => $round3($accelBoosted['Down'] ?? null),
                'strafe_boosted' => $round3($accelBoosted['Strafe'] ?? null),
                'vtol_boosted' => $round3($accelBoosted['Vtol'] ?? null),
            ],
            'acceleration_g' => [
                'forward_g' => $round3($accelRawG['Forward'] ?? null),
                'backward_g' => $round3($accelRawG['Backward'] ?? null),
                'up_g' => $round3($accelRawG['Up'] ?? null),
                'down_g' => $round3($accelRawG['Down'] ?? null),
                'strafe_g' => $round3($accelRawG['Strafe'] ?? null),
                'vtol_g' => $round3($accelRawG['Vtol'] ?? null),
                'forward_boosted_g' => $round3($accelBoostedG['Forward'] ?? null),
                'backward_boosted_g' => $round3($accelBoostedG['Backward'] ?? null),
                'up_boosted_g' => $round3($accelBoostedG['Up'] ?? null),
                'down_boosted_g' => $round3($accelBoostedG['Down'] ?? null),
                'strafe_boosted_g' => $round3($accelBoostedG['Strafe'] ?? null),
                'vtol_boosted_g' => $round3($accelBoostedG['Vtol'] ?? null),
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
                'Forward' => 0,
                'Backward' => 0,
                'Up' => 0,
                'Down' => 0,
                'Strafe' => 0,
                'Vtol' => 0,
            ];
        }

        return [
            'Forward' => ($thrustCapacity['Main'] ?? 0) / $mass,
            'Backward' => ($thrustCapacity['Retro'] ?? 0) / $mass,
            'Up' => ($thrustCapacity['Up'] ?? 0) / $mass + ($thrustCapacity['Vtol'] ?? 0) / $mass,
            'Down' => ($thrustCapacity['Down'] ?? 0) / $mass,
            'Strafe' => ($thrustCapacity['Strafe'] ?? 0) / $mass,
            'Vtol' => ($thrustCapacity['Vtol'] ?? 0) / $mass,
        ];
    }

    /**
     * Convert acceleration array to G-forces
     */
    private function convertToG(array $accelerations): array
    {
        return array_map(static fn ($value) => $value !== null ? $value / self::G : null, $accelerations);
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
        $forwardAccel = $accelerationRaw['Forward'] ?? 0;
        $backwardAccel = $accelerationRaw['Backward'] ?? 0;
        $forwardAccelBoosted = $accelerationBoosted['Forward'] ?? 0;
        $backwardAccelBoosted = $accelerationBoosted['Backward'] ?? 0;

        return [
            'ZeroToScm' => $forwardAccel > 0 ? $scmSpeed / $forwardAccel : null,
            'ZeroToMax' => $forwardAccel > 0 ? $maxSpeed / $forwardAccel : null,
            'ScmToZero' => $backwardAccel > 0 ? $scmSpeed / $backwardAccel : null,
            'MaxToZero' => $backwardAccel > 0 ? $maxSpeed / $backwardAccel : null,
            'ZeroToBoostForward' => ($boostForward !== null && $forwardAccelBoosted > 0) ? $boostForward / $forwardAccelBoosted : null,
            'ZeroToBoostBackward' => ($boostBackward !== null && $backwardAccelBoosted > 0) ? $boostBackward / $backwardAccelBoosted : null,
            'BoostForwardToZero' => ($boostForward !== null && $backwardAccelBoosted > 0) ? $boostForward / $backwardAccelBoosted : null,
            'BoostBackwardToZero' => ($boostBackward !== null && $forwardAccelBoosted > 0) ? $boostBackward / $forwardAccelBoosted : null,
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
            'Forward' => $forward,
            'Backward' => $backward,
            'Up' => $up,
            'Down' => $down,
            'Strafe' => $strafe,
            'Vtol' => $up,
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
}
