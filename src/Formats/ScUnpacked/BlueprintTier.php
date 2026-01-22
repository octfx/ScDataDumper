<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class BlueprintTier extends BaseFormat
{
    public function toArray(): ?array
    {
        if ($this->item === null) {
            return null;
        }

        $data = [];

        $recipe = $this->get('/CraftingRecipe');
        $research = $this->get('/CraftingResearch');

        if ($recipe !== null) {
            $data['Recipe'] = new BlueprintRecipe($recipe);
        }

        if ($research !== null) {
            $researchCosts = $this->get('/CraftingResearch/CraftingRecipeCosts');
            if ($researchCosts !== null) {
                $data['Research'] = new CraftingCost($researchCosts);
            }
        }

        return empty($data) ? null : $this->removeNullValues($data);
    }
}
