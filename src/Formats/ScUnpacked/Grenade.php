<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

class Grenade extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $explosion = $this->findExplosionParams();

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
        return $this->item?->getAttachSubType() === 'Grenade' && $this->findExplosionParams() !== null;
    }

    private function findExplosionParams(): ?Element
    {
        $triggerable = $this->get('Components/EntityComponentTriggerableDevicesParams');

        if ($triggerable instanceof Element) {
            $explosions = $this->extractExplosions($triggerable->get('/triggers'));
            if ($explosions !== []) {
                return $explosions[0];
            }

            $explosions = $this->extractExplosions($triggerable->get('/aiTriggers'));
            if ($explosions !== []) {
                return $explosions[0];
            }
        }

        $fallback = $this->get('Components/SHealthComponentParams/DeathExplosionParams/ExplosionParams');

        return $fallback instanceof Element ? $fallback : null;
    }

    /**
     * Collect explosion params from a trigger list element.
     */
    private function extractExplosions($section): array
    {
        if (! $section instanceof Element) {
            return [];
        }

        $explosions = [];

        foreach ($section->children() as $trigger) {
            $behavior = $trigger->get('/behavior');

            if (! $behavior instanceof Element) {
                continue;
            }

            foreach ($behavior->children() as $behaviorChild) {
                if ($behaviorChild->nodeName !== 'STriggerableDevicesBehaviorExplosionParams') {
                    continue;
                }

                $explosion = $behaviorChild->get('/explosionParams');

                if ($explosion instanceof Element) {
                    $explosions[] = $explosion;
                }
            }
        }

        return $explosions;
    }
}
