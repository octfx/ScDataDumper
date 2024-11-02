<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class QuantumInterdictionGenerator extends BaseFormat
{
    protected ?string $elementKey = 'Components.SCItemQuantumInterdictionGeneratorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $qig = $this->get();

        return $qig->attributesToArray(null, ['mainDeviceSwitchOn', 'mainDeviceSwitchOff']) + [
            'JammingRange' => $qig->get('jammerSettings.SCItemQuantumJammerParams.jammerRange'),
            'InterdictionRange' => $qig->get('quantumInterdictionPulseSettings.SCItemQuantumInterdictionPulseParams.radiusMeters'),
            'Jammer' => $qig->get('jammerSettings.SCItemQuantumJammerParams')?->attributesToArray(
                null,
                [
                    'setJammerSwitchOn',
                    'setJammerSwitchOff',
                ]
            ),
            'Pulse' => $qig->get('quantumInterdictionPulseSettings.SCItemQuantumInterdictionPulseParams')?->attributesToArray(
                null,
                [
                    'startChargingIP',
                    'cancelChargingIP',
                    'disperseChargeIP',
                ]
            ),
        ];
    }
}
