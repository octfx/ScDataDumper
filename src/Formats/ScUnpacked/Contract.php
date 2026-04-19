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
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\RewardValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\ShipSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\TimeTrialRaceValue;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Contract extends BaseFormat
{
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

        $title = $this->translateLocalizationValue($this->entry->getTitle());
        $description = $this->translateLocalizationValue($this->entry->getDescription());
        $locationPools = $this->buildLocationPools();

        return $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $this->entry->getId(),
            'debug_name' => $this->entry->getDebugName(),
            'type' => $this->handler->getHandlerType(),
            'mission_type' => $this->buildMissionType(),
            'mission_giver' => $meta['mission_giver'] ?? null,
            'title' => $title,
            'description' => $description,
            'location_pools' => $locationPools,
            'mission_tokens' => $this->buildMissionTokens($title, $description, $locationPools),
            ...$mergedRewards,
            'hauling_orders' => $this->buildHaulingOrders(),
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
            'npc_names' => $this->buildNPCNames(),
            'item_counts' => $this->buildMissionItemCounts(),
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

        return $this->removeNullValuesPreservingEmptyArrays([
            'min_standing' => $standingReqs['min'],
            'max_standing' => $standingReqs['max'],
            'rank_index' => $standingReqs['rank_index'],
            'crime_stat' => $crimeStat,
            'reputation_prerequisite' => $reputationPrereq,
            'prerequisites' => $tagPrerequisites,
            'availability_locations' => $localities,
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
        $prerequisites = [];
        foreach ($this->entry->getCompletedContractTagPrerequisites() as $prereq) {
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

            if ($prereq['factionReputation'] !== null) {
                $faction = null;
                $factionUuid = null;
                $factionRecord = $lookup->getFactionByFactionReputationUuid($prereq['factionReputation']);
                if ($factionRecord !== null) {
                    $faction = ServiceFactory::getLocalizationService()->translateValue($factionRecord->getName(), true);
                    $factionUuid = $factionRecord->getUuid();
                }

                $scopeName = null;
                if ($prereq['scope'] !== null) {
                    $scope = $lookup->getReputationScopeByReference($prereq['scope']);
                    $scopeName = $scope?->getScopeName();
                }

                $reputationPrereq = [
                    'faction' => $faction,
                    'faction_uuid' => $factionUuid,
                    'scope' => $scopeName,
                    'scope_uuid' => $prereq['scope'],
                    'min_standing' => $this->resolveStanding($lookup, $prereq['minStanding']),
                    'max_standing' => $this->resolveStanding($lookup, $prereq['maxStanding']),
                ];
            }
        }

        return [$crimeStat, $reputationPrereq];
    }

    private function buildLocalityPrerequisites(FoundryLookupService $lookup): array
    {
        $localityUuids = [];

        foreach ($this->entry->getLocalityPrerequisites() as $prereq) {
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
            'notify_on_available' => $this->handler->notifyOnAvailable() ?: null,
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

        $orders = [];

        foreach ($handlerProperties as $override) {
            $value = $override->getValue();
            if (! ($value instanceof HaulingOrdersValue)) {
                continue;
            }

            foreach ($value->toArray() as $order) {
                foreach ($this->resolveMissionItemRef($order, $handlerProperties, $baseOffset) as $resolved) {
                    $orders[] = $resolved;
                }
            }
        }

        return $orders;
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
        $mbeRef = $this->entry->getMissionBrokerEntryReference();
        if ($mbeRef === null) {
            return [];
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $mbe = $lookup->getMissionBrokerEntryByReference($mbeRef);
        if ($mbe === null) {
            return [];
        }

        $rep = $this->collectReputationRewards($lookup, ServiceFactory::getLocalizationService(), $mbe->getReputationRewards());

        $buyIn = $mbe->getBuyInAmount();

        return array_filter([
            'fixed_reward' => $mbe->getReward(),
            'cost' => $buyIn > 0 ? $buyIn : null,
            'reputation_gained' => $rep['gained'] !== [] ? $rep['gained'] : null,
            'reputation_lost' => $rep['lost'] !== [] ? $rep['lost'] : null,
            'refund_buy_in_on_withdraw' => $mbe->shouldRefundBuyInOnWithdraw() ?: null,
            'deadline' => $mbe->getDeadline(),
            'broker_reputation_prerequisites' => $mbe->getReputationPrerequisites(),
            'broker_reputation_requirements' => ($reqs = $mbe->getReputationRequirements()) !== [] ? $reqs : null,
        ], static fn ($v) => $v !== null);
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

        $blueprint = $results->getBlueprintRewards();
        $resolvedBlueprint = null;
        if ($blueprint !== null) {
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
            $resolvedBlueprint = [
                'chance' => $blueprint['chance'],
                'pool_uuid' => $poolUuid,
                'pool_contents' => $poolContents,
            ];
        }

        $items = [];
        foreach ($results->getItemResults() as $item) {
            $entity = $item['entityClass'] !== null
                ? $itemService->getByReference($item['entityClass'])
                : null;
            $items[] = [
                'name' => $entity?->getDisplayName(),
                'uuid' => $item['entityClass'],
                'amount' => $item['amount'],
                'send_to_home' => $item['sendToPlayerHomeLocation'],
            ];
        }

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
            'items' => $items !== [] ? $items : null,
            'blueprint' => $resolvedBlueprint,
            'reputation_gained' => $rep['gained'] !== [] ? $rep['gained'] : null,
            'reputation_lost' => $rep['lost'] !== [] ? $rep['lost'] : null,
            'completion_tags' => $resolvedTags !== [] ? $resolvedTags : null,
            'completion_bounty' => $results->hasCompletionBounty() ?: null,
            'scenario_progress' => $results->getScenarioProgress(),
            'journal_entries' => $journalEntries !== [] ? $journalEntries : null,
            'badge_award' => $results->getBadgeAward(),
            'items_weighting' => $results->getItemsWeighting(),
            'refund_buy_in' => $results->getRefundBuyIn(),
            'cost' => $buyIn > 0 ? $buyIn : null,
        ], static fn ($v) => $v !== null);
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
            $templateTypeUuid = $template?->getStringValue('ContractDisplayInfo@type');
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
            return null;
        }

        $lookup = ServiceFactory::getFoundryLookupService();
        $localization = ServiceFactory::getLocalizationService();

        $factionData = null;
        if ($factionRepUuid !== null) {
            $faction = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
            $name = $faction !== null
                ? $localization->translateValue($faction->getName(), true)
                : null;

            if ($name === null) {
                $factionRep = $lookup->getFactionReputationByReference($factionRepUuid);
                $name = $factionRep !== null
                    ? $localization->translateValue($factionRep->getDisplayName(), true)
                    : null;
            }

            $factionData = [
                'uuid' => $faction !== null ? $faction->getUuid() : $factionRepUuid,
                'name' => $name,
            ];
        }

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
            $factionRecord = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
            $faction = $factionRecord !== null
                ? $localization->translateValue($factionRecord->getName(), true)
                : null;
            $factionUuid = $factionRecord !== null ? $factionRecord->getUuid() : $factionRepUuid;
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

    private function resolveCalculatedReputation(FoundryLookupService $lookup, array $calcRep): ?array
    {
        $faction = null;
        $factionRepUuid = $calcRep['factionReputation'] ?? null;
        $factionUuid = null;
        if ($factionRepUuid !== null) {
            $factionRecord = $lookup->getFactionByFactionReputationUuid($factionRepUuid);
            $faction = $factionRecord !== null
                ? ServiceFactory::getLocalizationService()->translateValue($factionRecord->getName(), true)
                : null;
            $factionUuid = $factionRecord !== null ? $factionRecord->getUuid() : $factionRepUuid;
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
        ], static fn ($v) => $v !== null) ?: true;
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
        foreach ($this->getAllOverrides() as $override) {
            $value = $override->getValue();
            if ($value instanceof MissionItemValue) {
                $min = $value->getMinItemsToFind();
                $max = $value->getMaxItemsToFind();
                if ($min > 0 || $max > 0) {
                    return array_filter([
                        'min_items' => $min > 0 ? $min : null,
                        'max_items' => $max > 0 ? $max : null,
                    ], static fn ($v) => $v !== null);
                }
            }
        }

        return null;
    }

    private function buildMissionTokens(string $title, string $description, array $locationPools): ?array
    {
        $text = $title.' '.$description;

        if (! preg_match_all('/~mission\(([^)]+)\)/', $text, $matches)) {
            return null;
        }

        $tokens = [];
        foreach ($matches[1] as $tokenContent) {
            $parts = explode('|', $tokenContent);
            $tokens[$parts[0]] = true;
        }

        $purposeToNames = [];
        foreach ($locationPools as $pool) {
            $purpose = $pool['purpose'] ?? null;
            if ($purpose === null) {
                continue;
            }

            $names = [];
            foreach ($pool['resolved_locations'] ?? [] as $loc) {
                if ($loc['name'] !== null && $loc['name'] !== '') {
                    $names[] = $loc['name'];
                }
            }

            if ($names !== []) {
                $purposeToNames[$purpose] = $names;
            }
        }

        $tokenMap = [];
        foreach (array_keys($tokens) as $token) {
            $resolved = match ($token) {
                'Location' => $purposeToNames['Location'] ?? null,
                'Destination' => $purposeToNames['Destination'] ?? null,
                'GoToLocation' => $purposeToNames['GoToLocation'] ?? null,
                'Address' => $purposeToNames['Location'] ?? $purposeToNames['Destination'] ?? null,
                default => null,
            };

            if ($resolved !== null) {
                $tokenMap[$token] = $resolved;
            }
        }

        $tokenMap = collect($tokenMap)
            ->mapWithKeys(fn ($value, $key) => [$key => collect($value)->unique()->values()])->toArray();

        return $tokenMap !== [] ? $tokenMap : null;
    }
}
