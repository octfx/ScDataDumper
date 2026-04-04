<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

use DOMDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class ResourceType extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $qualityDistributionReference = $this->get(
            'properties/ResourceTypeCraftingData/qualityDistribution/CraftingQualityDistribution_RecordRef@qualityDistributionRecord'
        );
        $qualityLocationOverrideReference = $this->get(
            'properties/ResourceTypeCraftingData/qualityLocationOverride/CraftingQualityLocationOverride_RecordRef@locationOverrideRecord'
        );

        if (
            (! is_string($qualityDistributionReference) || $qualityDistributionReference === '') &&
            (! is_string($qualityLocationOverrideReference) || $qualityLocationOverrideReference === '')
        ) {
            return;
        }

        $lookup = ServiceFactory::getFoundryLookupService();

        $this->hydrateFoundryReference(
            $document,
            'properties/ResourceTypeCraftingData/qualityDistribution/CraftingQualityDistribution_RecordRef@qualityDistributionRecord',
            'QualityDistribution',
            $lookup->getCraftingQualityDistributionByReference($qualityDistributionReference)
        );

        $this->hydrateFoundryReference(
            $document,
            'properties/ResourceTypeCraftingData/qualityLocationOverride/CraftingQualityLocationOverride_RecordRef@locationOverrideRecord',
            'QualityLocationOverride',
            $lookup->getCraftingQualityLocationOverrideByReference($qualityLocationOverrideReference)
        );
    }

}
