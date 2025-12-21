<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Thruster extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemThrusterParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attributes = $this->get()?->attributesToArray([
            'nozzleAnimation',
            'thrusterAnimDriver',
        ]);

        return $attributes ? $this->transformArrayKeysToPascalCase($attributes) : null;
    }
}
