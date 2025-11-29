<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Durability extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'Lifetime' => $this->get('Components/SDegradationParams@MaxLifetimeHours'),
            'Health' => $this->get('Components/SHealthComponentParams@Health'),
            'Salvageable' => $this->get('Components/SHealthComponentParams@IsSalvagable'),
            'Repairable' => $this->get('Components/SHealthComponentParams@IsRepairable'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->has('Components/SDegradationParams') || $this->has('Components/SHealthComponentParams');
    }
}
