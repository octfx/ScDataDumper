<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Mining;

use Octfx\ScDataDumper\DocumentTypes\Mining\MineableCompositionPart;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MiningQualityRangeResolver
{
    /**
     * @return array{
     *     base_min: int,
     *     base_max: int,
     *     effective_min: int,
     *     effective_max: int,
     *     quality_scale: ?float
     * }|null
     */
    public function resolveForCompositionPart(
        MineableCompositionPart $part,
        ?string $locationName = null,
        ?string $systemName = null
    ): ?array {
        $resourceType = $part->getMineableElement()?->getResourceType();

        return $resourceType instanceof ResourceType
            ? $this->resolveForResourceType($resourceType, $part->getQualityScale(), $locationName, $systemName)
            : null;
    }

    /**
     * @return array{
     *     base_min: int,
     *     base_max: int,
     *     effective_min: int,
     *     effective_max: int,
     *     quality_scale: ?float
     * }|null
     */
    public function resolveForResourceTypeReference(
        ?string $resourceTypeUuid,
        ?float $qualityScale = null,
        ?string $locationName = null,
        ?string $systemName = null
    ): ?array {
        if (! is_string($resourceTypeUuid) || $resourceTypeUuid === '') {
            return null;
        }

        $resourceType = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($resourceTypeUuid);

        return $resourceType instanceof ResourceType
            ? $this->resolveForResourceType($resourceType, $qualityScale, $locationName, $systemName)
            : null;
    }

    /**
     * @return array{
     *     base_min: int,
     *     base_max: int,
     *     effective_min: int,
     *     effective_max: int,
     *     quality_scale: ?float
     * }|null
     */
    private function resolveForResourceType(
        ResourceType $resourceType,
        ?float $qualityScale,
        ?string $locationName,
        ?string $systemName
    ): ?array {
        $distribution = $resourceType->getQualityDistribution()?->getDefaultDistribution();

        if ($distribution === null) {
            return null;
        }

        $locationOverride = $resourceType->getQualityLocationOverride();

        foreach ([$locationName, $systemName] as $candidateLocation) {
            if (! is_string($candidateLocation) || $candidateLocation === '' || $locationOverride === null) {
                continue;
            }

            $overrideDistribution = $locationOverride->getDistributionForLocation($candidateLocation);
            if ($overrideDistribution !== null) {
                $distribution = $overrideDistribution;
                break;
            }
        }

        $effectiveRange = $this->applyQualityScale(
            $distribution['min'],
            $distribution['max'],
            $qualityScale
        );

        return [
            'base_min' => $distribution['min'],
            'base_max' => $distribution['max'],
            'effective_min' => $effectiveRange['min'],
            'effective_max' => $effectiveRange['max'],
            'quality_scale' => $qualityScale,
        ];
    }

    /**
     * @return array{min: int, max: int}
     */
    private function applyQualityScale(int $baseMin, int $baseMax, ?float $qualityScale): array
    {
        if ($qualityScale === null || $qualityScale >= 1.0) {
            return [
                'min' => $baseMin,
                'max' => $baseMax,
            ];
        }

        return [
            'min' => (int) round($baseMin * $qualityScale),
            'max' => (int) floor($baseMax * $qualityScale),
        ];
    }
}
