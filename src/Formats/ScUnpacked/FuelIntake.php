<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class FuelIntake extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemFuelIntakeParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $intake = $this->get();

        $flow = array_filter([
            'FuelPushRate' => $intake?->get('fuelPushRate') !== null ? (float) $intake->get('fuelPushRate') : null,
            'MinimumRate' => $intake?->get('minimumRate') !== null ? (float) $intake->get('minimumRate') : null,
        ], static fn ($value) => $value !== null);

        if (empty($flow)) {
            return null;
        }

        return [
            'FlowRates' => $flow,
        ];
    }

    public function canTransform(): bool
    {
        return parent::canTransform() && $this->item?->getAttachType() === 'FuelIntake';
    }
}
