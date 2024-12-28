<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class PowerPlant extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentPowerConnection';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $powerPlant = $this->get();

        return [
            'Output' => $powerPlant->get('PowerDraw'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'PowerPlant' && parent::canTransform();
    }
}
