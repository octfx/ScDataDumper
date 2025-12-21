<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Shield extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemShieldGeneratorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $shield = $this->get();

        $return = [];

        $attributes = $shield->attributesToArray(
            [
                'RegeneratingState',
                'IdleState',
            ]
        );

        foreach ($attributes as $key => $value) {
            switch ($key) {
                case 'DownedRegenDelay':
                    $return['DownedDelay'] = $value;
                    break;

                case 'DamagedRegenDelay':
                    $return['DamagedDelay'] = $value;
                    break;

                default:
                    $return[$this->toPascalCase($key)] = $value;
            }
        }

        $stunParams = $shield->get('/stunParams')?->attributesToArray();

        $return += [
            'StunParams' => $stunParams ? $this->transformArrayKeysToPascalCase($stunParams) : null,
            'Absorption' => new MinMaxList($shield->get('/ShieldAbsorption'), '/SShieldAbsorption'),
            'Resistance' => new MinMaxList($shield->get('/ShieldResistance'), '/SShieldResistance'),
        ];

        $return['RegenerationTime'] = Arr::get($return, 'MaxShieldHealth', 0) / Arr::get($return, 'MaxShieldRegen', 1);

        return $return;
    }
}
