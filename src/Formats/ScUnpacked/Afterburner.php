<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Formats\BaseFormat;

class Afterburner extends BaseFormat
{
    protected ?string $elementKey = 'Components/IFCSParams/afterburner';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $afterburner = $this->get();

        $return = $afterburner->attributesToArray([
            'capacitorAssignmentInputOutputRegen',
            'capacitorAssignmentInputOutputRegenNavMode',
            'capacitorAssignmentInputOutputUsage',
        ], pascalCase: true) + [
            'AccelerationMultiplierPositive' => new Vec3($afterburner->get('/afterburnAccelMultiplierPositive'))->toArray(),
            'AccelerationMultiplierNegative' => new Vec3($afterburner->get('/afterburnAccelMultiplierNegative'))->toArray(),
            'AngularMultiplier' => new Vec3($afterburner->get('/afterburnAngVelocityMultiplier'), [
                'x' => 'Pitch',
                'y' => 'Roll',
                'z' => 'Yaw',
            ])->toArray(),
            'AngularAccelerationMultiplier' => new Vec3($afterburner->get('/afterburnAngAccelMultiplier'), [
                'x' => 'Pitch',
                'y' => 'Roll',
                'z' => 'Yaw',
            ])->toArray(),
        ];

        $return['RegenTime'] = $this->calculateRegenTime(
            Arr::get($return, 'CapacitorMax', 0),
            Arr::get($return, 'CapacitorRegenPerSec', 1),
            Arr::get($return, 'CapacitorRegenDelayAfterUse', 0),
            1, // 1 = 100% = All power segments assigned
        );

        return $return;
    }

    /**
     * @param float $capacitorMax
     * @param float $regenPerSec
     * @param float $delayAfterUse
     * @param float|null $powerMultiplier Power multiplier for regen rate (default 1 = 100%)
     * @param float|null $startFrom Capacitor start value (default 0 = Empty, max = capacitorMax)
     * @return float
     */
    private function calculateRegenTime(float $capacitorMax, float $regenPerSec, float $delayAfterUse, ?float $powerMultiplier = 1, ?float $startFrom = 0): float
    {
        return round($delayAfterUse + (($capacitorMax - $startFrom) / ($regenPerSec * $powerMultiplier)), 1);
    }
}
