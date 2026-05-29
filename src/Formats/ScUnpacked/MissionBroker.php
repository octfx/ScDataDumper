<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\Concerns\FormatsMissionBrokerEntries;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MissionBroker extends BaseFormat
{
    use FormatsMissionBrokerEntries;

    /**
     * @param  array<string, string>  $mbeChainIndex Maps lowercased MBE UUID to debug/class name.
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

        $data = $this->transformArrayKeysToPascalCase($this->removeNullValuesPreservingEmptyArrays([
            'uuid' => $this->entry->getUuid(),
            'debug_name' => $this->entry->getClassName(),
            'type' => 'MissionBrokerEntry',
            'mission_type' => $this->resolveMbeMissionTypeInfo($this->entry->getMissionTypeUuid()),
            'mission_giver' => $this->resolveMbeMissionGiver($this->entry),
            'title' => $title,
            'display_title' => $this->buildDisplayTextFromMissionTokens($title, $missionTokens),
            'description' => $description,
            'display_description' => $this->buildDisplayTextFromMissionTokens($description, $missionTokens),
            'location_pools' => $locationPools,
            'mission_tokens' => $missionTokens,
            ...$this->buildMbeRewardFields($this->entry),
            'hauling_orders' => $this->buildMbeHaulingOrders($this->entry),
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
}
