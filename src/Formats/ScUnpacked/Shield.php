<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

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
                    $return[ucfirst($key)] = $value;
            }
        }

        return $return + [
            'StunParams' => $shield->get('stunParams')?->attributesToArray(),
            'Absorption' => new MinMaxList($shield->get('/ShieldAbsorption'), 'SShieldAbsorption', 6),
            'Resistance' => new MinMaxList($shield->get('/ShieldResistance'), 'SShieldResistance', 6),
        ];
    }
}
