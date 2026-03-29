<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RuntimeException;

final readonly class CraftingGameplayPropertyCacheBuilder
{
    private string $uuidToPathMapPath;

    public function __construct(private string $scDataDir)
    {
        $this->uuidToPathMapPath = sprintf(
            '%s%suuidToPathMap-%s.json',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }

    /**
     * @throws JsonException
     */
    public function build(): int
    {
        $properties = [];
        $uuidToPathMap = json_decode(file_get_contents($this->uuidToPathMapPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($uuidToPathMap as $uuid => $path) {
            if (! is_string($uuid) || ! is_string($path) || ! $this->isPropertyFile($path)) {
                continue;
            }

            $xml = file_get_contents($path);
            if (! is_string($xml) || trim($xml) === '') {
                continue;
            }

            $properties[strtolower($uuid)] = trim($xml);
        }

        $this->writeCache($properties);

        return count($properties);
    }

    private function isPropertyFile(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $ref = fopen($path, 'rb');
        if ($ref === false) {
            return false;
        }

        $firstLine = fgets($ref) ?: '';
        fclose($ref);

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', ltrim($firstLine));

        return str_starts_with($firstLine, '<CraftingGameplayPropertyDef.');
    }

    /**
     * @param  array<string, string>  $properties
     *
     * @throws JsonException
     */
    private function writeCache(array $properties): void
    {
        $ref = fopen($this->makeCachePath(), 'wb');
        if ($ref === false) {
            throw new RuntimeException('Failed to open crafting gameplay property cache for writing');
        }

        fwrite($ref, json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fclose($ref);
    }

    private function makeCachePath(): string
    {
        return sprintf(
            '%s%scrafting-gameplay-property-cache-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
