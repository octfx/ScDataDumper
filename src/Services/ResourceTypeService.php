<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use RuntimeException;

final class ResourceTypeService extends BaseService
{
    private array $resourceTypePaths = [];

    /**
     * Document cache keyed by file path
     *
     * @var array<string, ResourceType>
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
        $resourceTypePaths = json_decode(
            file_get_contents($this->classToPathMapPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        )['ResourceType'] ?? null;

        if (! is_array($resourceTypePaths)) {
            throw new RuntimeException(sprintf(
                'Missing resource type paths in %s. Run generate:cache first.',
                $this->classToPathMapPath
            ));
        }

        $this->resourceTypePaths = $resourceTypePaths;
    }

    public function count(): int
    {
        return count($this->resourceTypePaths);
    }

    public function iterator(): Generator
    {
        foreach ($this->resourceTypePaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $reference): ?ResourceType
    {
        if ($reference === null || ! isset(self::$uuidToPathMap[$reference])) {
            return null;
        }

        return $this->load(self::$uuidToPathMap[$reference]);
    }

    public function load(string $filePath, string $class = ResourceType::class): ResourceType
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        if (isset(self::$documentCache[$filePath])) {
            return self::$documentCache[$filePath];
        }

        $resourceType = new $class;
        $resourceType->load($filePath);
        if ($class === ResourceType::class) {
            $resourceType->checkValidity();
        }

        self::$documentCache[$filePath] = $resourceType;

        return $resourceType;
    }
}
