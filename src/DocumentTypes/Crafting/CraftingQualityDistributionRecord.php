<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class CraftingQualityDistributionRecord extends RootDocument
{
    /**
     * @return array{min: int, max: int, mean: int, stddev: int}|null
     */
    public function getDefaultDistribution(): ?array
    {
        $min = $this->getInt('qualityDistribution/CraftingQualityDistributionNormal'.'@min');
        $max = $this->getInt('qualityDistribution/CraftingQualityDistributionNormal'.'@max');
        $mean = $this->getInt('qualityDistribution/CraftingQualityDistributionNormal'.'@mean');
        $stddev = $this->getInt('qualityDistribution/CraftingQualityDistributionNormal'.'@stddev');

        if ($min === null || $max === null || $mean === null || $stddev === null) {
            return null;
        }

        return [
            'min' => $min,
            'max' => $max,
            'mean' => $mean,
            'stddev' => $stddev,
        ];
    }
}
