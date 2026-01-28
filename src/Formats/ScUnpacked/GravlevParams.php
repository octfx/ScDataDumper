<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class GravlevParams extends BaseFormat
{
    protected ?string $elementKey = 'Components/GravlevParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $gcsParams = $this->get();

        $handling = $gcsParams->get('handling');

        return $handling?->attributesToArray(pascalCase: true);
    }
}
