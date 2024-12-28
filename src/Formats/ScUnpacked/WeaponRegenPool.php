<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class WeaponRegenPool extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemWeaponRegenPoolComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray(
            // TODO: Add Service
            [
                'capacitorAssignmentInputOutputRegen',
                'capacitorAssignmentInputOutputRegenNavMode',
                'capacitorAssignmentInputOutputAmmoLoad',
            ]
        );
    }
}
