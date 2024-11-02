<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\Definitions\EntityClassDefinition\EntityClassDefinition;
use RuntimeException;

final class ItemService extends BaseService
{
    private array $entityPaths;

    public function count(): int
    {
        return count($this->entityPaths);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)['EntityClassDefinition'] ?? [];
        $items = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)['EntityClassDefinition'] ?? [];

        $this->entityPaths = array_intersect_key($items, $classes);
    }

    public function iterator(): Generator
    {
        foreach ($this->entityPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $reference): ?EntityClassDefinition
    {
        if ($reference === null || ! isset(self::$uuidToPathMap[$reference])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$reference]);
    }

    public function load(string $filePath, string $class = EntityClassDefinition::class): EntityClassDefinition
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $item = simplexml_load_string(file_get_contents($filePath), $class, LIBXML_NOCDATA | LIBXML_NOBLANKS);

        if ($item === false || ! is_object($item)) {
            throw new RuntimeException(sprintf('Cannot parse XML %s', $filePath));
        }

        if ($class === EntityClassDefinition::class) {
            $item->checkValidity();
        }

        return $item;
    }
}
