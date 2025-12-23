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

        if ((bool) ($data['Enable'] ?? 0) === false) {
            return null;
        }

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

        $cTemp = $this->calculateCTemperatures($signatureParams, $itemResourceParams);
        if ($cTemp !== null) {
            $data['Calculated'] = $cTemp;
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

    /**
     * Produces:
     * - CoolingThreshold: ItemResourceParams.MinCoolingTemperature
     * - IrThreshold:      SignatureParams.MinimumTemperatureForIR
     * - Overheat:         ItemResourceParams.OverheatWarningTemperature
     * - Max:              ItemResourceParams.OverheatTemperature
     * - Recovery:         ItemResourceParams.OverheatRecoveryTemperature
     *
     * Values are returned in Celsius
     */
    private function calculateCTemperatures(?array $signatureParams, ?array $itemResourceParams): ?array
    {
        $out = ['Unit' => 'C'];

        $minCoolingK = $this->arrayGetInsensitive($itemResourceParams, 'MinCoolingTemperature');
        $minIrK = $this->arrayGetInsensitive($signatureParams, 'MinimumTemperatureForIR');
        $warnK = $this->arrayGetInsensitive($itemResourceParams, 'OverheatWarningTemperature');
        $overheatK = $this->arrayGetInsensitive($itemResourceParams, 'OverheatTemperature');
        $recoveryK = $this->arrayGetInsensitive($itemResourceParams, 'OverheatRecoveryTemperature');

        $coolingC = $this->tempToC($minCoolingK);
        $irC = $this->tempToC($minIrK);
        $overheatC = $this->tempToC($warnK);
        $maxC = $this->tempToC($overheatK);
        $recoveryC = $this->tempToC($recoveryK);

        if ($coolingC !== null) {
            $out['CoolingThreshold'] = $coolingC;
        }
        if ($irC !== null) {
            $out['IrThreshold'] = $irC;
        }
        if ($overheatC !== null) {
            $out['Overheat'] = $overheatC;
        }
        if ($maxC !== null) {
            $out['Max'] = $maxC;
        }
        if ($recoveryC !== null) {
            $out['Recovery'] = $recoveryC;
        }

        return count($out) > 1 ? $out : null;
    }

    private function arrayGetInsensitive(?array $arr, string $key): mixed
    {
        if (! is_array($arr) || $arr === []) {
            return null;
        }

        return array_find($arr, fn ($v, $k) => is_string($k) && strcasecmp($k, $key) === 0);
    }

    /**
     * Convert either Kelvin or Celsius-ish input to Celsius:
     * - If value > 200, assume Kelvin and convert to Celsius
     * - Else assume it is already Celsius
     */
    private function tempToC(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $t = (float) $value;
        $c = ($t > 200.0) ? ($t - 273.15) : $t;

        return round($c, 1);
    }
}
