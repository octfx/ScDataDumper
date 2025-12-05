<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\InventoryContainer;
use RuntimeException;

final class InventoryContainerService extends BaseService
{
    private array $inventoryContainerPaths;

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $classes = json_decode(file_get_contents($this->classToTypeMapPath), true, 512, JSON_THROW_ON_ERROR);

        $classes = array_filter($classes, static fn ($type) => in_array($type, ['InventoryContainer', 'CargoGrid'], true));

        $items = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        $this->inventoryContainerPaths = array_intersect_key($items['InventoryContainer'], $classes);
    }

    public function getByReference($uuid): ?InventoryContainer
    {
        if (! is_string($uuid) || ! isset(self::$uuidToPathMap[$uuid])) {
            return null;
        }

        $filePath = self::$uuidToPathMap[$uuid];

        // Some references (e.g. item __ref values) point to EntityClassDefinition
        // files rather than standalone InventoryContainer records. Loading those
        // with an InventoryContainer DOM wrapper triggers a validity exception,
        // so we short‑circuit here.
        if (! $this->isInventoryContainerFile($filePath)) {
            return null;
        }

        try {
            return $this->load($filePath);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function getByClassName(string $className): ?InventoryContainer
    {
        if (isset($this->inventoryContainerPaths[$className])) {
            return $this->load($this->inventoryContainerPaths[$className]);
        }

        // Fallback: some cargo grids are missing type metadata, so resolve via class->uuid map
        $uuid = self::$classToUuidMap[$className] ?? null;
        $path = $uuid ? (self::$uuidToPathMap[$uuid] ?? null) : null;

        if ($path && $this->isInventoryContainerFile($path)) {
            try {
                return $this->load($path);
            } catch (RuntimeException) {
                return null;
            }
        }

        // Last resort: look up directly in the class-to-path map for InventoryContainer entries
        try {
            $allPaths = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);
            $path = $allPaths['InventoryContainer'][$className] ?? null;
            if ($path && $this->isInventoryContainerFile($path)) {
                return $this->load($path);
            }
        } catch (RuntimeException|JsonException) {
            return null;
        }

        return null;
    }

    protected function load(string $filePath): InventoryContainer
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $container = new InventoryContainer;
        $container->load($filePath);
        $container->checkValidity();

        return $container;
    }

    private function isInventoryContainerFile(string $filePath): bool
    {
        if (! is_readable($filePath)) {
            return false;
        }

        $ref = fopen($filePath, 'rb');
        if ($ref === false) {
            return false;
        }

        $firstLine = fgets($ref) ?: '';
        fclose($ref);

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', ltrim($firstLine));

        return str_starts_with($firstLine, '<InventoryContainer');
    }

    /**
     * Return all inventory containers whose class name starts with the given prefix.
     *
     * Useful for cargo grid variants (e.g. ORIG_890Jump_CargoGrid_Rear).
     *
     * @return InventoryContainer[]
     */
    public function findByClassPrefix(string $prefix): array
    {
        $results = [];
        $seen = [];

        foreach (self::$classToUuidMap as $className => $uuid) {
            // Some class names are numeric-only; ensure we treat them as strings for prefix checks
            $className = (string) $className;

            if (! str_starts_with($className, $prefix)) {
                continue;
            }

            if (str_ends_with(strtolower($className), 'template')) {
                continue;
            }

            $path = self::$uuidToPathMap[$uuid] ?? null;
            if (! $path || ! $this->isInventoryContainerFile($path)) {
                continue;
            }

            try {
                $container = $this->load($path);
            } catch (RuntimeException) {
                $container = null;
            }

            if ($container && ! isset($seen[$container->getUuid()])) {
                $seen[$container->getUuid()] = true;
                $results[] = $container;
            }
        }

        return $results;
    }
}
