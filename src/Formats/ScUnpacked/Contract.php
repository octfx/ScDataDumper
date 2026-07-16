<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Contract\ContractEntry;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractResultBlock;
use Octfx\ScDataDumper\DocumentTypes\Contract\MissionPropertyOverride;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\AINameValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\BooleanValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\CombinedDataSetEntriesValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\EntitySpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\FloatValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\HaulingOrdersValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\IntegerValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\MissionItemValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\NPCSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\OrganizationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\RewardValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\ShipSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\StringHashValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\TimeTrialRaceValue;
use Octfx\ScDataDumper\DocumentTypes\FoundryRecord;
use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\BuildsMissionItemHaulingOrders;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\FormatsMissionBrokerEntries;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Contract extends BaseFormat
{
    use BuildsMissionItemHaulingOrders;
    use FormatsMissionBrokerEntries;

    /**
     * @param  array<string, list<array{uuid: string, title: ?string, debug_name: ?string}>>  $chainIndex
     */
    public function __construct(
        protected ContractEntry $entry,
        protected ContractHandler $handler,
        protected ContractGeneratorRecord $record,
        protected array $chainIndex = [],
    ) {
        parent::__construct($entry);
    }

    /**
     * @return list<MissionPropertyOverride>
     */
    private function getAllOverrides(): array
    {
        $raw = array_merge(
            $this->handler->getContractParamPropertyOverrides(),
            $this->getTemplatePropertyOverrides(),
            $this->entry->getPropertyOverrides(),
        );

        $flat = [];

        foreach ($raw as $override) {
            $value = $override->getValue();

            if ($value instanceof CombinedDataSetEntriesValue) {
                array_push($flat, ...$value->getProperties());
            } else {
                $flat[] = $override;
            }
        }

        return $flat;
    }

    /**
     * Load property overrides from the entry's referenced ContractTemplate.
     * These provide defaults for tokens like SingleToMultiToken / MultiToSingleToken
     * that are defined at the template level but not overridden by handler or entry.
     *
     * @return list<MissionPropertyOverride>
     */
    private function getTemplatePropertyOverrides(): array
    {
        $templateRef = $this->entry->getTemplateReference();
        if ($templateRef === null) {
            return [];
        }

        $template = ServiceFactory::getFoundryLookupService()->getContractTemplateByReference($templateRef);
        if ($template === null) {
            return [];
        }

        $results = [];
        $nodes = $template->getAll('contractProperties/MissionProperty');

        foreach ($nodes as $node) {
            $doc = MissionPropertyOverride::fromNode($node->getNode(), $this->entry->isReferenceHydrationEnabled());
            if ($doc instanceof MissionPropertyOverride) {
                $results[] = $doc;
            }
        }

        return $results;
    }

    public function toArray(): ?array
    {
        $results = $this->entry->getResults();

        $meta = $this->buildMeta();
        $resolvedRewards = $results !== null ? $this->buildResults($results) : [];
        $brokerRewards = $this->buildBrokerRewards();

        $mergedRewards = array_merge($resolvedRewards, array_filter($brokerRewards, static fn ($v) => $v !== null));

        $rewardOverride = $this->buildRewardOverride();

        if ($rewardOverride !== null && ! isset($mergedRewards['fixed_reward'])) {
            $mergedRewards['fixed_reward'] = $rewardOverride;
        }

        $resolvedReputation = $this->buildFaction();

        $titleKey = $this->entry->getTitleKey();
        $descriptionKey = $this->entry->getDescriptionKey();
        $title = $this->translateLocalizationValue($this->entry->getTitle());
        $description = $this->translateLocalizationValue($this->entry->getDescription());
        $locationPools = $this->buildLocationPools();
        $haulingOrders = $this->buildHaulingOrders();
        $itemCounts = $this->buildMissionItemCounts();
        $missionType = $this->buildMissionType();
        $missionGiver = $meta['mission_giver'] ?? null;
        $missionTokens = $this->buildMissionTokens($title, $description, $locationPools);

        $mbe = $this->getMissionBrokerEntry();

        if ($mbe !== null) {
            if ($title === '') {
                $title = $this->translateMbeText($mbe->getTitle());
            }

            if ($description === '') {
                $description = $this->translateMbeText($mbe->getDescription());
            }

            if ($titleKey === null) {
                $titleKey = $mbe->getTitleKey();
            }

            if ($descriptionKey === null) {
                $descriptionKey = $mbe->getDescriptionKey();
            }

            if ($locationPools === []) {
                $locationPools = $this->buildMbeLocationPools($mbe);
            }

            if ($haulingOrders === []) {
                $haulingOrders = $this->buildMbeHaulingOrders($mbe);
            }

            if ($missionType === null) {
                $missionType = $this->resolveMbeMissionTypeInfo($mbe->getMissionTypeUuid());
            }

            if ($missionGiver === null || $missionGiver === '') {
                $missionGiver = $this->resolveMbeMissionGiver($mbe);
            }

            if ($missionTokens === null) {
                $missionTokens = $this->buildMbeMissionTokens($mbe, $title, $description, $locationPools);
            }
        }

        // Fallback for orders on the template's ObjectiveHandler_Hauling objective.
        if ($haulingOrders === []) {
            $haulingOrders = $this->buildTemplateObjectiveHaulingOrders();
        }

        if ($itemCounts !== null) {
            $haulingOrders = $this->enrichMissionItemHaulingOrdersFromItemCounts($haulingOrders, $itemCounts);

            if ($haulingOrders === []) {
                $haulingOrders = $this->buildMissionItemHaulingOrdersFromItemCounts($itemCounts);
            }
        }

        $displayTitle = $this->buildDisplayTextFromMissionTokens($title, $missionTokens);
        $displayDescription = $this->buildDisplayTextFromMissionTokens($description, $missionTokens);
        $missionTokens = $this->resolveTokenValueReferences($missionTokens ?? []);

        return $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $this->entry->getId(),
            'debug_name' => $this->entry->getDebugName(),
            'type' => $this->handler->getHandlerType(),
            'mission_type' => $missionType,
            'mission_giver' => $missionGiver,
            'title' => $title,
            'title_key' => $titleKey,
            'display_title' => $displayTitle,
            'description' => $description,
            'description_key' => $descriptionKey,
            'display_description' => $displayDescription,
            'location_pools' => $locationPools,
            'mission_tokens' => $missionTokens,
            ...$mergedRewards,
            'hauling_orders' => $haulingOrders,
            ...$this->buildRequirements(),
            ...$this->buildProperties(),
            'generator_class' => $meta['generator_class'] ?? null,
            'not_for_release' => $meta['not_for_release'] ?? null,
            'work_in_progress' => $meta['work_in_progress'] ?? null,
            'lifetime' => $this->buildLifetime(),
            'combat' => $combat = $this->buildCombat(),
            'combat_summary' => $this->buildCombatSummary($combat),
            'entity_spawns' => $this->buildEntitySpawns(),
            'difficulty' => $this->buildDifficulty($results),
            'faction' => $resolvedReputation['faction_reputation'] ?? null,
            'reputation_scope' => $resolvedReputation['reputation_scope']['scope_name'] ?? null,
            'time_trial_splits' => $this->buildTimeTrialSplits(),
            'property_overrides' => $this->buildPropertyOverrides(),
            'rental_ship_modifiers' => $this->buildRentShipModifiers(),
            'contract_plugins' => $this->buildContractPlugins(),
            'objective_tokens' => $this->buildObjectiveTokens(),
            'npc_names' => $this->buildNPCNames(),
            'item_counts' => $itemCounts,
        ]));
    }

    private function buildLocationPools(): array
    {
        $pools = [];
        $tagService = ServiceFactory::getTagDatabaseService();
        $resolver = ServiceFactory::getContractLocationResolver();

        $overrideSources = [
            $this->entry->getPropertyOverrides(),
        ];

        if ($this->hasLocationOverrides($this->entry->getPropertyOverrides()) === false) {
            $overrideSources[] = $this->handler->getContractParamPropertyOverrides();
        }

        foreach ($overrideSources as $overrides) {
            foreach ($overrides as $override) {
                $value = $override->getValue();

                if (! ($value instanceof LocationValue || $value instanceof LocationsValue)) {
                    continue;
                }

                $key = $override->getMissionVariableName() ?? $override->getExtendedTextToken() ?? 'default';

                $terms = $value->getTagSearchTerms();

                $resourceTags = $value instanceof LocationValue ? $value->getResourceTags() : [];

                $resolvedLocations = array_map(
                    static fn (array $loc): array => [
                        'uuid' => $loc['uuid'],
                        'location_template_uuid' => $loc['location_template_uuid'],
                        'name' => $loc['name'],
                        'starmap_uuid' => $loc['starmap_uuid'] ?? null,
                    ],
                    $resolver->resolveLocations($terms, $resourceTags),
                );

                $pool = [
                    'purpose' => $override->getExtendedTextToken(),
                    'resolved_locations' => $resolvedLocations,
                ];

                if ($pool['purpose'] === 'location') {
                    $pool['purpose'] = 'Location';
                }

                if ($value instanceof LocationsValue) {
                    $pool['min_locations'] = $value->getMinLocationsToFind();
                    $pool['max_locations'] = $value->getMaxLocationsToFind();
                    $pool['fail_if_min_not_found'] = $value->getFailIfMinAmountNotFound() ?: null;
                }

                if ($resourceTags !== []) {
                    $pool['resource_tags'] = $tagService->resolveUuidsToNameObjects($resourceTags);
                }

                $pools[$key] = $pool;
            }
        }

        return $pools;
    }

    private function hasLocationOverrides(array $overrides): bool
    {
        foreach ($overrides as $override) {
            $value = $override->getValue();

            if ($value instanceof LocationValue || $value instanceof LocationsValue) {
                return true;
            }
        }

        return false;
    }

    private function buildRequirements(): array
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $tagService = ServiceFactory::getTagDatabaseService();

        $standingReqs = $this->buildStandingRequirements($lookup);
        $tagPrerequisites = $this->buildTagPrerequisites($tagService);
        [$crimeStat, $reputationPrereq] = $this->buildHandlerPrerequisites($lookup);
        $localities = $this->buildLocalityPrerequisites($lookup);
        $requiredLocations = $this->buildLocationPrerequisites($lookup);

        return $this->removeNullValuesPreservingEmptyArrays([
            'min_standing' => $standingReqs['min'],
            'max_standing' => $standingReqs['max'],
            'rank_index' => $standingReqs['rank_index'],
            'crime_stat' => $crimeStat,
            'reputation_prerequisite' => $reputationPrereq,
            'prerequisites' => $tagPrerequisites,
            'availability_locations' => $localities,
            'required_locations' => $requiredLocations,
        ]);
    }

    private function resolveStanding(FoundryLookupService $lookup, ?string $ref): ?array
    {
        if ($ref === null) {
            return null;
        }

        $standing = $lookup->getReputationStandingByReference($ref);

        return $standing !== null ? [
            'name' => $this->translateLocalizationValue($standing->getDisplayName() ?? $standing->getName()),
            'min_reputation' => $standing->getMinReputation(),
        ] : null;
    }

    /**
     * @return array{min: ?array, max: ?array}
     */
    private function buildStandingRequirements(FoundryLookupService $lookup): array
    {
        $minRef = $this->entry->getMinStandingReference();
        $maxRef = $this->entry->getMaxStandingReference();

        $min = $this->resolveStanding($lookup, $minRef);
        $max = $this->resolveStanding($lookup, $maxRef);

        $rankIndex = null;
        $scopeUuid = $this->handler->getReputationScopeReference();

        if ($scopeUuid !== null && $minRef !== null) {
            $scope = $lookup->getReputationScopeByReference($scopeUuid);

            if ($scope !== null) {
                $refs = $scope->getStandingReferences();
                $position = array_search(strtolower($minRef), array_map('strtolower', $refs), true);

                if ($position !== false) {
                    $firstStanding = $lookup->getReputationStandingByReference($refs[0]);
                    $offset = ($firstStanding !== null && ($firstStanding->getMinReputation() ?? 0) < 0) ? 1 : 0;
                    $adjusted = $position - $offset;
                    $rankIndex = $adjusted >= 0 ? $adjusted : null;
                }
            }
        }

        return [
            'min' => $min,
            'max' => $max,
            'rank_index' => $rankIndex,
        ];
    }

    private function buildTagPrerequisites($tagService): array
    {
        // Chain gates can live on the handler (defaultAvailability, shared by every
        // contract under it) and/or the entry (additionalPrerequisites, per-contract).
        // Both must be satisfied, so they accumulate; identical handler+entry blocks
        // (an entry mirroring its handler's default) collapse to avoid duplication.
        $rawPrereqs = array_merge(
            $this->handler->getCompletedContractTagPrerequisites(),
            $this->entry->getCompletedContractTagPrerequisites(),
        );

        $prerequisites = [];
        $seen = [];

        foreach ($rawPrereqs as $prereq) {
            $signature = $this->completedContractTagSignature($prereq);

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;

            $requiredMissions = [];

            foreach ($prereq['requiredTags'] as $tag) {
                $awardedBy = $this->chainIndex['awarded'][$tag] ?? [];

                foreach ($awardedBy as $entry) {
                    $requiredMissions[] = $entry;
                }
            }

            $prerequisites[] = [
                'required_count' => $prereq['requiredCountValue'],
                'excluded_count' => $prereq['excludedCountValue'],
                'required_tags' => $tagService->resolveUuidsToNameObjects($prereq['requiredTags']),
                'excluded_tags' => $tagService->resolveUuidsToNameObjects($prereq['excludedTags']),
                'required_missions' => $this->deduplicateChainEntries($requiredMissions),
            ];
        }

        return $prerequisites;
    }

    /**
     * @param  array{requiredCountValue: int, excludedCountValue: int, requiredTags: list<string>, excludedTags: list<string>}  $prereq
     */
    private function completedContractTagSignature(array $prereq): string
    {
        $norm = static fn (array $tags): string => implode('|', array_map('strtolower', $tags));

        return $prereq['requiredCountValue'].':'.$prereq['excludedCountValue'].':'.$norm($prereq['requiredTags']).':'.$norm($prereq['excludedTags']);
    }

    /**
     * @return array{0: ?array, 1: ?array}
     */
    private function buildHandlerPrerequisites(FoundryLookupService $lookup): array
    {
        $crimeStat = null;
        $reputationPrereq = null;

        foreach ($this->handler->getDefaultPrerequisites() as $prereq) {
            if ($prereq['minCrimeStat'] !== null || $prereq['maxCrimeStat'] !== null) {
                $crimeStat = [
                    'min' => $prereq['minCrimeStat'],
                    'max' => $prereq['maxCrimeStat'],
                ];
            }

            $rep = $this->buildReputationPrerequisite($lookup, $prereq);

            if ($rep !== null) {
                $reputationPrereq = $rep;
            }
        }

        // TheCollector/Wikelo shape
        foreach ($this->entry->getReputationPrerequisites() as $prereq) {
            $rep = $this->buildReputationPrerequisite($lookup, $prereq);

            if ($rep !== null) {
                $reputationPrereq = $rep;
            }
        }

        return [$crimeStat, $reputationPrereq];
    }

    /**
     * @param  array{factionReputation: ?string, scope: ?string, minStanding: ?string, maxStanding: ?string}  $prereq
     * @return array{faction: ?string, faction_uuid: ?string, scope: ?string, scope_uuid: ?string, min_standing: ?array, max_standing: ?array}|null
     */
    private function buildReputationPrerequisite(FoundryLookupService $lookup, array $prereq): ?array
    {
        if ($prereq['factionReputation'] === null) {
            return null;
        }

        $factionData = $this->resolveFactionReputationSummary(
            $lookup,
            ServiceFactory::getLocalizationService(),
            $prereq['factionReputation'],
        );

        $scopeName = null;
        if ($prereq['scope'] !== null) {
            $scope = $lookup->getReputationScopeByReference($prereq['scope']);
            $scopeName = $scope?->getScopeName();
        }

        return [
            'faction' => $factionData['name'],
            'faction_uuid' => $factionData['uuid'],
            'scope' => $scopeName,
            'scope_uuid' => $prereq['scope'],
            'min_standing' => $this->resolveStanding($lookup, $prereq['minStanding']),
            'max_standing' => $this->resolveStanding($lookup, $prereq['maxStanding']),
        ];
    }

    private function buildLocalityPrerequisites(FoundryLookupService $lookup): array
    {
        $localityUuids = [];

        foreach ($this->entry->getLocalityPrerequisites() as $prereq) {
            if ($prereq['localityAvailable'] !== null) {
                $localityUuids[$prereq['localityAvailable']] = true;
            }
        }

        // SubContract variants gate where a location-specific overlay is offered;
        // those localities are additional places the contract appears, merged into the same set.
        foreach ($this->entry->getSubContractLocalityPrerequisites() as $prereq) {
            if ($prereq['localityAvailable'] !== null) {
                $localityUuids[$prereq['localityAvailable']] = true;
            }
        }

        foreach ($this->handler->getDefaultPrerequisites() as $prereq) {
            if ($prereq['type'] === 'ContractPrerequisite_Locality' && $prereq['localityAvailable'] !== null) {
                $localityUuids[$prereq['localityAvailable']] = true;
            }
        }

        $localities = [];
        foreach (array_keys($localityUuids) as $uuid) {
            $locality = $lookup->getMissionLocalityByReference($uuid);

            if ($locality === null) {
                continue;
            }

            $resolvedLocations = [];

            foreach ($locality->getAvailableLocationReferences() as $locationUuid) {
                $smo = $lookup->getStarMapObjectByReference($locationUuid);
                $resolvedLocations[] = [
                    'uuid' => $locationUuid,
                    'name' => $smo !== null
                        ? $this->translateLocalizationValue($smo->getName())
                        : null,
                ];
            }

            $localities[] = [
                'name' => $locality->getClassName(),
                'resolved_locations' => $resolvedLocations,
            ];
        }

        return $localities;
    }

    /**
     * Specific POIs/systems the player must be at (ContractPrerequisite_Location)
     */
    private function buildLocationPrerequisites(FoundryLookupService $lookup): array
    {
        $uuids = [];

        foreach ($this->entry->getLocationPrerequisites() as $prereq) {
            if ($prereq['locationAvailable'] !== null) {
                $uuids[$prereq['locationAvailable']] = true;
            }
        }

        foreach ($this->handler->getDefaultPrerequisites() as $prereq) {
            if ($prereq['type'] === 'ContractPrerequisite_Location' && $prereq['locationAvailable'] !== null) {
                $uuids[$prereq['locationAvailable']] = true;
            }
        }

        $locations = [];

        foreach (array_keys($uuids) as $uuid) {
            $smo = $lookup->getStarMapObjectByReference($uuid);
            $locations[] = [
                'uuid' => $uuid,
                'name' => $smo !== null
                    ? $this->translateLocalizationValue($smo->getName())
                    : null,
            ];
        }

        return $locations;
    }

    private function buildProperties(): array
    {
        return [
            'shareable' => $this->entry->isShareable() ?? $this->handler->isShareable() ?? false,
            'illegal' => $this->entry->isIllegal() ?? $this->handler->isIllegal() ?? false,
            'once_only' => ($this->entry->isOnceOnly() ?? $this->handler->isOnceOnly() ?? false) || $this->entry->excludesOwnCompletionTag(),
            'max_players_per_instance' => $this->entry->getMaxPlayersPerInstance() ?? $this->handler->getMaxPlayersPerInstance(),
            'reaccept_after_abandoning' => $this->entry->canReacceptAfterAbandoning() ?? $this->handler->canReacceptAfterAbandoning() ?? false,
            'reaccept_after_failing' => $this->entry->canReacceptAfterFailing() ?? $this->handler->canReacceptAfterFailing() ?? false,
            'cooldown' => [
                'abandoned_seconds' => $this->entry->getAbandonedCooldownTime() ?? $this->handler->getAbandonedCooldownTime(),
                'abandoned_variation_seconds' => $this->entry->getAbandonedCooldownTimeVariation() ?? $this->handler->getAbandonedCooldownTimeVariation(),
                'personal_seconds' => $this->entry->getPersonalCooldownTime() ?? $this->handler->getPersonalCooldownTime(),
                'personal_variation_seconds' => $this->entry->getPersonalCooldownTimeVariation() ?? $this->handler->getPersonalCooldownTimeVariation(),
            ],
            'available_in_prison' => $this->handler->isAvailableInPrison(),
            'fail_if_became_criminal' => $this->entry->failIfBecameCriminal() ?? $this->handler->failIfBecameCriminal() ?? false,
            'escaped_convicts' => $this->handler->hasEscapedConvicts() ?: null,
            'hidden_in_mobiglas' => $this->entry->isHideInMobiGlas() ?? $this->handler->isHideInMobiGlas() ?? false ?: null,
            'notify_on_available' => ($this->entry->isNotifyOnAvailable() ?? $this->handler->notifyOnAvailable()) ?: null,
        ];
    }

    private function buildMeta(): array
    {
        $contractor = $this->entry->getContractor() ?? $this->handler->getContractor();
        $missionGiver = $contractor !== null
            ? $this->translateLocalizationValue($contractor)
            : null;

        if ($missionGiver === null || $missionGiver === '') {
            $missionGiver = $this->resolveOrgFromOverrides($this->getAllOverrides());
        }

        $missionGiver = $this->applyManufacturerNameOverride($missionGiver);

        return $this->removeNullValuesPreservingEmptyArrays([
            'mission_giver' => $missionGiver,
            'generator_class' => $this->record->getClassName(),
            'not_for_release' => $this->entry->isNotForRelease(),
            'work_in_progress' => $this->entry->isWorkInProgress(),
        ]);
    }

    private function resolveOrgFromOverrides(array $overrides): ?string
    {
        foreach ($overrides as $override) {
            if ($override->getExtendedTextToken() === 'Contractor' || $override->getMissionVariableName() === 'Contractor') {
                $value = $override->getValue();

                if ($value === null) {
                    continue;
                }

                $typeName = $override->getValueTypeName();

                if ($typeName === 'Organization') {
                    $orgs = method_exists($value, 'getOrganizations') ? $value->getOrganizations() : [];

                    if ($orgs !== []) {
                        return $this->resolveOrgName($orgs[0]);
                    }
                }
            }
        }

        return null;
    }

    private function resolveOrgName(string $orgUuid): ?string
    {
        $org = ServiceFactory::getFoundryLookupService()->getMissionOrganizationByReference($orgUuid);

        if ($org === null) {
            return $orgUuid;
        }

        $displayName = $org->getStringValue('stringVariants/MissionStringVariant[last()]/@string');

        if ($displayName !== null && $displayName !== '') {
            $translated = ServiceFactory::getLocalizationService()->translateValue($displayName, true);

            if ($translated !== '') {
                return $translated;
            }
        }

        return $org->getClassName();
    }

    private function deduplicateChainEntries(array $entries): array
    {
        $seen = [];
        $result = [];

        foreach ($entries as $entry) {
            $key = $entry['uuid'] ?? $entry['debug_name'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $entry;
        }

        return $result;
    }

    private function buildLifetime(): array
    {
        return $this->removeNullValuesPreservingEmptyArrays([
            'instance_life_time' => $this->entry->getInstanceLifeTime(),
            'instance_life_time_variation' => $this->entry->getInstanceLifeTimeVariation(),
            'respawn_time' => $this->entry->getRespawnTime(),
            'respawn_time_variation' => $this->entry->getRespawnTimeVariation(),
            'max_instances' => $this->entry->getMaxInstances(),
            'max_instances_per_player' => $this->entry->getMaxInstancesPerPlayer(),
        ]);
    }

    /**
     * @param  list<class-string<RootDocument>>  $valueTypes
     */
    private function collectSpawnsForTypes(array $valueTypes): array
    {
        $spawns = [];

        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();

            foreach ($valueTypes as $type) {
                if ($value instanceof $type) {
                    array_push($spawns, ...$value->toArray($override->getMissionVariableName() ?? ''));
                    break;
                }
            }
        }

        return $spawns;
    }

    private function buildCombat(): array
    {
        return $this->collectSpawnsForTypes([ShipSpawnDescriptionsValue::class, NPCSpawnDescriptionsValue::class]);
    }

    private function buildEntitySpawns(): array
    {
        return $this->collectSpawnsForTypes([EntitySpawnDescriptionsValue::class]);
    }

    private function buildCombatSummary(array $combatRows): ?array
    {
        if ($combatRows === []) {
            return null;
        }

        $groups = [];

        foreach ($combatRows as $row) {
            $groupName = $row['group_name'] ?? 'default';
            $amount = $row['concurrent_amount'] ?? 1;

            if (! isset($groups[$groupName])) {
                $groups[$groupName] = ['min' => $amount, 'max' => $amount];
            } else {
                $groups[$groupName]['min'] = min($groups[$groupName]['min'], $amount);
                $groups[$groupName]['max'] = max($groups[$groupName]['max'], $amount);
            }
        }

        $totalMin = 0;
        $totalMax = 0;
        $byGroup = [];

        foreach ($groups as $name => $range) {
            $totalMin += $range['min'];
            $totalMax += $range['max'];
            $byGroup[] = ['group_name' => $name, 'min' => $range['min'], 'max' => $range['max']];
        }

        return [
            'total' => ['min' => $totalMin, 'max' => $totalMax],
            'by_group' => $byGroup,
        ];
    }

    private function buildDifficulty(?ContractResultBlock $results): ?array
    {
        if ($results === null) {
            return null;
        }

        $diff = $results->getDifficulty();
        $profileUuid = $diff['difficultyProfile'];

        $profileName = null;

        if ($profileUuid !== null) {
            $profile = ServiceFactory::getFoundryLookupService()
                ->getContractDifficultyProfileByReference($profileUuid);
            $profileName = $profile?->getClassName();
        }

        return [
            'mechanical_skill' => $diff['mechanicalSkill'],
            'mental_load' => $diff['mentalLoad'],
            'risk_of_loss' => $diff['riskOfLoss'],
            'game_knowledge' => $diff['gameKnowledge'],
            'difficulty_profile' => $profileName,
            'difficulty_profile_uuid' => $profileUuid,
        ];
    }

    private function buildHaulingOrders(): array
    {
        $handlerProperties = $this->getAllOverrides();
        $baseOffset = $this->computeHandlerPropertyBaseOffset($handlerProperties);
        $routing = $this->buildHaulingOrderRoutingMap($handlerProperties);

        $orders = [];

        foreach ($handlerProperties as $override) {
            $value = $override->getValue();

            if (! ($value instanceof HaulingOrdersValue)) {
                continue;
            }

            $variableName = $override->getMissionVariableName();
            $binding = $variableName !== null ? ($routing[$variableName] ?? null) : null;

            foreach ($value->toArray() as $order) {
                foreach ($this->resolveMissionItemRef($order, $handlerProperties, $baseOffset) as $resolved) {
                    if ($binding !== null) {
                        if ($binding['pickup'] !== null) {
                            $resolved['pickup_pool'] = $binding['pickup'];
                        }

                        if ($binding['dropoff'] !== null) {
                            $resolved['dropoff_pool'] = $binding['dropoff'];
                        }
                    }
                    $orders[] = $resolved;
                }
            }
        }

        return $orders;
    }

    /**
     * Build a {cargoVariable => {pickup, dropoff}} map from the template's HaulingOrder_Property routing edges.
     * Each edge binds a cargo override (by missionVariableName) to the location pools it hauls between
     *
     * The ObjectiveProperty_Referenced[hex] refs are absolute positions,
     * but routing edges reference a non-contiguous subset that does not start at the list head,
     * so the base cannot be taken from the min ref.
     *
     * @param  list<MissionPropertyOverride>  $overrides
     * @return array<string, array{pickup: ?string, dropoff: ?string}>
     */
    private function buildHaulingOrderRoutingMap(array $overrides): array
    {
        $templateRef = $this->entry->getTemplateReference();
        if ($templateRef === null) {
            return [];
        }

        $template = ServiceFactory::getFoundryLookupService()->getContractTemplateByReference($templateRef);
        if ($template === null) {
            return [];
        }

        $routes = $template->getAll('objectiveTokens/ObjectiveToken/objectiveHandler/ObjectiveHandler_Hauling/haulingOrders/HaulingOrder_Property');
        if ($routes === []) {
            return [];
        }

        $cargoVars = [];
        foreach ($overrides as $override) {
            $name = $override->getMissionVariableName();

            if ($name !== null && $override->getValue() instanceof HaulingOrdersValue) {
                $cargoVars[$name] = true;
            }
        }

        $map = [];
        foreach ($routes as $route) {
            $edge = $this->decodeRoutingEdge($route, $cargoVars);

            if ($edge !== null) {
                $map[$edge['cargo']] = ['pickup' => $edge['pickup'], 'dropoff' => $edge['dropoff']];
            }
        }

        return $map;
    }

    /**
     * Decode one HaulingOrder_Property edge's three ObjectiveProperty refs to variable names.
     * Returns null when the cargo ref cannot be anchored to a HaulingOrdersValue-backed variable (e.g. the variable is empty, or the contract carries no such override).
     *
     * @param  array<string, bool>  $cargoVars
     * @return ?array{cargo: string, pickup: ?string, dropoff: ?string}
     */
    private function decodeRoutingEdge(Element $route, array $cargoVars): ?array
    {
        $ops = $route->getAll('ancestor::ObjectiveToken/properties/ObjectiveProperty_Referenced');
        if ($ops === []) {
            return null;
        }
        $propCount = count($ops);

        $cargoHex = $this->extractObjectiveRefHex($route->get('haulingOrdersProperty@value'));
        $pickupHex = $this->extractObjectiveRefHex($route->get('pickUpLocation@value'));
        $dropoffHex = $this->extractObjectiveRefHex($route->get('dropOffLocation@value'));

        if ($cargoHex === null) {
            return null;
        }

        foreach ($ops as $i => $iValue) {
            $cargoName = $iValue->get('@missionVariableName');

            if ($cargoName === null || ! isset($cargoVars[$cargoName])) {
                continue;
            }

            $base = $cargoHex - $i;

            if (! $this->objectiveRefValid($cargoHex, $base, $propCount)) {
                continue;
            }

            if (($pickupHex !== null && ! $this->objectiveRefLandsOnNonCargo($pickupHex, $base, $ops, $cargoVars))
                || ($dropoffHex !== null && ! $this->objectiveRefLandsOnNonCargo($dropoffHex, $base, $ops, $cargoVars))) {
                continue;
            }

            return [
                'cargo' => $cargoName,
                'pickup' => $pickupHex !== null ? $ops[$pickupHex - $base]->get('@missionVariableName') : null,
                'dropoff' => $dropoffHex !== null ? $ops[$dropoffHex - $base]->get('@missionVariableName') : null,
            ];
        }

        return null;
    }

    private function extractObjectiveRefHex(?string $ref): ?int
    {
        if ($ref === null || ! preg_match('/ObjectiveProperty_Referenced\[([0-9A-Fa-f]+)\]/', $ref, $m)) {
            return null;
        }

        return hexdec($m[1]);
    }

    private function objectiveRefValid(int $hex, int $base, int $count): bool
    {
        return $hex - $base >= 0 && $hex - $base < $count;
    }

    /**
     * @param  list<Element>  $ops
     * @param  array<string, bool>  $cargoVars
     */
    private function objectiveRefLandsOnNonCargo(int $hex, int $base, array $ops, array $cargoVars): bool
    {
        if (! $this->objectiveRefValid($hex, $base, count($ops))) {
            return false;
        }
        $name = $ops[$hex - $base]->get('@missionVariableName');

        return $name !== null && ! isset($cargoVars[$name]);
    }

    /**
     * Orders declared on the template's ObjectiveHandler_Hauling objective.
     * buildHaulingOrders() only covers the property-override shape, so this is the fallback for missions
     * (e.g. TheCollector_Vehicle_Polaris) that use the objective-token shape instead.
     *
     * @return list<array{kind: string, uuid: ?string, name: ?string, min_amount: int, max_amount: int, max_container_size: int, min_scu: int, max_scu: int, items?: list<array{uuid: string, name: ?string}>}>
     */
    private function buildTemplateObjectiveHaulingOrders(): array
    {
        $templateRef = $this->entry->getTemplateReference();
        if ($templateRef === null) {
            return [];
        }

        $template = ServiceFactory::getFoundryLookupService()->getContractTemplateByReference($templateRef);
        if ($template === null) {
            return [];
        }

        $nodes = $template->getAll('objectiveTokens/ObjectiveToken/objectiveHandler/ObjectiveHandler_Hauling/haulingOrders/*');
        if ($nodes === []) {
            return [];
        }

        $itemService = ServiceFactory::getItemService();
        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();
        $overrides = $this->getAllOverrides();

        $orders = [];

        foreach ($nodes as $node) {
            $type = $node->nodeName;

            // HaulingOrder_Property and HaulingOrder_DropOff are indirect refs whose real cargo
            // lives in a hauling-content property override already emitted by buildHaulingOrders();
            // reaching here means the variable is empty, so skip them.
            if ($type === 'HaulingOrder_Property' || $type === 'HaulingOrder_DropOff') {
                continue;
            }

            $kind = match ($type) {
                'HaulingOrder_EntityClass' => 'Entity',
                'HaulingOrder_EntityClasses' => 'Entities',
                'HaulingOrder_Resource', 'HaulingOrder_ResourceUnlimitedDropOff' => 'Resource',
                'HaulingOrder_MissionItem' => 'MissionItem',
                'HaulingOrder_MissionItemDropOff' => 'MissionItemDropOff',
                default => str_starts_with($type, 'HaulingOrder_') ? substr($type, strlen('HaulingOrder_')) : $type,
            };

            $order = $this->buildTemplateObjectiveOrder(
                $node,
                $kind,
                $itemService,
                $lookup,
                $localization,
                $overrides,
            );

            foreach ($order as $entry) {
                $orders[] = $entry;
            }
        }

        return $orders;
    }

    /**
     * Resolve a single template-objective hauling order to the same shape HaulingOrdersValue emits.
     * Returns [] when the order carries no resolvable cargo (e.g. a MissionItem ref whose target variable is empty)
     * so the caller drops it instead of emitting a hollow placeholder.
     * A HaulingOrder_MissionItem resolving to multiple concrete items fans out to one row each
     * (matching the override path's resolveMissionItemRef).
     *
     * @param  list<MissionPropertyOverride>  $overrides
     * @return list<array{kind: string, uuid: ?string, name: ?string, min_amount: int, max_amount: int, max_container_size: int, min_scu: int, max_scu: int, items?: list<array{uuid: string, name: ?string}>}>
     */
    private function buildTemplateObjectiveOrder(
        Element $node,
        string $kind,
        ItemService $itemService,
        FoundryLookupService $lookup,
        LocalizationService $localization,
        array $overrides,
    ): array {
        $uuid = null;
        $name = null;
        $items = null;
        $multiItems = null;
        $tagTerms = null;
        $dropOffTargetTypes = null;

        if ($kind === 'Entity') {
            $entityClass = $node->get('@entityClass');

            if (is_string($entityClass)) {
                $uuid = $entityClass;
                $name = $itemService->getByReference($entityClass)?->getDisplayName();
            }
        } elseif ($kind === 'Entities') {
            $entityClasses = $node->get('@haulingEntityClasses');

            if (is_string($entityClasses)) {
                $uuid = $entityClasses;
                $record = $lookup->getHaulingEntityClassesByReference($entityClasses);
                $displayName = $record?->getStringValue('@orderDisplayName');
                $name = $displayName !== null ? $localization->translateValue($displayName, true) : $record?->getClassName();
                $items = $this->resolveEntityClassItems($record, $itemService);
            }
        } elseif ($kind === 'Resource') {
            $resource = $node->get('@resource');

            if (is_string($resource)) {
                $uuid = $resource;
                $resourceRecord = $lookup->getResourceTypeByReference($resource);
                $name = $resourceRecord !== null
                    ? $localization->translateValue($resourceRecord->getDisplayName(), true)
                    : null;
            }
        } elseif ($kind === 'MissionItemDropOff') {
            // Drop-off mechanism for mission items like freight elevators, kiosks, etc.
            $dropOffTargets = [];

            foreach ($node->getAll('dropOffTargetTypes/tags/Reference@value') as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $dropOffTargets[] = $tag;
                }
            }

            $dropOffTargetTypes = $dropOffTargets !== []
                ? ServiceFactory::getTagDatabaseService()->resolveUuidsToNameObjects($dropOffTargets)
                : null;
        } elseif ($kind === 'MissionItem') {
            // <item value="ObjectiveProperty_Referenced[hex]"/> -> template variable -> entry override.
            $resolved = $this->resolveTemplateMissionItemOrder($node, $overrides, $itemService, $lookup);
            if ($resolved === []) {
                return [];
            }

            if (isset($resolved['items'])) {
                if (count($resolved['items']) === 1) {
                    $uuid = $resolved['items'][0]['uuid'];
                    $name = $resolved['items'][0]['name'];
                } else {
                    $multiItems = $resolved['items'];
                }
            } elseif (isset($resolved['tag_terms'])) {
                // No concrete item UUID; carry the tag terms so the order still signals
                // "haul 1 item matching these tags" instead of looking empty.
                $tagTerms = $resolved['tag_terms'];
            }
        }

        $baseOrder = [
            'kind' => $kind,
            'uuid' => $uuid,
            'name' => $name,
            'min_amount' => (int) ($node->get('@minAmount') ?? 0),
            'max_amount' => (int) ($node->get('@maxAmount') ?? 0),
            'max_container_size' => (int) ($node->get('@maxContainerSize') ?? -1),
            'min_scu' => (int) ($node->get('@minSCU') ?? 0),
            'max_scu' => (int) ($node->get('@maxSCU') ?? 0),
        ];

        if ($items !== null) {
            $baseOrder['items'] = $items;
        }

        if ($tagTerms !== null) {
            $baseOrder['tag_search_terms'] = $tagTerms;
        }

        if ($dropOffTargetTypes !== null) {
            $baseOrder['drop_off_target_types'] = $dropOffTargetTypes;
            $baseOrder['delivery_order_input'] = $node->get('deliveryOrderInput@value');
        }

        if ($multiItems === null) {
            return [$baseOrder];
        }

        // Fan out one row per resolved concrete item (matches the override path).
        $results = [];

        foreach ($multiItems as $item) {
            $row = $baseOrder;
            $row['uuid'] = $item['uuid'];
            $row['name'] = $item['name'];
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Resolve a HaulingOrder_MissionItem's <item> ref through the template's ObjectiveProperty list
     * to a missionVariableName, then look that variable up in the merged overrides. Concrete
     * specificItems resolve to entity UUIDs; otherwise tag search terms are returned so callers
     * can still describe what to haul. Mirrors resolveMissionItemRef + buildMissionItemCounts.
     *
     * @param  list<MissionPropertyOverride>  $overrides
     * @return array{items?: list<array{uuid: string, name: ?string}>, tag_terms?: list<array{positive_tags?: list<array{uuid: string, name: ?string}>, negative_tags?: list<array{uuid: string, name: ?string}>}>}
     */
    private function resolveTemplateMissionItemOrder(
        Element $node,
        array $overrides,
        ItemService $itemService,
        FoundryLookupService $lookup,
    ): array {
        $itemRef = $node->get('item@value');
        if (! is_string($itemRef) || ! preg_match('/ObjectiveProperty_Referenced\[([0-9A-Fa-f]+)\]/', $itemRef, $m)) {
            return [];
        }

        // Scope the property table to the hauling node's enclosing ObjectiveToken.
        // The ObjectiveProperty_Referenced[hex] indices encode positions relative to THAT
        // token's property list, not the template-wide concatenation. Reading from the
        // template root silently crosses token boundaries on multi-objective templates
        // (e.g. eliminateall_courier: EliminateAll token has 12 properties, Courier token
        // has the hauling node) and lands on the wrong missionVariableName.
        $ops = $node->getAll('ancestor::ObjectiveToken/properties/ObjectiveProperty_Referenced');
        if ($ops === []) {
            return [];
        }

        // Collect every ObjectiveProperty_Referenced[hex] ref in this node so the base
        // offset can be validated self-consistently (see below).
        $nodeHexes = [];

        foreach ($node->children() as $child) {
            $val = $child->get('@value');

            if (is_string($val) && preg_match('/ObjectiveProperty_Referenced\[([0-9A-Fa-f]+)\]/', $val, $rm)) {
                $nodeHexes[] = hexdec($rm[1]);
            }
        }

        if ($nodeHexes === []) {
            return [];
        }

        // Decode the ObjectiveProperty index: base = min ref hex, assuming the node
        // references the token's first property (the common hauling shape: pickup at
        // position 0, dropoff at 1, item at 2+). Unlike computeHandlerPropertyBaseOffset()
        // we cannot type-check the resolved property (ObjectiveProperty_Referenced carries
        // only missionVariableName), so we validate structurally instead: every referenced
        // hex must resolve to a valid index in the scoped list. If the min-hex heuristic is
        // wrong the offsets go out of bounds and we fail loudly rather than reading the
        // wrong variable.
        $base = min($nodeHexes);
        $propCount = count($ops);

        foreach ($nodeHexes as $hex) {
            if ($hex - $base < 0 || $hex - $base >= $propCount) {
                return [];
            }
        }

        $idx = hexdec($m[1]) - $base;

        if ($idx < 0 || $idx >= $propCount) {
            return [];
        }

        $varName = $ops[$idx]->get('@missionVariableName');

        if (! is_string($varName)) {
            return [];
        }

        // The first matching override is not necessarily the populated one (e.g. an empty handler
        // default plus an entry-specific one), so scan all of them for one with concrete cargo.
        foreach ($overrides as $override) {
            if ($override->getMissionVariableName() !== $varName) {
                continue;
            }

            $value = $override->getValue();
            if (! ($value instanceof MissionItemValue)) {
                continue;
            }

            $specific = $value->getSpecificItems();
            if ($specific !== []) {
                $results = [];

                foreach ($specific as $itemUuid) {
                    $resolvedUuid = $lookup->resolveMissionItemEntityClass($itemUuid) ?? $itemUuid;
                    $results[] = [
                        'uuid' => $resolvedUuid,
                        'name' => $itemService->getByReference($resolvedUuid)?->getDisplayName(),
                    ];
                }

                return ['items' => $results];
            }
        }

        // No concrete items on any matching override: fall back to tag search terms.
        foreach ($overrides as $override) {
            if ($override->getMissionVariableName() !== $varName) {
                continue;
            }

            $value = $override->getValue();

            if (! ($value instanceof MissionItemValue)) {
                continue;
            }

            $tagTerms = $value->getTagSearchTerms();

            if ($tagTerms !== []) {
                return ['tag_terms' => ServiceFactory::getTagDatabaseService()->resolveTagSearchTermsNames($tagTerms)];
            }
        }

        return [];
    }

    /**
     * @return list<array{uuid: string, name: ?string}>
     */
    private function resolveEntityClassItems(?FoundryRecord $record, ItemService $itemService): array
    {
        if ($record === null) {
            return [];
        }

        $seen = [];
        $items = [];

        foreach ($record->getAll('entityClasses//Reference@value') as $refUuid) {
            if (is_string($refUuid) && ! isset($seen[strtolower($refUuid)])) {
                $seen[strtolower($refUuid)] = true;
                $items[] = [
                    'uuid' => $refUuid,
                    'name' => $itemService->getByReference($refUuid)?->getDisplayName(),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<MissionPropertyOverride>  $handlerProperties
     */
    private function computeHandlerPropertyBaseOffset(array $handlerProperties): ?int
    {
        $refs = [];

        foreach ($handlerProperties as $override) {
            $value = $override->getValue();

            if ($value instanceof HaulingOrdersValue) {
                foreach ($value->getOrders() as $order) {
                    if ($order['missionItem'] !== null) {
                        $refs[] = $order['missionItem'];
                    }
                }
            }
        }

        if ($refs === []) {
            return null;
        }

        $propCount = count($handlerProperties);

        $hexValues = array_map(static function (string $ref): int {
            preg_match('/MissionProperty\[([0-9A-Fa-f]+)\]/', $ref, $m);

            return hexdec($m[1]);
        }, $refs);

        $minHex = min($hexValues);
        $missionItemIndices = [];

        foreach ($handlerProperties as $i => $prop) {
            if ($prop->getValueTypeName() === 'MissionPropertyValue_MissionItem') {
                $missionItemIndices[] = $i;
            }
        }

        foreach ($missionItemIndices as $candidateIndex) {
            $baseOffset = $minHex - $candidateIndex;

            $valid = true;

            foreach ($hexValues as $hex) {
                $localIndex = $hex - $baseOffset;

                if ($localIndex < 0 || $localIndex >= $propCount) {
                    $valid = false;
                    break;
                }

                if ($handlerProperties[$localIndex]->getValueTypeName() !== 'MissionPropertyValue_MissionItem') {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                return $baseOffset;
            }
        }

        return null;
    }

    /**
     * @param  list<MissionPropertyOverride>  $fileProperties
     * @return list<array>
     */
    private function resolveMissionItemRef(array $order, array $fileProperties, ?int $baseOffset): array
    {
        $ref = $order['mission_item_ref'] ?? null;

        if ($ref === null || ! preg_match('/^MissionProperty\[([0-9A-Fa-f]+)\]$/', $ref, $matches)) {
            return [$order];
        }

        unset($order['mission_item_ref']);

        if ($baseOffset === null) {
            return [$order];
        }

        $localIndex = hexdec($matches[1]) - $baseOffset;

        if ($localIndex < 0 || ! isset($fileProperties[$localIndex])) {
            return [$order];
        }

        $value = $fileProperties[$localIndex]->getValue();

        if (! ($value instanceof MissionItemValue)) {
            return [$order];
        }

        $itemUuids = $value->getSpecificItems();

        if ($itemUuids === []) {
            return [$order];
        }

        $itemService = ServiceFactory::getItemService();
        $lookup = ServiceFactory::getFoundryLookupService();

        if (count($itemUuids) === 1) {
            $resolvedUuid = $lookup->resolveMissionItemEntityClass($itemUuids[0]) ?? $itemUuids[0];
            $order['uuid'] = $resolvedUuid;
            $order['name'] = $itemService->getByReference($resolvedUuid)?->getDisplayName();

            return [$order];
        }

        $results = [];

        foreach ($itemUuids as $uuid) {
            $resolvedUuid = $lookup->resolveMissionItemEntityClass($uuid) ?? $uuid;
            $entry = $order;
            $entry['uuid'] = $resolvedUuid;
            $entry['name'] = $itemService->getByReference($resolvedUuid)?->getDisplayName();
            $results[] = $entry;
        }

        return $results;
    }

    private function resolveMissionItemDisplayName(string $missionItemUuid, ItemService $itemService, FoundryLookupService $lookup): ?string
    {
        $entityClassUuid = $lookup->resolveMissionItemEntityClass($missionItemUuid);
        if ($entityClassUuid !== null) {
            return $itemService->getByReference($entityClassUuid)?->getDisplayName();
        }

        return null;
    }

    /**
     * @param  iterable<array{factionReputation: ?string, reputationScope: ?string, reward: ?string}>  $rewards
     * @return array{gained: list<array>, lost: list<array>}
     */
    private function collectReputationRewards(FoundryLookupService $lookup, LocalizationService $localization, iterable $rewards): array
    {
        $gained = [];
        $lost = [];

        foreach ($rewards as $rep) {
            $resolved = $this->resolveReputationEntry(
                $lookup,
                $localization,
                $rep['factionReputation'],
                $rep['reputationScope'],
            );

            $amount = null;
            $tier = null;

            if ($rep['reward'] !== null) {
                $rewardRecord = $lookup->getReputationRewardByReference($rep['reward']);

                if ($rewardRecord !== null) {
                    $amount = $rewardRecord->getIntValue('@reputationAmount');
                    $tier = $rewardRecord->getStringValue('@editorName');
                }
            }

            $entry = array_filter([
                ...$resolved,
                'amount' => $amount,
                'tier' => $tier,
                'outcome' => $rep['outcome'],
            ], static fn ($v) => $v !== null);

            if ($entry !== []) {
                if ($amount !== null && $amount < 0) {
                    $lost[] = $entry;
                } else {
                    $gained[] = $entry;
                }
            }
        }

        return ['gained' => $gained, 'lost' => $lost];
    }

    private function buildBrokerRewards(): array
    {
        $mbe = $this->getMissionBrokerEntry();

        if ($mbe === null) {
            return [];
        }

        return $this->buildMbeRewardFields($mbe);
    }

    private function getMissionBrokerEntry(): ?MissionBrokerEntry
    {
        $mbeRef = $this->entry->getMissionBrokerEntryReference();

        if ($mbeRef === null) {
            return null;
        }

        return ServiceFactory::getFoundryLookupService()->getMissionBrokerEntryByReference($mbeRef);
    }

    private function buildRewardOverride(): ?array
    {
        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();

            if ($value instanceof RewardValue) {
                return array_filter([
                    'reward' => $value->getReward(),
                    'max' => $value->getMax(),
                    'plusBonuses' => $value->isPlusBonuses() ?: null,
                    'currencyType' => $value->getCurrencyType(),
                ], static fn ($v) => $v !== null && $v !== false) ?: null;
            }
        }

        return null;
    }

    private function buildTimeTrialSplits(): ?array
    {
        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();

            if ($value instanceof TimeTrialRaceValue) {
                $splits = $value->getTargetSplits();

                return $splits !== [] ? $splits : null;
            }
        }

        return null;
    }

    private function buildResults(ContractResultBlock $results): array
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $tagService = ServiceFactory::getTagDatabaseService();
        $blueprintService = ServiceFactory::getBlueprintService();
        $itemService = ServiceFactory::getItemService();
        $localization = ServiceFactory::getLocalizationService();

        $calcRep = $results->getCalculatedReputation();

        $rep = $this->collectReputationRewards($lookup, $localization, $results->getLegacyReputationRewards());

        $resolvedBlueprints = [];

        foreach ($results->getAllBlueprintRewards() as $blueprint) {
            $poolUuid = $blueprint['blueprintPool'] ?? null;
            $poolContents = [];

            if ($poolUuid !== null) {
                $poolRecord = $lookup->getBlueprintPoolByReference($poolUuid);

                if ($poolRecord !== null) {
                    foreach ($poolRecord->getBlueprintRewardReferences() as $bpUuid) {
                        $bpRecord = $blueprintService->getByReference($bpUuid);
                        $outputUuid = $bpRecord?->getOutputEntityUuid();
                        $outputEntity = $outputUuid !== null ? $itemService->getByReference($outputUuid) : null;
                        $poolContents[] = [
                            'item_name' => $outputEntity?->getDisplayName(),
                            'item_uuid' => $outputUuid,
                            'blueprint_uuid' => $bpUuid,
                        ];
                    }
                }
            }

            $resolvedBlueprints[] = [
                'chance' => $blueprint['chance'],
                'pool_uuid' => $poolUuid,
                'pool_contents' => $poolContents,
            ];
        }

        $rewardItems = $this->buildRewardItems($results, $itemService);

        $buyIn = $results->getContractBuyInAmount();

        $resolvedTags = array_map(
            fn (string $tag): array => [
                'uuid' => $tag,
                'name' => $tagService->getTagName($tag),
                'unlocks_missions' => $this->deduplicateChainEntries($this->chainIndex['required'][$tag] ?? []),
            ],
            $results->getCompletionTags(),
        );

        $journalEntries = $results->getJournalEntryReferences();

        return array_filter([
            'calculated_reward' => $results->getCalculatedReward() ?: null,
            'calculated_reputation' => $calcRep !== null
                ? $this->resolveCalculatedReputation($lookup, $calcRep)
                : null,
            'fixed_reward' => $results->getFixedReward(),
            'time_to_complete' => ($t = $results->getTimeToComplete()) !== null && $t > 0 ? $t : null,
            'reward_items' => $rewardItems !== [] ? $rewardItems : null,
            'blueprints' => $resolvedBlueprints !== [] ? $resolvedBlueprints : null,
            'reputation_gained' => $rep['gained'] !== [] ? $rep['gained'] : null,
            'reputation_lost' => $rep['lost'] !== [] ? $rep['lost'] : null,
            'completion_tags' => $resolvedTags !== [] ? $resolvedTags : null,
            'completion_bounty' => $results->hasCompletionBounty() ?: null,
            'scenario_progress' => $results->getScenarioProgress(),
            'journal_entries' => $journalEntries !== [] ? $journalEntries : null,
            'badge_award' => $results->getBadgeAward(),
            'refund_buy_in' => $results->getRefundBuyIn(),
            'cost' => $buyIn > 0 ? $buyIn : null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Merges reward items (ContractResult_Item) and weighted award sets (ContractResult_ItemsWeighting) into a single list of award sets.
     * Each set is a group of items granted together.
     * Unconditional sets have weight null (always awarded); weighted sets carry the ItemAwardWeightings weighting value,
     * since only one such set is selected at mission completion.
     *
     * @return list<array{
     *     weight: ?int,
     *     award_only_to_mission_owner: bool,
     *     items: list<array{name: ?string, uuid: ?string, amount: int, send_to_home: ?bool}>
     * }>
     */
    private function buildRewardItems(ContractResultBlock $results, ItemService $itemService): array
    {
        $sets = [];

        // ContractResult_Item nodes are unconditional always-awarded items with no grouping in source
        $unconditionalItems = [];
        $unconditionalOwner = false;
        foreach ($results->getItemResults() as $item) {
            $entity = $item['entityClass'] !== null
                ? $itemService->getByReference($item['entityClass'])
                : null;

            $unconditionalItems[] = [
                'name' => $entity?->getDisplayName(),
                'uuid' => $item['entityClass'],
                'amount' => $item['amount'],
                'send_to_home' => $item['sendToPlayerHomeLocation'],
            ];
            // Per-item flags are uniform across every ContractResult_Item node in a contract (checked in 4.8.1)
            $unconditionalOwner = $item['awardOnlyToMissionOwner'];
        }

        if ($unconditionalItems !== []) {
            $sets[] = [
                'weight' => null,
                'award_only_to_mission_owner' => $unconditionalOwner,
                'items' => $unconditionalItems,
            ];
        }

        foreach ($results->getItemAwardSets() as $set) {
            $items = [];

            foreach ($set['items'] as $award) {
                $entity = $award['entityClass'] !== null
                    ? $itemService->getByReference($award['entityClass'])
                    : null;

                $items[] = [
                    'name' => $entity?->getDisplayName(),
                    'uuid' => $award['entityClass'],
                    'amount' => $award['amount'],
                    'send_to_home' => null,
                ];
            }

            $sets[] = [
                'weight' => $set['weight'],
                'award_only_to_mission_owner' => $set['awardOnlyToMissionOwner'],
                'items' => $items,
            ];
        }

        return $sets;
    }

    private function resolveMissionTypeInfo(?string $uuid): ?array
    {
        if ($uuid === null) {
            return null;
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $typeRecord = $lookup->getMissionTypeByReference($uuid);

        return [
            'uuid' => $uuid,
            'name' => $typeRecord !== null
                ? ServiceFactory::getLocalizationService()->translateValue($typeRecord->getStringValue('@LocalisedTypeName'), true)
                : null,
        ];
    }

    private function buildMissionType(): ?array
    {
        $typeOverrideUuid = $this->entry->getMissionTypeOverride() ?? $this->handler->getMissionTypeOverride();

        if ($typeOverrideUuid !== null) {
            return $this->resolveMissionTypeInfo($typeOverrideUuid);
        }

        $templateRef = $this->entry->getTemplateReference();

        if ($templateRef !== null) {
            $template = ServiceFactory::getFoundryLookupService()->getContractTemplateByReference($templateRef);
            $templateTypeUuid = $template?->getStringValue('contractDisplayInfo/ContractDisplayInfo@type');

            if ($templateTypeUuid !== null) {
                return $this->resolveMissionTypeInfo($templateTypeUuid);
            }
        }

        return null;
    }

    private function buildFaction(): ?array
    {
        $factionRepUuid = $this->handler->getFactionReputationReference();
        $scopeUuid = $this->handler->getReputationScopeReference();

        if ($factionRepUuid === null && $scopeUuid === null) {
            $results = $this->entry->getResults();

            if ($results !== null) {
                $legacy = $results->getLegacyReputationRewards();

                if ($legacy !== []) {
                    $factionRepUuid = $legacy[0]['factionReputation'];
                    $scopeUuid = $legacy[0]['reputationScope'];
                }

                if ($factionRepUuid === null && $scopeUuid === null) {
                    $calcRep = $results->getCalculatedReputation();

                    if ($calcRep !== null) {
                        $factionRepUuid = $calcRep['factionReputation'] ?? null;
                        $scopeUuid = $calcRep['reputationScope'] ?? null;
                    }
                }
            }
        }

        if ($factionRepUuid === null && $scopeUuid === null) {
            foreach ($this->entry->getReputationPrerequisites() as $prereq) {
                if ($prereq['factionReputation'] !== null) {
                    $factionRepUuid = $prereq['factionReputation'];
                    $scopeUuid = $prereq['scope'];
                    break;
                }
            }
        }

        if ($factionRepUuid === null && $scopeUuid === null) {
            $consensus = $this->resolveHandlerFactionConsensus();
            $factionRepUuid = $consensus['factionReputation'];
            $scopeUuid = $consensus['reputationScope'];
        }

        if ($factionRepUuid === null && $scopeUuid === null) {
            return null;
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        $factionData = $factionRepUuid !== null
            ? $this->resolveFactionReputationSummary($lookup, $localization, $factionRepUuid)
            : null;

        $reputationScope = null;

        if ($scopeUuid !== null) {
            $scope = $lookup->getReputationScopeByReference($scopeUuid);
            $reputationScope = [
                'uuid' => $scopeUuid,
                'scope_name' => $scope?->getScopeName(),
                'display_name' => $scope !== null ? $localization->translateValue($scope->getDisplayName(), true) : null,
                'reputation_ceiling' => $scope?->getReputationCeiling(),
                'initial_reputation' => $scope?->getInitialReputation(),
            ];
        }

        $result = [
            'faction_reputation' => $factionData,
            'reputation_scope' => $reputationScope,
        ];

        return array_filter($result, static fn ($v) => $v !== null) ?: null;
    }

    /**
     * @return array{factionReputation: ?string, reputationScope: ?string}
     */
    private function resolveHandlerFactionConsensus(): array
    {
        $factionTally = [];
        $scopeByFaction = [];

        foreach ($this->handler->getContracts() as $sibling) {
            $pairs = [];

            $results = $sibling->getResults();
            if ($results !== null) {
                foreach ($results->getLegacyReputationRewards() as $reward) {
                    $pairs[] = [$reward['factionReputation'], $reward['reputationScope']];
                }

                $calcRep = $results->getCalculatedReputation();
                if ($calcRep !== null) {
                    $pairs[] = [$calcRep['factionReputation'] ?? null, $calcRep['reputationScope'] ?? null];
                }
            }

            foreach ($sibling->getReputationPrerequisites() as $prereq) {
                $pairs[] = [$prereq['factionReputation'], $prereq['scope']];
            }

            foreach ($pairs as [$faction, $scope]) {
                if ($faction === null) {
                    continue;
                }

                $factionTally[$faction] = ($factionTally[$faction] ?? 0) + 1;

                if ($scope !== null) {
                    $scopeByFaction[$faction][$scope] = ($scopeByFaction[$faction][$scope] ?? 0) + 1;
                }
            }
        }

        if ($factionTally === []) {
            return ['factionReputation' => null, 'reputationScope' => null];
        }

        arsort($factionTally);
        $dominantFaction = array_key_first($factionTally);

        $dominantScope = null;
        if (isset($scopeByFaction[$dominantFaction])) {
            arsort($scopeByFaction[$dominantFaction]);
            $dominantScope = array_key_first($scopeByFaction[$dominantFaction]);
        }

        return ['factionReputation' => $dominantFaction, 'reputationScope' => $dominantScope];
    }

    /**
     * @return array{faction: ?string, faction_uuid: ?string, scope: ?string, scope_uuid: ?string}
     */
    private function resolveReputationEntry(
        FoundryLookupService $lookup,
        LocalizationService $localization,
        ?string $factionRepUuid,
        ?string $scopeRefUuid,
    ): array {
        $faction = null;
        $factionUuid = null;

        if ($factionRepUuid !== null) {
            $factionData = $this->resolveFactionReputationSummary($lookup, $localization, $factionRepUuid);
            $faction = $factionData['name'];
            $factionUuid = $factionData['uuid'];
        }

        $scope = null;
        $resolvedScopeUuid = null;

        if ($scopeRefUuid !== null) {
            $scopeRecord = $lookup->getReputationScopeByReference($scopeRefUuid);
            $scope = $scopeRecord?->getScopeName();
            $resolvedScopeUuid = $scopeRefUuid;
        }

        return array_filter([
            'faction' => $faction,
            'faction_uuid' => $factionUuid,
            'scope' => $scope,
            'scope_uuid' => $resolvedScopeUuid,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @return array{uuid: string, name: ?string}
     */
    private function resolveFactionReputationSummary(
        FoundryLookupService $lookup,
        LocalizationService $localization,
        string $factionRepUuid,
    ): array {
        $faction = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
        $name = $faction !== null
            ? $localization->translateValue($faction->getName(), true)
            : null;

        if ($name !== null && (str_contains($name, 'UNINITIALIZED') || str_contains($name, 'PLACEHOLDER'))) {
            $name = null;
        }

        if ($name === null) {
            $factionRep = $lookup->getFactionReputationByReference($factionRepUuid);
            $name = $factionRep !== null
                ? $localization->translateValue($factionRep->getDisplayName(), true)
                : null;
        }

        return $this->applyManufacturerFactionOverride(
            $name,
            $faction !== null ? $faction->getUuid() : $factionRepUuid,
        );
    }

    /**
     * Unify a manufacturer-named mission giver (Shubin, Stanton) to the canonical manufacturer name.
     * Non-manufacturer givers pass through.
     */
    private function applyManufacturerNameOverride(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return $name;
        }

        $canonical = ServiceFactory::getManufacturerService()->resolveCanonicalByNameOrCode($name, null);

        return $canonical !== null ? $canonical['name'] : $name;
    }

    /**
     * Unify a manufacturer-named faction to the canonical manufacturer name + primary uuid.
     * Other factions pass through. Keeps the faction uuid when the manufacturer has no XML record.
     *
     * @return array{name: ?string, uuid: ?string}
     */
    private function applyManufacturerFactionOverride(?string $name, ?string $fallbackUuid): array
    {
        if ($name === null || $name === '') {
            return [
                'uuid' => $fallbackUuid,
                'name' => $name,
            ];
        }

        $canonical = ServiceFactory::getManufacturerService()->resolveCanonicalByNameOrCode($name, null);

        if ($canonical === null) {
            return [
                'uuid' => $fallbackUuid,
                'name' => $name,
            ];
        }

        return [
            'uuid' => $canonical['uuid'] ?? $fallbackUuid,
            'name' => $canonical['name'],
        ];
    }

    private function resolveCalculatedReputation(FoundryLookupService $lookup, array $calcRep): ?array
    {
        $faction = null;
        $factionRepUuid = $calcRep['factionReputation'] ?? null;
        $factionUuid = null;

        if ($factionRepUuid !== null) {
            $factionData = $this->resolveFactionReputationSummary(
                $lookup,
                ServiceFactory::getLocalizationService(),
                $factionRepUuid,
            );
            $faction = $factionData['name'];
            $factionUuid = $factionData['uuid'];
        }

        $scope = null;
        $scopeUuid = $calcRep['reputationScope'] ?? null;

        if ($scopeUuid !== null) {
            $scopeRecord = $lookup->getReputationScopeByReference($scopeUuid);
            $scope = $scopeRecord?->getScopeName();
        }

        return array_filter([
            'faction' => $faction,
            'faction_uuid' => $factionUuid,
            'scope' => $scope,
            'scope_uuid' => $scopeUuid,
        ], static fn ($v) => $v !== null);
    }

    private function buildPropertyOverrides(): ?array
    {
        $properties = [];

        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();
            $name = $override->getMissionVariableName();

            if ($name === null) {
                continue;
            }

            if ($value instanceof IntegerValue) {
                $properties[] = [
                    'name' => $name,
                    'type' => 'integer',
                    'options' => $value->getOptions(),
                ];
            } elseif ($value instanceof FloatValue) {
                $properties[] = [
                    'name' => $name,
                    'type' => 'float',
                    'options' => $value->getOptions(),
                ];
            } elseif ($value instanceof BooleanValue) {
                $properties[] = [
                    'name' => $name,
                    'type' => 'boolean',
                    'value' => $value->getValue(),
                ];
            }
        }

        return $properties !== [] ? $properties : null;
    }

    private function buildNPCNames(): ?array
    {
        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();

            if ($value instanceof AINameValue) {
                return array_filter([
                    'given_name' => $value->getCharacterGivenName(),
                    'last_name' => $value->getCharacterGivenLastName(),
                    'nick_name' => $value->getCharacterGivenNickName(),
                ]);
            }
        }

        return null;
    }

    private function buildMissionItemCounts(): ?array
    {
        $allItems = [];
        $allTagTerms = [];

        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();

            if (! ($value instanceof MissionItemValue)) {
                continue;
            }

            $min = $value->getMinItemsToFind();
            $max = $value->getMaxItemsToFind();

            if ($min <= 0 && $max <= 0) {
                continue;
            }

            $result = array_filter([
                'min_items' => $min > 0 ? $min : null,
                'max_items' => $max > 0 ? $max : null,
            ], static fn ($v) => $v !== null);

            $specificItems = $this->resolveSpecificItems($value);

            if ($specificItems !== []) {
                $result['items'] = $specificItems;
            }

            $tagTerms = $value->getTagSearchTerms();

            if ($tagTerms !== []) {
                $allTagTerms = $tagTerms;
            }

            $allItems[] = $result;
        }

        if ($allItems === []) {
            return null;
        }

        $merged = array_merge(...$allItems);

        if ($allTagTerms !== []) {
            $merged['tag_search_terms'] = ServiceFactory::getTagDatabaseService()->resolveTagSearchTermsNames($allTagTerms);
        }

        return $merged !== [] ? $merged : null;
    }

    /**
     * @return list<array{uuid: string, name: ?string}>
     */
    private function resolveSpecificItems(MissionItemValue $value): array
    {
        $itemUuids = $value->getSpecificItems();

        if ($itemUuids === []) {
            return [];
        }

        $itemService = ServiceFactory::getItemService();
        $lookup = ServiceFactory::getFoundryLookupService();

        $resolved = [];

        foreach ($itemUuids as $uuid) {
            $entityClassUuid = $lookup->resolveMissionItemEntityClass($uuid);

            if ($entityClassUuid === null) {
                continue;
            }

            $resolved[] = [
                'uuid' => $entityClassUuid,
                'name' => $itemService->getByReference($entityClassUuid)?->getDisplayName(),
            ];
        }

        return $resolved;
    }

    /**
     * Check whether a token base key is runtime-only and must remain unresolved.
     * These tokens are filled at mission instantiation by subsumption.
     */
    private function isDenylistedToken(string $token): bool
    {
        $normalized = strtolower($token);

        if (str_starts_with($normalized, 'item')) {
            return true;
        }

        return in_array($normalized, [
            'targetname', 'targetname2',
            'target1', 'target2', 'target3',
            'nearbylocation',
            'namekill1',
            'namesave1', 'namesave2', 'namesave3',
            'achievedfinish', 'prevcheckpointnumber', 'checkpointwinningplayer',
            'creature',
            'missingpersonlist',
            'itemstorecover',
            'distractionkilldescription',
        ], true);
    }

    /**
     * Find the last override matching an extended text token.
     * getAllOverrides() merges handler first, then entry, so the last match implements entry-wins-over-handler precedence.
     */
    private function findLastOverride(string $extendedTextToken): ?MissionPropertyOverride
    {
        $last = null;

        foreach ($this->getAllOverrides() as $override) {
            if ($override->getExtendedTextToken() === $extendedTextToken) {
                $last = $override;
            }
        }

        return $last;
    }

    /**
     * Resolve StringHash override values for a contract property.
     *
     * @return list<string>|null Resolved display strings, or null.
     */
    private function resolveContractStringHashValues(MissionPropertyOverride $override): ?array
    {
        $value = $override->getValue();

        if (! ($value instanceof StringHashValue)) {
            return null;
        }

        $values = [];

        foreach ($value->getOptions() as $option) {
            $raw = $option['textId'] ?? $option['value'] ?? null;

            if (! is_string($raw) || $raw === '' || $raw === '@LOC_UNINITIALIZED' || $raw === '@LOC_EMPTY') {
                continue;
            }

            $translated = str_starts_with($raw, '@')
                ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                : $raw;

            if ($translated !== null && $translated !== '') {
                $values[] = $translated;
            }
        }

        return $values !== [] ? $values : null;
    }

    /**
     * @return list<string>|null Resolved org display strings, or null.
     */
    private function resolveContractOrgValues(MissionPropertyOverride $override, ?string $variant): ?array
    {
        $value = $override->getValue();

        if (! ($value instanceof OrganizationValue)) {
            return null;
        }

        $organizationUuids = $value->getOrganizations();

        if ($organizationUuids === []) {
            return null;
        }

        $variantTagUuid = $variant !== null ? $this->resolveTagUuidByName($variant) : null;

        if ($variant !== null && $variantTagUuid === null) {
            return null;
        }

        $values = [];

        foreach ($organizationUuids as $organizationUuid) {
            $organization = ServiceFactory::getFoundryLookupService()
                ->getMissionOrganizationByReference($organizationUuid);

            if ($organization === null) {
                continue;
            }

            if ($variantTagUuid !== null) {
                foreach ($organization->getAll(
                    sprintf('stringVariants/variants/MissionStringVariant[@tag="%s"]@string', $variantTagUuid),
                    raw: true,
                ) as $raw) {
                    if (! is_string($raw) || $raw === '') {
                        continue;
                    }

                    $translated = str_starts_with($raw, '@')
                        ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                        : $raw;

                    if ($translated !== null && $translated !== '') {
                        $values[] = $translated;
                    }
                }

                continue;
            }

            $raw = $organization->getStringValue('stringVariants/variants/MissionStringVariant@string');

            if ($raw !== null && $raw !== '') {
                $translated = str_starts_with($raw, '@')
                    ? ServiceFactory::getLocalizationService()->translateValue($raw, true)
                    : $raw;

                if ($translated !== null && $translated !== '') {
                    $values[] = $translated;
                }
            }
        }

        return $values !== [] ? $values : null;
    }

    /**
     * Dispatch contract property token resolution by override value type.
     *
     * @return list<string>|null Resolved display strings, or null.
     */
    private function resolveContractPropertyTokenValues(MissionPropertyOverride $override, ?string $variant): ?array
    {
        $typeName = $override->getValueTypeName();

        return match ($typeName) {
            'MissionPropertyValue_Organization' => $this->resolveContractOrgValues($override, $variant),
            'MissionPropertyValue_StringHash' => $this->resolveContractStringHashValues($override),
            default => null,
        };
    }

    private function buildMissionTokens(string $title, string $description, array $locationPools): ?array
    {
        $tokenMap = [];
        $requestedTokens = $this->collectMissionTokenReferences($title, $description);
        $resolvePropertyToken = function (string $tokenContent): ?array {
            $key = $this->missionTokenKey($tokenContent);

            if ($this->isDenylistedToken($key)) {
                return null;
            }

            $override = $this->findLastOverride($key);

            if ($override === null) {
                return null;
            }

            $values = $this->resolveContractPropertyTokenValues($override, $this->missionTokenVariant($tokenContent));

            if ($values === null || $values === []) {
                return null;
            }

            return [
                'map_key' => $this->missionTokenMapKey($tokenContent, $override->getValueTypeName()),
                'values' => $values,
            ];
        };

        if ($requestedTokens !== []) {
            $tokenMap = $this->resolveLocationBasedTokens($locationPools, $requestedTokens);

            foreach (array_keys($requestedTokens) as $tokenContent) {
                $key = $this->missionTokenKey($tokenContent);

                if (isset($tokenMap[$key]) || isset($tokenMap[$tokenContent])) {
                    continue;
                }

                $resolved = $resolvePropertyToken($tokenContent);

                if ($resolved === null) {
                    continue;
                }

                $tokenMap[$resolved['map_key']] = $resolved['values'];
            }
        }

        $tokenMap = $this->expandNestedMissionTokens($tokenMap, $locationPools, $resolvePropertyToken);

        $seenTokens = [];

        foreach ($this->getAllOverrides() as $override) {
            $token = $override->getExtendedTextToken();

            if ($token === null || isset($tokenMap[$token]) || isset($seenTokens[$token])) {
                continue;
            }

            $seenTokens[$token] = true;

            if ($this->isDenylistedToken($token)) {
                continue;
            }

            $matched = $this->findLastOverride($token);

            if ($matched === null) {
                continue;
            }

            $values = $this->resolveContractPropertyTokenValues($matched, null);

            if ($values === null || $values === []) {
                continue;
            }

            $tokenMap[$token] = $values;
        }

        $tokenMap = $this->normalizeMissionTokenMap($tokenMap);

        return $tokenMap !== [] ? $tokenMap : null;
    }

    /**
     * Rent-ship modifiers merged from entry paramOverrides and the template's modifiers section.
     *
     * @return list<array{modifier_name: ?string, enabled: bool, item_record_guid: ?string, item_name: ?string, duration_seconds: ?int, clear_rental_on_fail: bool, source: string}>|null
     */
    private function buildRentShipModifiers(): ?array
    {
        $results = [];
        $itemService = ServiceFactory::getItemService();

        foreach ($this->entry->getRentShipModifiers() as $mod) {
            $results[] = $this->formatRentShipModifier($mod, 'entry', $itemService);
        }

        $template = $this->getContractTemplate();

        if ($template !== null) {
            $nodes = $template->getAll('modifiers/MissionModifier_RequestRentShip');

            foreach ($nodes as $node) {
                $results[] = $this->formatRentShipModifier([
                    'modifierName' => $node->get('@modifierName'),
                    'enabled' => (int) ($node->get('@enabled') ?? 1) === 1,
                    'itemRecordGUID' => $node->get('@itemRecordGUID'),
                    'durationSeconds' => $node->get('@durationSeconds') !== null ? (int) $node->get('@durationSeconds') : null,
                    'clearRentalOnFail' => (int) ($node->get('@clearRentalOnFail') ?? 0) === 1,
                ], 'template', $itemService);
            }
        }

        return $results !== [] ? $results : null;
    }

    /**
     * @param  array{modifierName: ?string, enabled: bool, itemRecordGUID: ?string, durationSeconds: ?int, clearRentalOnFail: bool}  $mod
     * @return array{modifier_name: ?string, enabled: bool, item_record_guid: ?string, item_name: ?string, duration_seconds: ?int, clear_rental_on_fail: bool, source: string}
     */
    private function formatRentShipModifier(array $mod, string $source, ItemService $itemService): array
    {
        $itemUuid = $mod['itemRecordGUID'];
        $itemName = $itemUuid !== null ? $itemService->getByReference($itemUuid)?->getDisplayName() : null;

        return [
            'modifier_name' => $mod['modifierName'],
            'enabled' => $mod['enabled'],
            'item_record_guid' => $itemUuid,
            'item_name' => $itemName,
            'duration_seconds' => $mod['durationSeconds'],
            'clear_rental_on_fail' => $mod['clearRentalOnFail'],
            'source' => $source,
        ];
    }

    /**
     * @return list<array{plugin_type: string, tag: ?string, storyline_mission: bool, available_to_accept_from_contract_manager: bool}>|null
     */
    private function buildContractPlugins(): ?array
    {
        $plugins = $this->entry->getContractPlugins();

        if ($plugins === []) {
            return null;
        }

        return array_map(static fn (array $p): array => [
            'plugin_type' => $p['pluginType'],
            'tag' => $p['tag'],
            'storyline_mission' => $p['storylineMission'],
            'available_to_accept_from_contract_manager' => $p['availableToAcceptFromContractManager'],
        ], $plugins);
    }

    /**
     * Objective token metadata from the ContractTemplate
     * phase identifiers, objective handlers (e.g. MeetAndTalk), and objective property values (Output/Input)
     *
     * @return list<array{id: ?string, debug_name: ?string, phase_identifier_tag: ?string, handler_type: ?string, meet_and_talk: ?array}>|null
     */
    private function buildObjectiveTokens(): ?array
    {
        $template = $this->getContractTemplate();

        if ($template === null) {
            return null;
        }

        $tokens = $template->getAll('objectiveTokens/ObjectiveToken');

        if ($tokens === []) {
            return null;
        }

        $results = [];

        foreach ($tokens as $token) {
            $handler = $token->get('objectiveHandler');
            $handlerType = null;
            $meetAndTalk = null;

            if ($handler !== null) {
                foreach ($handler->children() as $child) {
                    $handlerType = $child->nodeName;

                    if ($handlerType === 'ObjectiveHandler_MeetAndTalk') {
                        $meetAndTalk = $this->parseMeetAndTalkHandler($child);
                    }

                    break;
                }
            }

            $results[] = $this->removeNullValues([
                'id' => $token->get('@id'),
                'debug_name' => $token->get('@debugName'),
                'phase_identifier_tag' => $token->get('@missionPhaseIdentifierTag'),
                'handler_type' => $handlerType,
                'meet_and_talk' => $meetAndTalk,
            ]);

        }

        return $results !== [] ? $results : null;
    }

    /**
     * @return array{travel_radius_km: ?float, marker_label: ?string, location_ref: ?string, oc_tags: list<string>, travel_objective_info: ?array}
     */
    private function parseMeetAndTalkHandler(Element $handler): array
    {
        $travelInfoNode = $handler->get('travelObjectiveInfo');
        $travelInfo = null;

        if ($travelInfoNode !== null) {
            $travelInfo = $this->removeNullValues([
                'short_description' => $this->translateLocalizationValue($travelInfoNode->get('@shortDescription')),
                'long_description' => $this->translateLocalizationValue($travelInfoNode->get('@longDescription')),
                'objective_marker_label' => $this->translateLocalizationValue($travelInfoNode->get('@objectiveMarkerLabel')),
                'category' => $travelInfoNode->get('@category'),
                'hide_on_hud' => (int) ($travelInfoNode->get('@hideOnHUD') ?? 0) === 1 ?: null,
            ]);
        }

        $ocTags = [];

        foreach ($handler->getAll('ocTagsToSearch/tags/Reference@value') as $tag) {
            if (is_string($tag) && $tag !== '') {
                $ocTags[] = $tag;
            }
        }

        return [
            'travel_radius_km' => $handler->get('@travelRadiusKM') !== null ? (float) $handler->get('@travelRadiusKM') : null,
            'marker_label' => $this->translateLocalizationValue($handler->get('@meetAndTalkObjectiveMarkerLabel')),
            'location_ref' => $handler->get('location@value'),
            'oc_tags' => $ocTags,
            'travel_objective_info' => $travelInfo,
        ];
    }

    private function getContractTemplate(): ?FoundryRecord
    {
        $templateRef = $this->entry->getTemplateReference();

        if ($templateRef === null) {
            return null;
        }

        return ServiceFactory::getFoundryLookupService()->getContractTemplateByReference($templateRef);
    }
}
