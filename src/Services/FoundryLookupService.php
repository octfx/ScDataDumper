<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\AmmoParams;
use Octfx\ScDataDumper\DocumentTypes\ConsumableSubtype;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGameplayPropertyDef;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityDistributionRecord;
use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingQualityLocationOverrideRecord;
use Octfx\ScDataDumper\DocumentTypes\DamageResistanceMacro;
use Octfx\ScDataDumper\DocumentTypes\Faction\Faction;
use Octfx\ScDataDumper\DocumentTypes\Faction\Faction_LEGACY;
use Octfx\ScDataDumper\DocumentTypes\Faction\FactionReputation;
use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableClusterPreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableSetup;
use Octfx\ScDataDumper\DocumentTypes\MeleeCombatConfig;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableComposition;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableElement;
use Octfx\ScDataDumper\DocumentTypes\Mining\MiningGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\MiningLaserGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\Radar\RadarContactTypeEntry;
use Octfx\ScDataDumper\DocumentTypes\RadarSystemSharedParams;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationContextUI;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationScopeParams;
use Octfx\ScDataDumper\DocumentTypes\Reputation\SReputationStandingParams;
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

    public function getMiningGlobalParamsByReference(?string $uuid): ?MiningGlobalParams
    {
        return $this->getByReference($uuid, ['/records/mining/miningglobalparams'], MiningGlobalParams::class);
    }

    public function getResourceTypeByReference(?string $uuid): ?ResourceType
    {
        return $this->getByReference($uuid, class: ResourceType::class);
    }

    public function getCraftingQualityDistributionByReference(?string $uuid): ?CraftingQualityDistributionRecord
    {
        return $this->getByReference($uuid, ['/records/crafting/qualitydistribution/'], CraftingQualityDistributionRecord::class);
    }

    public function getCraftingQualityLocationOverrideByReference(?string $uuid): ?CraftingQualityLocationOverrideRecord
    {
        return $this->getByReference(
            $uuid,
            ['/records/crafting/qualitydistribution/'],
            CraftingQualityLocationOverrideRecord::class
        );
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

    public function getHarvestablePresetByReference(?string $uuid): ?HarvestablePreset
    {
        return $this->getByReference($uuid, ['/records/harvestable/harvestablepresets/'], HarvestablePreset::class);
    }

    public function getHarvestableClusterPresetByReference(?string $uuid): ?HarvestableClusterPreset
    {
        return $this->getByReference($uuid, ['/records/harvestable/clusteringpresets/'], HarvestableClusterPreset::class);
    }

    public function getHarvestableSetupByReference(?string $uuid): ?HarvestableSetup
    {
        return $this->getByReference($uuid, ['/records/harvestable/harvestablesetups/'], HarvestableSetup::class);
    }

    public function getMineableCompositionByReference(?string $uuid): ?MineableComposition
    {
        return $this->getByReference($uuid, ['/records/mining/rockcompositionpresets/'], MineableComposition::class);
    }

    public function getMineableElementByReference(?string $uuid): ?MineableElement
    {
        return $this->getByReference($uuid, ['/records/mining/mineableelements/'], MineableElement::class);
    }

    public function getMissionLocalityByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiondata/pu_missionlocality/']);
    }

    public function getMissionOrganizationByReference(?string $uuid): ?FoundryRecord
    {
        return $this->getByReference($uuid, ['/records/missiondata/pu_organizations/']);
    }

    public function getFactionReputationByReference(?string $uuid): ?FactionReputation
    {
        return $this->getByReference($uuid, ['/records/factions/factionreputation/'], FactionReputation::class);
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

    public function getReputationStandingByReference(?string $uuid): ?SReputationStandingParams
    {
        return $this->getByReference($uuid, ['/records/reputation/standings/'], SReputationStandingParams::class);
    }

    public function getReputationScopeByReference(?string $uuid): ?SReputationScopeParams
    {
        return $this->getByReference($uuid, ['/records/reputation/scopes/'], SReputationScopeParams::class);
    }

    public function getReputationContextByReference(?string $uuid): ?SReputationContextUI
    {
        return $this->getByReference($uuid, ['/records/reputation/contexts/'], SReputationContextUI::class);
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
        $cacheKey = $this->buildDocumentCacheKey($filePath);

        if (isset($this->cache[$class][$cacheKey])) {
            /** @var T */
            return $this->cache[$class][$cacheKey];
        }

        $this->hits[$cacheKey] ??= 0;
        $this->hits[$cacheKey]++;

        $document = $this->loadDocument($filePath, $class);

        if ($this->hits[$cacheKey] > 1) {
            $this->cache[$class][$cacheKey] = $document;
        }

        return $document;
    }

    private function buildDocumentCacheKey(string $filePath): string
    {
        return sprintf(
            '%d:%s',
            $this->referenceHydrationEnabled ? 1 : 0,
            $filePath
        );
    }
}
