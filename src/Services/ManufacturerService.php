<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\SCItemManufacturer;

final class ManufacturerService extends BaseService
{
    private array $manufacturerPaths;

    /**
     * LRU document cache keyed by file path.
     *
     * @var array<string, SCItemManufacturer>
     */
    private static array $documentCache = [];

    private const int CACHE_LIMIT = 100;

    /**
     * Canonical manufacturer index keyed by raw localization name key.
     *
     * Duplicate name keys prefer the primary `scitemmanufacturer.*.xml` record.
     *
     * @var array<string, array{code: string, uuid: string}>
     */
    private array $canonicalIndex = [];

    /** @var array<string, string> one uuid per code: collapses aliases/dup codes to the primary record */
    private array $codeToUuid = [];

    /** Primary record path per code (mirrors codeToUuid's winner). */
    private array $codeToPrimaryPath = [];

    /** @var array<string, string> normalized display name -> canonical code */
    private static array $nameToCode = [];

    /** @var array<string, string> canonical code -> display name */
    private static array $codeToName = [];

    /** Set once loadDataJson runs, even on missing/empty file: avoid re-stat per call. */
    private static bool $dataJsonLoaded = false;

    /** Override the wiki data path (tests inject a committed fixture; config survives reset). */
    private static ?string $wikiDataPath = null;

    /**
     * Cross-code aliases for XML codes whose name won't normalize-match data.json
     * (prefix / plural gaps). Uuid stays from the original XML code -- the
     * canonical code has no XML record. Same-code forwards are excluded: they'd
     * clobber variant names (Banu).
     */
    private const array CODE_ALIASES = [
        'BEH' => 'BEHR', // "Behring" -> "Behring Applied Technology"
        'VNC' => 'VNCL', // "Vanduul" -> "Vanduul Clans"
        'ASD' => 'ASAD', // "PH Associated Science and Development" -> "Associated Sciences & Development"
    ];

    public static function resetDocumentCache(): void
    {
        self::$documentCache = [];
    }

    public function count(): int
    {
        return count($this->manufacturerPaths);
    }

    public function initialize(): void
    {
        // data.json must load first: the token-suffix identity fallback needs codeToName.
        $this->loadDataJson();

        /** @var array<string, list<array{code: string, uuid: string, path: string, is_primary: bool}>> $candidates */
        $candidates = [];
        /** @var array<string, list<array{uuid: string, path: string, is_primary: bool}>> $candidatesByCode */
        $candidatesByCode = [];
        $paths = [];

        foreach (self::$uuidToPathMap as $path) {
            if (str_contains($path, 'scitemmanufacturer') !== true) {
                continue;
            }

            $manufacturer = new SCItemManufacturer;
            $manufacturer->setReferenceHydrationEnabled($this->referenceHydrationEnabled);
            $manufacturer->load($path);

            if (! str_starts_with($manufacturer->documentElement->nodeName, 'SCItemManufacturer.')) {
                continue;
            }

            $manufacturer->checkValidity();
            $paths[] = $path;

            $code = $manufacturer->getCode();
            $uuid = $manufacturer->getUuid();

            // uuid is required; code may be absent on codeless alias records (stor.xml).
            if ($uuid === '') {
                continue;
            }

            $nameKey = $manufacturer->get('Localization@Name', raw: true);

            $lookupKey = null;
            if (is_string($nameKey) && str_starts_with($nameKey, '@manufacturer_')) {
                $lookupKey = $nameKey;
                if (str_starts_with($lookupKey, '@manufacturer_Desc')) {
                    $lookupKey = '@manufacturer_Name'.substr($lookupKey, strlen('@manufacturer_Desc'));
                }
            }

            // Token suffix recovers bugged-code records (mrai.xml Code=MIS -> MRAI)
            // and codeless records (stor.xml) that a raw-code lookup would miss.
            $identityCode = null;
            if (is_string($code) && $code !== '' && isset(self::$codeToName[$code])) {
                $identityCode = $code;
            } elseif ($lookupKey !== null) {
                $tokenCode = $this->codeFromNameToken($lookupKey);

                if ($tokenCode !== null) {
                    $identityCode = $tokenCode;
                } elseif (is_string($code) && $code !== '') {
                    $identityCode = $code;
                }
            } elseif (is_string($code) && $code !== '') {
                $identityCode = $code;
            }

            $basename = basename($path);
            $isPrimary = str_starts_with($basename, 'scitemmanufacturer.');

            if ($identityCode !== null) {
                $candidatesByCode[$identityCode][] = [
                    'uuid' => $uuid,
                    'path' => $path,
                    'is_primary' => $isPrimary,
                ];
            }

            if ($lookupKey !== null) {
                $candidates[$lookupKey][] = [
                    'code' => $identityCode ?? $code,
                    'uuid' => $uuid,
                    'path' => $path,
                    'is_primary' => $isPrimary,
                ];
            }
        }

        $this->manufacturerPaths = $paths;

        $pickPrimary = static function (array $entries): array {
            usort($entries, static function (array $a, array $b): int {
                if ($a['is_primary'] !== $b['is_primary']) {
                    return $a['is_primary'] ? -1 : 1;
                }

                return $a['path'] <=> $b['path'];
            });

            return $entries[0];
        };

        foreach ($candidates as $key => $entries) {
            $winner = $pickPrimary($entries);
            $this->canonicalIndex[$key] = [
                'code' => $winner['code'],
                'uuid' => $winner['uuid'],
            ];
        }

        foreach ($candidatesByCode as $code => $entries) {
            $winner = $pickPrimary($entries);
            $this->codeToUuid[$code] = $winner['uuid'];
            $this->codeToPrimaryPath[$code] = $winner['path'];
        }
    }

    /** Loads import/wiki_manufacturers.json (Module:Manufacturers/data.json synced). */
    private function loadDataJson(): void
    {
        if (self::$dataJsonLoaded) {
            return;
        }

        self::$dataJsonLoaded = true;

        $path = self::$wikiDataPath ?? dirname(__DIR__, 2).'/import/wiki_manufacturers.json';

        if (! is_file($path)) {
            return;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (! is_array($data)) {
            return;
        }

        foreach ($data as $code => $entry) {
            $code = (string) $code;
            $name = trim((string) ($entry['name'] ?? ''));

            if ($name !== '') {
                self::$nameToCode[self::normalizeName($name)] = $code;
            }

            self::$codeToName[$code] = $name;
        }
    }

    /** lowercase, & -> and, drop non-alphanumeric */
    private static function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = str_replace('&', 'and', $name);

        return preg_replace('/[^a-z0-9]+/', '', $name) ?? '';
    }

    public static function resetDataOverrideCache(): void
    {
        self::$nameToCode = [];
        self::$codeToName = [];
        self::$dataJsonLoaded = false;
    }

    /** Point the wiki lookup at a fixture path. Pass null to restore the default import/ location. */
    public static function useWikiDataPath(?string $path): void
    {
        self::$wikiDataPath = $path;
    }

    /**
     * Resolve {code, name, uuid} from data.json + XML.
     *
     * A curated code is identity: an XML Name token can be a copy-paste pointing
     * at another manufacturer (FSKI's Name is @AEGS), so the code is the only
     * trustworthy handle. Falls through to name normalization, then null (trust
     * raw XML). Cross-code aliases (CODE_ALIASES) are checked first.
     *
     * @return array{code: string, name: string, uuid: ?string}|null
     */
    public function resolveCanonicalByNameOrCode(?string $name, ?string $code): ?array
    {
        $this->loadDataJson();

        if (is_string($code) && $code !== '') {
            if ($this->isAliasedCode($code)) {
                return $this->resolveAliasedCode($code, null);
            }

            // Curated code is identity even when the Name token copy-pastes to
            // a different manufacturer (FSKI -> AEGS).
            if (isset(self::$codeToName[$code])) {
                return [
                    'code' => $code,
                    'name' => self::$codeToName[$code],
                    'uuid' => $this->canonicalUuidForCode($code),
                ];
            }
        }

        if (is_string($name) && $name !== '' && ! str_starts_with($name, '@')) {
            $norm = self::normalizeName($name);

            if (isset(self::$nameToCode[$norm])) {
                $c = self::$nameToCode[$norm];

                return [
                    'code' => $c,
                    'name' => self::$codeToName[$c] ?? $name,
                    'uuid' => $this->canonicalUuidForCode($c),
                ];
            }
        }

        return null;
    }

    /**
     * Forward lookup for a known code (e.g. a wiki-supplied code): resolves the
     * canonical name + uuid. Null means the code isn't in data.json; the caller
     * falls through to name resolution.
     *
     * @return array{code: string, name: string, uuid: ?string}|null
     */
    public function resolveCanonicalByCode(string $code): ?array
    {
        $this->loadDataJson();

        if ($this->isAliasedCode($code)) {
            return $this->resolveAliasedCode($code, null);
        }

        return isset(self::$codeToName[$code])
            ? ['code' => $code, 'name' => self::$codeToName[$code], 'uuid' => $this->canonicalUuidForCode($code)]
            : null;
    }

    /**
     * Single entry point for the wiki-first precedence: a curated wiki code wins,
     * an unknown one falls through to name resolution. Null means trust raw XML.
     *
     * @return array{code: string, name: string, uuid: ?string}|null
     */
    public function resolveCanonicalFor(?string $name, ?string $code, ?string $wikiCode): ?array
    {
        if ($wikiCode !== null) {
            return $this->resolveCanonicalByCode($wikiCode)
                ?? $this->resolveCanonicalByNameOrCode($name, $code);
        }

        return $this->resolveCanonicalByNameOrCode($name, $code);
    }

    /**
     * Resolve a manufacturer entity to {code, name, uuid}: token path (codeless
     * aliases), data.json canonical, cross-code alias, raw-XML fallback.
     *
     * @return array{code: ?string, name: ?string, uuid: ?string}|null
     */
    public function resolveForEntity(?SCItemManufacturer $manufacturer, ?string $wikiCode): ?array
    {
        if ($manufacturer === null) {
            return $wikiCode !== null ? $this->resolveCanonicalByCode($wikiCode) : null;
        }

        $code = $manufacturer->getCode();
        $rawNameKey = $manufacturer->get('Localization@Name', raw: true);

        // Token path: codeless aliases resolve by @manufacturer_* token.
        $canonicalByToken = null;
        if (($code === null || $code === '')
            && is_string($rawNameKey)
            && str_starts_with($rawNameKey, '@manufacturer_')
        ) {
            $canonicalByToken = $this->getCanonicalByNameKey($rawNameKey);
        }

        $canonical = $this->resolveCanonicalFor(
            $manufacturer->get('Localization@Name'),
            $code,
            $wikiCode,
        );

        return [
            'code' => $canonical['code'] ?? $canonicalByToken['code'] ?? $code,
            'name' => $canonical['name'] ?? $manufacturer->get('Localization@Name'),
            'uuid' => $canonical['uuid'] ?? $canonicalByToken['uuid'] ?? $manufacturer->getUuid(),
        ];
    }

    /** Canonical uuid for a code (the primary record's uuid). Null when there's no XML record; callers coalesce. */
    private function canonicalUuidForCode(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->codeToUuid[$code] ?? null;
    }

    private function isAliasedCode(?string $code): bool
    {
        return is_string($code) && $code !== '' && array_key_exists($code, self::CODE_ALIASES);
    }

    /** Code from a name-token suffix (`@manufacturer_NameMRAI` -> `MRAI`) when curated. */
    private function codeFromNameToken(string $lookupKey): ?string
    {
        $prefix = '@manufacturer_Name';
        if (! str_starts_with($lookupKey, $prefix)) {
            return null;
        }

        $suffix = substr($lookupKey, strlen($prefix));

        return $suffix !== '' && isset(self::$codeToName[$suffix]) ? $suffix : null;
    }

    /**
     * Relabel a cross-code alias to its canonical code+name, keeping the uuid
     * from the original XML code (the canonical code has no XML record).
     *
     * @return array{code: string, name: string, uuid: ?string}
     */
    private function resolveAliasedCode(string $code, ?string $fallbackName): array
    {
        $canonicalCode = self::CODE_ALIASES[$code];

        return [
            'code' => $canonicalCode,
            'name' => self::$codeToName[$canonicalCode] ?? $fallbackName ?? $code,
            'uuid' => $this->canonicalUuidForCode($code),
        ];
    }

    /**
     * Look up a canonical manufacturer by raw localization name key.
     *
     * @param  string  $nameKey  Raw localization key, e.g. `@manufacturer_NameRSI`
     * @return array{code: string, uuid: string}|null Canonical manufacturer data, or null if not found
     */
    public function getCanonicalByNameKey(string $nameKey): ?array
    {
        if (str_starts_with($nameKey, '@manufacturer_Desc')) {
            $nameKey = '@manufacturer_Name'.substr($nameKey, strlen('@manufacturer_Desc'));
        }

        return $this->canonicalIndex[$nameKey] ?? null;
    }

    public function iterator(): Generator
    {
        foreach ($this->manufacturerPaths as $path) {
            yield $this->load($path);
        }
    }

    /**
     * Yields one primary record per code -- collapses aliases, dup codes, and
     * paint-logo imposters. Use for canonical exports, never iterator().
     */
    public function canonicalIterator(): Generator
    {
        foreach ($this->codeToPrimaryPath as $path) {
            yield $this->load($path);
        }
    }

    public function getByReference($uuid): ?SCItemManufacturer
    {
        $path = $this->resolvePathByReference(is_string($uuid) ? $uuid : null);

        if ($path === null || ! in_array($path, $this->manufacturerPaths, true)) {
            return null;
        }

        return $this->load($path);
    }

    public function load(string $filePath): ?SCItemManufacturer
    {
        $cacheKey = $this->referenceHydrationEnabled ? '1:'.$filePath : '0:'.$filePath;

        $cached = self::cacheGet(self::$documentCache, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $doc = $this->loadDocument($filePath, SCItemManufacturer::class);
        self::cachePut(self::$documentCache, $cacheKey, $doc, self::CACHE_LIMIT);

        return $doc;
    }
}
