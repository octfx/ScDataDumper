<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\BuildsMissionItemHaulingOrders;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\FormatsMissionBrokerEntries;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MissionBroker extends BaseFormat
{
    use BuildsMissionItemHaulingOrders;
    use FormatsMissionBrokerEntries;

    /**
     * @param  array<string, string>  $mbeChainIndex  Maps lowercased MBE UUID to debug/class name.
     */
    public function __construct(
        protected MissionBrokerEntry $entry,
        protected array $mbeChainIndex = [],
    ) {
        parent::__construct($entry);
    }

    public function toArray(): ?array
    {
        $title = $this->translateMbeText($this->entry->getTitle());
        $description = $this->translateMbeText($this->entry->getDescription());
        $locationPools = $this->buildMbeLocationPools($this->entry);
        $missionTokens = $this->buildMbeMissionTokens($this->entry, $title, $description, $locationPools);
        $standingReqs = $this->buildMbeStandingRequirements($this->entry);
        $resolvedReputation = $this->buildMbeFaction($this->entry);
        $crimeStat = $this->buildCrimeStat();
        $displayTitle = $this->buildDisplayTextFromMissionTokens($title, $missionTokens);
        $displayDescription = $this->buildDisplayTextFromMissionTokens($description, $missionTokens);
        $missionTokens = $this->resolveTokenValueReferences($missionTokens ?? []);
        $haulingOrders = $this->buildMbeHaulingOrders($this->entry);
        $itemCounts = $this->buildMbeItemCounts();

        if ($itemCounts !== null) {
            $haulingOrders = $this->enrichMissionItemHaulingOrdersFromItemCounts($haulingOrders, $itemCounts);

            if ($haulingOrders === []) {
                $haulingOrders = $this->buildMissionItemHaulingOrdersFromItemCounts($itemCounts);
            }
        }

        $data = $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $this->entry->getUuid(),
            'debug_name' => $this->entry->getClassName(),
            'type' => 'MissionBrokerEntry',
            'mission_type' => $this->resolveMbeMissionTypeInfo($this->entry->getMissionTypeUuid()),
            'mission_giver' => $this->resolveMbeMissionGiver($this->entry),
            'title' => $title,
            'display_title' => $displayTitle,
            'description' => $description,
            'display_description' => $displayDescription,
            'location_pools' => $locationPools,
            'mission_tokens' => $missionTokens,
            ...$this->buildMbeRewardFields($this->entry),
            'hauling_orders' => $haulingOrders,
            'min_standing' => $standingReqs['min'],
            'max_standing' => $standingReqs['max'],
            'rank_index' => $standingReqs['rank_index'],
            'crime_stat' => $crimeStat,
            'availability_locations' => $this->buildMbeAvailabilityLocations($this->entry),
            ...$this->buildProperties(),
            'generator_class' => null,
            'not_for_release' => $this->entry->isNotForRelease(),
            'work_in_progress' => $this->entry->isWorkInProgress(),
            'lifetime' => $this->buildLifetime(),
            'faction' => $resolvedReputation['faction_reputation'] ?? null,
            'reputation_scope' => $resolvedReputation['reputation_scope']['scope_name'] ?? null,
            'completion_tags' => $this->buildCompletionTags(),
            'required_missions' => $requiredMissions = $this->buildRequiredMissions(),
            'prerequisites' => $this->buildPrerequisites($requiredMissions),
            'item_counts' => $itemCounts,
        ]));

        $data['GeneratorClass'] = null;

        return $data;
    }

    private function buildCrimeStat(): ?array
    {
        $prerequisites = $this->entry->getReputationPrerequisites();
        if ($prerequisites === null) {
            return null;
        }

        return [
            'min' => $prerequisites['min_wanted_level'],
            'max' => $prerequisites['max_wanted_level'],
        ];
    }

    private function buildProperties(): array
    {
        return [
            'shareable' => $this->entry->canBeShared(),
            'illegal' => ! $this->entry->isLawful(),
            'once_only' => $this->entry->isOnceOnly(),
            'max_players_per_instance' => $this->entry->getMaxPlayersPerInstance(),
            'reaccept_after_abandoning' => $this->entry->canReacceptAfterAbandoning(),
            'reaccept_after_failing' => $this->entry->canReacceptAfterFailing(),
            'cooldown' => [
                'abandoned_seconds' => $this->entry->getAbandonedCooldownTime(),
                'abandoned_variation_seconds' => $this->entry->getAbandonedCooldownTimeVariation(),
                'personal_seconds' => $this->entry->getPersonalCooldownTime(),
                'personal_variation_seconds' => $this->entry->getPersonalCooldownTimeVariation(),
            ],
            'available_in_prison' => $this->entry->isAvailableInPrison(),
            'fail_if_became_criminal' => $this->entry->failIfBecameCriminal(),
        ];
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

    private function buildCompletionTags(): ?array
    {
        $tags = $this->entry->getCompletionTagUuids();

        return $tags !== []
            ? ServiceFactory::getTagDatabaseService()->resolveUuidsToNameObjects($tags)
            : null;
    }

    private function buildRequiredMissions(): ?array
    {
        $required = [];

        foreach ($this->entry->getRequiredMissionUuids() as $uuid) {
            $required[] = [
                'uuid' => $uuid,
                'debug_name' => $this->mbeChainIndex[strtolower($uuid)] ?? null,
            ];
        }

        return $required !== [] ? $required : null;
    }

    private function buildPrerequisites(?array $requiredMissions): ?array
    {
        if ($requiredMissions === null) {
            return null;
        }

        return [[
            'required_count' => count($requiredMissions),
            'excluded_count' => 0,
            'required_tags' => [],
            'excluded_tags' => [],
            'required_missions' => $requiredMissions,
        ]];
    }

    private function buildMbeItemCounts(): ?array
    {
        $itemProperties = array_values(array_filter(
            $this->entry->getProperties(),
            static fn (array $property): bool => $property['valueTypeName'] === 'MissionPropertyValue_MissionItem',
        ));

        if ($itemProperties === []) {
            return null;
        }

        $itemService = ServiceFactory::getItemService();
        $lookup = ServiceFactory::getFoundryLookupService();

        $merged = [];
        $allTagTerms = [];

        foreach ($itemProperties as $property) {
            $specificItemUuids = $property['specificItems'] ?? [];

            // MissionItem properties can also encode dropoff/pickup method selectors (RandomDropoff,
            // DeadDrop, race classes): their references point at MissionItem records without an
            // entityClass. Those markers describe no hauled cargo and must not inflate the counts.
            if ($specificItemUuids !== [] && $this->isSelectorMarkerProperty($specificItemUuids)) {
                continue;
            }

            $min = $property['minItemsToFind'] ?? 0;
            $max = $property['maxItemsToFind'] ?? 0;

            // Mission variables are independent requirements (find N of A AND M of B),
            // so the merged counts accumulate instead of the last property winning.
            if ($min > 0) {
                $merged['min_items'] = ($merged['min_items'] ?? 0) + $min;
            }

            if ($max > 0) {
                $merged['max_items'] = ($merged['max_items'] ?? 0) + $max;
            }

            if ($specificItemUuids !== []) {
                foreach ($specificItemUuids as $uuid) {
                    $entityClassUuid = $lookup->resolveMissionItemEntityClass($uuid);

                    if ($entityClassUuid === null) {
                        continue;
                    }

                    $merged['items'][$entityClassUuid] ??= [
                        'uuid' => $entityClassUuid,
                        'name' => $itemService->getByReference($entityClassUuid)?->getDisplayName(),
                    ];
                }
            }

            foreach ($property['tagSearchTerms'] ?? [] as $term) {
                $allTagTerms[] = $term;
            }
        }

        if (isset($merged['items'])) {
            $merged['items'] = array_values($merged['items']);
        }

        if ($allTagTerms !== []) {
            $merged['tag_search_terms'] = ServiceFactory::getTagDatabaseService()->resolveTagSearchTermsNames($allTagTerms);
        }

        return $merged !== [] ? $merged : null;
    }

    /**
     * A MissionItem property is a selector marker when every referenced MissionItem record
     * exists but carries no entityClass (e.g. DeliveryRandomTarget, DeliveryDeadDrop): it picks
     * a dropoff/pickup method instead of describing cargo.
     *
     * @param  list<string>  $specificItemUuids
     */
    private function isSelectorMarkerProperty(array $specificItemUuids): bool
    {
        $lookup = ServiceFactory::getFoundryLookupService();

        foreach ($specificItemUuids as $uuid) {
            if ($lookup->resolveMissionItemEntityClass($uuid) !== null) {
                return false;
            }

            // Records that fail to load are unresolved data, not intentional markers.
            if ($lookup->resolveMissionItemClassName($uuid) === null) {
                return false;
            }
        }

        return true;
    }
}
