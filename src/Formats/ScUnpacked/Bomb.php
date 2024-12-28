<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Bomb extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemBombParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $bomb = $this->get();

        return [
            'Damage' => new Damage($bomb->get('explosionParams/damage/DamageInfo')),
        ];
    }
}
