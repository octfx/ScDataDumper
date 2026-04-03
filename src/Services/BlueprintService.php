<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\BlueprintPoolRecord;
use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprintRecord;
use Octfx\ScDataDumper\DocumentTypes\CraftingGlobalParams;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class BlueprintService extends BaseService
{
    /**
     * @var array<string, string>
     */
    private array $blueprintPathsByClass = [];

    /**
     * @var array<string, string>
     */
    private array $blueprintPathsByUuid = [];

    /**
     * @var array<string, true>
     */
    private array $defaultBlueprintWhitelist = [];

    /**
     * @var array<string, list<array{uuid: string, key: string}>>
     */
    private array $rewardPoolsByBlueprint = [];

    /**
     * LRU document cache keyed by file path.
     *
     * @var array<string, CraftingBlueprintRecord>
     */
    protected static array $documentCache = [];

    private const CACHE_LIMIT = 200;

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->blueprintPathsByUuid);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $this->loadBlueprintPaths();
        $this->loadDefaultBlueprintWhitelist();
        $this->loadRewardPools();
    }

    public function iterator(): Generator
    {
        foreach ($this->blueprintPathsByClass as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference(?string $uuid): ?CraftingBlueprintRecord
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null || ! isset($this->blueprintPathsByUuid[$normalizedUuid])) {
            return null;
        }

        return $this->load($this->blueprintPathsByUuid[$normalizedUuid]);
    }

    public function load(string $filePath): CraftingBlueprintRecord
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $document = self::cacheGet(self::$documentCache, $filePath);
        if ($document instanceof CraftingBlueprintRecord) {
            return $document;
        }

        $document = new CraftingBlueprintRecord;
        $document->load($filePath);
        $document->checkValidity();
        self::cachePut(self::$documentCache, $filePath, $document, self::CACHE_LIMIT);

        return $document;
    }

    public function isDefaultBlueprint(string $uuid): bool
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        return $normalizedUuid !== null && isset($this->defaultBlueprintWhitelist[$normalizedUuid]);
    }

    /**
     * @return list<array{uuid: string, key: string}>
     */
    public function getRewardPoolsForBlueprint(string $uuid): array
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null) {
            return [];
        }

        return $this->rewardPoolsByBlueprint[$normalizedUuid] ?? [];
    }

    /**
     * @throws JsonException
     */
    private function loadBlueprintPaths(): void
    {
        $classToPathMap = json_decode(file_get_contents($this->classToPathMapPath), true, 512, JSON_THROW_ON_ERROR);
        $blueprintPaths = $classToPathMap['CraftingBlueprintRecord'] ?? [];

        $this->blueprintPathsByClass = [];
        $this->blueprintPathsByUuid = [];

        foreach ($blueprintPaths as $className => $path) {
            if (! is_string($className) || ! is_string($path) || ! $this->isModernCreationBlueprintPath($path)) {
                continue;
            }

            $uuid = self::$classToUuidMap[$className] ?? null;
            $normalizedUuid = $this->normalizeUuid($uuid);

            if ($normalizedUuid === null) {
                continue;
            }

            $this->blueprintPathsByClass[$className] = $path;
            $this->blueprintPathsByUuid[$normalizedUuid] = $path;
        }

        ksort($this->blueprintPathsByClass);
    }

    private function loadDefaultBlueprintWhitelist(): void
    {
        $this->defaultBlueprintWhitelist = [];

        $path = sprintf(
            '%s%sData%sLibs%sFoundry%sRecords%scrafting%sglobalparams%scraftingglobalparams.xml',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        if (! file_exists($path)) {
            return;
        }

        $document = new CraftingGlobalParams;
        $document->load($path);
        $document->checkValidity();

        foreach ($document->getDefaultBlueprintReferences() as $uuid) {
            $normalizedUuid = $this->normalizeUuid($uuid);

            if ($normalizedUuid !== null) {
                $this->defaultBlueprintWhitelist[$normalizedUuid] = true;
            }
        }
    }

    private function loadRewardPools(): void
    {
        $this->rewardPoolsByBlueprint = [];

        $basePath = sprintf(
            '%s%sData%sLibs%sFoundry%sRecords%scrafting%sblueprintrewards',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        if (! is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                continue;
            }

            $path = $file->getRealPath();

            if (! is_string($path)) {
                continue;
            }

            $pool = new BlueprintPoolRecord;
            $pool->load($path);
            $pool->checkValidity();

            $poolData = [
                'uuid' => $pool->getUuid(),
                'key' => $pool->getClassName(),
            ];

            foreach ($pool->getBlueprintRewardReferences() as $blueprintUuid) {
                $normalizedUuid = $this->normalizeUuid($blueprintUuid);

                if ($normalizedUuid === null) {
                    continue;
                }

                $this->rewardPoolsByBlueprint[$normalizedUuid] ??= [];

                if (! in_array($poolData, $this->rewardPoolsByBlueprint[$normalizedUuid], true)) {
                    $this->rewardPoolsByBlueprint[$normalizedUuid][] = $poolData;
                }
            }
        }

        foreach ($this->rewardPoolsByBlueprint as &$rewardPools) {
            usort(
                $rewardPools,
                static fn (array $left, array $right): int => [$left['key'], $left['uuid']] <=> [$right['key'], $right['uuid']]
            );
        }
        unset($rewardPools);
    }

    private function isModernCreationBlueprintPath(string $path): bool
    {
        $normalizedPath = strtolower(str_replace('\\', '/', $path));

        return str_contains($normalizedPath, '/crafting/blueprints/crafting/');
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
