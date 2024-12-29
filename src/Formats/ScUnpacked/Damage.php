<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\AmmoParams;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Damage extends BaseFormat
{
    public static function fromDamageInfo(AmmoParams|Element|null $element): ?Damage
    {
        if ($element === null) {
            return null;
        }

        $instance = new self($element);

        if (! $instance->canTransform()) {
            return null;
        }

        return $instance;
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'Physical' => $this->item->get('DamagePhysical'),
            'Energy' => $this->item->get('DamageEnergy'),
            'Distortion' => $this->item->get('DamageDistortion'),
            'Thermal' => $this->item->get('DamageThermal'),
            'Biochemical' => $this->item->get('DamageBiochemical'),
            'Stun' => $this->item->get('DamageStun'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'DamageInfo';
    }
}
