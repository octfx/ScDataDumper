<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Missile extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemMissileParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $missile = $this->get();

        return [
            'Damage' => new Damage($missile->get('explosionParams/damage/DamageInfo')),
        ];
    }
}
