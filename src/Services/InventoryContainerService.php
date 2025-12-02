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
        return $this->load($this->inventoryContainerPaths[$className]);
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
}
