<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Temperature extends BaseFormat
{
    protected ?string $elementKey = 'Components/SEntityPhysicsControllerParams/PhysType/SEntityRigidPhysicsControllerParams/temperature';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element $temperature */
        $temperature = $this->get();

        $data = $temperature->attributesToArray([], true);

        $coolingMultiplier = $temperature->get('/coolingEqualizationParams/CoolingEqualizationMultiplier@coolingEqualizationMultiplier');
        if ($coolingMultiplier !== null) {
            $data['CoolingEqualizationMultiplier'] = (float) $coolingMultiplier;
        }

        $signatureParams = $this->formatSignatureParams($temperature->get('/signatureParams'));
        if ($signatureParams !== null) {
            $data['SignatureParams'] = $signatureParams;
        }

        $itemResourceParams = $this->formatItemResourceParams($temperature->get('/itemResourceParams'));
        if ($itemResourceParams !== null) {
            $data['ItemResourceParams'] = $itemResourceParams;
        }

        return $data === [] ? null : $this->transformArrayKeysToPascalCase($data);
    }

    private function formatSignatureParams(?Element $signature): ?array
    {
        if (! $signature) {
            return null;
        }

        $data = $signature->attributesToArray([], true);

        return $data === [] ? null : $data;
    }

    private function formatItemResourceParams(?Element $params): ?array
    {
        if (! $params) {
            return null;
        }

        $data = $params->attributesToArray([], true);

        $allowed = [
            'PoweredAmbientCoolingMultiplier',
            'MinOperatingTemperature',
            'MinCoolingTemperature',
            'EnableOverheat',
            'OverheatTemperature',
            'OverheatWarningTemperature',
            'OverheatRecoveryTemperature',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));

        return $filtered === [] ? null : $filtered;
    }
}
