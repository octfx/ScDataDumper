<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Engineering Boost modifier attached to multi-crew ships via the `engineeringBuff` item port.
 *
 * The modifier entity uses `EntityComponentAttachableModifierParams` with
 * `ItemportTraversingModifiersParams` targeting `WeaponGun` items, applying
 * `regenModifier` multipliers (power ratio, ammo load, regen rate).
 *
 * @extends BaseFormat<EntityClassDefinition>
 */
final class EngineeringBoost extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentAttachableModifierParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element|null $component */
        $component = $this->get();

        $modifiersRoot = $component?->get('modifiers/ItemportTraversingModifiersParams/modifiers/ItemWeaponModifiersParams');
        if ($modifiersRoot === null) {
            return null;
        }

        $weaponStats = $modifiersRoot->get('weaponModifier/weaponStats');
        $regen = $weaponStats?->get('regenModifier');

        if ($regen === null) {
            return null;
        }

        $data = [
            'UUID' => $this->item->getUuid(),
            'PowerRatio' => (float) $regen->get('@powerRatioMultiplier', 1.0),
            'MaxAmmoLoad' => (float) $regen->get('@maxAmmoLoadMultiplier', 1.0),
            'MaxRegenPerSec' => (float) $regen->get('@maxRegenPerSecMultiplier', 1.0),
        ];

        // If all multipliers are 1.0, there's no meaningful boost
        if ($data['PowerRatio'] === 1.0 && $data['MaxAmmoLoad'] === 1.0 && $data['MaxRegenPerSec'] === 1.0) {
            return null;
        }

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        if ($this->item === null || ! $this->has($this->elementKey ?? '')) {
            return false;
        }

        return $this->item->get('Components/EntityComponentAttachableModifierParams/modifiers/ItemportTraversingModifiersParams') !== null;
    }
}
