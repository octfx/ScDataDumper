<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

/**
 * Personal / FPS weapons
 */
final class PersonalWeapon extends AbstractWeapon
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->buildBaseWeaponArray();
    }
}
