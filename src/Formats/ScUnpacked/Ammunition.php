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

        $projectiles = $ammo->get('projectileParams/BulletProjectileParams');

        $ammoAttrs = new Element($ammo->documentElement)->attributesToArray();
        $physicsController = $ammo->get('/physicsControllerParams/SEntityPhysicsControllerParams/PhysType/SEntityParticlePhysicsControllerParams');
        $penetrationParams = $projectiles?->get('/penetrationParams');
        $pierceabilityParams = $projectiles?->get('/pierceabilityParams');

        $lifetime = $ammoAttrs['lifetime'] ?? null;
        $speed = $ammoAttrs['speed'] ?? null;

        $data = [
            'UUID' => $ammo->getUuid(),
            'Type' => $ammo->get('__type'),
            'Speed' => $speed,
            'Lifetime' => $lifetime,
            'Range' => ($speed ?? 0) * ($lifetime ?? 0),
            'Size' => $ammoAttrs['size'] ?? null,
            'ImpactDamage' => Damage::fromDamageInfo($projectiles?->get('damage/DamageInfo')),
            'DetonationDamage' => Damage::fromDamageInfo($projectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams/damage/DamageInfo')),
            'InitialCapacity' => $this->item->get('Components/SAmmoContainerComponentParams@initialAmmoCount'),
            'Capacity' => $this->item->get('Components/SAmmoContainerComponentParams@maxAmmoCount') ?? $this->item->get('Components/SAmmoContainerComponentParams@maxRestockCount'),
            'BulletImpulseFalloff' => new BulletImpulseFalloff($projectiles),
            'BulletPierceability' => new BulletPierceability($projectiles),
            'BulletElectron' => new BulletElectron($projectiles),
            'DamageDropMinDistance' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDistance/DamageInfo')),
            'DamageDropPerMeter' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropPerMeter/DamageInfo')),
            'DamageDropMinDamage' => Damage::fromDamageInfo($projectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDamage/DamageInfo')),
            'Pierceability' => $physicsController?->get('@pierceability'),
            'Mass' => $physicsController?->get('@Mass'),
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
                'Angle' => $penetrationParams?->get('@angle'),
            ],
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return $this->has('Components/SAmmoContainerComponentParams@ammoParamsRecord') || $this->has('Components/SCItemWeaponComponentParams@ammoContainerRecord');
    }

    private function castBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
