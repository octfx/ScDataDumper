<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Factory wrapper that dispatches to PersonalWeapon or VehicleWeapon based on type.
 */
final class Weapon extends BaseFormat
{
    private AbstractWeapon $delegate;

    public function __construct($item)
    {
        parent::__construct($item);
        $this->delegate = $this->resolveFormatter();
    }

    public function toArray(): ?array
    {
        return $this->delegate->toArray();
    }

    public function canTransform(): bool
    {
        return $this->delegate->canTransform();
    }

    private function resolveFormatter(): AbstractWeapon
    {
        $type = $this->item?->get('Components/SAttachableComponentParams/AttachDef@Type');

        if ($type === 'WeaponPersonal') {
            return new PersonalWeapon($this->item);
        }

        return new VehicleWeapon($this->item);
    }
}
