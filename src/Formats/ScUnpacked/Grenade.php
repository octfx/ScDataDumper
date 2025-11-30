<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Grenade extends BaseFormat
{
    protected ?string $elementKey = 'Components/SHealthComponentParams/DeathExplosionParams/ExplosionParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $explosion = $this->get();

        if ($explosion === null) {
            return null;
        }

        $damageInfo = $explosion->get('damage/DamageInfo');

        [$damageTotal, $damageType] = $this->extractDamageData($damageInfo);

        return [
            'AreaOfEffect' => $explosion->get('@maxRadius') ?? $explosion->get('@maxPhysRadius'),
            'DamageType' => $damageType,
            'Damage' => $damageTotal,
        ];
    }

    private function extractDamageData($damageInfo): array
    {
        if ($damageInfo === null) {
            return [null, null];
        }

        $damageValues = array_filter([
            'Physical' => $damageInfo->get('DamagePhysical'),
            'Energy' => $damageInfo->get('DamageEnergy'),
            'Distortion' => $damageInfo->get('DamageDistortion'),
            'Thermal' => $damageInfo->get('DamageThermal'),
            'Biochemical' => $damageInfo->get('DamageBiochemical'),
            'Stun' => $damageInfo->get('DamageStun'),
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
        if ($this->item?->getAttachType() !== 'Grenade') {
            return false;
        }

        return parent::canTransform();
    }
}
