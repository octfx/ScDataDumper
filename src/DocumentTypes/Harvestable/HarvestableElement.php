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
        $resolved = $this->resolveRelatedDocument(
            'Harvestable',
            HarvestablePreset::class,
            $this->getHarvestableReference(),
            static fn (string $reference): ?HarvestablePreset => ServiceFactory::getFoundryLookupService()
                ->getHarvestablePresetByReference($reference)
        );

        return $resolved instanceof HarvestablePreset ? $resolved : null;
    }

    public function getHarvestableEntity(): ?EntityClassDefinition
    {
        $resolved = $this->resolveRelatedDocument(
            'HarvestableEntity',
            EntityClassDefinition::class,
            $this->getHarvestableEntityClassReference(),
            static fn (string $reference): ?EntityClassDefinition => ServiceFactory::getItemService()->getByReference($reference)
        );

        return $resolved instanceof EntityClassDefinition ? $resolved : null;
    }

    public function getClustering(): ?HarvestableClusterPreset
    {
        $resolved = $this->resolveRelatedDocument(
            'Clustering',
            HarvestableClusterPreset::class,
            $this->getClusteringReference(),
            static fn (string $reference): ?HarvestableClusterPreset => ServiceFactory::getFoundryLookupService()
                ->getHarvestableClusterPresetByReference($reference)
        );

        return $resolved instanceof HarvestableClusterPreset ? $resolved : null;
    }

    public function getHarvestableSetup(): ?HarvestableSetup
    {
        $resolved = $this->resolveRelatedDocument(
            'HarvestableSetup',
            HarvestableSetup::class,
            $this->getHarvestableSetupReference(),
            static fn (string $reference): ?HarvestableSetup => ServiceFactory::getFoundryLookupService()
                ->getHarvestableSetupByReference($reference)
        );

        return $resolved instanceof HarvestableSetup ? $resolved : null;
    }
}
