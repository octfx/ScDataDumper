<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class OrdnanceCluster extends BaseFormat
{
    protected ?string $elementKey = 'clusterParams/SOrdnanceClusterParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray([
            'detachAngleRelativeToGravity',
            'detachAngleInitial',
            'detachAngleIncrement',
            'detachAngleResetCount',
        ], true);
    }
}
