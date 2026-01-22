<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;
use XMLReader;

final class MaterialStatService extends BaseService
{
    private array $statDataByUuid = [];

    private readonly string $materialStatDatabasePath;

    /**
     * @throws RuntimeException
     */
    public function __construct(string $scDataDir)
    {
        parent::__construct($scDataDir);

        $this->materialStatDatabasePath = sprintf(
            '%s%sData%sLibs%sFoundry%sRecords%scrafting%sstattypes%smaterialstatdatabase.xml',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        if (! file_exists($this->materialStatDatabasePath)) {
            throw new RuntimeException(
                sprintf('Missing materialstatdatabase.xml at %s', $this->materialStatDatabasePath)
            );
        }
    }

    public function initialize(): void
    {
        $cachePath = $this->makeCachePath();

        if (! file_exists($cachePath)) {
            $this->buildCache();
        } else {
            $this->loadCache($cachePath);
        }
    }

    /**
     * Get material stat data by UUID
     *
     * @return array<string, mixed>|null
     */
    public function getStatType(string $uuid): ?array
    {
        $key = strtolower($uuid);

        return $this->statDataByUuid[$key] ?? null;
    }

    /**
     * Build cache by parsing materialstatdatabase.xml
     *
     * @throws JsonException
     */
    private function buildCache(): void
    {
        if (! file_exists($this->materialStatDatabasePath)) {
            $this->statDataByUuid = [];

            return;
        }

        $this->statDataByUuid = $this->loadMaterialStatDatabase();
        $this->writeCache();
    }

    /**
     * Parse materialstatdatabase.xml to extract stat type UUIDs
     *
     * @return array<string, array<string, string>>
     */
    private function loadMaterialStatDatabase(): array
    {
        $reader = new XMLReader;

        if (! $reader->open($this->materialStatDatabasePath)) {
            throw new RuntimeException(sprintf('Failed to open %s', $this->materialStatDatabasePath));
        }

        $statTypes = [];
        $inStatTypes = false;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'statTypes') {
                $inStatTypes = true;

                continue;
            }

            if ($inStatTypes && $reader->name === 'Reference') {
                $uuid = $reader->getAttribute('value');

                if ($uuid !== null) {
                    $statTypes[strtolower($uuid)] = [
                        'stat_type_uuid' => $uuid,
                    ];
                }

                continue;
            }

            if ($inStatTypes && $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'statTypes') {
                $inStatTypes = false;
            }
        }

        $reader->close();

        return $statTypes;
    }

    /**
     * Write cache to disk
     *
     * @throws JsonException
     */
    private function writeCache(): void
    {
        $cachePath = $this->makeCachePath();

        $ref = fopen($cachePath, 'wb');
        fwrite(
            $ref,
            json_encode($this->statDataByUuid, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        fclose($ref);
    }

    /**
     * Load cache from disk
     *
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->statDataByUuid = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function makeCachePath(): string
    {
        return sprintf(
            '%s%smaterialstat-cache-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
