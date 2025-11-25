<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Helmet extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemSuitHelmetParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $helmet = $this->get();

        return [
            'atmosphere_capacity' => $helmet->get('atmosphereCapacity'),
            'puncture_max_area' => $helmet->get('punctureMaxArea'),
            'puncture_max_number' => $helmet->get('punctureMaxNumber'),
        ];
    }
}
