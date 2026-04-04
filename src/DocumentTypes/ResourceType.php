<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityDistributionRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityLocationOverrideRecord;

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
        return $this->getHydratedDocument('QualityDistribution', CraftingQualityDistributionRecord::class);
    }

    public function getQualityLocationOverride(): ?CraftingQualityLocationOverrideRecord
    {
        return $this->getHydratedDocument('QualityLocationOverride', CraftingQualityLocationOverrideRecord::class);
    }
}
