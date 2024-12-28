<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class JumpPerformance extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return array_filter($this->item->attributesToArray(), static function ($key) {
            return ! str_starts_with($key, 'VFX') && ! str_starts_with($key, 'Shader') && ! str_ends_with($key, 'State');
        }, ARRAY_FILTER_USE_KEY);
    }

    public function canTransform(): bool
    {
        return $this->has('/stageOneAccelRate');
    }
}
