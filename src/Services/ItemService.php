<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;

final class ItemService extends BaseService
{
    private array $entityPaths;

    /**
     * LRU document cache keyed by file path.
     * Most-recently-used entries are at the end; oldest at the front.
     *
     * @var array<string, EntityClassDefinition>
     */
    protected static array $documentCache = [];

    private const CACHE_LIMIT = 2000;

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->entityPaths);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $items = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)['EntityClassDefinition'] ?? [];

        // $items = array_filter($items, static fn ($path) => ! str_contains($path, 'entities'.DIRECTORY_SEPARATOR.'spaceships') && ! str_contains($path, 'entities'.DIRECTORY_SEPARATOR.'groundvehicles'));

        // Testing
        // $items = array_filter($items, static fn ($path) => str_contains($path, 'rsi_bengal_scitem_remote_turret_main_gun'));

        $this->entityPaths = $items;
    }

    public function iterator(): Generator
    {
        foreach ($this->entityPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByClassName(?string $className): ?EntityClassDefinition
    {
        if ($className === null || ! isset($this->entityPaths[$className])) {
            return null;
        }

        return $this->load($this->entityPaths[$className]);
    }

    /**
     * Find the first class name that starts with the given prefix and load it.
     */
    public function getFirstByClassNamePrefix(string $prefix): ?EntityClassDefinition
    {
        foreach ($this->entityPaths as $className => $path) {
            if (str_starts_with($className, $prefix)) {
                return $this->load($path);
            }
        }

        return null;
    }

    /**
     * Find the next class name after $afterClassName that starts with $prefix.
     */
    public function getNextByClassNamePrefix(string $prefix, string $afterClassName): ?EntityClassDefinition
    {
        $found = false;
        foreach ($this->entityPaths as $className => $path) {
            if (! $found) {
                if ($className === $afterClassName) {
                    $found = true;
                }

                continue;
            }

            if (str_starts_with($className, $prefix)) {
                return $this->load($path);
            }
        }

        return null;
    }

    public function getByReference(?string $reference): ?EntityClassDefinition
    {
        $path = $this->resolvePathByReference($reference);

        if ($path === null) {
            return null;
        }

        return $this->load($path);
    }

    /**
     * @return array<int, string>
     */
    public function getPathsBySubType(string $subType): array
    {
        $this->requireEntityMetadata();

        $normalizedSubType = strtolower(trim($subType));
        if ($normalizedSubType === '') {
            return [];
        }

        $paths = [];

        foreach (self::$entityMetadataMap as $metadata) {
            $metadataSubType = strtolower((string) ($metadata['sub_type'] ?? ''));
            $path = $metadata['path'] ?? null;

            if ($metadataSubType !== $normalizedSubType || ! is_string($path) || $path === '') {
                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Get item UUID by class name using the cache map
     *
     * @param  string|null  $className  The entity class name
     * @return string|null The UUID if found, null otherwise
     */
    public function getUuidByClassName(?string $className): ?string
    {
        if ($className === null || empty($className)) {
            return null;
        }

        return self::$classToUuidMap[$className] ?? null;
    }

    public function load(string $filePath, string $class = EntityClassDefinition::class): EntityClassDefinition
    {
        $cacheKey = $this->buildDocumentCacheKey($filePath);
        $item = self::cacheGet(self::$documentCache, $cacheKey);
        if ($item instanceof EntityClassDefinition) {
            return $item;
        }

        $item = $this->loadDocument($filePath, $class, $class === EntityClassDefinition::class);
        self::cachePut(self::$documentCache, $cacheKey, $item, self::CACHE_LIMIT);

        return $item;
    }

    /**
     * Find all items whose entity tag references intersect with the given tag UUIDs.
     *
     * Expands positive tags to include descendants (so searching for "Weapon" also matches "Pistol", "Rifle", etc.),
     * then excludes items matching any negative tags.
     *
     * @param  list<string>  $positiveTagUuids  Tag UUIDs that items must have
     * @param  list<string>  $negativeTagUuids  Tag UUIDs that exclude items
     * @return list<array{uuid: string, name: ?string}>
     */
    public function findByTagUuids(array $positiveTagUuids, array $negativeTagUuids = []): array
    {
        $tagService = ServiceFactory::getTagDatabaseService();
        $expandedPositive = array_map('strtolower', $tagService->expandTagsWithDescendants($positiveTagUuids));
        $expandedNegative = array_map('strtolower', $tagService->expandTagsWithDescendants($negativeTagUuids));

        $results = [];
        foreach ($this->iterator() as $item) {
            $itemTags = array_map('strtolower', $item->getEntityTagReferences());

            // Item must have at least one positive tag
            if (empty(array_intersect($expandedPositive, $itemTags))) {
                continue;
            }

            // Item must not have any negative tag
            if (! empty(array_intersect($expandedNegative, $itemTags))) {
                continue;
            }

            $results[] = [
                'uuid' => $item->getUuid(),
                'name' => $item->getDisplayName(),
            ];
        }

        return $results;
    }

    private function buildDocumentCacheKey(string $filePath): string
    {
        return sprintf(
            '%d:%s',
            $this->referenceHydrationEnabled ? 1 : 0,
            $filePath
        );
    }
}
