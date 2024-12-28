<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Ammunition extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $ammo = ServiceFactory::getAmmoParamsService()->getByEntity($this->item);

        if ($ammo === null) {
            return null;
        }

        $projectiles = $ammo->get('projectileParams/BulletProjectileParams');

        return [
            'UUID' => $ammo->get('__ref'),
            'Type' => $ammo->get('__type'),
            'Speed' => $ammo->get('speed'),
            'Range' => $ammo->get('lifetime') * $ammo->get('speed'),
            'Size' => $ammo->get('size'),
            'ImpactDamage' => Damage::fromDamageInfo($projectiles?->get('damage/DamageInfo'))?->toArray(),
            'DetonationDamage' => Damage::fromDamageInfo($projectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams/damage/DamageInfo'))?->toArray(),
            'Capacity' => $this->item->get('Components/SAmmoContainerComponentParams@maxAmmoCount') ?? $this->item->get('Components/SAmmoContainerComponentParams@maxRestockCount'),
            'BulletImpulseFalloff' => new BulletImpulseFalloff($projectiles),
            'BulletPierceability' => new BulletPierceability($projectiles),
            'BulletElectron' => new BulletImpulseFalloff($projectiles),
            'DamageDropMinDistance' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDistance/DamageInfo')),
            'DamageDropPerMeter' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropPerMeter/DamageInfo')),
            'DamageDropMinDamage' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDamage/DamageInfo')),
        ];
    }

    public function canTransform(): bool
    {
        return $this->has('Components/SAmmoContainerComponentParams@ammoParamsRecord') || $this->has('Components/SCItemWeaponComponentParams@ammoContainerRecord');
    }
}
