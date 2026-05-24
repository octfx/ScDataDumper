<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;

final class CraftingGameplayPropertyModifier extends RootDocument
{
    public function getPropertyReference(): ?string
    {
        return $this->getString('@gameplayPropertyRecord');
    }

    public function getValueRange(): ?Element
    {
        return $this->get('valueRanges/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $this->get('valueRange/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $this->get('valueRanges/CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive');
    }

    /**
     * Returns the type of value range used by this modifier.
     *
     * @return 'linear'|'linear_integer_additive'|'unknown'
     */
    public function getValueRangeType(): string
    {
        $additive = $this->get('valueRanges/CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive');

        if ($additive instanceof Element) {
            return 'linear_integer_additive';
        }

        $linear = $this->get('valueRanges/CraftingGameplayPropertyModifierValueRange_Linear')
            ?? $this->get('valueRange/CraftingGameplayPropertyModifierValueRange_Linear');

        if ($linear instanceof Element) {
            return 'linear';
        }

        return 'unknown';
    }

    /**
     * Returns all value range segments from this modifier.
     *
     * For multi-segment Linear modifiers, each segment has:
     *   {type: 'linear', quality_min, quality_max, modifier_at_start, modifier_at_end}
     *
     * For IntegerAdditive modifiers, each segment has:
     *   {type: 'linear_integer_additive', quality_min, quality_max, additive_at_start, additive_at_end}
     *
     * @return list<array{type: string, quality_min: int|float|null, quality_max: int|float|null, modifier_at_start?: int|float|null, modifier_at_end?: int|float|null, additive_at_start?: int|float|null, additive_at_end?: int|float|null}>
     */
    public function getValueSegments(): array
    {
        $valueRanges = $this->get('valueRanges');

        if (! $valueRanges instanceof Element) {
            return [];
        }

        $segments = [];

        foreach ($valueRanges->children() as $child) {
            $nodeName = $child->nodeName ?? '';

            if ($nodeName === 'CraftingGameplayPropertyModifierValueRange_Linear') {
                $segments[] = [
                    'type' => 'linear',
                    'quality_min' => $child->get('@startQuality'),
                    'quality_max' => $child->get('@endQuality'),
                    'modifier_at_start' => $child->get('@modifierAtStart'),
                    'modifier_at_end' => $child->get('@modifierAtEnd'),
                ];
            } elseif ($nodeName === 'CraftingGameplayPropertyModifierValueRange_LinearIntegerAdditive') {
                $segments[] = [
                    'type' => 'linear_integer_additive',
                    'quality_min' => $child->get('@startQuality'),
                    'quality_max' => $child->get('@endQuality'),
                    'additive_at_start' => $child->get('@additiveModifierAtStart'),
                    'additive_at_end' => $child->get('@additiveModifierAtEnd'),
                ];
            }
        }

        return $segments;
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
