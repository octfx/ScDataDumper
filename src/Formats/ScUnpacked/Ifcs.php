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

        $noFuel = $ifcs->get('noFuelParams');
        $noFuelLegacy = $ifcs->get('noFuelParamsLegacy');

        $noFuelData = null;
        if ($noFuel !== null || $noFuelLegacy !== null) {
            $noFuelData = array_filter([
                'LinearAccelerationModifier' => $noFuel?->get('@linearAccelerationModifier') ?? $noFuelLegacy?->get('@linearAccelerationModifier'),
                'AngularAccelerationModifier' => $noFuel?->get('@angularAccelerationModifier') ?? $noFuelLegacy?->get('@angularAccelerationModifier'),
                'AngularVelocityModifier' => $noFuel?->get('@angularVelocityModifier') ?? $noFuelLegacy?->get('@angularVelocityModifier'),
                'LegacyMaxSpeed' => $noFuelLegacy?->get('@linearMaxSpeed'),
            ], static fn ($v) => $v !== null);
        }

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
            'NoFuelParams' => $noFuelData,
        ];
    }
}
