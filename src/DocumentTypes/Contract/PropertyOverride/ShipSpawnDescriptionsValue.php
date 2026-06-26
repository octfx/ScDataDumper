<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class ShipSpawnDescriptionsValue extends RootDocument
{
    public function isAllowedForMissionRestrictedDeliveries(): bool
    {
        return $this->getBool('@allowedForMissionRestrictedDeliveries');
    }

    /**
     * @return list<array{name: ?string, shipOptions: list<array{ships: list<array{concurrentAmount: int, includeLocationAISpawnTags: bool, weight: int, initialDamageSettings: ?string, tags: list<string>, negativeTags: list<string>, markupTags: list<string>}>}>}>
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
                    $ships[] = [
                        'concurrentAmount' => (int) ($ship->get('@concurrentAmount') ?? 1),
                        'includeLocationAISpawnTags' => (int) ($ship->get('@includeLocationAISpawnTags') ?? 0) === 1,
                        'weight' => (int) ($ship->get('@weight') ?? 1),
                        'initialDamageSettings' => $ship->get('@initialDamageSettings'),
                        'tags' => $this->referenceValues($ship, 'tags/tags/Reference@value'),
                        'negativeTags' => $this->referenceValues($ship, 'negativeTags/tags/Reference@value'),
                        'markupTags' => $this->referenceValues($ship, 'markupTags/tags/Reference@value'),
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
     * @return list<string>
     */
    private function referenceValues(Element $ship, string $path): array
    {
        $values = [];

        foreach ($ship->getAll($path) as $val) {
            if (is_string($val) && $val !== '') {
                $values[] = $val;
            }
        }

        return $values;
    }

    /**
     * @param  string  $variableName
     * @return list<array{spawn_kind: string, role: string, group_name: ?string, concurrent_amount: int, weight: int, initial_damage_settings: ?string, ships: list<array{className: ?string, name: string}>}>
     */
    public function toArray(string $variableName = ''): array
    {
        $role = self::resolveShipRole($variableName);
        $resolver = ServiceFactory::getShipPoolResolver();
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
                        'ships' => $resolver->resolve($ship['tags'], $ship['negativeTags']),
                    ];
                }
            }
        }

        return $rows;
    }
}
