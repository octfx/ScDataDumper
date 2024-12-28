<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Radar extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemRadarComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $radar = $this->get();
        $signatures = [];

        foreach ($radar->get('/signatureDetection') as $signature) {
            $signatures[] = [
                'Sensitivity' => $signature->get('sensitivity'),
                'piercing' => $signature->get('piercing'),
                'permitPassiveDetection' => $signature->get('permitPassiveDetection'),
                'permitActiveDetection' => $signature->get('permitActiveDetection'),
            ];
        }

        return [
            'Cooldown' => $radar->get('pingProperties@cooldownTime'),
            'SignatureDetection' => $signatures,
            'Sensitivity' => $radar->get('sensitivityModifiers/SCItemRadarSensitivityModifier@sensitivityAddition'),
        ];
    }
}
