<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use RuntimeException;

final class ContractGeneratorService extends BaseService
{
    /**
     * @var array<string, string>
     */
    private array $pathsByClass = [];

    /**
     * @var array<string, string>
     */
    private array $pathsByUuid = [];

    /**
     * @var array<string, ContractGeneratorRecord>
     */
    protected static array $documentCache = [];

    private const CACHE_LIMIT = 100;

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->pathsByUuid);
    }

    public function initialize(): void
    {
        $this->loadPaths();
    }

    public function iterator(): Generator
    {
        foreach ($this->pathsByClass as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $uuid): ?ContractGeneratorRecord
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null || ! isset($this->pathsByUuid[$normalizedUuid])) {
            return null;
        }

        return $this->load($this->pathsByUuid[$normalizedUuid]);
    }

    public function load(string $filePath): ContractGeneratorRecord
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $cacheKey = sprintf(
            '%d:%s',
            $this->isReferenceHydrationEnabled() ? 1 : 0,
            $filePath
        );

        $document = self::cacheGet(self::$documentCache, $cacheKey);
        if ($document instanceof ContractGeneratorRecord) {
            return $document;
        }

        $document = $this->loadDocument($filePath, ContractGeneratorRecord::class);
        $document->checkValidity();
        self::cachePut(self::$documentCache, $cacheKey, $document, self::CACHE_LIMIT);

        return $document;
    }

    private function loadPaths(): void
    {
        $this->pathsByClass = [];
        $this->pathsByUuid = [];

        $generatorPaths = self::$classToPathMap['ContractGenerator'] ?? [];

        foreach ($generatorPaths as $className => $path) {
            if (! is_string($className) || ! is_string($path)) {
                continue;
            }

            $uuid = self::$classToUuidMap[$className] ?? null;
            $normalizedUuid = $this->normalizeUuid($uuid);

            $this->pathsByClass[$className] = $path;

            if ($normalizedUuid !== null) {
                $this->pathsByUuid[$normalizedUuid] = $path;
            }
        }

        ksort($this->pathsByClass);
    }

    private function normalizeUuid(?string $uuid): ?string
    {
        if (! is_string($uuid)) {
            return null;
        }

        $normalizedUuid = strtolower(trim($uuid));

        return $normalizedUuid === '' ? null : $normalizedUuid;
    }
}
