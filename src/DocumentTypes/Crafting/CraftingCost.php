<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingCost extends RootDocument
{
    use NormalizesValues;

    public function getCostKind(): string
    {
        return match ($this->documentElement->nodeName) {
            'CraftingCost_Select' => 'group',
            'CraftingCost_Item' => 'item',
            'CraftingCost_Resource' => 'resource',
            'CraftingCost_Blueprint', 'CraftingCost_BlueprintRef' => 'blueprint_ref',
            default => 'unknown',
        };
    }

    public function getNodeName(): string
    {
        return $this->documentElement->nodeName;
    }

    public function isCostNode(): bool
    {
        return str_starts_with($this->documentElement->nodeName, 'CraftingCost_');
    }

    public function isSelectNode(): bool
    {
        return $this->documentElement->nodeName === 'CraftingCost_Select';
    }

    public function getOptionsContainer(): Element
    {
        $container = $this->get('options');

        return $container instanceof Element ? $container : new Element($this->documentElement);
    }

    public function getRequiredCount(): int|float|null
    {
        return $this->normalizeNumber($this->get('@count'));
    }

    public function getQuantity(): int|float|null
    {
        return $this->normalizeNumber($this->get('@quantity'));
    }

    public function getMinQuality(): int|float|null
    {
        return $this->normalizeNumber($this->get('@minQuality'));
    }

    public function getEntityClassReference(): ?string
    {
        $ref = $this->get('@entityClass');

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    public function getResourceReference(): ?string
    {
        $ref = $this->get('@resource');

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    public function getBlueprintReference(): ?string
    {
        return $this->get('@blueprintRecord')
            ?? $this->get('@blueprint')
            ?? $this->get('@entityClass')
            ?? $this->get('@record');
    }

    public function getDebugName(): ?string
    {
        $nameInfo = $this->get('nameInfo');

        if (! $nameInfo instanceof Element) {
            return null;
        }

        $debugName = $nameInfo->get('@debugName');

        return is_string($debugName) && trim($debugName) !== '' ? $debugName : null;
    }

    public function getResolvedName(): ?string
    {
        $nameInfo = $this->get('nameInfo');

        if (! $nameInfo instanceof Element) {
            return null;
        }

        $displayName = $nameInfo->get('@displayName');
        $translated = ServiceFactory::getLocalizationService()->translateValue($displayName);

        if ($translated !== null) {
            return $translated;
        }

        return $this->getDebugName();
    }

    public function getQuantityMultiplier(): float
    {
        return CraftingBlueprintRecord::readResourceQuantityMultiplier(new Element($this->documentElement));
    }

    public function getInputEntity(): ?EntityClassDefinition
    {
        return EntityClassDefinition::resolveFromCraftingCost(new Element($this->documentElement));
    }

    public function getResourceType(): ?ResourceType
    {
        return ResourceType::resolveFromCraftingCost(new Element($this->documentElement));
    }

    public function getQuantityElement(): ?Element
    {
        return $this->get('quantity');
    }

    public function getResolvedGameplayProperty(): ?CraftingGameplayPropertyDef
    {
        return CraftingGameplayPropertyDef::resolveFromModifier(new Element($this->documentElement));
    }

    public function isSyntheticSelectWrapper(): bool
    {
        if (! $this->isSelectNode()) {
            return false;
        }

        $selectChildCount = $this->countSelectChildren();

        if ($selectChildCount === 0) {
            return false;
        }

        if ($this->getStatModifierElements() !== []) {
            return false;
        }

        $requiredCount = $this->getRequiredCount();

        if (
            $requiredCount !== null
            && (float) $requiredCount !== 1.0
            && (float) $requiredCount !== (float) $selectChildCount
        ) {
            return false;
        }

        $nameInfo = $this->get('nameInfo');

        if (! $nameInfo instanceof Element) {
            return $requiredCount === null
                || (float) $requiredCount === (float) $selectChildCount;
        }

        $slotKey = $nameInfo->get('@debugName')
            ?? $nameInfo->get('@displayName');

        return is_string($slotKey) && strtoupper($slotKey) === 'ASPECTS';
    }

    /**
     * @return list<self>
     */
    public function getCostChildren(): array
    {
        $children = [];

        foreach ($this->getOptionsContainer()->children() as $child) {
            $cost = self::fromNode($child->getNode());

            if (! $cost instanceof self || ! $cost->isCostNode()) {
                continue;
            }

            $children[] = $cost;
        }

        return $children;
    }

    /**
     * @return list<CraftingGameplayPropertyModifier>
     */
    public function getStatModifierElements(): array
    {
        $modifiers = $this->get(
            'context/CraftingCostContext_ResultGameplayPropertyModifiers/gameplayPropertyModifiers/CraftingGameplayPropertyModifiers_List/gameplayPropertyModifiers'
        );

        if (! $modifiers instanceof Element) {
            return [];
        }

        $results = [];

        foreach ($modifiers->children() as $modifier) {
            if ($modifier->nodeName !== 'CraftingGameplayPropertyModifierCommon') {
                continue;
            }

            $instance = CraftingGameplayPropertyModifier::fromModifierElement($modifier);

            if ($instance !== null) {
                $results[] = $instance;
            }
        }

        return $results;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getAttributes(): array
    {
        $attributes = [];

        foreach ($this->documentElement->attributes ?? [] as $attribute) {
            if (in_array($attribute->nodeName, ['__type', '__polymorphicType'], true)) {
                continue;
            }

            $attributes[$attribute->nodeName] = $this->normalizeNumber($attribute->nodeValue) ?? $attribute->nodeValue;
        }

        return $attributes;
    }

    /**
     * @return list<self>
     */
    public function getFlatLeaves(): array
    {
        $kind = $this->getCostKind();

        if (in_array($kind, ['item', 'resource', 'blueprint_ref'], true)) {
            return [$this];
        }

        $leaves = [];

        foreach ($this->getCostChildren() as $child) {
            if ($child->isSyntheticSelectWrapper()) {
                array_push($leaves, ...$child->getFlatLeaves());

                continue;
            }

            array_push($leaves, ...$child->getFlatLeaves());
        }

        return $leaves;
    }

    private function countSelectChildren(): int
    {
        $count = 0;

        foreach ($this->getOptionsContainer()->children() as $child) {
            if (! str_starts_with($child->nodeName, 'CraftingCost_')) {
                continue;
            }

            if ($child->nodeName !== 'CraftingCost_Select') {
                return 0;
            }

            $count++;
        }

        return $count;
    }
}
