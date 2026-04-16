<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class ShipSpawnDescriptionsValue extends RootDocument
{
    public function isAllowedForMissionRestrictedDeliveries(): bool
    {
        return $this->getBool('@allowedForMissionRestrictedDeliveries');
    }

    /**
     * @return list<array{name: ?string, shipOptions: list<array{ships: list<array{concurrentAmount: int, includeLocationAISpawnTags: bool, weight: int, initialDamageSettings: ?string, tags: list<string>, markupTags: list<string>}>}>}>
     */
    public function getShipGroups(): array
    {
        $results = [];
        $groups = $this->getAll('spawnDescriptions/SpawnDescription_ShipGroup');

        foreach ($groups as $group) {
            $shipOptionsList = [];
            $optionsContainers = $group->getAll('ships/SpawnDescription_ShipOptions');

            foreach ($optionsContainers as $optionsContainer) {
                $ships = [];
                $shipNodes = $optionsContainer->getAll('options/SpawnDescription_Ship');

                foreach ($shipNodes as $ship) {
                    $tags = [];
                    foreach ($ship->getAll('tags/tags/Reference@value') as $val) {
                        if (is_string($val) && $val !== '') {
                            $tags[] = $val;
                        }
                    }

                    $markupTags = [];
                    foreach ($ship->getAll('markupTags/tags/Reference@value') as $val) {
                        if (is_string($val) && $val !== '') {
                            $markupTags[] = $val;
                        }
                    }

                    $ships[] = [
                        'concurrentAmount' => (int) ($ship->get('@concurrentAmount') ?? 1),
                        'includeLocationAISpawnTags' => (int) ($ship->get('@includeLocationAISpawnTags') ?? 0) === 1,
                        'weight' => (int) ($ship->get('@weight') ?? 1),
                        'initialDamageSettings' => $ship->get('@initialDamageSettings'),
                        'tags' => $tags,
                        'markupTags' => $markupTags,
                    ];
                }

                $shipOptionsList[] = ['ships' => $ships];
            }

            $results[] = [
                'name' => $group->get('@Name'),
                'shipOptions' => $shipOptionsList,
            ];
        }

        return $results;
    }

    public static function resolveShipRole(string $variableName): string
    {
        return match (true) {
            str_contains($variableName, 'Hostile'), str_contains($variableName, 'WaveShips'), str_contains($variableName, 'FinalWave'), str_contains($variableName, 'Defending') => 'enemy',
            str_contains($variableName, 'Attacked') => 'defend_target',
            str_contains($variableName, 'Escort') => 'escort_target',
            str_contains($variableName, 'Salvage'), str_contains($variableName, 'Cargo'), str_contains($variableName, 'Heist') => 'neutral',
            default => 'unknown',
        };
    }

    /**
     * @param  string  $variableName  The mission variable name from the override
     * @return list<array{spawn_kind: string, role: string, group_name: ?string, concurrent_amount: int, weight: int, initial_damage_settings: ?string}>
     */
    public function toArray(string $variableName = ''): array
    {
        $role = self::resolveShipRole($variableName);
        $rows = [];

        foreach ($this->getShipGroups() as $group) {
            foreach ($group['shipOptions'] as $option) {
                foreach ($option['ships'] as $ship) {
                    $rows[] = [
                        'spawn_kind' => 'Ship',
                        'role' => $role,
                        'group_name' => $group['name'],
                        'concurrent_amount' => $ship['concurrentAmount'],
                        'weight' => $ship['weight'],
                        'initial_damage_settings' => $ship['initialDamageSettings'],
                    ];
                }
            }
        }

        return $rows;
    }
}
