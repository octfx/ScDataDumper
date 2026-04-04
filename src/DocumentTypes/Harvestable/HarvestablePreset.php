<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class HarvestablePreset extends RootDocument
{
    public function getEntityClassReference(): ?string
    {
        return $this->getString('@entityClass');
    }

    public function getEntityClass(): ?EntityClassDefinition
    {
        $entityClass = $this->getHydratedDocument('EntityClass', EntityClassDefinition::class);

        if ($entityClass instanceof EntityClassDefinition) {
            return $entityClass;
        }

        $reference = $this->getEntityClassReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getItemService()->getByReference($reference);

        return $resolved instanceof EntityClassDefinition ? $resolved : null;
    }
}
