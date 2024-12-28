<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class BulletImpulseFalloff extends BaseFormat
{
    protected ?string $elementKey = 'impulseFalloffParams/BulletImpulseFalloffParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $falloffParams = $this->get();

        return [
            'MinDistance' => $falloffParams->get('minDistance'),
            'DropFalloff' => $falloffParams->get('dropFalloff'),
            'MaxFalloff' => $falloffParams->get('maxFalloff'),
        ];
    }
}
