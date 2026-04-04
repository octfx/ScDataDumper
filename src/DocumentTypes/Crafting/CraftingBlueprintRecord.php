<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

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

    public function getOutputEntity(): ?EntityClassDefinition
    {
        $entity = $this->resolveRelatedDocument(
            'blueprint/CraftingBlueprint/processSpecificData/CraftingProcess_Creation/OutputEntity',
            EntityClassDefinition::class,
            $this->getOutputEntityUuid(),
            static fn (string $reference): ?EntityClassDefinition => ServiceFactory::getItemService()->getByReference($reference)
        );

        return $entity instanceof EntityClassDefinition ? $entity : null;
    }

    public function getCraftTier(): ?Element
    {
        return $this->get('blueprint/CraftingBlueprint/tiers/CraftingBlueprintTier');
    }
}
