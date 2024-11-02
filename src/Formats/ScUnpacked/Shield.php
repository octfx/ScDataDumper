<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Shield extends BaseFormat
{
    protected ?string $elementKey = 'Components.SCItemShieldGeneratorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $shield = $this->get();

        $return = [];

        $attributes = $shield->attributesToArray(
            null,
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
            'StunParams' => $shield->attributesToArray($shield->get('stunParams')),
            'Absorption' => new MinMaxList($shield->ShieldAbsorption, 'SShieldAbsorption', 6),
            'Resistance' => new MinMaxList($shield->ShieldResistance, 'SShieldResistance', 6),
        ];
    }
}
