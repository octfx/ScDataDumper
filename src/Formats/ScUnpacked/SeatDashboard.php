<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class SeatDashboard extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemSeatDashboardParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element $seat */
        $seat = $this->get();

        $data = [
            'PowerForObservedItemsToggle' => $seat->get('@canTogglePowerForObservedItems'),
            'LightAmplificationToggle' => $seat->get('@canToggleLightAmplification'),
        ];

        return $this->removeNullValues($data);
    }
}
