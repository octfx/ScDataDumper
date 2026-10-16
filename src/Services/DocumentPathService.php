<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use RuntimeException;

/**
 * Base for services that index one document type via the classToPathMap
 * and cache loaded documents in a per-class LRU cache.
 *
 * @template TDocument of RootDocument
 */
abstract class DocumentPathService extends BaseService
{
    protected const CACHE_LIMIT = 100;

    /**
     * Redeclared by subclasses so every service keeps its own cache.
     *
     * @var array<string, TDocument>
     */
    protected static array $documentCache = [];

    /** @var array<string, string> */
    private array $pathsByClass = [];

    /** @var array<string, string> */
    private array $pathsByUuid = [];

    public static function resetDocumentCache(): void
    {
        static::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->pathsByUuid);
    }

    public function initialize(): void
    {
        $this->loadPaths();
    }

    /**
     * @return Generator<string, TDocument, mixed, void>
     */
    public function iterator(): Generator
    {
        foreach ($this->pathsByClass as $className => $path) {
            yield $className => $this->loadDocumentAtPath($path);
        }
    }

    /**
     * @return TDocument|null
     */
    public function getByReference(?string $uuid): ?RootDocument
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null || ! isset($this->pathsByUuid[$normalizedUuid])) {
            return null;
        }

        return $this->loadDocumentAtPath($this->pathsByUuid[$normalizedUuid]);
    }

    /**
     * @return TDocument
     */
    public function load(string $filePath): RootDocument
    {
        return $this->loadDocumentAtPath($filePath);
    }

    protected function countPathsByClass(): int
    {
        return count($this->pathsByClass);
    }

    /**
     * @return class-string<TDocument>
     */
    abstract protected function documentClass(): string;

    abstract protected function cacheMapKey(): string;

    /**
     * Hook for subclasses to filter indexed paths.
     */
    protected function acceptsPath(string $path, ?string $normalizedUuid): bool
    {
        return true;
    }

    protected function loadPaths(): void
    {
        $this->pathsByClass = [];
        $this->pathsByUuid = [];

        foreach (self::$classToPathMap[$this->cacheMapKey()] ?? [] as $className => $path) {
            if (! is_string($className) || ! is_string($path)) {
                continue;
            }

            $normalizedUuid = $this->normalizeUuid(self::$classToUuidMap[$className] ?? null);

            if (! $this->acceptsPath($path, $normalizedUuid)) {
                continue;
            }

            $this->pathsByClass[$className] = $path;

            if ($normalizedUuid !== null) {
                $this->pathsByUuid[$normalizedUuid] = $path;
            }
        }

        ksort($this->pathsByClass);
    }

    /**
     * @return TDocument
     */
    protected function loadDocumentAtPath(string $filePath): RootDocument
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $cacheKey = sprintf(
            '%d:%s',
            $this->isReferenceHydrationEnabled() ? 1 : 0,
            $filePath
        );

        $document = static::cacheGet(static::$documentCache, $cacheKey);

        $class = $this->documentClass();

        if ($document instanceof $class) {
            return $document;
        }

        $document = $this->loadDocument($filePath, $class);
        static::cachePut(static::$documentCache, $cacheKey, $document, static::CACHE_LIMIT);

        return $document;
    }

    protected function normalizeUuid(?string $uuid): ?string
    {
        if (! is_string($uuid)) {
            return null;
        }

        $normalizedUuid = strtolower(trim($uuid));

        return $normalizedUuid === '' ? null : $normalizedUuid;
    }
}
