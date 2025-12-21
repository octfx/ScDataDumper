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
            'AccelerationMultiplierPositive' => new Vec3($afterburner->get('/afterburnAccelMultiplierPositive')),
            'AccelerationMultiplierNegative' => new Vec3($afterburner->get('/afterburnAccelMultiplierNegative')),
            'AngularMultiplier' => new Vec3($afterburner->get('/afterburnAngVelocityMultiplier'), [
                'x' => 'Pitch',
                'y' => 'Yaw',
                'z' => 'Roll',
            ]),
            'AngularAccelerationMultiplier' => new Vec3($afterburner->get('/afterburnAngAccelMultiplier'), [
                'x' => 'Pitch',
                'y' => 'Yaw',
                'z' => 'Roll',
            ]),
        ];

        $return['RegenTime'] = round(Arr::get($return, 'CapacitorRegenDelayAfterUse', 0) + (Arr::get($return, 'CapacitorMax', 0) / Arr::get($return, 'CapacitorRegenPerSec', 1)));

        return $return;
    }
}
