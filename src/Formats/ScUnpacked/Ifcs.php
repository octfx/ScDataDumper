<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Ifcs extends BaseFormat
{
    protected ?string $elementKey = 'Components/IFCSParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $ifcs = $this->get();
        $attributes = $ifcs->attributesToArray(
            [
                'pitchYawLimiterType',
                'linearLimiterType',
                'thrusterImbalanceMessage',
                'intoxicationModifierRef',
            ],
            pascalCase: true
        );

        $afterburner = new Afterburner($this->item)->toArray();

        return $attributes + [
            'Pitch' => $ifcs->get('maxAngularVelocity@x'),
            'Yaw' => $ifcs->get('maxAngularVelocity@z'),
            'Roll' => $ifcs->get('maxAngularVelocity@y'),
            'PitchBoosted' => round($ifcs->get('maxAngularVelocity@x') * Arr::get($afterburner, 'AngularMultiplier.Pitch', 1)),
            'YawBoosted' => round($ifcs->get('maxAngularVelocity@z') * Arr::get($afterburner, 'AngularMultiplier.Yaw', 1)),
            'RollBoosted' => round($ifcs->get('maxAngularVelocity@y') * Arr::get($afterburner, 'AngularMultiplier.Roll', 1)),

            'Afterburner' => $afterburner,
            'AfterburnerNew' => new AfterburnerNew($this->item),
            'Gravlev' => new GravlevParams($this->item),
        ];
    }
}
