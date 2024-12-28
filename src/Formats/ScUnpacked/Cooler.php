<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Cooler extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemCoolerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray();
    }
}
