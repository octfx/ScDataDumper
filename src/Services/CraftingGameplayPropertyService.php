<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use DOMDocument;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\CraftingGameplayPropertyDef;
use RuntimeException;

final class CraftingGameplayPropertyService extends BaseService
{
    /**
     * @var array<string, string>
     */
    private array $gameplayPropertyXmlByUuid = [];

    /**
     * @var array<string, CraftingGameplayPropertyDef>
     */
    private array $instances = [];

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $cachePath = $this->makeCachePath();

        if (! file_exists($cachePath)) {
            throw new RuntimeException(sprintf(
                'Missing crafting gameplay property cache at %s. Run generate:cache with blueprint support first.',
                $cachePath
            ));
        }

        $this->loadCache($cachePath);
    }

    public function getByReference(?string $uuid): ?CraftingGameplayPropertyDef
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null) {
            return null;
        }

        if (isset($this->instances[$normalizedUuid])) {
            return $this->instances[$normalizedUuid];
        }

        if (! isset($this->gameplayPropertyXmlByUuid[$normalizedUuid])) {
            return null;
        }

        $property = $this->hydrateProperty($this->gameplayPropertyXmlByUuid[$normalizedUuid]);

        if ($property === null) {
            return null;
        }

        $this->instances[$normalizedUuid] = $property;

        return $property;
    }

    private function hydrateProperty(string $xml): ?CraftingGameplayPropertyDef
    {
        $document = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        if (! $loaded || $document->documentElement === null) {
            return null;
        }

        $property = CraftingGameplayPropertyDef::fromNode($document->documentElement);
        $property?->checkValidity();

        return $property;
    }

    /**
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->gameplayPropertyXmlByUuid = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
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

    private function normalizeUuid(?string $uuid): ?string
    {
        if (! is_string($uuid)) {
            return null;
        }

        $normalizedUuid = strtolower(trim($uuid));

        return $normalizedUuid === '' ? null : $normalizedUuid;
    }
}
