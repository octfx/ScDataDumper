<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Missile Guidance and Control Params
 */
final class GCSParams extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemMissileParams/GCSParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $gcsParams = $this->get();

        return $gcsParams->attributesToArray([
            'dumbfireRotationScale',
            'pidIntegralTerm',
            'pidDerivativeTerm',
            'pidProportionalTerm',
        ], true) + [
            'BoostSpeed' => $gcsParams->get('boostPhase@angularSpeed'),
            'BoostRotationAcceleration' => $gcsParams->get('boostPhase@maxRotationAccel'),
            'InterceptSpeed' => $gcsParams->get('interceptPhase@angularSpeed'),
            'InterceptRotationAcceleration' => $gcsParams->get('interceptPhase@maxRotationAccel'),
            'TerminalSpeed' => $gcsParams->get('terminalPhase@angularSpeed'),
            'TerminalRotationAcceleration' => $gcsParams->get('terminalPhase@maxRotationAccel'),
        ];
    }
}
