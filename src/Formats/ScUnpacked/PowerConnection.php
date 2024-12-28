<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class PowerConnection extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentPowerConnection';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray(
            [
                'MisfireItemTypeLocID',
                'WarningDisplayTime',
                'WarningDelayTime',
            ]
        );
    }
}
