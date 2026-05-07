<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Detects cargo grids that belong to sibling vehicle variants
 *
 * Provides methods to identify and filter out cargo grids from other variants
 * of the same vehicle family, preventing over-counting when multiple variants
 * share a class name prefix.
 *
 * Two detection mechanisms:
 *
 * 1. Variant-suffix filtering (3+ part names like CRUS_Starlifter_A2):
 *    Grids whose suffix contains a non-descriptive identifier that doesn't match
 *    the vehicle's own variant suffix are from sibling variants.
 *
 * 2. Unmatched-capacity filtering (2-part names like DRAK_Cutter):
 *    When the loadout already found grids, any prefix/base-discovered grid with
 *    a non-descriptive suffix that doesn't match the loadout grids is from a
 *    sibling variant.
 */
trait DetectsSiblingVariantGrids
{
    /**
     * Check whether fallback cargo-grid resolution should be skipped entirely.
     *
     * Returns true when the result is already satisfied or the vehicle has no
     * cargo infrastructure (so any grids found would belong to sibling variants).
     */
    private function shouldSkipFallback(CargoGridResult $result): bool
    {
        return ! $result->shouldContinueSearching() || ! $result->hasCargoInfrastructure();
    }

    /**
     * Descriptive suffixes that describe grid position/type, not vehicle variant.
     *
     * Grids like ORIG_890Jump_CargoGrid_Rear use "Rear" as a positional descriptor,
     * not a variant identifier. These must not be filtered out.
     */
    private const DESCRIPTIVE_SUFFIXES = [
        'Rear', 'Main', 'Small', 'Large', 'Wide', 'Hangar',
        'Left', 'Right', 'Front', 'Back', 'Top', 'Bottom',
        'Mid', 'Outer', 'Inner', 'Nose', 'Center', 'Side',
        'Walkway', 'Personal', 'Armory', 'DS',
    ];

    /**
     * Extract the variant suffix from a vehicle class name.
     *
     * For example:
     * - CRUS_Starlifter_A2 -> "A2"
     * - ORIG_890Jump -> null (no variant suffix, 2-part name)
     * - DRAK_Cutter -> null (no variant suffix, 2-part name)
     */
    private function extractVariantSuffix(string $className): ?string
    {
        if (! str_contains($className, '_')) {
            return null;
        }

        $parts = explode('_', $className);

        if (count($parts) < 3) {
            return null;
        }

        return array_pop($parts);
    }

    /**
     * Check if a cargo grid belongs to a sibling vehicle variant rather than this one.
     *
     * When the vehicle has a variant suffix (e.g. CRUS_Starlifter_A2 -> variant "A2"),
     * grids whose name contains a different variant identifier are from sibling variants
     * and should be excluded to prevent over-counting.
     *
     * Positional/descriptive suffixes like Rear, Main, Large, Small, Hangar are NOT
     * variant identifiers - they describe grid position, not the vehicle variant.
     */
    private function isSiblingVariantGrid(string $vehicleClassName, string $gridClassName): bool
    {
        $vehicleVariant = $this->extractVariantSuffix($vehicleClassName);

        if ($vehicleVariant === null) {
            return false;
        }

        $suffix = $this->extractCargoGridSuffix($gridClassName);

        if ($suffix === null) {
            // Bare _CargoGrid with no suffix - belongs to the base model, not this variant
            return true;
        }

        $parts = array_filter(explode('_', $suffix));

        foreach ($parts as $part) {
            if (in_array($part, self::DESCRIPTIVE_SUFFIXES, true)) {
                continue;
            }

            // This part is a candidate variant identifier
            if (strtolower($part) !== strtolower($vehicleVariant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a prefix/base-discovered grid belongs to a sibling variant
     * for vehicles with no variant suffix (2-part class names).
     *
     * When the loadout already found grids and the vehicle has no variant suffix,
     * we can't use variant-based filtering. Instead, we check if the grid's suffix
     * (after _CargoGrid_) is a known positional/descriptive term or matches the
     * loadout-found grids. Capacity-based suffixes (like 2SCU, 4SCU) that don't
     * match the loadout grids indicate a sibling variant.
     *
     * For example:
     * - DRAK_Cutter (loadout found 4SCU) -> DRAK_Cutter_CargoGrid_2SCU is rejected (2SCU ≠ 4SCU)
     * - ORIG_890Jump (loadout found Hangar) -> ORIG_890Jump_CargoGrid_Rear is allowed ("Rear" is positional)
     */
    private function isUnmatchedVariantCapacityGrid(
        string $vehicleClassName,
        string $gridClassName,
        CargoGridResult $result
    ): bool {
        // Only applies when we can't detect variants (2-part names)
        if ($this->extractVariantSuffix($vehicleClassName) !== null) {
            return false;
        }

        // Only applies when the loadout found grids
        if (! $result->loadoutFoundGrids()) {
            return false;
        }

        $suffix = $this->extractCargoGridSuffix($gridClassName);

        if ($suffix === null) {
            // Bare _CargoGrid - can't determine if it's a sibling; let UUID dedup handle it
            return false;
        }

        $parts = array_filter(explode('_', $suffix));

        // If ALL parts are descriptive, this is a positional grid -> allow
        $allDescriptive = true;
        foreach ($parts as $part) {
            if (! in_array($part, self::DESCRIPTIVE_SUFFIXES, true)) {
                $allDescriptive = false;
                break;
            }
        }

        if ($allDescriptive) {
            return false;
        }

        // Check if non-descriptive parts match any loadout-found grid
        foreach ($parts as $part) {
            if (in_array($part, self::DESCRIPTIVE_SUFFIXES, true)) {
                continue;
            }

            // Non-descriptive part: check if it matches a loadout grid suffix
            if ($this->partMatchesLoadoutGrid($part, $result)) {
                return false;
            }
        }

        // Non-descriptive suffix that doesn't match any loadout grid -> sibling variant
        return true;
    }

    /**
     * Check if a suffix part matches any of the loadout-found grid class names.
     */
    private function partMatchesLoadoutGrid(string $part, CargoGridResult $result): bool
    {
        $partLower = strtolower($part);

        foreach ($result->loadoutGridClassNames as $loadoutClass) {
            $loadoutSuffix = $this->extractCargoGridSuffix($loadoutClass);

            if ($loadoutSuffix === null) {
                continue;
            }

            $loadoutParts = array_filter(explode('_', $loadoutSuffix));

            if (array_any($loadoutParts, fn ($loadoutPart) => strtolower($loadoutPart) === $partLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the suffix after _CargoGrid_ (or _CargoGrid at end-of-string) from a class name.
     *
     * Returns null if the class name doesn't contain _CargoGrid at all.
     * Returns an empty string for bare _CargoGrid names (no trailing suffix).
     *
     * @return string|null The suffix after _CargoGrid_, or null if not a cargo grid class
     */
    private function extractCargoGridSuffix(string $className): ?string
    {
        $lower = strtolower($className);

        $pos = strpos($lower, '_cargogrid_');
        if ($pos !== false) {
            return substr($className, $pos + strlen('_cargogrid_'));
        }

        if (str_ends_with($lower, '_cargogrid')) {
            return '';
        }

        return null;
    }
}
