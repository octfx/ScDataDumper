<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use RuntimeException;

final class ItemService extends BaseService
{
    private array $entityPaths;

    /**
     * Document cache keyed by file path
     *
     * @var array<string, EntityClassDefinition>
     */
    protected static array $documentCache = [];

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

        $items = array_filter($items, static fn ($path) => ! str_contains($path, 'entities'.DIRECTORY_SEPARATOR.'spaceships') && ! str_contains($path, 'entities'.DIRECTORY_SEPARATOR.'groundvehicles'));

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

    public function getByReference(?string $reference): ?EntityClassDefinition
    {
        if ($reference === null || ! isset(self::$uuidToPathMap[$reference])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$reference]);
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
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        if (isset(self::$documentCache[$filePath])) {
            return self::$documentCache[$filePath];
        }

        $item = new $class;
        $item->load($filePath);
        if ($class === EntityClassDefinition::class) {
            $item->checkValidity();
        }

        self::$documentCache[$filePath] = $item;

        return $item;
    }
}
