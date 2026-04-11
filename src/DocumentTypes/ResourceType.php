<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;
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
        $resolved = $this->resolveRelatedDocument(
            'QualityDistribution',
            CraftingQualityDistributionRecord::class,
            $this->getQualityDistributionReference(),
            static fn (string $reference): ?CraftingQualityDistributionRecord => ServiceFactory::getFoundryLookupService()
                ->getCraftingQualityDistributionByReference($reference)
        );

        return $resolved instanceof CraftingQualityDistributionRecord ? $resolved : null;
    }

    public function getQualityLocationOverride(): ?CraftingQualityLocationOverrideRecord
    {
        $resolved = $this->resolveRelatedDocument(
            'QualityLocationOverride',
            CraftingQualityLocationOverrideRecord::class,
            $this->getQualityLocationOverrideReference(),
            static fn (string $reference): ?CraftingQualityLocationOverrideRecord => ServiceFactory::getFoundryLookupService()
                ->getCraftingQualityLocationOverrideByReference($reference)
        );

        return $resolved instanceof CraftingQualityLocationOverrideRecord ? $resolved : null;
    }

    public static function resolveFromCraftingCost(Element $cost): ?self
    {
        $resourceType = $cost->get('ResourceType');

        if ($resourceType instanceof Element) {
            $resolved = self::fromNode($resourceType->getNode());

            if ($resolved instanceof self) {
                return $resolved;
            }
        }

        $reference = $cost->get('@resource');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference)
            : null;
    }
}
