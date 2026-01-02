<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Services\ItemService;

/**
 * Shared item resolution logic for loadout builders
 *
 * Classes using this trait must have an $itemService property of type ItemService.
 */
trait ItemResolutionTrait
{
    private const string NULL_UUID = '00000000-0000-0000-0000-000000000000';

    private array $itemCache = [];

    /**
     * Resolve an item by reference UUID or class name.
     */
    private function resolveItem(?string $classReference, ?string $className): ?EntityClassDefinition
    {
        $cacheKey = $classReference ?? $className;

        if ($cacheKey !== null && $cacheKey !== self::NULL_UUID && isset($this->itemCache[$cacheKey])) {
            return $this->itemCache[$cacheKey];
        }

        $entity = null;

        // Try by reference first
        if (! empty($classReference) && $classReference !== self::NULL_UUID) {
            $entity = $this->itemService->getByReference($classReference);
        }

        // Fall back to class name
        if ($entity === null && ! empty($className)) {
            $entity = $this->itemService->getByClassName($className);
        }

        // Cache the result
        if ($entity !== null && $cacheKey !== null) {
            $this->itemCache[$cacheKey] = $entity;
        }

        return $entity;
    }

    /**
     * Find a loadout entry by port name (case-insensitive).
     */
    private function findLoadoutEntry(?string $portName, array $loadoutEntries): ?array
    {
        if ($portName === null) {
            return null;
        }

        $portNameLower = strtolower($portName);

        return array_find($loadoutEntries, fn ($entry) => strtolower($entry['portName'] ?? '') === $portNameLower);
    }
}
