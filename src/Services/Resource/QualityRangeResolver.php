<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use Octfx\ScDataDumper\DocumentTypes\Mining\MineableCompositionPart;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class QualityRangeResolver
{
    /**
     * @return array{
     *     base_min: int,
     *     base_max: int,
     *     base_mean: int,
     *     base_stddev: int,
     *     effective_min: int,
     *     effective_max: int,
     *     effective_mean: int,
     *     effective_stddev: int,
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
     *     base_mean: int,
     *     base_stddev: int,
     *     effective_min: int,
     *     effective_max: int,
     *     effective_mean: int,
     *     effective_stddev: int,
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
     *     base_mean: int,
     *     base_stddev: int,
     *     effective_min: int,
     *     effective_max: int,
     *     effective_mean: int,
     *     effective_stddev: int,
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

        $effectiveMean = $this->applyQualityScaleValue(
            $distribution['mean'],
            $qualityScale
        );

        $effectiveStddev = $this->applyQualityScaleValue(
            $distribution['stddev'],
            $qualityScale
        );

        return [
            'base_min' => $distribution['min'],
            'base_max' => $distribution['max'],
            'base_mean' => $distribution['mean'],
            'base_stddev' => $distribution['stddev'],
            'effective_min' => $effectiveRange['min'],
            'effective_max' => $effectiveRange['max'],
            'effective_mean' => $effectiveMean,
            'effective_stddev' => $effectiveStddev,
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

    private function applyQualityScaleValue(int $value, ?float $qualityScale): int
    {
        if ($qualityScale === null || $qualityScale >= 1.0) {
            return $value;
        }

        return (int) round($value * $qualityScale);
    }

    /**
     * Filter quantization bands to only those reachable within the effective range.
     *
     * @param  list<array{start: int, end: int, mappedValue: int}>|null  $bands
     * @return list<int>|null Sorted list of reachable mapped values
     */
    public static function filterReachableQuantization(?array $bands, ?int $effectiveMin, ?int $effectiveMax): ?array
    {
        if ($bands === null || $bands === []) {
            return null;
        }

        // No range info: return all mapped values
        if ($effectiveMin === null || $effectiveMax === null) {
            return array_values(array_unique(array_map(
                static fn (array $band): int => $band['mappedValue'],
                $bands
            )));
        }

        $reachable = [];
        foreach ($bands as $band) {
            if ($band['end'] < $effectiveMin || $band['start'] > $effectiveMax) {
                continue;
            }
            $reachable[] = $band['mappedValue'];
        }

        return array_values(array_unique($reachable));
    }
}
