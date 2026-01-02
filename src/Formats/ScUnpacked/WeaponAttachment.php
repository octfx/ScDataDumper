<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;

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
            'Magnification' => $magnification,
            'UtilityClass' => $utilityClass,
            'Ammo' => $this->loadAmmoData(),
            'IronSight' => $subType === 'IronSight' ? $this->loadIronSightData() : [],
        ];

        return $this->removeNullValues($data);
    }

    private function loadAmmoData(): array
    {
        $ammo = $this->item->get('Components/SAmmoContainerComponentParams');

        if ($ammo === null) {
            return [];
        }

        $max = $ammo->get('maxAmmoCount');
        if ($max === 0) {
            $max = $ammo->get('maxRestockCount');
        }

        $data = [
            'AmmunitionUuid' => $ammo->get('@ammoParamsRecord'),
            'InitialAmmoCount' => $ammo->get('@initialAmmoCount'),
            'MaxAmmoCount' => $max,
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
}
