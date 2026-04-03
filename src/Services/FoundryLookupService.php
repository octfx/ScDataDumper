<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\AmmoParams;
use Octfx\ScDataDumper\DocumentTypes\ConsumableSubtype;
use Octfx\ScDataDumper\DocumentTypes\CraftingGameplayPropertyDef;
use Octfx\ScDataDumper\DocumentTypes\DamageResistanceMacro;
use Octfx\ScDataDumper\DocumentTypes\Faction;
use Octfx\ScDataDumper\DocumentTypes\Faction_LEGACY;
use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\MeleeCombatConfig;
use Octfx\ScDataDumper\DocumentTypes\MiningLaserGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\Radar\RadarContactTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\RadarSystemSharedParams;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\DocumentTypes\Starmap\Jurisdiction;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapAmenityTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObjectType;

final class FoundryLookupService extends BaseService
{
    /**
     * @var array<class-string<RootDocument>, array<string, RootDocument>>
     */
    private array $cache;

    private array $hits;

    public function __construct(string $scDataDir)
    {
        parent::__construct($scDataDir);

        $this->cache = [];
        $this->hits = [];
    }

    public function initialize(): void {}

    public function getRadarSystemParamsByReference(?string $uuid): ?RadarSystemSharedParams
    {
        return $this->getByReference($uuid, class: RadarSystemSharedParams::class);
    }

    public function getRadarContactTypeByReference(?string $uuid): ?RadarContactTypeEntry
    {
        return $this->getByReference($uuid, ['foundry/records/radarsystem'], RadarContactTypeEntry::class);
    }

    public function getCraftingGameplayPropertyByReference(?string $uuid): ?CraftingGameplayPropertyDef
    {
        return $this->getByReference($uuid, class: CraftingGameplayPropertyDef::class);
    }

    public function getMeleeCombatConfigByReference(?string $uuid): ?MeleeCombatConfig
    {
        return $this->getByReference($uuid, class: MeleeCombatConfig::class);
    }

    public function getMiningLaserGlobalParamsByReference(?string $uuid): ?MiningLaserGlobalParams
    {
        return $this->getByReference($uuid, class: MiningLaserGlobalParams::class);
    }

    public function getResourceTypeByReference(?string $uuid): ?ResourceType
    {
        return $this->getByReference($uuid, class: ResourceType::class);
    }

    public function getDamageResistanceMacroByReference(?string $uuid): ?DamageResistanceMacro
    {
        return $this->getByReference($uuid, class: DamageResistanceMacro::class);

    }

    public function getAmmoParamsByReference(?string $uuid): ?AmmoParams
    {
        return $this->getByReference($uuid, class: AmmoParams::class);
    }

    public function getConsumableSubtypeByReference(?string $uuid): ?ConsumableSubtype
    {
        return $this->getByReference($uuid, class: ConsumableSubtype::class);
    }

    public function countDocumentType(string $mapKey): int
    {
        return count(self::$classToPathMap[$mapKey] ?? []);
    }

    /**
     * @return Generator<int, ResourceType, mixed, void>
     */
    public function getResourceTypes(): Generator
    {
        yield from $this->getDocumentType('ResourceType', ResourceType::class);
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T> $class
     * @return Generator<int, T, mixed, void>
     */
    public function getDocumentType(string $mapKey, string $class): Generator
    {
        foreach (self::$classToPathMap[$mapKey] ?? [] as $path) {
            yield $this->load($path, $class);
        }
    }

    public function getFactionByReference(string $uuid): ?RootDocument
    {
        $path = $this->resolvePathByReference($uuid);

        if ($path === null) {
            return null;
        }

        if ($this->pathMatches($path, ['/records/factions_legacy/'])) {
            return $this->load($path, Faction_LEGACY::class);
        }

        if ($this->pathMatches($path, ['/records/factions/'])) {
            return $this->load($path, Faction::class);
        }

        return null;
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
    public function getStarMapObjectByReference(?string $uuid): ?StarMapObject
    {
        return $this->getByReference($uuid, ['/records/starmap/pu/'], StarMapObject::class);
    }

    public function getJurisdictionByReference(?string $uuid): ?Jurisdiction
    {
        return $this->getByReference($uuid, ['/records/lawsystem/jurisdictions/'], Jurisdiction::class);
    }

    public function getStarMapObjectTypeByReference(?string $uuid): ?StarMapObjectType
    {
        return $this->getByReference($uuid, ['/records/starmap/'], StarMapObjectType::class);
    }

    public function getStarMapAmenityTypeByReference(?string $uuid): ?StarMapAmenityTypeEntry
    {
        return $this->getByReference($uuid, ['/records/starmapamenitytypes/'], StarMapAmenityTypeEntry::class);
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

    /**
     * @template T of RootDocument
     *
     * @param  ?string $uuid
     * @param  ?array<int, string> $pathNeedles
     * @param  class-string<T> $class
     * @return T|null
     */
    private function getByReference(?string $uuid, ?array $pathNeedles = null, string $class = FoundryRecord::class): ?RootDocument
    {
        $path = $this->resolvePathByReference($uuid);

        if ($path === null) {
            return null;
        }

        if (! empty($pathNeedles) && ! $this->pathMatches($path, $pathNeedles)) {
            return null;
        }

        return $this->load($path, $class);
    }

    /**
     * @template T of RootDocument
     *
     * @param  class-string<T> $class
     * @return T
     */
    private function load(string $filePath, string $class = FoundryRecord::class): RootDocument
    {
        if (isset($this->cache[$class][$filePath])) {
            /** @var T */
            return $this->cache[$class][$filePath];
        }

        $this->hits[$filePath] ??= 0;
        $this->hits[$filePath]++;

        $document = $this->loadDocument($filePath, $class);

        if ($this->hits[$filePath] > 1) {
            $this->cache[$class][$filePath] = $document;
        }

        return $document;
    }
}
