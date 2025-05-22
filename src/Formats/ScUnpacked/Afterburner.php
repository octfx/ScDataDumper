<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Afterburner extends BaseFormat
{
    protected ?string $elementKey = 'Components/IFCSParams/afterburner';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $afterburner = $this->get('Components/IFCSParams/afterburner');

        return [
            'PreDelayTime' => $afterburner->get('afterburnerPreDelayTime'),
            'RampUpTime' => $afterburner->get('afterburnerRampUpTime'),
            'RampDownTime' => $afterburner->get('afterburnerRampDownTime'),
            'CapacitorThresholdRatio' => $afterburner->get('afterburnerCapacitorThresholdRatio'),
            'CapacitorMax' => $afterburner->get('capacitorMax'),
            'CapacitorAfterburnerIdleCost' => $afterburner->get('capacitorAfterburnerIdleCost'),
            'CapacitorAfterburnerLinearCost' => $afterburner->get('capacitorAfterburnerLinearCost'),
            'CapacitorAfterburnerAngularCost' => $afterburner->get('capacitorAfterburnerAngularCost'),
            'CapacitorRegenDelayAfterUse' => $afterburner->get('capacitorRegenDelayAfterUse'),
            'CapacitorRegenPerSec' => $afterburner->get('capacitorRegenPerSec'),
        ];
    }
}
