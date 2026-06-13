<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;

final class SalvageModifier extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentAttachableModifierParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element|null $component */
        $component = $this->get();

        $salvage = $component?->get('modifiers/ItemWeaponModifiersParams/weaponModifier/weaponStats/salvageModifier');

        if (! $salvage instanceof Element) {
            return null;
        }

        return $this->removeNullValues([
            'SalvageSpeedMultiplier' => $salvage->get('@salvageSpeedMultiplier'),
            'RadiusMultiplier' => $salvage->get('@radiusMultiplier'),
            'ExtractionEfficiency' => $salvage->get('@extractionEfficiency'),
        ]);
    }

    public function canTransform(): bool
    {
        if ($this->item === null || ! $this->has($this->elementKey ?? '')) {
            return false;
        }

        return $this->item->get('Components/EntityComponentAttachableModifierParams/modifiers/ItemWeaponModifiersParams/weaponModifier/weaponStats/salvageModifier') !== null;
    }
}
