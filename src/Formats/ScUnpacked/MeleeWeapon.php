<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class MeleeWeapon extends BaseFormat
{
    protected ?string $elementKey = 'Components.SMeleeWeaponComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $weapon = $this->get();

        $attributes = $weapon->attributesToArray(
            null,
            [
                'helper',
                'audioTriggerName',
                'matFxTriggerName',
                'proceduralAnimationRecord',
            ]
        );

        $out = [
            ...$attributes,
            'Modes' => [],
        ];

        foreach ($weapon->get('MeleeCombatConfig.attackCategoryParams')?->children() ?? [] as $attackCategory) {
            $attributes = $attackCategory->attributesToArray(null, ['cameraShakeParams']);

            $attributes['Damage'] = Damage::fromDamageInfo($attackCategory->get('damageInfo'));

            $out['Modes'][] = $attributes;
        }

        return $out;
    }
}
