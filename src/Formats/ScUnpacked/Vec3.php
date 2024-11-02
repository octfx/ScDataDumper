<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Vec3 extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'x' => $this->get('x'),
            'y' => $this->get('y'),
            'z' => $this->get('z'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getType() === 'Vec3';
    }
}
