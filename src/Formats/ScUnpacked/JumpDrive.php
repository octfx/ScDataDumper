<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class JumpDrive extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemJumpDriveParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $data = $this->get();

        return $data->attributesToArray([
            'idleState',
            'transitingState',
            'flightTuning',
            'tunnelForces',
        ], true);
    }
}
