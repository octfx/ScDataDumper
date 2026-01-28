<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Seat extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemSeatParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element $seat */
        $seat = $this->get();

        $data = [
            'SeatType' => $seat->get('@seatType'),
            'Yaw' => new MinMax($seat, '@minYaw', '@maxYaw'),
            'Pitch' => new MinMax($seat, '@minPitch', '@maxPitch'),
            'SetYawPitchLimits' => $this->boolOrNull($seat->get('@setYawPitchLimits')),
            'HasEjection' => $seat->has('ejection/SCItemSeatEjectParams', 'SCItemSeatEjectParams'),
            'Ejection' => $this->formatEjection($seat),
            'ActorAttachment' => $this->formatActorAttachment($seat),
        ];

        return $this->removeNullValues($data);
    }

    private function formatEjection(Element $seat): ?array
    {
        $eject = $seat->get('ejection/SCItemSeatEjectParams');

        if (! $eject instanceof Element) {
            return null;
        }

        $attributes = $eject->attributesToArray(['__type', '__polymorphicType']);

        $allowedKeys = [
            'maxLinearVelocity',
            'maxLinearAcceleration',
            'maxAngularVelocity',
            'maxAngularAcceleration',
            'ejectionLoopTime',
        ];

        $attributes = array_intersect_key($attributes, array_flip($allowedKeys));

        return $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;
    }

    private function formatActorAttachment(Element $seat): ?array
    {
        $attachment = $seat->get('actorAttachment');

        if (! $attachment instanceof Element) {
            return null;
        }

        $attributes = $attachment->attributesToArray(['__type', '__polymorphicType']);

        return $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) (is_string($value) ? (int) $value : $value);
    }
}
