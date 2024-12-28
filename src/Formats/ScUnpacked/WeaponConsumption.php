<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class WeaponConsumption extends BaseFormat
{
    protected ?string $elementKey = 'weaponRegenConsumerParams/SWeaponRegenConsumerParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $regenParams = $this->get();

        return [
            'InitialRegenPerSec' => $regenParams->get('initialRegenPerSec'),
            'RequestedRegenPerSec' => $regenParams->get('requestedRegenPerSec'),
            'RequestedAmmoLoad' => $regenParams->get('requestedAmmoLoad'),
            'Cooldown' => $regenParams->get('regenerationCooldown'),
            'CostPerBullet' => $regenParams->get('regenerationCostPerBullet'),
        ];
    }
}
