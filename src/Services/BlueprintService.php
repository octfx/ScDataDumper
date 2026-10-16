<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\BlueprintPoolRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingBlueprintRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGlobalParams;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @extends DocumentPathService<CraftingBlueprintRecord>
 */
final class BlueprintService extends DocumentPathService
{
    protected const CACHE_LIMIT = 200;

    /**
     * @var array<string, CraftingBlueprintRecord>
     */
    protected static array $documentCache = [];

    /**
     * @var array<string, true>
     */
    private array $defaultBlueprintWhitelist = [];

    /**
     * @var array<string, list<array{uuid: string, key: string}>>
     */
    private array $rewardPoolsByBlueprint = [];

    private ?float $dismantleEfficiency = null;

    private ?int $dismantleTimeSeconds = null;

    /**
     * @var array<string, true>
     */
    private array $dismantleBlacklistResources = [];

    /**
     * @var array<string, true>
     */
    private array $dismantleBlacklistEntityClasses = [];

    public function initialize(): void
    {
        parent::initialize();

        $this->loadDismantleBlueprint();
        $this->loadDefaultBlueprintWhitelist();
        $this->loadDismantleBlacklists();
        $this->loadRewardPools();
    }

    /**
     * @return array{efficiency: float, time_seconds: int}|null
     */
    public function getDismantleParams(): ?array
    {
        if ($this->dismantleEfficiency === null) {
            return null;
        }

        return [
            'efficiency' => $this->dismantleEfficiency,
            'time_seconds' => $this->dismantleTimeSeconds,
        ];
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

    protected function documentClass(): string
    {
        return CraftingBlueprintRecord::class;
    }

    protected function cacheMapKey(): string
    {
        return 'CraftingBlueprintRecord';
    }

    protected function acceptsPath(string $path, ?string $normalizedUuid): bool
    {
        return $normalizedUuid !== null && $this->isModernCreationBlueprintPath($path);
    }

    private function loadDismantleBlueprint(): void
    {
        $this->dismantleEfficiency = null;
        $this->dismantleTimeSeconds = null;

        $blueprintPaths = self::$classToPathMap['CraftingBlueprintRecord'] ?? [];
        $dismantlePath = $blueprintPaths['GlobalGenericDismantle'] ?? null;

        if ($dismantlePath === null || ! file_exists($dismantlePath)) {
            return;
        }

        $document = $this->loadDocument($dismantlePath, CraftingBlueprintRecord::class);

        $efficiency = $document->get(
            'blueprint/GenericCraftingBlueprint/processSpecificData/GenericCraftingProcess_Dismantle@efficiency'
        );
        $this->dismantleEfficiency = is_numeric($efficiency) ? (float) $efficiency : null;

        if ($this->dismantleEfficiency === null) {
            return;
        }

        $timeElement = $document->get(
            'blueprint/GenericCraftingBlueprint/processSpecificData/GenericCraftingProcess_Dismantle/dismantleTime/TimeValue_Partitioned'
        );

        if ($timeElement === null) {
            return;
        }

        $days = (float) ($timeElement->get('@days') ?? 0);
        $hours = (float) ($timeElement->get('@hours') ?? 0);
        $minutes = (float) ($timeElement->get('@minutes') ?? 0);
        $seconds = (float) ($timeElement->get('@seconds') ?? 0);

        $this->dismantleTimeSeconds = (int) (($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds);
    }

    private function loadDefaultBlueprintWhitelist(): void
    {
        $this->defaultBlueprintWhitelist = [];

        $document = $this->loadCraftingGlobalParams();

        if ($document === null) {
            return;
        }

        foreach ($document->getDefaultBlueprintReferences() as $uuid) {
            $normalizedUuid = $this->normalizeUuid($uuid);

            if ($normalizedUuid !== null) {
                $this->defaultBlueprintWhitelist[$normalizedUuid] = true;
            }
        }
    }

    private function loadDismantleBlacklists(): void
    {
        $this->dismantleBlacklistResources = [];
        $this->dismantleBlacklistEntityClasses = [];

        $document = $this->loadCraftingGlobalParams();

        if ($document === null) {
            return;
        }

        foreach ($document->getDismantleBlacklistResourceUuids() as $uuid) {
            $this->dismantleBlacklistResources[$uuid] = true;
        }

        foreach ($document->getDismantleBlacklistEntityClassUuids() as $uuid) {
            $this->dismantleBlacklistEntityClasses[$uuid] = true;
        }
    }

    public function isDismantleBlacklistedResource(string $uuid): bool
    {
        return isset($this->dismantleBlacklistResources[$uuid]);
    }

    public function isDismantleBlacklistedEntityClass(string $uuid): bool
    {
        return isset($this->dismantleBlacklistEntityClasses[$uuid]);
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

    private function loadCraftingGlobalParams(): ?CraftingGlobalParams
    {
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
            return null;
        }

        $document = new CraftingGlobalParams;
        $document->load($path);
        $document->checkValidity();

        return $document;
    }
}
