<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprint;
use RuntimeException;

final class BlueprintService extends BaseService
{
    private array $blueprintPaths;

    public function count(): int
    {
        return count($this->blueprintPaths);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $blueprints = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR)['CraftingBlueprintRecord'] ?? [];

        // Filter out test blueprints for production use
        // $blueprints = array_filter($blueprints, static fn ($path) => ! str_contains($path, 'test'.DIRECTORY_SEPARATOR));

        $this->blueprintPaths = $blueprints;
    }

    public function iterator(): Generator
    {
        foreach ($this->blueprintPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $reference): ?CraftingBlueprint
    {
        if ($reference === null || ! isset(self::$uuidToPathMap[$reference])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$reference]);
    }

    public function getByClassName(?string $className): ?CraftingBlueprint
    {
        if ($className === null || ! isset($this->blueprintPaths[$className])) {
            return null;
        }

        return $this->load($this->blueprintPaths[$className]);
    }

    protected function load(string $filePath): CraftingBlueprint
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $blueprint = new CraftingBlueprint;
        $blueprint->load($filePath);

        return $blueprint;
    }
}
