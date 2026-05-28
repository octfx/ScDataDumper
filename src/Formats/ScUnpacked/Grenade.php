<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\TriggerableDevice;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @extends BaseFormat<EntityClassDefinition>
 */
class Grenade extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $device = $this->asTriggerableDevice();

        // IF Grenade has a hazard zone, use it as primary damage
        $hazardZone = $device?->getSpawnedHazardZone();
        if ($hazardZone !== null) {
            return $this->removeNullValues([
                'AreaOfEffect' => $hazardZone->getRadius(),
                'DamageType' => $hazardZone->getDamageType(),
                'Damage' => $hazardZone->getTotalDamage(),
                'DamagePerTick' => $hazardZone->getDamagePerTick(),
                'DamagePeriod' => $hazardZone->getDamagePeriod(),
                'Duration' => $hazardZone->getDuration(),
                'IgnoreShields' => $hazardZone->getIgnoreShields(),
            ]);
        }

        $explosion = $device?->getFirstExplosion() ?? $device?->getDeathExplosion();

        if ($explosion === null) {
            return null;
        }

        $damageInfo = $explosion->get('damage/DamageInfo');

        [$damageTotal, $damageType] = $this->extractDamageData($damageInfo);

        return $this->removeNullValues([
            'AreaOfEffect' => $explosion->get('@maxRadius') ?? $explosion->get('@maxPhysRadius'),
            'MinAreaOfEffect' => $explosion->get('@minRadius'),
            'DamageType' => $damageType ?? $explosion->get('@hitType'),
            'Damage' => $damageTotal,
        ]);
    }

    protected function extractDamageData($damageInfo): array
    {
        if ($damageInfo === null) {
            return [null, null];
        }

        $damageValues = array_filter([
            'Physical' => $damageInfo->get('@DamagePhysical'),
            'Energy' => $damageInfo->get('@DamageEnergy'),
            'Distortion' => $damageInfo->get('@DamageDistortion'),
            'Thermal' => $damageInfo->get('@DamageThermal'),
            'Biochemical' => $damageInfo->get('@DamageBiochemical'),
            'Stun' => $damageInfo->get('@DamageStun'),
        ], static fn ($value) => $value !== null);

        if ($damageValues === []) {
            return [null, null];
        }

        $total = array_sum($damageValues);

        arsort($damageValues, SORT_NUMERIC);

        $primaryType = null;
        $first = array_key_first($damageValues);
        if ($first !== null && ($damageValues[$first] ?? 0) > 0) {
            $primaryType = $first;
        }

        return [$total, $primaryType];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachSubType() === 'Grenade' && $this->asTriggerableDevice()?->getFirstExplosion() !== null;
    }

    private function asTriggerableDevice(): ?TriggerableDevice
    {
        if (! $this->item instanceof EntityClassDefinition) {
            return null;
        }

        $device = TriggerableDevice::fromNode($this->item->documentElement);

        return $device instanceof TriggerableDevice ? $device : null;
    }
}
