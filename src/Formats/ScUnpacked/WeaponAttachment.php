<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class WeaponAttachment extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        if ($this->item?->getAttachType() !== 'WeaponAttachment') {
            return null;
        }

        $attachDef = $this->get();

        $subType = $attachDef->get('@SubType');
        $type = $attachDef->get('@Type');

        $attachmentPoint = $this->deriveAttachmentPoint($subType, $type);
        $itemType = $this->deriveItemType($attachmentPoint, $subType);

        $magnification = $this->getMagnificationData();

        $utilityClass = null;
        if ($subType === 'Utility') {
            $description = $attachDef->get('Localization/English@Description', '') ?? '';
            $utilityClass = $this->parseUtilityClassFromDescription($description);
        }

        $data = [
            'ItemType' => $itemType,
            'AttachmentPoint' => $attachmentPoint,
            'Magnification' => (int) $magnification === 1 ? null : $magnification,
            'UtilityClass' => $utilityClass,
            'Magazine' => $this->loadMagazineData(),
            'IronSight' => $subType === 'IronSight' ? $this->loadIronSightData() : [],
            'LaserPointer' => $this->loadLaserPointerData(),
            'Barrel' => $subType === 'Barrel' ? $this->loadBarrelData($attachDef) : [],
            'Flashlight' => $this->loadFlashlightModes(),
        ];

        return $this->removeNullValues($data);
    }

    private function loadMagazineData(): array
    {
        $ammo = $this->item->get('Components/SAmmoContainerComponentParams');

        if ($ammo === null) {
            return [];
        }

        $max = $ammo->get('@maxAmmoCount');
        if ($max === 0) {
            $max = $ammo->get('@maxRestockCount');
        }

        $data = [
            'AmmoParamsRecord' => $ammo->get('@ammoParamsRecord'),
            'InitialAmmoCount' => $ammo->get('@initialAmmoCount'),
            'MaxAmmoCount' => $max,
            'MaxRestockCount' => $ammo->get('@maxRestockCount'),
        ];

        return $this->removeNullValues($data);
    }

    private function loadIronSightData(): array
    {
        $aimModifier = $this->item->get('Components/SWeaponModifierComponentParams/modifier/weaponStats/aimModifier');
        $zeroingParams = $this->item->get('Components/SWeaponModifierComponentParams/zeroingParams/SWeaponZeroingParams');
        $scopeParams = $this->item->get('Components/SWeaponModifierComponentParams/scopeAttachmentParams/SScopeAttachmentParams');

        if ($aimModifier === null && $zeroingParams === null) {
            return [];
        }

        $data = [
            'ScopeType' => $scopeParams?->get('@scopeType'),
            'DefaultRange' => $zeroingParams?->get('@defaultRange'),
            'MaxRange' => $zeroingParams?->get('@maxRange'),
            'RangeIncrement' => $zeroingParams?->get('@rangeIncrement'),
            'AutoZeroingTime' => $zeroingParams?->get('@autoZeroingTime'),
            'ZoomScale' => $aimModifier?->get('@zoomScale'),
            'ZoomTimeScale' => $aimModifier?->get('@zoomTimeScale'),
        ];

        return $this->removeNullValues($data);
    }

    private function loadLaserPointerData(): array
    {
        $component = $this->item->get('Components/SAuxiliaryWeaponActionComponentParams');

        if ($component === null) {
            return [];
        }

        $beamParams = $component->get('auxiliaryWeaponAction/SAuxiliaryWeaponActionBeamParams');

        if ($beamParams === null) {
            return [];
        }

        $tintColor = $beamParams->get('beamGroup/SBeamGroupParams/beamEffects/SBeamEffectParams/tintColor/RGB')
            ?->attributesToArray(pascalCase: true) ?? [];
        $tintColorCss = $this->normalizeRgbToCssRange($tintColor);

        $data = [
            'Pausable' => $this->castBool($component->get('@pausable')),
            'AlwaysOn' => $this->castBool($component->get('@alwaysOn')),
            'Range' => $beamParams->get('@range'),
            'Color' => $tintColorCss,
            'ColorCss' => $tintColorCss ? sprintf('rgb(%d,%d,%d)', $tintColorCss['R'], $tintColorCss['G'], $tintColorCss['B']) : null,
        ];

        return $this->removeNullValues($data);
    }

    private function loadBarrelData(Element $attachDef): array
    {
        $barrelType = $this->resolveBarrelType($attachDef);

        if ($barrelType === null) {
            return [];
        }

        $component = $this->item->get('Components/SWeaponModifierComponentParams');
        $weaponStats = $component?->get('modifier/weaponStats');
        $recoil = $weaponStats?->get('recoilModifier');
        $aimRecoil = $recoil?->get('aimRecoilModifier');

        $fireEffects = $component?->get('fireEffects');
        $flashScale = null;
        $flashDelay = null;

        foreach ($fireEffects?->children() ?? [] as $node) {
            if ($node->nodeName !== 'SWeaponParticleEffectParams') {
                continue;
            }

            $flashScale = $node->get('@scale');
            $flashDelay = $node->get('@delay');
            break;
        }

        $fireRecoilStrengthMultiplier = $recoil?->get('@fireRecoilStrengthMultiplier');
        $randomnessMultiplier = $recoil?->get('@randomnessMultiplier');
        $randomPitchMultiplier = $aimRecoil?->get('@randomPitchMultiplier');
        $soundRadiusMultiplier = $weaponStats?->get('@soundRadiusMultiplier');

        if ($barrelType === 'flashhider') {
            return $this->removeNullValues([
                'Type' => 'Flash Hider',
                'MuzzleFlashScale' => $flashScale,
                'MuzzleFlashScaleChange' => round($flashScale - 1, 2),
                'RecoilStability' => $fireRecoilStrengthMultiplier,
                'RecoilStabilityChange' => round(1 - $fireRecoilStrengthMultiplier, 2),
                'RecoilSmoothness' => $randomnessMultiplier,
                'RecoilSmoothnessChange' => round(1 - $randomnessMultiplier, 2),
            ]);
        }

        if ($barrelType === 'compensator') {
            return $this->removeNullValues([
                'Type' => 'Compensator',
                'MuzzleFlashDelay' => $flashDelay,
                'VisualRecoil' => $randomPitchMultiplier,
                'VisualRecoilChange' => round($randomPitchMultiplier - 1, 2),
                'AimRecoil' => $fireRecoilStrengthMultiplier,
                'AimRecoilChange' => round($fireRecoilStrengthMultiplier - 1, 2),
                'AudibleRange' => $soundRadiusMultiplier,
                'AudibleRangeChange' => round($soundRadiusMultiplier - 1, 2),
            ]);
        }

        if ($barrelType === 'stabilizer') {
            return $this->removeNullValues([
                'Type' => 'Stabilizer',
                'Spread' => $weaponStats->get('spreadModifier@maxMultiplier'),
                'SpreadChange' => round($weaponStats->get('spreadModifier@maxMultiplier', 1) - 1, 2),
                'ProjectileSpeed' => $weaponStats->get('@projectileSpeedMultiplier'),
                'ProjectileSpeedChange' => round($weaponStats->get('@projectileSpeedMultiplier', 1) - 1, 2),
                'VisualRecoil' => $randomPitchMultiplier,
                'VisualRecoilChange' => round($randomPitchMultiplier - 1, 2),
                'AimRecoil' => $fireRecoilStrengthMultiplier,
                'AimRecoilChange' => round($fireRecoilStrengthMultiplier - 1, 2),
            ]);
        }

        return [];
    }

    private function resolveBarrelType(Element $attachDef): ?string
    {
        $description = $attachDef->get('Localization/English@Description', '') ?? '';
        $parsed = ItemDescriptionParser::parse($description, ['Type' => 'Type', 'Item Type' => 'ItemType']);
        $parsedData = $parsed['data'] ?? [];
        $type = strtolower($parsedData['Type'] ?? $parsedData['ItemType'] ?? '');

        if ($type !== '') {
            if (str_contains($type, 'flash hider')) {
                return 'flashhider';
            }

            if (str_contains($type, 'compensator')) {
                return 'compensator';
            }

            if (str_contains($type, 'stabilizer')) {
                return 'stabilizer';
            }
        }

        $className = strtolower($this->item?->getClassName() ?? '');

        if ($className !== '') {
            if (str_contains($className, 'flhd')) {
                return 'flashhider';
            }

            if (str_contains($className, 'comp')) {
                return 'compensator';
            }

            if (str_contains($className, 'stab')) {
                return 'stabilizer';
            }
        }

        return null;
    }

    private function loadFlashlightModes(): array
    {
        $entries = $this->item->get('Components/SEntityComponentDefaultLoadoutParams/loadout/SItemPortLoadoutManualParams/entries');

        if ($entries === null) {
            return [];
        }

        $itemService = ServiceFactory::getItemService();
        $modes = [];

        foreach ($entries->children() ?? [] as $entry) {
            if ($entry->get('@skipPart') === '1') {
                continue;
            }

            $className = $entry->get('@entityClassName');
            $item = null;

            if (! empty($className)) {
                $item = $itemService->getByClassName($className);
            }

            if ($item === null) {
                $entityClassReference = $entry->get('@entityClassReference');
                if (! empty($entityClassReference) && $entityClassReference !== '00000000-0000-0000-0000-000000000000') {
                    $item = $itemService->getByReference($entityClassReference);
                }
            }

            if ($item === null) {
                continue;
            }

            $light = $item->get('Components/LightComponentParams');
            if ($light === null) {
                continue;
            }

            $defaultState = $light->get('defaultState');
            $color = $defaultState?->get('color')?->attributesToArray(pascalCase: true) ?? [];
            $useTemperature = (float) ($light->get('@useTemperature') ?? 0);
            $resolvedClassName = $className ?: $item->getClassName();

            $colorNormalized = $this->normalizeRgbToCssRange($color);

            $mode = [
                'PortName' => $entry->get('@itemPortName'),
                'ClassName' => $resolvedClassName,
                'Name' => $this->deriveFlashlightModeName($resolvedClassName, $entry->get('@itemPortName')),
                'LightType' => $light->get('@lightType'),
                'LightRadius' => $light->get('sizeParams@lightRadius'),
                'Intensity' => $defaultState?->get('@intensity'),
                'Temperature' => $useTemperature !== 0.0 ? $defaultState?->get('@temperature') : null,
                'Color' => $colorNormalized,
                'ColorCss' => $colorNormalized ? sprintf('rgb(%d,%d,%d)', $colorNormalized['R'], $colorNormalized['G'], $colorNormalized['B']) : null,
            ];

            $modes[] = $this->removeNullValues($mode);
        }

        return $modes;
    }

    private function deriveFlashlightModeName(?string $className, ?string $portName): ?string
    {
        $source = strtolower($className ?? '');

        if ($source !== '') {
            if (preg_match('/(narrow|wide)/', $source, $matches) === 1) {
                return ucfirst($matches[1]);
            }

            if (preg_match('/light_([^_]+)_/', $source, $matches) === 1) {
                return ucfirst($matches[1]);
            }
        }

        return $portName ? ucfirst(str_replace('_', ' ', $portName)) : null;
    }

    private function getMagnificationData(): ?float
    {
        $aimModifier = $this->item->get('Components/SWeaponModifierComponentParams/modifier/weaponStats/aimModifier');

        return $aimModifier?->get('@zoomScale');
    }

    private function deriveAttachmentPoint(string $subType, string $type): ?string
    {
        return match ($subType) {
            'IronSight' => 'Optic',
            'Magazine' => 'Magazine Well',
            'Barrel' => 'Barrel',
            'Utility' => 'Utility',
            'Weapon' => $type === 'Light' ? 'Utility' : null,
            default => null,
        };
    }

    private function deriveItemType(?string $attachmentPoint, string $subType): ?string
    {
        if ($attachmentPoint === 'Optic') {
            return 'Scope';
        }

        return match ($subType) {
            'Magazine' => 'Magazine',
            'Barrel' => 'Barrel',
            default => null,
        };
    }

    private function parseUtilityClassFromDescription(string $description): ?string
    {
        if (empty($description)) {
            return null;
        }

        $parsed = ItemDescriptionParser::parse($description, ['Class' => 'UtilityClass']);

        return $parsed['data']['UtilityClass'] ?? null;
    }

    private function castBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    private function normalizeRgbToCssRange(array $rgb): ?array
    {
        if ($rgb === []) {
            return null;
        }

        $r = $rgb['R'] ?? null;
        $g = $rgb['G'] ?? null;
        $b = $rgb['B'] ?? null;

        if ($r === null || $g === null || $b === null) {
            return null;
        }

        $max = max($r, $g, $b);
        if ($max <= 1) {
            $r = (int) round($r * 255);
            $g = (int) round($g * 255);
            $b = (int) round($b * 255);
        } else {
            $r = (int) round($r);
            $g = (int) round($g);
            $b = (int) round($b);
        }

        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        return ['R' => $r, 'G' => $g, 'B' => $b];
    }
}
