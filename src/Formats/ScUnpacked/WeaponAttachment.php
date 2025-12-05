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

        $description = $attachDef->get('Localization/English@Description', '') ?? '';

        $descriptionData = ItemDescriptionParser::parse($description, [
            'Manufacturer' => 'Manufacturer',
            'Item Type' => 'ItemType',
            'Type' => 'Type',
            'Attachment Point' => 'AttachmentPoint',
            'Magnification' => 'Magnification',
            'Capacity' => 'Capacity',
            'Class' => 'UtilityClass',
        ]);

        $parsed = $descriptionData['data'] ?? [];

        $type = $parsed['Type'] ?? $attachDef->get('SubType');
        $attachmentPoint = $parsed['AttachmentPoint'] ?? null;
        $itemType = $parsed['ItemType'] ?? null;

        if ($attachDef->get('SubType') === 'IronSight' && empty($attachmentPoint)) {
            $attachmentPoint = 'Optic';
            $type = 'IronSight';
        }

        if (empty($type)) {
            $type = $attachDef->get('SubType');
        }

        if ($type === 'Magazine') {
            $attachmentPoint = $attachmentPoint ?? 'Magazine Well';
        }

        if ($attachmentPoint === null && $type === 'Utility') {
            $attachmentPoint = 'Utility';
        }

        if ($attachmentPoint === 'Optic') {
            $itemType = $itemType ?? 'Scope';
        }

        if ($attachmentPoint === null && $itemType === 'Ammo' && $type === 'Missile') {
            $attachmentPoint = 'Barrel';
        }

        $data = [
            'ItemType' => $itemType,
            'AttachmentPoint' => $attachmentPoint,
            'Magnification' => $parsed['Magnification'] ?? null,
            'Capacity' => $parsed['Capacity'] ?? null,
            'UtilityClass' => $parsed['UtilityClass'] ?? null,
            'Ammo' => $this->loadAmmoData(),
            'IronSight' => $attachDef->get('SubType') === 'IronSight' ? $this->loadIronSightData() : [],
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
            'AmmunitionUuid' => $ammo->get('ammoParamsRecord'),
            'InitialAmmoCount' => $ammo->get('initialAmmoCount'),
            'MaxAmmoCount' => $max,
        ];

        return $this->removeNullValues($data);
    }

    private function loadIronSightData(): array
    {
        $aimModifier = $this->item->get('Components/SWeaponModifierComponentParams/modifier/weaponStats/aimModifier');
        $zeroingParams = $this->item->get('Components/SWeaponModifierComponentParams/zeroingParams/SWeaponZeroingParams');

        if ($aimModifier === null && $zeroingParams === null) {
            return [];
        }

        $data = [
            'DefaultRange' => $zeroingParams?->get('defaultRange'),
            'MaxRange' => $zeroingParams?->get('maxRange'),
            'RangeIncrement' => $zeroingParams?->get('rangeIncrement'),
            'AutoZeroingTime' => $zeroingParams?->get('autoZeroingTime'),
            'ZoomScale' => $aimModifier?->get('zoomScale'),
            'ZoomTimeScale' => $aimModifier?->get('zoomTimeScale'),
        ];

        return $this->removeNullValues($data);
    }
}
