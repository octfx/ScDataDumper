<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
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

        $projectiles = $ammo->get('./projectileParams/BulletProjectileParams');

        $ammoAttrs = new Element($ammo->documentElement)->attributesToArray();
        $physicsController = $ammo->get('./physicsControllerParams/SEntityPhysicsControllerParams/PhysType/SEntityParticlePhysicsControllerParams')
            ?? $ammo->get('./physicsControllerParams/SEntityPhysicsControllerParams/PhysType/*');
        $penetrationParams = $projectiles?->get('penetrationParams');
        $pierceabilityParams = $projectiles?->get('pierceabilityParams');

        $lifetime = $ammoAttrs['lifetime'] ? round($ammoAttrs['lifetime'], 2) : null;
        $speed = $ammoAttrs['speed'] ?? null;
        $ammoContainer = $this->item->get('Components/SAmmoContainerComponentParams')
            ?? $this->item->get('Components/SCItemWeaponComponentParams/Magazine/Components/SAmmoContainerComponentParams');

        $data = [
            'UUID' => $ammo->getUuid(),
            'Type' => $ammo->get('@__type'),
            'Speed' => $speed,
            'Lifetime' => $lifetime,
            'Range' => round(($speed ?? 0) * ($lifetime ?? 0)),
            'Size' => $ammoAttrs['size'] ?? null,
            'ImpactDamage' => Damage::fromDamageInfo($projectiles?->get('damage/DamageInfo'))?->toArray(),
            'DetonationDamage' => Damage::fromDamageInfo($projectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams/damage/DamageInfo'))?->toArray(),
            'ExplosionRadius' => $projectiles?->has('detonationParams/ProjectileDetonationParams/explosionParams') ? [
                'Minimum' => $projectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams@minRadius'),
                'Maximum' => $projectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams@maxRadius'),
            ] : null,
            'InitialCapacity' => $ammoContainer?->get('@initialAmmoCount'),
            'Capacity' => $ammoContainer?->get('@maxAmmoCount') ?? $ammoContainer?->get('@maxRestockCount'),
            'BulletImpulseFalloff' => new BulletImpulseFalloff($projectiles)->toArray(),
            'BulletPierceability' => new BulletPierceability($projectiles)->toArray(),
            'BulletElectron' => new BulletElectron($projectiles)->toArray(),
            'DamageDropMinDistance' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDistance/DamageInfo')),
            'DamageDropPerMeter' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropPerMeter/DamageInfo')),
            'DamageDropMinDamage' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDamage/DamageInfo')),
            'Pierceability' => $physicsController?->get('@pierceability'),
            'Mass' => $physicsController?->get('@Mass') ?? $physicsController?->get('@mass'),
            'ImpulseScale' => $ammoAttrs['impulseScale'] ?? null,
            'BulletType' => $ammoAttrs['bulletType'] ?? null,
            'DamageFalloffLevel1' => $pierceabilityParams?->get('@damageFalloffLevel1'),
            'DamageFalloffLevel2' => $pierceabilityParams?->get('@damageFalloffLevel2'),
            'DamageFalloffLevel3' => $pierceabilityParams?->get('@damageFalloffLevel3'),
            'MaxPenetrationThickness' => $pierceabilityParams?->get('@maxPenetrationThickness'),
            'PhysicalDimensions' => [
                'Radius' => $physicsController?->get('@radius'),
                'Thickness' => $physicsController?->get('@thickness'),
                'Length' => $physicsController?->get('@length'),
            ],
            'FlightPhysics' => [
                'AirResistance' => $physicsController?->get('@airResistance'),
                'DisableGravity' => $this->castBool($physicsController?->get('@disableGravity')),
            ],
            'Penetration' => [
                'BasePenetrationDistance' => $penetrationParams?->get('@basePenetrationDistance'),
                'NearRadius' => $penetrationParams?->get('@nearRadius'),
                'FarRadius' => $penetrationParams?->get('@farRadius'),
                'Angle' => $penetrationParams?->get('@angle'),
            ],
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        if ($this->has('Components/SAmmoContainerComponentParams@ammoParamsRecord')) {
            return true;
        }

        if ($this->has('Components/SCItemWeaponComponentParams@ammoContainerRecord')) {
            $weapon = $this->item->get('Components/SCItemWeaponComponentParams');

            return $weapon instanceof Element && $weapon->get('fireActions') !== null;
        }

        return false;
    }

    private function castBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
