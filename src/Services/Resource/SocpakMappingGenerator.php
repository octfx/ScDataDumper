<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JsonException;
use ZipArchive;

final class SocpakMappingGenerator
{
    private const array EXCLUDED_CHILD_SUFFIXES = [
        '_station_lghts.socpak',
        '_cloud_lghts.socpak',
        '_cloud_lights.socpak',
        '_entry.socpak',
    ];

    private const array MANUAL_MAPPING = [
        'stanton/stanton2c.socpak' => 'Stanton2c',
        'pyro/akirocluster.socpak' => 'Pyro_AkiroCluster',
        'nyx/glaciemRing/glaciem_gascloud_gen.socpak' => 'GlaciemRing',
        'nyx/keegerbelt/keeger_gascloud_gen.socpak' => 'KeegerBelt',
    ];

    private const array PARENT_OVERRIDES = [
        'GlaciemRing' => 'NyxStar',
        'KeegerBelt' => 'NyxStar',
    ];

    private const string NULL_UUID = '00000000-0000-0000-0000-000000000000';

    private const string OBJECTCONTAINERS_PATTERN = '#^Data/objectcontainers/#i';

    /** @var array<string, array<string, true>> */
    private array $dirFileCache = [];

    /** @var array<string, true>|null */
    private ?array $hppUuidSet = null;

    /** @var array<string, array{hppUuid: string|null, xml: string|null}> */
    private array $socpakCache = [];

    public function __construct(
        private readonly string $scDataPath,
    ) {}

    /**
     * @throws JsonException
     */
    public function generate(): void
    {
        $baseDir = implode(DIRECTORY_SEPARATOR, [
            $this->scDataPath,
            'Data',
            'ObjectContainers',
            'PU',
            'system',
        ]);

        if (! is_dir($baseDir)) {
            return;
        }

        $mappings = [];
        $parentMappings = [];
        $pinnedUuids = [];
        $uuidToClassMap = $this->loadUuidToClassMap();
        $this->loadHppUuidSet();

        $queue = [];
        $processed = [];
        $pinnedSocpaks = [];

        $this->seedFromLagrangePoints($baseDir, $queue, $processed, $pinnedSocpaks);
        $this->seedFromManualMapping($baseDir, $queue, $processed, $pinnedSocpaks);
        $this->seedFromSystemSocpaks($baseDir, $uuidToClassMap, $queue, $processed, $pinnedSocpaks, $parentMappings);

        $this->walkQueue($queue, $processed, $uuidToClassMap, $mappings, $pinnedUuids, $parentMappings);

        $parentMappings = array_merge($parentMappings, self::PARENT_OVERRIDES);
        $parentMappings = array_filter($parentMappings, fn (string $parent, string $child) => $parent !== $child, ARRAY_FILTER_USE_BOTH);

        foreach ($mappings as $uuid => $classNames) {
            $mappings[$uuid] = array_values(array_unique($classNames));
        }

        file_put_contents(
            $this->scDataPath.DIRECTORY_SEPARATOR.'socpak_mappings.json',
            json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        file_put_contents(
            $this->scDataPath.DIRECTORY_SEPARATOR.'socpak_parent_mappings.json',
            json_encode($parentMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        $this->generateCaveMappings($baseDir, $uuidToClassMap);
    }

    /**
     * @param  array<string, string>  $uuidToClassMap
     *
     * @throws JsonException
     */
    private function generateCaveMappings(string $baseDir, array $uuidToClassMap): void
    {
        $caveMappings = [];

        $systemDirs = glob($baseDir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
        if ($systemDirs === false) {
            return;
        }

        foreach ($systemDirs as $systemDir) {
            $systemName = basename($systemDir);
            $systemKey = ucfirst($systemName);

            $socpaks = glob($systemDir.DIRECTORY_SEPARATOR.'*.socpak');
            if ($socpaks === false) {
                continue;
            }

            foreach ($socpaks as $socpakPath) {
                $xml = $this->extractXmlFromSocpakCached($socpakPath);
                if ($xml === null) {
                    continue;
                }

                $dom = new DOMDocument;
                $dom->loadXML($xml);
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query('//ChildObjectContainers/Child');
                if ($nodes === false) {
                    continue;
                }

                $className = $this->resolveSocpakClassName($socpakPath, $uuidToClassMap);
                if ($className === null) {
                    continue;
                }

                foreach ($nodes as $node) {
                    if (! ($node instanceof DOMElement)) {
                        continue;
                    }

                    $name = $node->getAttribute('name');
                    $caveInfo = $this->parseCaveReference($name);
                    if ($caveInfo === null) {
                        continue;
                    }

                    $caveMappings[$systemKey][$caveInfo['type']][$caveInfo['occupancy']][$className] = true;
                }
            }
        }

        foreach ($caveMappings as &$systemData) {
            foreach ($systemData as &$typeData) {
                foreach ($typeData as &$occupancyData) {
                    $occupancyData = array_keys($occupancyData);
                    sort($occupancyData);
                }
            }
        }
        unset($systemData, $typeData, $occupancyData);

        file_put_contents(
            $this->scDataPath.DIRECTORY_SEPARATOR.'cave_mappings.json',
            json_encode($caveMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array{type: string, occupancy: string}|null
     */
    private function parseCaveReference(string $name): ?array
    {
        if (! preg_match('#/cave/(rock|sand|acidic)\d+/system/\w+_(occu|unoc)_\d+#i', $name, $matches)) {
            return null;
        }

        $type = strtolower($matches[1]);
        $occupancy = $matches[2] === 'occu' ? 'occupied' : 'unoccupied';

        return ['type' => $type, 'occupancy' => $occupancy];
    }

    /**
     * @param  array<string, string>  $uuidToClassMap
     */
    private function resolveSocpakClassName(string $socpakPath, array $uuidToClassMap): ?string
    {
        $filename = pathinfo($socpakPath, PATHINFO_FILENAME);

        $xml = $this->extractXmlFromSocpakCached($socpakPath);
        if ($xml === null) {
            return null;
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//ChildObjectContainers/Child');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! ($node instanceof DOMElement)) {
                    continue;
                }

                $starMapRecord = $node->getAttribute('starMapRecord');
                if ($starMapRecord !== '') {
                    $resolved = $uuidToClassMap[strtolower($starMapRecord)] ?? null;
                    if ($resolved !== null && ! $this->isPrivateMiningPoint($resolved)) {
                        return $resolved;
                    }
                }
            }
        }

        $match = [];
        if (preg_match('/^([a-z]+\d+[a-z]?)/i', $filename, $match)) {
            return ucfirst($match[1]);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $uuidToClassMap
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     * @param  array<string, string>  $parentMappings
     */
    private function seedFromSystemSocpaks(string $baseDir, array $uuidToClassMap, array &$queue, array &$processed, array &$pinnedSocpaks, array &$parentMappings): void
    {
        $systemDirs = glob($baseDir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
        if ($systemDirs === false) {
            return;
        }

        foreach ($systemDirs as $systemDir) {
            $systemName = basename($systemDir);
            $systemSocpak = $systemDir.DIRECTORY_SEPARATOR.$systemName.'system.socpak';
            if (! file_exists($systemSocpak)) {
                continue;
            }

            $xml = $this->extractXmlFromSocpakCached($systemSocpak);
            if ($xml === null) {
                continue;
            }

            $dom = new DOMDocument;
            $dom->loadXML($xml);

            $this->walkSystemXmlNode($dom->documentElement, null, false, $systemDir, $uuidToClassMap, $queue, $processed, $pinnedSocpaks, $parentMappings);
        }
    }

    /**
     * @param  array<string, string>  $uuidToClassMap
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     * @param  array<string, string>  $parentMappings
     */
    private function walkSystemXmlNode(DOMElement $node, ?string $parentClassName, bool $pinned, string $systemDir, array $uuidToClassMap, array &$queue, array &$processed, array &$pinnedSocpaks, array &$parentMappings): void
    {
        foreach ($node->childNodes as $childNode) {
            if (! ($childNode instanceof DOMElement) || $childNode->nodeName !== 'ChildObjectContainers') {
                continue;
            }

            foreach ($childNode->childNodes as $child) {
                if (! ($child instanceof DOMElement) || $child->nodeName !== 'Child') {
                    continue;
                }

                $name = $child->getAttribute('name');
                if ($name === '' || $this->shouldExcludeChild($name)) {
                    continue;
                }

                $childClassName = $this->resolveChildClassName($child, $parentClassName, $pinned, $uuidToClassMap);

                if ($childClassName !== null && $parentClassName !== null) {
                    $parentMappings[$childClassName] = $parentClassName;
                }

                $socpakPath = $this->resolveSocpakPath($name, $systemDir);
                if ($socpakPath === null) {
                    continue;
                }

                if (! isset($pinnedSocpaks[$socpakPath])) {
                    $queueKey = $socpakPath.'|'.($childClassName ?? '');
                    if (! isset($processed[$queueKey])) {
                        $queue[] = ['socpakPath' => $socpakPath, 'className' => $childClassName, 'pinned' => $pinned];
                    }
                }

                $this->walkSystemXmlNode($child, $childClassName, $pinned, $systemDir, $uuidToClassMap, $queue, $processed, $pinnedSocpaks, $parentMappings);
            }
        }
    }

    /**
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     */
    private function seedFromLagrangePoints(string $baseDir, array &$queue, array &$processed, array &$pinnedSocpaks): void
    {
        $systemDirs = glob($baseDir.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
        if ($systemDirs === false) {
            return;
        }

        foreach ($systemDirs as $systemDir) {
            $lagrangeDir = $systemDir.DIRECTORY_SEPARATOR.'lagrangepoints';
            if (! is_dir($lagrangeDir)) {
                continue;
            }

            $socpaks = glob($lagrangeDir.DIRECTORY_SEPARATOR.'*.socpak');
            if ($socpaks === false) {
                continue;
            }

            foreach ($socpaks as $socpakPath) {
                $dirName = pathinfo($socpakPath, PATHINFO_FILENAME);
                if (! preg_match('/^[a-z]+\d+_l\d+$/i', $dirName)) {
                    continue;
                }

                $className = $this->lagrangeDirToClassName($dirName);
                $queueKey = $socpakPath.'|'.$className;
                if (isset($processed[$queueKey])) {
                    continue;
                }

                $queue[] = ['socpakPath' => $socpakPath, 'className' => $className, 'pinned' => true];
                $pinnedSocpaks[$socpakPath] = true;
            }
        }
    }

    /**
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     * @param  array<string, true>  $pinnedSocpaks
     */
    private function seedFromManualMapping(string $baseDir, array &$queue, array &$processed, array &$pinnedSocpaks): void
    {
        foreach (self::MANUAL_MAPPING as $relativePath => $className) {
            $fullPath = $baseDir.DIRECTORY_SEPARATOR.$relativePath;
            if (! file_exists($fullPath)) {
                continue;
            }

            $queueKey = $fullPath.'|'.$className;
            if (isset($processed[$queueKey])) {
                continue;
            }

            $queue[] = ['socpakPath' => $fullPath, 'className' => $className, 'pinned' => true];
            $processed[$queueKey] = true;
            $pinnedSocpaks[$fullPath] = true;
        }
    }

    /**
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     * @param  array<string, string>  $uuidToClassMap
     * @param  array<string, list<string>>  $mappings
     * @param  array<string, true>  $pinnedUuids
     * @param  array<string, string>  $parentMappings
     */
    private function walkQueue(array &$queue, array &$processed, array $uuidToClassMap, array &$mappings, array &$pinnedUuids, array &$parentMappings): void
    {
        $i = 0;
        while (isset($queue[$i])) {
            $item = $queue[$i++];
            $socpakPath = $item['socpakPath'];
            $className = $item['className'];
            $pinned = (bool) $item['pinned'];
            $queueKey = $socpakPath.'|'.($className ?? '');

            if (isset($processed[$queueKey])) {
                continue;
            }
            $processed[$queueKey] = true;

            $hppUuid = $this->extractHppUuidFromSocpakCached($socpakPath);
            if ($hppUuid !== null && $hppUuid !== self::NULL_UUID && $className !== null && $this->isHppUuid($hppUuid) && $this->shouldExtractMapping($pinned, $hppUuid, $pinnedUuids)) {
                $mappings[$hppUuid][] = $className;
                if ($pinned) {
                    $pinnedUuids[$hppUuid] = true;
                }
            }

            $this->enqueueChildren($socpakPath, $className, $pinned, $uuidToClassMap, $queue, $processed, $parentMappings);
        }
    }

    /**
     * @param  array<string, string>  $uuidToClassMap
     * @param  list<array{socpakPath: string, className: string, pinned: bool}>  $queue
     * @param  array<string, true>  $processed
     * @param  array<string, string>  $parentMappings
     */
    private function enqueueChildren(string $socpakPath, ?string $parentClassName, bool $pinned, array $uuidToClassMap, array &$queue, array &$processed, array &$parentMappings): void
    {
        $xml = $this->extractXmlFromSocpakCached($socpakPath);
        if ($xml === null) {
            return;
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//ChildObjectContainers/Child');
        if ($nodes === false) {
            return;
        }

        $systemDir = $this->inferSystemDir($socpakPath);

        foreach ($nodes as $node) {
            $name = $node->getAttribute('name');
            if ($name === '' || $this->shouldExcludeChild($name)) {
                continue;
            }

            $childClassName = $this->resolveChildClassName($node, $parentClassName, $pinned, $uuidToClassMap);

            if ($childClassName !== null && $parentClassName !== null) {
                $parentMappings[$childClassName] = $parentClassName;
            }

            $childPath = $this->resolveSocpakPath($name, $systemDir);
            if ($childPath === null) {
                continue;
            }

            $queueKey = $childPath.'|'.($childClassName ?? '');
            if (! isset($processed[$queueKey])) {
                $queue[] = ['socpakPath' => $childPath, 'className' => $childClassName, 'pinned' => $pinned];
            }
        }
    }

    /**
     * @param  DOMElement  $node  A <Child> element
     * @param  array<string, string>  $uuidToClassMap
     */
    private function resolveChildClassName(DOMElement $node, ?string $parentClassName, bool $pinned, array $uuidToClassMap): ?string
    {
        if ($pinned) {
            return $parentClassName;
        }

        $starMapRecord = $node->getAttribute('starMapRecord');
        if ($starMapRecord !== '') {
            $resolved = $uuidToClassMap[strtolower($starMapRecord)] ?? null;
            if ($resolved !== null && ! $this->isPrivateMiningPoint($resolved)) {
                return $resolved;
            }
        }

        return $parentClassName;
    }

    private function inferSystemDir(string $socpakPath): string
    {
        $needle = 'ObjectContainers'.DIRECTORY_SEPARATOR.'PU'.DIRECTORY_SEPARATOR.'system'.DIRECTORY_SEPARATOR;
        $puSystemPos = strpos($socpakPath, $needle);
        if ($puSystemPos === false) {
            return dirname($socpakPath);
        }

        $afterSystem = substr($socpakPath, $puSystemPos + strlen($needle));
        $systemName = explode(DIRECTORY_SEPARATOR, $afterSystem)[0];

        return substr($socpakPath, 0, $puSystemPos).$needle.$systemName;
    }

    private function resolveSocpakPath(string $childRef, string $systemDir): ?string
    {
        $filename = basename(str_replace('\\', '/', $childRef));

        $searchDirs = [
            $systemDir,
            $systemDir.DIRECTORY_SEPARATOR.'lagrangepoints',
            $systemDir.DIRECTORY_SEPARATOR.'lagrangepoints'.DIRECTORY_SEPARATOR.'childclouds',
            $systemDir.DIRECTORY_SEPARATOR.'asteroidbase',
            $systemDir.DIRECTORY_SEPARATOR.'asteroidCluster',
        ];

        foreach ($searchDirs as $dir) {
            if ($this->fileExistsInDir($filename, $dir)) {
                return $dir.DIRECTORY_SEPARATOR.$filename;
            }
        }

        if (preg_match(self::OBJECTCONTAINERS_PATTERN, $childRef)) {
            $relative = preg_replace(self::OBJECTCONTAINERS_PATTERN, '', $childRef);
            $basePath = dirname($systemDir, 2);
            $fullPath = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($fullPath);
            $base = basename($fullPath);
            if ($this->fileExistsInDir($base, $dir)) {
                return $fullPath;
            }
        }

        return null;
    }

    private function fileExistsInDir(string $filename, string $dir): bool
    {
        if (! isset($this->dirFileCache[$dir])) {
            $entries = @scandir($dir);
            $this->dirFileCache[$dir] = $entries !== false
                ? array_flip(array_map('strtolower', $entries))
                : [];
        }

        return isset($this->dirFileCache[$dir][strtolower($filename)]);
    }

    private function lagrangeDirToClassName(string $dirName): string
    {
        $parts = explode('_', $dirName);

        return ucfirst($parts[0]).'_L'.substr($parts[1], 1);
    }

    private function shouldExcludeChild(string $name): bool
    {
        $lower = strtolower($name);

        return array_any(self::EXCLUDED_CHILD_SUFFIXES, fn ($suffix) => str_ends_with($lower, $suffix));
    }

    private function isPrivateMiningPoint(string $className): bool
    {
        return stripos($className, 'PrivateMiningPoint') === 0;
    }

    /**
     * @param  array<string, true>  $pinnedUuids
     */
    private function shouldExtractMapping(bool $pinned, string $hppUuid, array $pinnedUuids): bool
    {
        if ($pinned) {
            return true;
        }

        return ! isset($pinnedUuids[$hppUuid]);
    }

    private function isHppUuid(string $uuid): bool
    {
        return isset($this->hppUuidSet[$uuid]);
    }

    /**
     * @throws JsonException
     */
    private function loadHppUuidSet(): void
    {
        $filename = sprintf('classToUuidMap-%s.json', PHP_OS_FAMILY);
        $path = $this->scDataPath.DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($path)) {
            $this->hppUuidSet = [];

            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->hppUuidSet = [];

            return;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            $this->hppUuidSet = [];

            return;
        }

        $this->hppUuidSet = [];
        foreach ($data as $className => $uuid) {
            if (! is_string($className) || ! is_string($uuid)) {
                continue;
            }
            if (str_starts_with($className, 'HPP_') || str_starts_with($className, 'AsteroidCluster_')) {
                $this->hppUuidSet[strtolower($uuid)] = true;
            }
        }
    }

    private function extractHppUuidFromSocpakCached(string $socpakPath): ?string
    {
        return $this->extractSocpakCached($socpakPath)['hppUuid'];
    }

    private function extractXmlFromSocpakCached(string $socpakPath): ?string
    {
        return $this->extractSocpakCached($socpakPath)['xml'];
    }

    /**
     * @return array{hppUuid: string|null, xml: string|null}
     */
    private function extractSocpakCached(string $socpakPath): array
    {
        if (array_key_exists($socpakPath, $this->socpakCache)) {
            return $this->socpakCache[$socpakPath];
        }

        $zip = new ZipArchive;
        if ($zip->open($socpakPath) !== true) {
            return $this->socpakCache[$socpakPath] = ['hppUuid' => null, 'xml' => null];
        }

        $hppUuid = null;
        $xml = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);

            if ($hppUuid === null && str_ends_with($name, '.soc')) {
                $content = $zip->getFromIndex($i);
                if ($content !== false && preg_match('/preset\x00([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $content, $matches)) {
                    $hppUuid = strtolower($matches[1]);
                }
            }

            if ($xml === null && str_ends_with($name, '.xml')
                && ! str_contains($name, '_editor')
                && ! str_contains($name, 'metadata')) {
                $xml = $zip->getFromIndex($i);
            }

            if ($hppUuid !== null && $xml !== null) {
                break;
            }
        }

        $zip->close();

        return $this->socpakCache[$socpakPath] = ['hppUuid' => $hppUuid, 'xml' => $xml];
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function loadUuidToClassMap(): array
    {
        $filename = sprintf('uuidToClassMap-%s.json', PHP_OS_FAMILY);
        $path = $this->scDataPath.DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }
}
