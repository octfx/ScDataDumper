<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class CraftingBlueprintTier extends RootDocument
{
    private const string RECIPE_COSTS_PATH = 'recipe/CraftingRecipe/costs/CraftingRecipeCosts';

    public function getCraftTimeSeconds(): ?float
    {
        return CraftingBlueprintRecord::parseTimeValue(
            $this->get(self::RECIPE_COSTS_PATH.'/craftTime/TimeValue_Partitioned')
        );
    }

    public function getMandatoryCost(): ?CraftingCost
    {
        $element = $this->get(self::RECIPE_COSTS_PATH.'/mandatoryCost');

        if (! $element instanceof Element) {
            return null;
        }

        $cost = CraftingCost::fromNode($element->getNode());

        return $cost instanceof CraftingCost ? $cost : null;
    }

    public static function fromTierElement(Element $tier): ?self
    {
        return self::fromNode($tier->getNode());
    }
}
