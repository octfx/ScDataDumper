<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class ShieldController extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemShieldEmitterParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $data = $this->get();

        return $data->attributesToArray([
            'capacitorAssignmentInputOutputRegen',
            'capacitorAssignmentInputOutputRegenNavMode',
            'capacitorAssignmentInputOutputResistance',
            'regenerateEffectTag',
            'shieldMeshDeprecated',
            'shieldEffectType',
        ], pascalCase: true);
    }
}
