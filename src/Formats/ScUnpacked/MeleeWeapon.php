<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class MeleeWeapon extends BaseFormat
{
    protected ?string $elementKey = 'Components/SMeleeWeaponComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $melee = $this->get();
        $attackConfig = $melee->get('@meleeCombatConfig');

        if ($attackConfig === null || $attackConfig === '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        $out = [
            ...$melee->attributesToArray(
                [
                    'helper',
                    'audioTriggerName',
                    'matFxTriggerName',
                    'proceduralAnimationRecord',
                ], pascalCase: true
            ),
            'AttackConfig' => [],
        ];

        foreach ($melee->get('MeleeCombatConfig/attackCategoryParams')?->children() ?? [] as $attackCategory) {
            $attributes = $attackCategory->attributesToArray([
                'cameraShakeParams',
                'fullbodyAnimation',
            ], pascalCase: true);
            $mode = $this->transformArrayKeysToPascalCase($attributes ?? []);

            $damage = Damage::fromDamageInfo($attackCategory->get('damageInfo'))?->toArray();
            if ($damage !== null) {
                $mode['Damage'] = $damage;
                $mode['DamageTotal'] = $this->calculateDamageTotal($damage);
            }

            $mode = array_filter($mode, static fn ($value) => $value !== null && $value !== '');

            if ($mode !== []) {
                $out['AttackConfig'][] = $mode;
            }
        }

        return $out;
    }

    private function calculateDamageTotal(array $damage): ?float
    {
        $values = array_filter(
            $damage,
            static fn ($value) => $value !== null && $value !== '' && is_numeric($value)
        );

        if ($values === []) {
            return null;
        }

        return array_sum(array_map(static fn ($value) => (float) $value, $values));
    }
}
