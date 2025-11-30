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

        $allowedKeys = [
            'driveSpeed',
            'cooldownTime',
            'stageOneAccelRate',
            'stageTwoAccelRate',
            'engageSpeed',
            'interdictionEffectTime',
            'calibrationRate',
            'minCalibrationRequirement',
            'maxCalibrationRequirement',
            'calibrationProcessAngleLimit',
            'calibrationWarningAngleLimit',
            'calibrationDelayInSeconds',
            'spoolUpTime',
        ];

        $values = [];

        foreach ($allowedKeys as $key) {
            $value = $this->item->get($key);

            if ($value !== null) {
                $values[$this->toPascalCase($key)] = $value;
            }
        }

        return $values;
    }

    public function canTransform(): bool
    {
        return $this->item !== null && $this->item->get('driveSpeed') !== null;
    }
}
