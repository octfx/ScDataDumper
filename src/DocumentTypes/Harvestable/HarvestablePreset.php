<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestablePreset extends RootDocument
{
    public function getEntityClassReference(): ?string
    {
        return $this->getString('@entityClass');
    }

    public function getEntityClass(): ?EntityClassDefinition
    {
        return $this->getHydratedDocument('EntityClass', EntityClassDefinition::class);
    }
}
