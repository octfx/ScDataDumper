<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class HydrogenFuelIntake extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemFuelIntakeParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $intake = $this->get();

        return [
            'Rate' => $intake->get('fuelPushRate'),
            'MinRate' => $intake->get('minimumRate'),
        ];
    }
}
