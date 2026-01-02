<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class TargetingParams extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemMissileParams/targetingParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray([
            'maxTimesCanMiss',
            'dynamicLaunchZoneRecord',
        ], true);
    }
}
