<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Distortion extends BaseFormat
{
    protected ?string $elementKey = 'Components/SDistortionParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attributes = $this->get()?->attributesToArray();

        $return = $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;

        $return['ShutdownTime'] = (Arr::get($return, 'Maximum', 0) / max(1, Arr::get($return, 'DecayRate', 1))) + Arr::get($return, 'DecayDelay', 0);
        $return['ShutdownTime'] = round($return['ShutdownTime'], 2);

        return $return;
    }
}
