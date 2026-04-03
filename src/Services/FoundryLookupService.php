<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\RadarSystemSharedParams;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use RuntimeException;

final class FoundryLookupService extends BaseService
{
    private array $cache;

    public function __construct(string $scDataDir)
    {
        parent::__construct($scDataDir);

        $this->cache = [];
    }

    public function initialize(): void {}

    public function getRadarSystemParamsByReference(string $uuid): ?RadarSystemSharedParams
    {
        $path = $this->resolvePath($uuid);

        if (empty($path)) {
            return null;
        }

        return $this->load($path, RadarSystemSharedParams::class);
    }

    public function getMissionTypeByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiontype/']);
    }

    public function getMissionGiverByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiongiver/']);
    }

    public function getMissionLocalityByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiondata/pu_missionlocality/']);
    }

    public function getMissionOrganizationByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiondata/pu_organizations/']);
    }

    public function getFactionReputationByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/factions/factionreputation/']);
    }

    /**
     * Mission location templates used by the mission system for place selection (pickup/dropoff etc).
     */
    public function getMissionLocationTemplateByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiondata/pu_locations/']);
    }

    /**
     * Starmap objects represent concrete navigable places (stars, planets, stations, outposts, etc).
     */
    public function getStarMapObjectByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/starmap/pu/']);
    }

    public function getReputationStandingByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/reputation/standings/']);
    }

    public function getReputationScopeByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/reputation/scopes/']);
    }

    public function getReputationRewardByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/reputation/rewards/missionrewards_reputation/']);
    }

    private function getByReference(?string $uuid, array $pathNeedles): ?FoundryRecord
    {
        $path = $this->resolvePath($uuid);

        if ($path === null || ! $this->pathMatches($path, $pathNeedles)) {
            return null;
        }

        return $this->load($path);
    }

    private function resolvePath(?string $uuid): ?string
    {
        if (! is_string($uuid)) {
            return null;
        }

        $trimmed = trim($uuid);

        if ($trimmed === '') {
            return null;
        }

        return self::$uuidToPathMap[$trimmed] ?? self::$uuidToPathMap[strtolower($trimmed)] ?? null;
    }

    private function pathMatches(string $path, array $pathNeedles): bool
    {
        $normalizedPath = strtolower(str_replace('\\', '/', $path));

        return array_any($pathNeedles, fn($needle) => str_contains($normalizedPath, $needle));
    }

    private function load(string $filePath, ?string $class = FoundryRecord::class): RootDocument
    {
        if (empty($filePath) || ! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        if (isset($this->cache[$class][$filePath])) {
            return $this->cache[$class][$filePath];
        }

        $document = new $class;
        $document->load($filePath);
        $document->checkValidity();

        $this->cache[$class][$filePath] = $document;

        return $document;
    }
}
