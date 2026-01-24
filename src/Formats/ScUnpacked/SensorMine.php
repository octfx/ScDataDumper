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

        $mineSensor = $this->get()?->get('/TriggerType/SSensorMineLaserTrigger');

        $damageInfo = $explosion->get('damage/DamageInfo');

        [$damageTotal, $damageType] = $this->extractDamageData($damageInfo);

        return $this->removeNullValues([
            'LaserLength' => $mineSensor?->get('@LaserLength'),
            'AreaOfEffect' => $explosion->get('@maxRadius') ?? $explosion->get('@maxPhysRadius'),
            'MinAreaOfEffect' => $explosion->get('@minRadius'),
            'DamageType' => $damageType ?? $explosion->get('@hitType'),
            'Damage' => $damageTotal,
        ]);
    }
}
