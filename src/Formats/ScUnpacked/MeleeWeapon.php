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

        $weapon = $this->get();

        $attributes = $weapon->attributesToArray(
            [
                'helper',
                'audioTriggerName',
                'matFxTriggerName',
                'proceduralAnimationRecord',
            ]
        );

        if ($attributes['meleeCombatConfig'] === '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        $out = [
            ...$attributes,
            'Modes' => [],
        ];

        foreach ($weapon->get('MeleeCombatConfig/attackCategoryParams')?->children() ?? [] as $attackCategory) {
            $attributes = $attackCategory->attributesToArray(['cameraShakeParams']);

            $attributes['Damage'] = Damage::fromDamageInfo($attackCategory->get('damageInfo'));

            $out['Modes'][] = $attributes;
        }

        return $out;
    }
}
