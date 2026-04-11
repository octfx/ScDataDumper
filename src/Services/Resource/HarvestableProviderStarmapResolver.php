<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use Illuminate\Support\Str;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class HarvestableProviderStarmapResolver
{
    private const array PROVIDER_METADATA = [
        'asteroidcluster_low_yield' => ['name' => 'Asteroid Cluster (Low Yield)'],
        'asteroidcluster_medium_yield' => ['name' => 'Asteroid Cluster (Medium Yield)'],
        'hpp_aaronhalo' => ['name' => 'Aaron Halo'],
        'hpp_lagrange_occupied' => ['name' => 'Lagrange (Occupied)'],
        'hpp_pyro_deepspaceasteroids' => ['name' => 'Pyro Deep Space Asteroids'],
        'hpp_shipgraveyard_001' => ['name' => 'Ship Graveyard'],
        'hpp_spacederelict_general' => ['name' => 'Space Derelict', 'type' => 'special'],
        'hpp_stanton2c_belt' => ['name' => 'Yela Asteroid Belt'],
        'hpp_pyro_akirocluster' => ['name' => 'Akiro Cluster'],
    ];

    private const array TYPE_OVERRIDES = [
        'asteroid' => 'lagrange',
        'asteroid_validqt' => 'belt',
    ];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}>
     */
    private array $byClassName = [];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}>
     */
    private array $byLocationHierarchyTagName = [];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}>
     */
    private array $byDisplayName = [];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}>
     */
    private array $byAsteroidRingParent = [];

    /**
     * @var array<string, list<string>>
     */
    private array $socpakMapping = [];

    public function __construct()
    {
        $this->loadSocpakMapping();
        $this->buildIndex();
    }

    /**
     * @return array{
     *     presetFile: string,
     *     systemKey: ?string,
     *     locationName: string,
     *     locationType: string,
     *     starmapKey: string,
     *     starmapObjectUuid: ?string,
     *     starmapLocationHierarchyTagUuid: ?string,
     *     starmapLocationHierarchyTagName: ?string,
     *     matchStrategy: string,
     *     locations: list<array{className: string, starmapObjectUuid: ?string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, name: string, type: string}>
     * }
     */
    public function resolveHarvestableProvider(HarvestableProviderPreset $provider): array
    {
        $providerUuid = strtolower($provider->getUuid());
        if (isset($this->socpakMapping[$providerUuid])) {
            return $this->resolveViaSocpak($provider, $this->socpakMapping[$providerUuid]);
        }

        $systemKey = $this->deriveSystemKey($provider);
        $starmapKey = $this->deriveStarmapKey($provider, $systemKey);
        $presetFile = $this->derivePresetFile($provider);

        $match = $this->lookup($this->normalizeKey($starmapKey), $this->byLocationHierarchyTagName)
            ?? $this->lookup($this->normalizeKey($starmapKey), $this->byClassName);

        $matchStrategy = 'none';
        $locationName = $this->deriveLocationName($presetFile, $starmapKey, $match);

        if ($match !== null) {
            $matchStrategy = 'tag';
        } else {
            $match = $this->lookup($this->normalizeKey($locationName), $this->byDisplayName);
            if ($match !== null) {
                $matchStrategy = 'display_name';
                $locationName = $this->deriveLocationName($presetFile, $starmapKey, $match);
            } else {
                $asteroidKey = $this->deriveAsteroidRingKey($provider);
                if ($asteroidKey !== null) {
                    $match = $this->lookup($asteroidKey, $this->byAsteroidRingParent);
                    if ($match !== null) {
                        $matchStrategy = 'asteroid_ring';
                        $locationName = $this->deriveLocationName($presetFile, $starmapKey, $match);
                    }
                }
            }
        }

        $locationType = $this->deriveLocationType($presetFile, $provider, $match);

        $locations = [];
        if ($match !== null) {
            $locations[] = [
                'className' => $match['className'] ?? $starmapKey,
                'starmapObjectUuid' => $match['starmapObjectUuid'] ?? null,
                'starmapLocationHierarchyTagUuid' => $match['starmapLocationHierarchyTagUuid'] ?? null,
                'starmapLocationHierarchyTagName' => $match['starmapLocationHierarchyTagName'] ?? null,
                'name' => $match['name'] ?? $locationName,
                'type' => $locationType,
            ];
        }

        return [
            'presetFile' => $presetFile,
            'starmapKey' => $starmapKey,
            'systemKey' => $systemKey,
            'locationName' => $locationName,
            'locationType' => $locationType,
            'starmapObjectUuid' => $match['starmapObjectUuid'] ?? null,
            'starmapLocationHierarchyTagUuid' => $match['starmapLocationHierarchyTagUuid'] ?? null,
            'starmapLocationHierarchyTagName' => $match['starmapLocationHierarchyTagName'] ?? null,
            'matchStrategy' => $matchStrategy,
            'locations' => $locations,
        ];
    }

    /**
     * @return array{className: string, starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null
     */
    public function resolveByClassName(string $className): ?array
    {
        return $this->lookup($this->normalizeKey($className), $this->byClassName);
    }

    private function buildIndex(): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();

        foreach ($lookup->getDocumentType('StarMapObject', StarMapObject::class) as $object) {
            $entry = $this->buildEntry($object);

            $classKey = $this->normalizeKey($object->getClassName());
            if ($classKey !== '') {
                $this->byClassName[$classKey] = $entry;
            }

            $displayName = $entry['name'];
            if ($displayName !== null) {
                $displayKey = $this->normalizeKey($displayName);
                if ($displayKey !== '' && ! isset($this->byDisplayName[$displayKey])) {
                    $this->byDisplayName[$displayKey] = $entry;
                }
            }

            if ($object->getAsteroidRing() !== null) {
                $asteroidKey = $this->normalizeKey($object->getClassName());
                if ($asteroidKey !== '') {
                    $this->byAsteroidRingParent[$asteroidKey] = $entry;
                }
            }

            $tagName = $object->getLocationHierarchyTagName();
            if ($tagName === null || $tagName === '') {
                continue;
            }

            $tagKey = $this->normalizeKey($tagName);
            $existing = $this->byLocationHierarchyTagName[$tagKey] ?? null;

            if ($existing === null || $classKey === $tagKey) {
                $this->byLocationHierarchyTagName[$tagKey] = $entry;
            }
        }
    }

    private function loadSocpakMapping(): void
    {
        $path = ServiceFactory::getActiveScDataPath();
        if ($path === null) {
            return;
        }

        $mappingFile = $path.DIRECTORY_SEPARATOR.'socpak_mappings.json';
        if (! file_exists($mappingFile)) {
            return;
        }

        $content = file_get_contents($mappingFile);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (is_array($data)) {
            $this->socpakMapping = $data;
        }
    }

    /**
     * @param  list<string>  $classNames
     * @return array{
     *     presetFile: string,
     *     systemKey: ?string,
     *     locationName: string,
     *     locationType: string,
     *     starmapKey: string,
     *     starmapObjectUuid: ?string,
     *     starmapLocationHierarchyTagUuid: ?string,
     *     starmapLocationHierarchyTagName: ?string,
     *     matchStrategy: string,
     *     locations: list<array{className: string, starmapObjectUuid: ?string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, name: string, type: string}>
     * }
     */
    private function resolveViaSocpak(HarvestableProviderPreset $provider, array $classNames): array
    {
        $systemKey = $this->deriveSystemKey($provider);
        $presetFile = $this->derivePresetFile($provider);
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;

        $firstClassName = $classNames[0] ?? '';
        $firstMatch = $this->lookup($this->normalizeKey($firstClassName), $this->byClassName);

        $locationName = $this->deriveSocpakLocationName($presetFile, $firstClassName, $firstMatch, $metadata);
        $locationType = $this->deriveSocpakLocationType($presetFile, $provider, $firstMatch);

        $locations = [];
        foreach ($classNames as $className) {
            $normalizedKey = $this->normalizeKey($className);
            $match = $this->lookup($normalizedKey, $this->byClassName);

            $locations[] = [
                'className' => $className,
                'starmapObjectUuid' => $match['starmapObjectUuid'] ?? null,
                'starmapLocationHierarchyTagUuid' => $match['starmapLocationHierarchyTagUuid'] ?? null,
                'starmapLocationHierarchyTagName' => $match['starmapLocationHierarchyTagName'] ?? null,
                'name' => $match['name'] ?? Str::headline($className),
                'type' => $match['type'] ?? $locationType,
            ];
        }

        return [
            'presetFile' => $presetFile,
            'starmapKey' => $firstClassName,
            'systemKey' => $systemKey,
            'locationName' => $locationName,
            'locationType' => $locationType,
            'starmapObjectUuid' => $firstMatch['starmapObjectUuid'] ?? null,
            'starmapLocationHierarchyTagUuid' => $firstMatch['starmapLocationHierarchyTagUuid'] ?? null,
            'starmapLocationHierarchyTagName' => $firstMatch['starmapLocationHierarchyTagName'] ?? null,
            'matchStrategy' => 'socpak',
            'locations' => $locations,
        ];
    }

    /**
     * @param  array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null  $match
     * @param  array{name: string, type?: string}|null  $metadata
     */
    private function deriveSocpakLocationName(string $presetFile, string $className, ?array $match, ?array $metadata): string
    {
        if ($metadata !== null) {
            return $metadata['name'];
        }

        if ($match !== null && $match['name'] !== null && $match['name'] !== '') {
            return $match['name'];
        }

        return Str::headline($className);
    }

    /**
     * @param  array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null  $match
     */
    private function deriveSocpakLocationType(string $presetFile, HarvestableProviderPreset $provider, ?array $match): string
    {
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;
        if (isset($metadata['type'])) {
            return $metadata['type'];
        }

        $conventionType = $this->deriveTypeFromConventions($provider);
        if ($conventionType !== null) {
            return $conventionType;
        }

        if ($match !== null && $match['type'] !== null) {
            return $match['type'];
        }

        return 'unknown';
    }

    /**
     * @return array{className: string, starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}
     */
    private function buildEntry(StarMapObject $object): array
    {
        $typeDoc = $object->getTypeDocument();
        $typeName = $typeDoc?->getName();
        $translatedName = $this->translate($object->getName());

        $mappedType = null;
        if ($typeName !== null) {
            $typeKey = $this->normalizeKey($typeName);
            $mappedType = self::TYPE_OVERRIDES[$typeKey] ?? $typeKey;

            if ($mappedType === 'lagrange' && $object->getAsteroidRing() !== null) {
                $mappedType = 'belt';
            }
        }

        return [
            'className' => $object->getClassName(),
            'starmapObjectUuid' => $object->getUuid(),
            'starmapLocationHierarchyTagUuid' => $object->getLocationHierarchyTagReference(),
            'starmapLocationHierarchyTagName' => $object->getLocationHierarchyTagName(),
            'type' => $mappedType,
            'name' => $translatedName,
        ];
    }

    /**
     * @param  array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}>  $index
     * @return array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null
     */
    private function lookup(string $key, array $index): ?array
    {
        return $key === '' ? null : ($index[$key] ?? null);
    }

    private function deriveSystemKey(HarvestableProviderPreset $provider): ?string
    {
        if (preg_match('#/system/([^/]+)/#i', $provider->getPath(), $matches) !== 1) {
            return null;
        }

        return Str::studly($matches[1]);
    }

    private function deriveStarmapKey(HarvestableProviderPreset $provider, ?string $systemKey): string
    {
        $className = preg_replace('/^HPP_/i', '', $provider->getClassName()) ?? $provider->getClassName();

        if ($systemKey !== null) {
            $prefix = $systemKey.'_';

            if (str_starts_with(strtolower($className), strtolower($prefix))) {
                $className = substr($className, strlen($prefix)) ?: $className;
            }
        }

        return Str::studly($className);
    }

    private function derivePresetFile(HarvestableProviderPreset $provider): string
    {
        $path = trim($provider->getPath());
        if ($path === '') {
            return strtolower($provider->getClassName());
        }

        $filename = pathinfo($path, PATHINFO_FILENAME);

        return is_string($filename) && $filename !== '' ? $filename : strtolower($provider->getClassName());
    }

    /**
     * @param  array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null  $match
     */
    private function deriveLocationType(string $presetFile, HarvestableProviderPreset $provider, ?array $match): string
    {
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;
        if (isset($metadata['type'])) {
            return $metadata['type'];
        }

        $conventionType = $this->deriveTypeFromConventions($provider);
        if ($conventionType !== null) {
            return $conventionType;
        }

        if ($match !== null && $match['type'] !== null) {
            return $match['type'];
        }

        return 'unknown';
    }

    private function deriveTypeFromConventions(HarvestableProviderPreset $provider): ?string
    {
        $lowerClassName = strtolower($provider->getClassName());
        $lowerPresetFile = strtolower($this->derivePresetFile($provider));
        $lowerPath = strtolower($provider->getPath());

        if (str_contains($lowerClassName, 'cluster')) {
            return 'cluster';
        }

        if (str_contains($lowerClassName, 'lagrange') || str_contains($lowerPresetFile, 'lagrange')) {
            return 'lagrange';
        }

        if (str_contains($lowerPath, '/asteroidfield/')) {
            return 'belt';
        }

        return null;
    }

    private function deriveAsteroidRingKey(HarvestableProviderPreset $provider): ?string
    {
        $className = preg_replace('/^HPP_/i', '', $provider->getClassName()) ?? $provider->getClassName();
        $className = preg_replace('/_Belt$/i', '', $className) ?? $className;

        $key = $this->normalizeKey(Str::studly($className));

        return $key !== '' ? $key : null;
    }

    /**
     * @param  array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, type: ?string, name: ?string}|null  $match
     */
    private function deriveLocationName(string $presetFile, string $starmapKey, ?array $match): string
    {
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;
        if ($metadata !== null) {
            return $metadata['name'];
        }

        if ($match !== null && $match['name'] !== null && $match['name'] !== '') {
            return $match['name'];
        }

        return Str::headline($starmapKey);
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function translate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! str_starts_with($value, '@')) {
            return $value;
        }

        try {
            $translated = ServiceFactory::getLocalizationService()->getTranslation($value);
        } catch (RuntimeException) {
            return null;
        }

        if (! is_string($translated) || $translated === '' || $translated === $value) {
            return null;
        }

        return $translated;
    }
}
