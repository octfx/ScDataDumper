<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;

final class ManufacturerService extends BaseService
{
    private array $manufacturerPaths;

    /**
     * LRU document cache keyed by file path.
     *
     * @var array<string, SCItemManufacturer>
     */
    private static array $documentCache = [];

    private const int CACHE_LIMIT = 100;

    /**
     * Canonical manufacturer index keyed by raw localization name key.
     *
     * Duplicate name keys prefer the primary `scitemmanufacturer.*.xml` record.
     *
     * @var array<string, array{code: string, uuid: string}>
     */
    private array $canonicalIndex = [];

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->manufacturerPaths);
    }

    public function initialize(): void
    {
        /** @var array<string, list<array{code: string, uuid: string, path: string, is_primary: bool}>> $candidates */
        $candidates = [];
        $paths = [];

        foreach (self::$uuidToPathMap as $path) {
            if (str_contains($path, 'scitemmanufacturer') !== true) {
                continue;
            }

            $manufacturer = new SCItemManufacturer;
            $manufacturer->setReferenceHydrationEnabled($this->referenceHydrationEnabled);
            $manufacturer->load($path);

            if (! str_starts_with($manufacturer->documentElement->nodeName, 'SCItemManufacturer.')) {
                continue;
            }

            $manufacturer->checkValidity();
            $paths[] = $path;

            $code = $manufacturer->getCode();
            $uuid = $manufacturer->getUuid();
            $nameKey = $manufacturer->get('Localization@Name', raw: true);

            if ($code === null || $code === '' || $uuid === '') {
                continue;
            }

            if (! is_string($nameKey) || ! str_starts_with($nameKey, '@manufacturer_')) {
                continue;
            }

            $lookupKey = $nameKey;
            if (str_starts_with($lookupKey, '@manufacturer_Desc')) {
                $lookupKey = '@manufacturer_Name'.substr($lookupKey, strlen('@manufacturer_Desc'));
            }

            $basename = basename($path);
            $isPrimary = str_starts_with($basename, 'scitemmanufacturer.');

            $candidates[$lookupKey][] = [
                'code' => $code,
                'uuid' => $uuid,
                'path' => $path,
                'is_primary' => $isPrimary,
            ];
        }

        $this->manufacturerPaths = $paths;

        foreach ($candidates as $key => $entries) {
            usort($entries, static function (array $a, array $b): int {
                if ($a['is_primary'] !== $b['is_primary']) {
                    return $a['is_primary'] ? -1 : 1;
                }

                return $a['path'] <=> $b['path'];
            });

            $winner = $entries[0];
            $this->canonicalIndex[$key] = [
                'code' => $winner['code'],
                'uuid' => $winner['uuid'],
            ];
        }
    }

    /**
     * Look up a canonical manufacturer by raw localization name key.
     *
     * @param  string  $nameKey  Raw localization key, e.g. `@manufacturer_NameRSI`
     * @return array{code: string, uuid: string}|null Canonical manufacturer data, or null if not found
     */
    public function getCanonicalByNameKey(string $nameKey): ?array
    {
        if (str_starts_with($nameKey, '@manufacturer_Desc')) {
            $nameKey = '@manufacturer_Name'.substr($nameKey, strlen('@manufacturer_Desc'));
        }

        return $this->canonicalIndex[$nameKey] ?? null;
    }

    public function iterator(): Generator
    {
        foreach ($this->manufacturerPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?SCItemManufacturer
    {
        $path = $this->resolvePathByReference(is_string($uuid) ? $uuid : null);

        if ($path === null || ! in_array($path, $this->manufacturerPaths, true)) {
            return null;
        }

        return $this->load($path);
    }

    public function load(string $filePath): ?SCItemManufacturer
    {
        $cacheKey = $this->referenceHydrationEnabled ? '1:'.$filePath : '0:'.$filePath;

        $cached = self::cacheGet(self::$documentCache, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $doc = $this->loadDocument($filePath, SCItemManufacturer::class);
        self::cachePut(self::$documentCache, $cacheKey, $doc, self::CACHE_LIMIT);

        return $doc;
    }
}
