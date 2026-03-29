<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;
use XMLReader;

final readonly class ResourceTypeCacheBuilder
{
    private string $gameDataPath;

    public function __construct(private string $scDataDir)
    {
        $this->gameDataPath = sprintf(
            '%s%sData%sGame2.xml',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );
    }

    /**
     * @throws JsonException
     */
    public function build(): int
    {
        $resourceTypes = $this->parseGameData();
        $this->writeCache($resourceTypes);

        return count($resourceTypes);
    }

    /**
     * @return array<string, string>
     */
    private function parseGameData(): array
    {
        if (! file_exists($this->gameDataPath)) {
            return [];
        }

        $reader = new XMLReader;

        if (! $reader->open($this->gameDataPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException(sprintf('Failed to open %s', $this->gameDataPath));
        }

        $resourceTypes = [];

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || ! str_starts_with($reader->name, 'ResourceType.')) {
                    continue;
                }

                $uuid = $this->normalizeUuid($reader->getAttribute('__ref'));
                $xml = $reader->readOuterXml();

                if ($uuid === null || $xml === '') {
                    continue;
                }

                $resourceTypes[$uuid] = $xml;
            }
        } finally {
            $reader->close();
        }

        return $resourceTypes;
    }

    /**
     * @param  array<string, string>  $resourceTypes
     *
     * @throws JsonException
     */
    private function writeCache(array $resourceTypes): void
    {
        $ref = fopen($this->makeCachePath(), 'wb');
        if ($ref === false) {
            throw new RuntimeException('Failed to open resource type cache for writing');
        }

        fwrite($ref, json_encode($resourceTypes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fclose($ref);
    }

    private function makeCachePath(): string
    {
        return sprintf(
            '%s%sresource-type-cache-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
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
