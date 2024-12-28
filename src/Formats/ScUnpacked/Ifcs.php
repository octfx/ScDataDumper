<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

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
            ]
        );

        return $attributes + [
            'Pitch' => $ifcs->get('maxAngularVelocity@x'),
            'Yaw' => $ifcs->get('maxAngularVelocity@z'),
            'Roll' => $ifcs->get('maxAngularVelocity@y'),
            'MaxAngularVelocity' => (new Vec3($ifcs->get('/maxAngularVelocity')))->toArray(),
            'Afterburner' => (new Afterburner($this->item))->toArray(),
        ];
    }
}
