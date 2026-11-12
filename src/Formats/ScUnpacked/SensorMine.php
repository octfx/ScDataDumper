<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

final class SensorMine extends Grenade
{
    protected ?string $elementKey = 'Components/SSensorMineComponentParams';

    public function canTransform(): bool
    {
        return $this->item !== null && $this->has($this->elementKey);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $explosion = $this->get('Components/SHealthComponentParams/DeathExplosionParams/ExplosionParams');

        if ($explosion === null) {
            return null;
        }

        $mineComponent = $this->get();

        $laserTrigger = $mineComponent?->get('TriggerType/SSensorMineLaserTrigger');
        $proximityTrigger = $mineComponent?->get('TriggerType/SSensorMineProximityTrigger');

        $damageInfo = $explosion->get('damage/DamageInfo');

        [$damageTotal, $damageType] = $this->extractDamageData($damageInfo);

        return $this->removeNullValues([
            'LaserLength' => $laserTrigger?->get('@LaserLength'),
            'Radius' => $proximityTrigger?->get('@Radius'),
            'WarningRadius' => $proximityTrigger?->get('@WarningRadius'),
            'MaxRaysPerMine' => $proximityTrigger?->get('@MaxRaysPerMine'),
            'AngleVertical' => $proximityTrigger?->get('@AngleVertical'),
            'AngleHorizontal' => $proximityTrigger?->get('@AngleHorizontal'),
            'AreaOfEffect' => $explosion->get('@maxRadius') ?? $explosion->get('@maxPhysRadius'),
            'MinAreaOfEffect' => $explosion->get('@minRadius'),
            'DamageType' => $damageType ?? $explosion->get('@hitType'),
            'Damage' => $damageTotal,
        ]);
    }
}
