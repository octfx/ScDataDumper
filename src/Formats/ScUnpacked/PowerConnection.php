<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @deprecated 4.7.0 EntityComponentPowerConnection removed from game data.
 */
final class PowerConnection extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentPowerConnection';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attributes = $this->get()?->attributesToArray(
            [
                'MisfireItemTypeLocID',
                'WarningDisplayTime',
                'WarningDelayTime',
            ]
        );

        return $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;
    }
}
