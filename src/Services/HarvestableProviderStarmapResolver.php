<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use RuntimeException;

final class HarvestableProviderStarmapResolver
{
    /**
     * Canonical mining-location metadata derived from provider preset identity.
     *
     * @var array<string, array{name: string, type: string}>
     */
    private const PROVIDER_METADATA = [
        'asteroidcluster_low_yield' => ['name' => 'Asteroid Cluster (Low Yield)', 'type' => 'cluster'],
        'asteroidcluster_medium_yield' => ['name' => 'Asteroid Cluster (Medium Yield)', 'type' => 'cluster'],
        'hpp_aaronhalo' => ['name' => 'Aaron Halo', 'type' => 'belt'],
        'hpp_lagrange_a' => ['name' => 'Lagrange A', 'type' => 'lagrange'],
        'hpp_lagrange_b' => ['name' => 'Lagrange B', 'type' => 'lagrange'],
        'hpp_lagrange_c' => ['name' => 'Lagrange C', 'type' => 'lagrange'],
        'hpp_lagrange_d' => ['name' => 'Lagrange D', 'type' => 'lagrange'],
        'hpp_lagrange_e' => ['name' => 'Lagrange E', 'type' => 'lagrange'],
        'hpp_lagrange_f' => ['name' => 'Lagrange F', 'type' => 'lagrange'],
        'hpp_lagrange_g' => ['name' => 'Lagrange G', 'type' => 'lagrange'],
        'hpp_lagrange_occupied' => ['name' => 'Lagrange (Occupied)', 'type' => 'lagrange'],
        'hpp_nyx_glaciemring' => ['name' => 'Glaciem Ring', 'type' => 'belt'],
        'hpp_nyx_keegerbelt' => ['name' => 'Keeger Belt', 'type' => 'belt'],
        'hpp_pyro1' => ['name' => 'Pyro I', 'type' => 'planet'],
        'hpp_pyro2' => ['name' => 'Pyro II (Monox)', 'type' => 'planet'],
        'hpp_pyro3' => ['name' => 'Pyro III (Bloom)', 'type' => 'planet'],
        'hpp_pyro4' => ['name' => 'Pyro IV', 'type' => 'planet'],
        'hpp_pyro5a' => ['name' => 'Pyro V-a (Ignis)', 'type' => 'moon'],
        'hpp_pyro5b' => ['name' => 'Pyro V-b (Vatra)', 'type' => 'moon'],
        'hpp_pyro5c' => ['name' => 'Pyro V-c (Adir)', 'type' => 'moon'],
        'hpp_pyro5d' => ['name' => 'Pyro V-d (Fairo)', 'type' => 'moon'],
        'hpp_pyro5e' => ['name' => 'Pyro V-e (Fuego)', 'type' => 'moon'],
        'hpp_pyro5f' => ['name' => 'Pyro V-f (Vuur)', 'type' => 'moon'],
        'hpp_pyro6' => ['name' => 'Pyro VI (Terminus)', 'type' => 'planet'],
        'hpp_pyro_akirocluster' => ['name' => 'Akiro Cluster', 'type' => 'belt'],
        'hpp_pyro_cool01' => ['name' => 'Pyro Belt (Cool 1)', 'type' => 'belt'],
        'hpp_pyro_cool02' => ['name' => 'Pyro Belt (Cool 2)', 'type' => 'belt'],
        'hpp_pyro_deepspaceasteroids' => ['name' => 'Pyro Deep Space Asteroids', 'type' => 'belt'],
        'hpp_pyro_warm01' => ['name' => 'Pyro Belt (Warm 1)', 'type' => 'belt'],
        'hpp_pyro_warm02' => ['name' => 'Pyro Belt (Warm 2)', 'type' => 'belt'],
        'hpp_shipgraveyard_001' => ['name' => 'Ship Graveyard', 'type' => 'special'],
        'hpp_spacederelict_general' => ['name' => 'Space Derelict', 'type' => 'special'],
        'hpp_stanton1' => ['name' => 'Hurston', 'type' => 'planet'],
        'hpp_stanton1a' => ['name' => 'Arial', 'type' => 'moon'],
        'hpp_stanton1b' => ['name' => 'Aberdeen', 'type' => 'moon'],
        'hpp_stanton1c' => ['name' => 'Magda', 'type' => 'moon'],
        'hpp_stanton1d' => ['name' => 'Ita', 'type' => 'moon'],
        'hpp_stanton2a' => ['name' => 'Daymar', 'type' => 'moon'],
        'hpp_stanton2b' => ['name' => 'Cellin', 'type' => 'moon'],
        'hpp_stanton2c' => ['name' => 'Yela', 'type' => 'moon'],
        'hpp_stanton2c_belt' => ['name' => 'Yela Asteroid Belt', 'type' => 'belt'],
        'hpp_stanton3a' => ['name' => 'Lyria', 'type' => 'moon'],
        'hpp_stanton3b' => ['name' => 'Wala', 'type' => 'moon'],
        'hpp_stanton4' => ['name' => 'microTech', 'type' => 'planet'],
        'hpp_stanton4a' => ['name' => 'Calliope', 'type' => 'moon'],
        'hpp_stanton4b' => ['name' => 'Clio', 'type' => 'moon'],
        'hpp_stanton4c' => ['name' => 'Euterpe', 'type' => 'moon'],
    ];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}>
     */
    private array $byClassName = [];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}>
     */
    private array $byLocationHierarchyTagName = [];

    /**
     * @var array<string, array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}>
     */
    private array $byDisplayName = [];

    public function __construct()
    {
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
     *     matchStrategy: string
     * }
     */
    public function resolveHarvestableProvider(HarvestableProviderPreset $provider): array
    {
        $systemKey = $this->deriveSystemKey($provider);
        $starmapKey = $this->deriveStarmapKey($provider, $systemKey);
        $presetFile = $this->derivePresetFile($provider);
        $canonicalLocationName = $this->deriveLocationName($provider, $presetFile, $starmapKey, $systemKey);
        $locationType = $this->deriveLocationType($provider, $presetFile);

        $tagMatch = $this->findByLocationHierarchyTagName($starmapKey);

        if ($tagMatch !== null) {
            return [
                'presetFile' => $presetFile,
                'starmapKey' => $starmapKey,
                'systemKey' => $systemKey,
                'locationName' => $canonicalLocationName,
                'locationType' => $locationType,
                'starmapObjectUuid' => $tagMatch['starmapObjectUuid'],
                'starmapLocationHierarchyTagUuid' => $tagMatch['starmapLocationHierarchyTagUuid'],
                'starmapLocationHierarchyTagName' => $tagMatch['starmapLocationHierarchyTagName'],
                'matchStrategy' => 'tag',
            ];
        }

        $objectMatch = $this->findByClassName($starmapKey);

        if ($objectMatch !== null) {
            return [
                'presetFile' => $presetFile,
                'starmapKey' => $starmapKey,
                'systemKey' => $systemKey,
                'locationName' => $canonicalLocationName,
                'locationType' => $locationType,
                'starmapObjectUuid' => $objectMatch['starmapObjectUuid'],
                'starmapLocationHierarchyTagUuid' => $objectMatch['starmapLocationHierarchyTagUuid'],
                'starmapLocationHierarchyTagName' => $objectMatch['starmapLocationHierarchyTagName'],
                'matchStrategy' => 'class',
            ];
        }

        $displayNameMatch = $this->findByDisplayName($canonicalLocationName);

        if ($displayNameMatch !== null) {
            return [
                'presetFile' => $presetFile,
                'starmapKey' => $starmapKey,
                'systemKey' => $systemKey,
                'locationName' => $canonicalLocationName,
                'locationType' => $locationType,
                'starmapObjectUuid' => $displayNameMatch['starmapObjectUuid'],
                'starmapLocationHierarchyTagUuid' => $displayNameMatch['starmapLocationHierarchyTagUuid'],
                'starmapLocationHierarchyTagName' => $displayNameMatch['starmapLocationHierarchyTagName'],
                'matchStrategy' => 'display_name',
            ];
        }

        return [
            'presetFile' => $presetFile,
            'starmapKey' => $starmapKey,
            'systemKey' => $systemKey,
            'locationName' => $canonicalLocationName,
            'locationType' => $locationType,
            'starmapObjectUuid' => null,
            'starmapLocationHierarchyTagUuid' => null,
            'starmapLocationHierarchyTagName' => null,
            'matchStrategy' => 'none',
        ];
    }

    private function buildIndex(): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();

        foreach ($lookup->getDocumentType('StarMapObject', StarMapObject::class) as $object) {
            $entry = [
                'starmapObjectUuid' => $object->getUuid(),
                'starmapLocationHierarchyTagUuid' => $object->getLocationHierarchyTagReference(),
                'starmapLocationHierarchyTagName' => $object->getLocationHierarchyTagName(),
            ];

            $classKey = $this->normalizeKey($object->getClassName());
            if ($classKey !== '') {
                $this->byClassName[$classKey] = $entry;
            }

            $displayName = $this->translate($object->getName());
            if (is_string($displayName) && $displayName !== '') {
                $displayKey = $this->normalizeKey($displayName);
                if ($displayKey !== '' && ! isset($this->byDisplayName[$displayKey])) {
                    $this->byDisplayName[$displayKey] = $entry;
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

    /**
     * @return array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}|null
     */
    private function findByLocationHierarchyTagName(string $name): ?array
    {
        $key = $this->normalizeKey($name);

        return $key === '' ? null : ($this->byLocationHierarchyTagName[$key] ?? null);
    }

    /**
     * @return array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}|null
     */
    private function findByClassName(string $name): ?array
    {
        $key = $this->normalizeKey($name);

        return $key === '' ? null : ($this->byClassName[$key] ?? null);
    }

    /**
     * @return array{starmapObjectUuid: string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string}|null
     */
    private function findByDisplayName(string $name): ?array
    {
        $key = $this->normalizeKey($name);

        return $key === '' ? null : ($this->byDisplayName[$key] ?? null);
    }

    private function deriveSystemKey(HarvestableProviderPreset $provider): ?string
    {
        if (preg_match('#/system/([^/]+)/#i', $provider->getPath(), $matches) !== 1) {
            return null;
        }

        return $this->toPascalCase($matches[1]);
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

        return $this->toPascalCase($className);
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

    private function deriveLocationType(HarvestableProviderPreset $provider, string $presetFile): string
    {
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;
        if ($metadata !== null) {
            return $metadata['type'];
        }

        $path = strtolower($provider->getPath());
        $className = strtolower($provider->getClassName());

        if (str_contains($path, '/lagrange/') || str_contains($className, 'lagrange')) {
            return 'lagrange';
        }

        if (str_contains($path, '/asteroidfield/') || str_contains($className, 'belt') || str_contains($className, 'halo')) {
            return 'belt';
        }

        if (str_contains($className, 'asteroidcluster')) {
            return 'cluster';
        }

        if (preg_match('/hpp_[a-z0-9]+[0-9][a-z]?$/', $className) === 1) {
            return str_contains($className, '5') ? 'moon' : 'planet';
        }

        if (str_contains($className, 'graveyard') || str_contains($className, 'derelict')) {
            return 'special';
        }

        return 'unknown';
    }

    private function deriveLocationName(
        HarvestableProviderPreset $provider,
        string $presetFile,
        string $starmapKey,
        ?string $systemKey
    ): string {
        $metadata = self::PROVIDER_METADATA[strtolower($presetFile)] ?? null;
        if ($metadata !== null) {
            return $metadata['name'];
        }

        $base = preg_replace('/^hpp_/i', '', $presetFile) ?? $presetFile;

        if ($systemKey !== null) {
            $prefix = strtolower($systemKey).'_';
            if (str_starts_with(strtolower($base), $prefix)) {
                $base = substr($base, strlen($prefix)) ?: $base;
            }
        }

        $special = match (strtolower($base)) {
            'spacederelict_general' => 'Space Derelict',
            'shipgraveyard_001' => 'Ship Graveyard',
            default => null,
        };

        if ($special !== null) {
            return $special;
        }

        return $this->humanizeKey($starmapKey);
    }

    private function toPascalCase(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! str_contains($value, '_')) {
            return ucfirst($value);
        }

        $parts = array_filter(explode('_', $value), static fn (string $part): bool => $part !== '');

        return implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
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

    private function humanizeKey(string $value): string
    {
        $spaced = preg_replace('/(?<!^)([A-Z0-9])/', ' $1', $value) ?? $value;
        $normalized = trim(str_replace('_', ' ', $spaced));

        return $normalized === '' ? $value : $normalized;
    }
}
