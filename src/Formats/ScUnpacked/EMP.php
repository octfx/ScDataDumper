<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class EMP extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemEMPParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray([
            'chargingTag',
            'chargedTag',
            'startChargingTrigger',
            'stopChargingTrigger',
            'startChargedTrigger',
            'stopChargedTrigger',
            'startUnleashTrigger',
            'stopUnleashTrigger',
            'idleState',
            'chargingState',
            'chargedState',
            'releasingState',
        ], pascalCase: true);
    }
}
