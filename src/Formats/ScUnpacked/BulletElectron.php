<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class BulletElectron extends BaseFormat
{
    protected ?string $elementKey = 'electronParams/BulletElectronParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $electronParams = $this->get();

        return [
            'JumpRange' => $electronParams->get('jumpRange'),
            'MaximumJumps' => $electronParams->get('maximumJumps'),
            'ResidualChargeMultiplier' => $electronParams->get('residualChargeMultiplier'),
        ];
    }
}
