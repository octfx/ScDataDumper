<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class CraftingGameplayPropertyModifier extends RootDocument
{
    public function getPropertyReference(): ?string
    {
        return $this->getString('@gameplayPropertyRecord');
    }

    public function getValueRange(): ?Element
    {
        return $this->get('valueRanges/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $this->get('valueRange/CraftingGameplayPropertyModifierValueRange_Linear');
    }

    public function getQualityMin(): int|float|null
    {
        $range = $this->getValueRange();

        return $range?->get('@startQuality') ?? $range?->get('@minInputValue');
    }

    public function getQualityMax(): int|float|null
    {
        $range = $this->getValueRange();

        return $range?->get('@endQuality') ?? $range?->get('@maxInputValue');
    }

    public function getModifierAtMinQuality(): int|float|null
    {
        $range = $this->getValueRange();

        return $range?->get('@modifierAtStart') ?? $range?->get('@minOutputMultiplier');
    }

    public function getModifierAtMaxQuality(): int|float|null
    {
        $range = $this->getValueRange();

        return $range?->get('@modifierAtEnd') ?? $range?->get('@maxOutputMultiplier');
    }

    public function getResolvedProperty(): ?CraftingGameplayPropertyDef
    {
        return CraftingGameplayPropertyDef::resolveFromModifier(new Element($this->documentElement));
    }

    public static function fromModifierElement(Element $modifier): ?self
    {
        return self::fromNode($modifier->getNode());
    }
}
