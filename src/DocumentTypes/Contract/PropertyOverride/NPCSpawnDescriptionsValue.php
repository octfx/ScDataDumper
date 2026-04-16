<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class NPCSpawnDescriptionsValue extends RootDocument
{
    /**
     * @return list<string>
     */
    private function extractTagList(object $node, string $path): array
    {
        $tags = [];
        foreach ($node->getAll($path) as $val) {
            if (is_string($val) && $val !== '') {
                $tags[] = $val;
            }
        }

        return $tags;
    }

    /**
     * @return list<array{name: ?string, options: list<array{priority: int, includeLocationAISpawnTags: bool, weight: int, autoSpawnSettings: array{name: ?string, initialActivity: ?string, excludeShipCrew: bool, excludeSpawnGender: ?string, minGroupSize: int, maxGroupSize: int, maxConcurrentSpawns: int, maxSpawns: int, minSpawnDelay: int, maxSpawnDelay: int, missionAlliedMarker: bool, isCritical: bool, positiveCharacterTags: list<string>, closetPositiveTags: list<string>, defendAreaPositiveTags: list<string>, entityTags: list<string>}}>}>
     */
    public function getNPCGroups(): array
    {
        $results = [];
        $groups = $this->getAll('spawnDescriptions/SpawnDescription_NPC_Group');

        foreach ($groups as $group) {
            $options = [];
            $optionNodes = $group->getAll('options/SpawnDescription_NPCOption');

            foreach ($optionNodes as $optionNode) {
                $settings = $optionNode->get('autoSpawnSettings');
                $autoSpawn = [
                    'name' => null,
                    'initialActivity' => null,
                    'excludeShipCrew' => false,
                    'excludeSpawnGender' => null,
                    'minGroupSize' => 1,
                    'maxGroupSize' => 1,
                    'maxConcurrentSpawns' => 1,
                    'maxSpawns' => 1,
                    'minSpawnDelay' => 0,
                    'maxSpawnDelay' => 0,
                    'missionAlliedMarker' => false,
                    'isCritical' => false,
                    'positiveCharacterTags' => [],
                    'closetPositiveTags' => [],
                    'defendAreaPositiveTags' => [],
                    'entityTags' => [],
                ];

                if ($settings !== null) {
                    $autoSpawn = [
                        'name' => $settings->get('@name'),
                        'initialActivity' => $settings->get('@initialActivity'),
                        'excludeShipCrew' => (int) ($settings->get('@excludeShipCrew') ?? 0) === 1,
                        'excludeSpawnGender' => $settings->get('@excludeSpawnGender'),
                        'minGroupSize' => (int) ($settings->get('@minGroupSize') ?? 1),
                        'maxGroupSize' => (int) ($settings->get('@maxGroupSize') ?? 1),
                        'maxConcurrentSpawns' => (int) ($settings->get('@maxConcurrentSpawns') ?? 1),
                        'maxSpawns' => (int) ($settings->get('@maxSpawns') ?? 1),
                        'minSpawnDelay' => (int) ($settings->get('@minSpawnDelay') ?? 0),
                        'maxSpawnDelay' => (int) ($settings->get('@maxSpawnDelay') ?? 0),
                        'missionAlliedMarker' => (int) ($settings->get('@missionAlliedMarker') ?? 0) === 1,
                        'isCritical' => (int) ($settings->get('@isCritical') ?? 0) === 1,
                        'positiveCharacterTags' => $this->extractTagList($settings, 'positiveCharacterTags/tags/Reference@value'),
                        'closetPositiveTags' => $this->extractTagList($settings, 'closetPositiveTags/tags/Reference@value'),
                        'defendAreaPositiveTags' => $this->extractTagList($settings, 'defendAreaPositiveTags/tags/Reference@value'),
                        'entityTags' => $this->extractTagList($settings, 'entityTags/tags/Reference@value'),
                    ];
                }

                $options[] = [
                    'priority' => (int) ($optionNode->get('@priority') ?? 0),
                    'includeLocationAISpawnTags' => (int) ($optionNode->get('@includeLocationAISpawnTags') ?? 0) === 1,
                    'weight' => (int) ($optionNode->get('@weight') ?? 1),
                    'autoSpawnSettings' => $autoSpawn,
                ];
            }

            $results[] = [
                'name' => $group->get('@Name'),
                'options' => $options,
            ];
        }

        return $results;
    }

    /**
     * @param  string  $variableName  The mission variable name from the override
     * @return list<array{spawn_kind: string, group_name: ?string, name: ?string, initial_activity: ?string, exclude_ship_crew: bool, exclude_spawn_gender: ?string, min_group_size: int, max_group_size: int, max_concurrent_spawns: int, max_spawns: int, min_spawn_delay: int, max_spawn_delay: int, mission_allied_marker: bool, is_critical: bool, positive_character_tags: list<string>, closet_positive_tags: list<string>, defend_area_positive_tags: list<string>, entity_tags: list<string>}>
     */
    public function toArray(string $variableName = ''): array
    {
        $rows = [];

        foreach ($this->getNPCGroups() as $group) {
            foreach ($group['options'] as $option) {
                $settings = $option['autoSpawnSettings'] ?? [];
                $rows[] = [
                    'spawn_kind' => 'Npc',
                    'group_name' => $group['name'],
                    'name' => $settings['name'] ?? null,
                    'initial_activity' => $settings['initialActivity'] ?? null,
                    'exclude_ship_crew' => $settings['excludeShipCrew'] ?? false,
                    'exclude_spawn_gender' => $settings['excludeSpawnGender'] ?? null,
                    'min_group_size' => $settings['minGroupSize'] ?? 1,
                    'max_group_size' => $settings['maxGroupSize'] ?? 1,
                    'max_concurrent_spawns' => $settings['maxConcurrentSpawns'] ?? 1,
                    'max_spawns' => $settings['maxSpawns'] ?? 1,
                    'min_spawn_delay' => $settings['minSpawnDelay'] ?? 0,
                    'max_spawn_delay' => $settings['maxSpawnDelay'] ?? 0,
                    'mission_allied_marker' => $settings['missionAlliedMarker'] ?? false,
                    'is_critical' => $settings['isCritical'] ?? false,
                    'positive_character_tags' => $settings['positiveCharacterTags'] ?? [],
                    'closet_positive_tags' => $settings['closetPositiveTags'] ?? [],
                    'defend_area_positive_tags' => $settings['defendAreaPositiveTags'] ?? [],
                    'entity_tags' => $settings['entityTags'] ?? [],
                ];
            }
        }

        return $rows;
    }
}
