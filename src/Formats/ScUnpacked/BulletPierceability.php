<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class BulletPierceability extends BaseFormat
{
    protected ?string $elementKey = 'pierceabilityParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $pierce = $this->get();

        return [
            'DamageFalloffLevel1' => $pierce->get('damageFalloffLevel1'),
            'DamageFalloffLevel2' => $pierce->get('damageFalloffLevel2'),
            'DamageFalloffLevel3' => $pierce->get('damageFalloffLevel3'),
            'MaxPenetrationThickness' => $pierce->get('maxPenetrationThickness'),
        ];
    }
}
