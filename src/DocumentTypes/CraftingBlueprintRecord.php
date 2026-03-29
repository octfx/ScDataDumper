<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;

final class CraftingBlueprintRecord extends RootDocument
{
    public function getBlueprint(): ?Element
    {
        return $this->get('blueprint/CraftingBlueprint');
    }

    public function getCategoryUuid(): ?string
    {
        return $this->get('blueprint/CraftingBlueprint@category');
    }

    public function getOutputEntityUuid(): ?string
    {
        return $this->get('blueprint/CraftingBlueprint/processSpecificData/CraftingProcess_Creation@entityClass');
    }

    public function getCraftTier(): ?Element
    {
        return $this->get('blueprint/CraftingBlueprint/tiers/CraftingBlueprintTier');
    }
}
