<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

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
        $harvestable = $this->getHydratedDocument('Harvestable', HarvestablePreset::class);

        if ($harvestable instanceof HarvestablePreset) {
            return $harvestable;
        }

        $reference = $this->getHarvestableReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getHarvestablePresetByReference($reference);

        return $resolved instanceof HarvestablePreset ? $resolved : null;
    }

    public function getHarvestableEntity(): ?EntityClassDefinition
    {
        $entityClass = $this->getHydratedDocument('HarvestableEntity', EntityClassDefinition::class);

        if ($entityClass instanceof EntityClassDefinition) {
            return $entityClass;
        }

        $reference = $this->getHarvestableEntityClassReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getItemService()->getByReference($reference);

        return $resolved instanceof EntityClassDefinition ? $resolved : null;
    }

    public function getClustering(): ?HarvestableClusterPreset
    {
        $clustering = $this->getHydratedDocument('Clustering', HarvestableClusterPreset::class);

        if ($clustering instanceof HarvestableClusterPreset) {
            return $clustering;
        }

        $reference = $this->getClusteringReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getHarvestableClusterPresetByReference($reference);

        return $resolved instanceof HarvestableClusterPreset ? $resolved : null;
    }

    public function getHarvestableSetup(): ?HarvestableSetup
    {
        $setup = $this->getHydratedDocument('HarvestableSetup', HarvestableSetup::class);

        if ($setup instanceof HarvestableSetup) {
            return $setup;
        }

        $reference = $this->getHarvestableSetupReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getHarvestableSetupByReference($reference);

        return $resolved instanceof HarvestableSetup ? $resolved : null;
    }
}
