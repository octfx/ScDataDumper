<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class HarvestableElement extends RootDocument
{
    public function getRelativeProbability(): ?float
    {
        return $this->getFloat('@relativeProbability');
    }

    public function getHarvestableReference(): ?string
    {
        return $this->getString('@harvestable');
    }

    public function getHarvestableEntityClassReference(): ?string
    {
        return $this->getString('@harvestableEntityClass');
    }

    public function getClusteringReference(): ?string
    {
        return $this->getString('@clustering');
    }

    public function getHarvestableSetupReference(): ?string
    {
        return $this->getString('@harvestableSetup');
    }

    public function getHarvestable(): ?HarvestablePreset
    {
        return $this->getHydratedDocument('Harvestable', HarvestablePreset::class);
    }

    public function getHarvestableEntity(): ?EntityClassDefinition
    {
        return $this->getHydratedDocument('HarvestableEntity', EntityClassDefinition::class);
    }

    public function getClustering(): ?HarvestableClusterPreset
    {
        return $this->getHydratedDocument('Clustering', HarvestableClusterPreset::class);
    }

    public function getHarvestableSetup(): ?HarvestableSetup
    {
        return $this->getHydratedDocument('HarvestableSetup', HarvestableSetup::class);
    }
}
