<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Weapon extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemWeaponComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $weapon = $this->get();

        $ammunition = new Ammunition($this->item);

        $out = [
            'Modes' => [],
            'Ammunition' => $ammunition->toArray(),
            'Consumption' => (new WeaponConsumption($weapon))->toArray(),
        ];

        $damageReducer = static fn ($carry, $cur) => $carry + $cur;

        foreach ($weapon->get('/fireActions')?->children() as $action) {
            $mode = new WeaponMode($action);
            if (! $mode->canTransform()) {
                continue;
            }

            $mode = $mode->toArray();

            $impact = array_reduce($out['Ammunition']['ImpactDamage'] ?? [], $damageReducer, 0);
            $detonation = array_reduce($out['Ammunition']['DetonationDamage'] ?? [], $damageReducer, 0);

            $mode['DamagePerShot'] = ($impact + $detonation) * ($mode['PelletsPerShot'] ?? 0);
            $mode['DamagePerSecond'] = $mode['DamagePerShot'] * (($mode['RoundsPerMinute'] ?? 0) / 60.0);

            $out['Modes'][] = $mode;
        }

        return $out;
    }
}
