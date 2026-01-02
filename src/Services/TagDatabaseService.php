<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;
use XMLReader;

final class TagDatabaseService extends BaseService
{
    private array $tagNameByUuid = [];

    private readonly string $gameDataPath;

    /**
     * @throws JsonException
     */
    public function __construct(string $scDataDir)
    {
        parent::__construct($scDataDir);

        $this->gameDataPath = sprintf(
            '%s%sData%sGame2.xml',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        if (! file_exists($this->gameDataPath)) {
            throw new RuntimeException(sprintf('Missing Game2.xml at %s', $this->gameDataPath));
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

    public function getTagName(string $uuid): ?string
    {
        $key = strtolower($uuid);

        return $this->tagNameByUuid[$key] ?? null;
    }

    /**
     * @param  array<int, string>  $uuids
     * @return array<int, string>
     */
    public function getTagNames(array $uuids): array
    {
        $names = [];

        foreach ($uuids as $uuid) {
            $name = $this->getTagName($uuid);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array<string, string> Map of uuid => tagName
     */
    public function getTagMap(): array
    {
        return $this->tagNameByUuid;
    }

    /**
     * Build cache by parsing Game2.xml
     *
     * @return array<string, string>
     */
    private function loadTagDatabase(): array
    {
        $reader = new XMLReader;

        if (! $reader->open($this->gameDataPath)) {
            throw new RuntimeException(sprintf('Failed to open %s', $this->gameDataPath));
        }

        $tags = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if (! str_starts_with($reader->name, 'Tag.')) {
                continue;
            }

            $tagName = $reader->getAttribute('tagName');
            $uuid = $reader->getAttribute('__ref');

            if ($tagName === null || $uuid === null) {
                continue;
            }

            $tags[strtolower($uuid)] = $tagName;
        }

        $reader->close();

        return $tags;
    }

    /**
     * @throws JsonException
     */
    private function buildCache(): void
    {
        if (! file_exists($this->gameDataPath)) {
            $this->tagNameByUuid = [];

            return;
        }

        $this->tagNameByUuid = $this->loadTagDatabase();
        $this->writeCache();
    }

    /**
     * @throws JsonException
     */
    private function writeCache(): void
    {
        $cachePath = $this->makeCachePath();

        $ref = fopen($cachePath, 'wb');
        fwrite(
            $ref,
            json_encode($this->tagNameByUuid, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        fclose($ref);
    }

    /**
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->tagNameByUuid = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function makeCachePath(): string
    {
        return sprintf(
            '%s%stagdatabase-cache-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
