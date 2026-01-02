<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

final class Medical extends Food
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        if (! $this->isMedicalSubtype()) {
            return null;
        }

        return $this->buildConsumableData();
    }
}
