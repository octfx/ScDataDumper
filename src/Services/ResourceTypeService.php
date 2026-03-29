<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use DOMDocument;
use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use RuntimeException;

final class ResourceTypeService extends BaseService
{
    /**
     * @var array<string, string>
     */
    private array $resourceTypeXmlByUuid = [];

    /**
     * @var array<string, ResourceType>
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
                'Missing resource type cache at %s. Run generate:cache with blueprint support first.',
                $cachePath
            ));
        }

        $this->loadCache($cachePath);
    }

    public function count(): int
    {
        return count($this->resourceTypeXmlByUuid);
    }

    public function iterator(): Generator
    {
        foreach (array_keys($this->resourceTypeXmlByUuid) as $uuid) {
            $resourceType = $this->getByReference($uuid);

            if ($resourceType !== null) {
                yield $resourceType;
            }
        }
    }

    public function getByReference(?string $uuid): ?ResourceType
    {
        $normalizedUuid = $this->normalizeUuid($uuid);

        if ($normalizedUuid === null) {
            return null;
        }

        if (isset($this->instances[$normalizedUuid])) {
            return $this->instances[$normalizedUuid];
        }

        if (! isset($this->resourceTypeXmlByUuid[$normalizedUuid])) {
            return null;
        }

        $resourceType = $this->hydrateResourceType($this->resourceTypeXmlByUuid[$normalizedUuid]);

        if ($resourceType === null) {
            return null;
        }

        $this->instances[$normalizedUuid] = $resourceType;

        return $resourceType;
    }

    private function hydrateResourceType(string $xml): ?ResourceType
    {
        $document = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        if (! $loaded || $document->documentElement === null) {
            return null;
        }

        $resourceType = ResourceType::fromNode($document->documentElement);
        $resourceType?->checkValidity();

        return $resourceType;
    }

    /**
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->resourceTypeXmlByUuid = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
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
