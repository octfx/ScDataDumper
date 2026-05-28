<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @deprecated EntityComponentHeatConnection has been removed from game data
 */
final class HeatConnection extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentHeatConnection';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attributes = $this->get()?->attributesToArray();

        return $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;
    }
}
