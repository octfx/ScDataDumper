<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\CraftingGameplayPropertyDef;
use RuntimeException;

final class CraftingGameplayPropertyService extends BaseService
{
    private array $gameplayPropertyPaths = [];

    /**
     * Document cache keyed by file path
     *
     * @var array<string, CraftingGameplayPropertyDef>
     */
    protected static array $documentCache = [];

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $gameplayPropertyPaths = json_decode(
            file_get_contents($this->classToPathMapPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        )['CraftingGameplayPropertyDef'] ?? null;

        if (! is_array($gameplayPropertyPaths)) {
            throw new RuntimeException(sprintf(
                'Missing crafting gameplay property paths in %s. Run generate:cache first.',
                $this->classToPathMapPath
            ));
        }

        $this->gameplayPropertyPaths = $gameplayPropertyPaths;
    }

    public function iterator(): Generator
    {
        foreach ($this->gameplayPropertyPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $reference): ?CraftingGameplayPropertyDef
    {
        if ($reference === null || ! isset(self::$uuidToPathMap[$reference])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$reference]);
    }

    public function load(string $filePath, string $class = CraftingGameplayPropertyDef::class): CraftingGameplayPropertyDef
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        if (isset(self::$documentCache[$filePath])) {
            return self::$documentCache[$filePath];
        }

        $property = new $class;
        $property->load($filePath);
        if ($class === CraftingGameplayPropertyDef::class) {
            $property->checkValidity();
        }

        self::$documentCache[$filePath] = $property;

        return $property;
    }
}
