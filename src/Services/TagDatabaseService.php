<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use XMLReader;

final class TagDatabaseService extends BaseService
{
    /**
     * @var array<string, array{name: string, legacyGUID: string, children: list<string>}>
     */
    private array $tagByUuid = [];

    /**
     * @var array<string, string>|null Lazy-built child UUID → parent UUID map
     */
    private ?array $parentMap = null;

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

        return $this->tagByUuid[$key]['name'] ?? null;
    }

    /**
     * @return array{name: string, legacyGUID: string}|null
     */
    public function getTag(string $uuid): ?array
    {
        $key = strtolower($uuid);

        return $this->tagByUuid[$key] ?? null;
    }

    /**
     * @param  list<string>  $uuids
     * @return list<array{uuid: string, name: ?string}>
     */
    public function resolveUuidsToNameObjects(array $uuids): array
    {
        return array_map(fn (string $uuid): array => [
            'uuid' => $uuid,
            'name' => $this->getTagName($uuid),
        ], $uuids);
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
     * @return array<string, array{name: string, legacyGUID: string}>
     */
    public function getTagMap(): array
    {
        return $this->tagByUuid;
    }

    /**
     * @return array<string, string>
     */
    public function getTagNameMap(): array
    {
        return array_map(static function ($tag) {
            return $tag['name'];
        }, $this->tagByUuid);
    }

    /**
     * @return list<string> All ancestor UUIDs (including self), from leaf toward root.
     */
    public function getAncestorUuids(string $uuid): array
    {
        $this->ensureParentMap();

        $ancestors = [];
        $current = strtolower($uuid);

        while ($current !== '') {
            $ancestors[] = $current;
            $current = $this->parentMap[$current] ?? '';
        }

        return $ancestors;
    }

    /**
     * @return list<string> All descendant UUIDs (including self).
     */
    public function getAllDescendantUuids(string $uuid): array
    {
        $key = strtolower($uuid);
        $descendants = [$key];

        $children = $this->tagByUuid[$key]['children'] ?? [];
        foreach ($children as $childUuid) {
            $descendants = array_merge($descendants, $this->getAllDescendantUuids($childUuid));
        }

        return $descendants;
    }

    /**
     * @param  list<string>  $uuids
     * @return list<string>
     */
    public function expandTagsWithDescendants(array $uuids): array
    {
        $expanded = [];
        foreach ($uuids as $uuid) {
            foreach ($this->getAllDescendantUuids($uuid) as $descendant) {
                $expanded[$descendant] = true;
            }
        }

        return array_keys($expanded);
    }

    /**
     * Expand a list of tag UUIDs to include all ancestor UUIDs.
     *
     * @param  list<string>  $uuids
     * @return list<string>
     */
    public function expandTagsWithAncestors(array $uuids): array
    {
        $expanded = [];
        foreach ($uuids as $uuid) {
            foreach ($this->getAncestorUuids($uuid) as $ancestor) {
                $expanded[$ancestor] = true;
            }
        }

        return array_keys($expanded);
    }

    /**
     * Build cache by parsing extracted Tag XML files referenced in classToPathMap.
     *
     * @return array<string, array{name: string, legacyGUID: string, children: list<string>}>
     *
     * @throws JsonException
     */
    private function loadTagDatabase(): array
    {
        $classToPathMap = json_decode(
            file_get_contents($this->classToPathMapPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $tagPaths = $classToPathMap['Tag'] ?? [];
        if (! is_array($tagPaths)) {
            return [];
        }

        $tags = [];

        foreach ($tagPaths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $tag = $this->readTagFile($path);
            if ($tag === null) {
                continue;
            }

            $tags[strtolower($tag['uuid'])] = [
                'name' => $tag['name'],
                'legacyGUID' => $tag['legacyGUID'],
                'children' => $tag['children'],
            ];
        }

        return $tags;
    }

    /**
     * @throws JsonException
     */
    private function buildCache(): void
    {
        $this->tagByUuid = $this->loadTagDatabase();
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
            json_encode($this->tagByUuid, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        fclose($ref);
    }

    /**
     * @throws JsonException
     */
    private function loadCache(string $path): void
    {
        $this->tagByUuid = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $needsRebuild = false;
        foreach ($this->tagByUuid as $tag) {
            if (! array_key_exists('children', $tag)) {
                $needsRebuild = true;
                break;
            }
        }

        if ($needsRebuild) {
            $this->buildCache();
        }
    }

    private function makeCachePath(): string
    {
        return sprintf(
            '%s%stagdatabase-%s.json',
            $this->scDataDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }

    /**
     * @return array{uuid: string, name: string, legacyGUID: string, children: list<string>}|null
     */
    private function readTagFile(string $path): ?array
    {
        $reader = XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);
        if ($reader === false) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                $uuid = $reader->getAttribute('__ref') ?? '';
                $name = $reader->getAttribute('tagName') ?? '';

                if ($uuid === '' || $name === '') {
                    return null;
                }

                $children = [];
                $innerXml = $reader->readInnerXml();
                if ($innerXml !== '' && str_contains($innerXml, 'children')) {
                    preg_match_all('/value="([^"]+)"/', $innerXml, $matches);
                    $children = array_map('strtolower', $matches[1] ?? []);
                }

                return [
                    'uuid' => $uuid,
                    'name' => $name,
                    'legacyGUID' => $reader->getAttribute('legacyGUID') ?? '',
                    'children' => $children,
                ];
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    private function ensureParentMap(): void
    {
        if ($this->parentMap !== null) {
            return;
        }

        $this->parentMap = [];
        foreach ($this->tagByUuid as $uuid => $tag) {
            foreach ($tag['children'] ?? [] as $childUuid) {
                $this->parentMap[$childUuid] = $uuid;
            }
        }
    }
}
