<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Radar extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemRadarComponentParams';

    /**
     * presumed "Ground Vehicle" group
     */
    private const string GROUND_VEHICLE_CONTACT_GROUP_ID = '0ea6278c-4230-4325-95d8-13b68af5415e';

    /**
     * IR = 0, CS = 2, EM = 1, RS = 4, dB = 3
     */
    private const array SIGNATURE_INDEX_MAP = [
        'IR' => 0,
        'CS' => 2,
        'EM' => 1,
        'RS' => 4,
        'dB' => 3,
    ];

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $radar = $this->get();

        $signatureList = [];
        foreach (($radar->get('/signatureDetection')?->children() ?? []) as $sig) {
            $signatureList[] = $sig;
        }

        $sensitivity = $this->extractSignatureColumns($signatureList, 'sensitivity');
        $piercing = $this->extractSignatureColumns($signatureList, 'piercing');

        $gvSensitivityAddition = $this->findSensitivityAdditionForContactGroup(
            $radar
        );

        $gvScalar = $gvSensitivityAddition === null
            ? null
            : $this->quantizeToStep($this->clamp01(10 ** $gvSensitivityAddition), 0.25);

        $gvSensitivity = $this->repeatAcrossSignatureColumns($gvScalar);

        $rawSignatureDetection = [];
        foreach ($signatureList as $sig) {
            $rawSignatureDetection[] = [
                'Sensitivity' => $this->toFloatOrNull($sig->get('sensitivity')),
                'Piercing' => $this->toFloatOrNull($sig->get('piercing')),
                'PermitPassiveDetection' => $sig->get('permitPassiveDetection'),
                'PermitActiveDetection' => $sig->get('permitActiveDetection'),
            ];
        }

        return [
            'Cooldown' => $this->toFloatOrNull($radar->get('pingProperties@cooldownTime')),

            'Sensitivity' => $sensitivity,
            'GroundVehicleDetectionSensitivity' => $gvSensitivity,
            'Piercing' => $piercing,

            'GroundVehicleSensitivityAddition' => $this->toFloatOrNull($gvSensitivityAddition),
            'SignatureDetection' => $rawSignatureDetection,
        ];
    }

    /**
     * Extracts IR/CS/EM/RS/dB columns from signatureDetection by index mapping
     */
    private function extractSignatureColumns(array $signatureList, string $field): array
    {
        $out = [];

        foreach (self::SIGNATURE_INDEX_MAP as $label => $idx) {
            $sig = $signatureList[$idx] ?? null;
            $out[$label] = $sig ? $this->toFloatOrNull($sig->get($field)) : null;
        }

        return $out;
    }

    /**
     * Finds sensitivityAddition for a specific contact group id under sensitivityModifiers.
     *
     * Handles both single and multiple SCItemRadarSensitivityModifier nodes.
     */
    private function findSensitivityAdditionForContactGroup($radar): ?float
    {
        $mods = $radar->get('/sensitivityModifiers/SCItemRadarSensitivityModifier')?->children() ?? [];

        if ($mods === null) {
            return $this->toFloatOrNull(
                $radar->get('sensitivityModifiers/SCItemRadarSensitivityModifier@sensitivityAddition')
            );
        }

        $modList = [];
        if (is_iterable($mods)) {
            foreach ($mods as $m) {
                $modList[] = $m;
            }
        } else {
            $modList[] = $mods;
        }

        foreach ($modList as $mod) {
            $ref = $mod->get(
                'modifierType/SCItemRadarSensitivityModifierTypeContactGroups/contactGroups/Reference@value'
            );

            if ($ref === self::GROUND_VEHICLE_CONTACT_GROUP_ID) {
                return $this->toFloatOrNull($mod->get('@sensitivityAddition'));
            }
        }

        return $this->toFloatOrNull(
            $radar->get('sensitivityModifiers/SCItemRadarSensitivityModifier@sensitivityAddition')
        );
    }

    /**
     * Repeats a scalar across the IR/CS/EM/RS/dB columns (to match the screenshot layout).
     */
    private function repeatAcrossSignatureColumns(?float $value): array
    {
        return [
            'IR' => $value,
            'CS' => $value,
            'EM' => $value,
            'RS' => $value,
            'dB' => $value,
        ];
    }

    private function clamp01(float $v): float
    {
        if ($v < 0.0) {
            return 0.0;
        }
        if ($v > 1.0) {
            return 1.0;
        }

        return $v;
    }

    /**
     * Quantize to a fixed step size (0.25 matches the observed screenshot values).
     */
    private function quantizeToStep(float $v, float $step): float
    {
        if ($step <= 0) {
            return $v;
        }

        $q = round($v / $step) * $step;

        return round($q, 2);
    }

    private function toFloatOrNull(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }
}
