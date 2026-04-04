<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityDistributionRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityLocationOverrideRecord;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class ResourceType extends RootDocument
{
    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }

    public function getDescription(): ?string
    {
        return $this->getString('@description');
    }

    public function getDensityGramsPerCubicCentimeter(): ?float
    {
        return $this->getFloat('densityType/ResourceTypeDensity/densityUnit/GramsPerCubicCentimeter@gramsPerCubicCentimeter');
    }

    public function getQualityDistributionReference(): ?string
    {
        return $this->getString(
            'properties/ResourceTypeCraftingData/qualityDistribution/CraftingQualityDistribution_RecordRef@qualityDistributionRecord'
        );
    }

    public function getQualityLocationOverrideReference(): ?string
    {
        return $this->getString(
            'properties/ResourceTypeCraftingData/qualityLocationOverride/CraftingQualityLocationOverride_RecordRef@locationOverrideRecord'
        );
    }

    public function getQualityDistribution(): ?CraftingQualityDistributionRecord
    {
        $distribution = $this->getHydratedDocument('QualityDistribution', CraftingQualityDistributionRecord::class);

        if ($distribution instanceof CraftingQualityDistributionRecord) {
            return $distribution;
        }

        $reference = $this->getQualityDistributionReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getCraftingQualityDistributionByReference($reference);

        return $resolved instanceof CraftingQualityDistributionRecord ? $resolved : null;
    }

    public function getQualityLocationOverride(): ?CraftingQualityLocationOverrideRecord
    {
        $override = $this->getHydratedDocument('QualityLocationOverride', CraftingQualityLocationOverrideRecord::class);

        if ($override instanceof CraftingQualityLocationOverrideRecord) {
            return $override;
        }

        $reference = $this->getQualityLocationOverrideReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getCraftingQualityLocationOverrideByReference($reference);

        return $resolved instanceof CraftingQualityLocationOverrideRecord ? $resolved : null;
    }
}
