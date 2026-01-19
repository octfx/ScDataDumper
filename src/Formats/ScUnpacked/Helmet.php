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
            'AtmosphereCapacity' => $helmet->get('atmosphereCapacity'),
            'PunctureMaxArea' => $helmet->get('punctureMaxArea'),
            'PunctureMaxNumber' => $helmet->get('punctureMaxNumber'),
        ];
    }
}
