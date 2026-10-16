<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use DOMNode;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;

final class Ammunition extends BaseFormat
{
    public function __construct(RootDocument|Element|DOMNode|null $item, private readonly bool $secondary = false)
    {
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $magazine = $this->item->getMagazine();
        $ammo = $this->secondary
            ? ($this->item->getSecondaryAmmoParams() ?? $magazine?->getSecondaryAmmoParams())
            : ($this->item->getAmmoParams() ?? $magazine?->getAmmoParams());

        if ($ammo === null) {
            return null;
        }

        $projectiles = $ammo->get('./projectileParams/BulletProjectileParams');
        $tachyonProjectiles = $ammo->get('./projectileParams/TachyonProjectileParams');

        $ammoAttrs = new Element($ammo->documentElement)->attributesToArray();
        $physicsController = $ammo->get('./physicsControllerParams/SEntityPhysicsControllerParams/PhysType/SEntityParticlePhysicsControllerParams')
            ?? $ammo->get('./physicsControllerParams/SEntityPhysicsControllerParams/PhysType/*');

        $lifetime = $ammoAttrs['lifetime'] ? round($ammoAttrs['lifetime'], 2) : null;
        $speed = $ammoAttrs['speed'] ?? null;
        $ammoContainer = $this->item->get('Components/SAmmoContainerComponentParams')
            ?? $magazine?->get('Components/SAmmoContainerComponentParams');

        // Use whichever projectile type is present; Tachyon takes precedence if both exist
        $activeProjectiles = $tachyonProjectiles ?? $projectiles;
        $activePenetrationParams = $activeProjectiles?->get('penetrationParams');
        $activePierceabilityParams = $activeProjectiles?->get('pierceabilityParams');

        $data = [
            'UUID' => $ammo->getUuid(),
            'Type' => $ammo->get('@__type'),
            'Speed' => $speed,
            'Lifetime' => $lifetime,
            'Range' => round(($speed ?? 0) * ($lifetime ?? 0)),
            'Size' => $ammoAttrs['size'] ?? null,
            'ImpactDamage' => Damage::fromDamageInfo($activeProjectiles?->get('damage/DamageInfo'))?->toArray(),
            'DetonationDamage' => Damage::fromDamageInfo($activeProjectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams/damage/DamageInfo'))?->toArray(),
            'ExplosionRadius' => $activeProjectiles?->has('detonationParams/ProjectileDetonationParams/explosionParams') ? [
                'Minimum' => $activeProjectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams@minRadius'),
                'Maximum' => $activeProjectiles?->get('detonationParams/ProjectileDetonationParams/explosionParams@maxRadius'),
            ] : null,
            'InitialCapacity' => $ammoContainer?->get('@initialAmmoCount'),
            'Capacity' => $ammoContainer?->get('@maxAmmoCount') ?? $ammoContainer?->get('@maxRestockCount'),
            'BulletImpulseFalloff' => new BulletImpulseFalloff($activeProjectiles)->toArray(),
            'BulletPierceability' => new BulletPierceability($activeProjectiles)->toArray(),
            'BulletElectron' => new BulletElectron($activeProjectiles)->toArray(),
            'DamageDropMinDistance' => Damage::fromDamageInfo($activeProjectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDistance/DamageInfo')),
            'DamageDropPerMeter' => Damage::fromDamageInfo($activeProjectiles?->get('damageDropParams/BulletDamageDropParams/damageDropPerMeter/DamageInfo')),
            'DamageDropMinDamage' => Damage::fromDamageInfo($activeProjectiles?->get('damageDropParams/BulletDamageDropParams/damageDropMinDamage/DamageInfo')),
            'Pierceability' => $physicsController?->get('@pierceability'),
            'Mass' => $physicsController?->get('@Mass') ?? $physicsController?->get('@mass'),
            'ImpulseScale' => $ammoAttrs['impulseScale'] ?? null,
            'BulletType' => $ammoAttrs['bulletType'] ?? null,
            'DamageFalloffLevel1' => $activePierceabilityParams?->get('@damageFalloffLevel1'),
            'DamageFalloffLevel2' => $activePierceabilityParams?->get('@damageFalloffLevel2'),
            'DamageFalloffLevel3' => $activePierceabilityParams?->get('@damageFalloffLevel3'),
            'MaxPenetrationThickness' => $activePierceabilityParams?->get('@maxPenetrationThickness'),
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
                'BasePenetrationDistance' => $activePenetrationParams?->get('@basePenetrationDistance'),
                'NearRadius' => $activePenetrationParams?->get('@nearRadius'),
                'FarRadius' => $activePenetrationParams?->get('@farRadius'),
                'Angle' => $activePenetrationParams?->get('@angle'),
            ],
            'ConversionRateMicroScu' => $this->conversionRateMicroScu($ammo),
        ];

        // Tachyon-specific: range-based damage falloff parameters
        if ($tachyonProjectiles !== null) {
            $data['ProjectileType'] = 'Tachyon';
            $data['FullDamageRange'] = $tachyonProjectiles->get('@fullDamageRange');
            $data['ZeroDamageRange'] = $tachyonProjectiles->get('@zeroDamageRange');
        }

        return $this->removeNullValues($data);
    }

    /**
     * Ammo conversion rate normalized to microSCU.
     */
    private function conversionRateMicroScu(Element|RootDocument $ammo): mixed
    {
        $conversion = Item::cargoUnitConversion($ammo->get('./conversionRate'));

        if ($conversion === null) {
            return null;
        }

        return $conversion['exponent'] === 6
            ? $conversion['value']
            : $conversion['value'] * (10 ** (6 - $conversion['exponent']));
    }

    public function canTransform(): bool
    {
        if ($this->has($this->secondary
            ? 'Components/SAmmoContainerComponentParams@secondaryAmmoParamsRecord'
            : 'Components/SAmmoContainerComponentParams@ammoParamsRecord')) {
            return true;
        }

        if ($this->has('Components/SCItemWeaponComponentParams@ammoContainerRecord')) {
            $weapon = $this->item->get('Components/SCItemWeaponComponentParams');

            return $weapon instanceof Element && $weapon->get('fireActions') !== null;
        }

        return false;
    }
}
