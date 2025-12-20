<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\ValueObjects\PortFinderOptions;

/**
 * Build port summary with categorized ports
 *
 * Categorizes all ship ports by type using data-driven configuration.
 */
final class PortSummaryBuilder
{
    private array $categoryDefinitions;

    public function __construct(
        private readonly PortFinder $portFinder,
        private readonly PortClassifierService $portClassifier
    ) {
        $this->categoryDefinitions = [
            'pilotHardpoints' => [
                'category' => 'Weapon hardpoints',
                'excludeChildren' => ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets'],
            ],
            'miningHardpoints' => [
                'category' => 'Mining hardpoints',
                'excludeChildren' => ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets'],
            ],
            'utilityHardpoints' => [
                'category' => 'Utility hardpoints',
                'excludeChildren' => ['Manned turrets', 'Remote turrets', 'Mining turrets', 'Utility turrets'],
            ],
            'miningTurrets' => [
                'category' => 'Mining turrets',
                'excludeChildren' => [],
            ],
            'mannedTurrets' => [
                'category' => 'Manned turrets',
                'excludeChildren' => [],
            ],
            'remoteTurrets' => [
                'category' => 'Remote turrets',
                'excludeChildren' => [],
            ],
            'utilityTurrets' => [
                'category' => 'Utility turrets',
                'excludeChildren' => [],
            ],
            'interdictionHardpoints' => [
                'categories' => ['EMP hardpoints', 'QIG hardpoints'],
                'excludeChildren' => [],
            ],
            'missileRacks' => [
                'category' => 'Missile racks',
                'excludeChildren' => [],
            ],
            'powerPlants' => [
                'category' => 'Power plants',
                'excludeChildren' => [],
            ],
            'coolers' => [
                'category' => 'Coolers',
                'excludeChildren' => [],
            ],
            'shields' => [
                'category' => 'Shield generators',
                'excludeChildren' => [],
            ],
            'cargoGrids' => [
                'predicate' => fn ($x) => isset($x['InstalledItem']['InventoryContainer']),
                'excludeChildren' => [],
            ],
            'countermeasures' => [
                'category' => 'Countermeasures',
                'excludeChildren' => [],
            ],
            'mainThrusters' => [
                'category' => 'Main thrusters',
                'excludeChildren' => [],
            ],
            'retroThrusters' => [
                'category' => 'Retro thrusters',
                'excludeChildren' => [],
            ],
            'vtolThrusters' => [
                'category' => 'VTOL thrusters',
                'excludeChildren' => [],
            ],
            'maneuveringThrusters' => [
                'category' => 'Maneuvering thrusters',
                'excludeChildren' => [],
            ],
            'hydrogenFuelIntakes' => [
                'category' => 'Fuel intakes',
                'excludeChildren' => [],
            ],
            'hydrogenFuelTanks' => [
                'category' => 'Fuel tanks',
                'excludeChildren' => [],
            ],
            'quantumDrives' => [
                'category' => 'Quantum drives',
                'excludeChildren' => [],
            ],
            'quantumFuelTanks' => [
                'category' => 'Quantum fuel tanks',
                'excludeChildren' => [],
            ],
            'avionics' => [
                'categories' => ['Scanners', 'Pings', 'Radars', 'Transponders', 'FlightControllers'],
                'excludeChildren' => [],
            ],
        ];
    }

    /**
     * Enrich port summary entries with installed items from loadout
     *
     * @param  array<string, Collection>  $portSummary
     * @return array<string, Collection>
     */
    public function attachInstalledItems(array $portSummary, array $loadout): array
    {
        $loadouts = collect($loadout);

        return collect($portSummary)
            ->mapWithKeys(function ($items, $portName) use ($loadouts) {
                return [
                    $portName => collect($items)->map(function ($item) use ($loadouts) {
                        $loadout = $loadouts->first(fn ($x) => $x['portName'] === $item['Name']);

                        if ($loadout && isset($loadout['Item'])) {
                            $item['InstalledItem'] = $loadout['Item'];
                        }

                        return $item;
                    }),
                ];
            })
            ->all();
    }

    /**
     * Attach loadout items and classify ports on parts tree.
     *
     * @return array Parts tree with InstalledItem and Category fields
     */
    public function preparePartsWithClassification(array $parts, array $loadout): array
    {
        $loadouts = collect($loadout);

        return array_map(function ($part) use ($loadouts) {
            if (isset($part['Parts'])) {
                $part['Parts'] = $this->preparePartsWithClassification($part['Parts'], $loadouts->toArray());
            }

            $loadout = $loadouts->first(fn ($x) => $x['portName'] === ($part['Name'] ?? null));

            if ($loadout && isset($loadout['Item'])) {
                $part['InstalledItem'] = $loadout['Item'];
            }

            $classification = $this->portClassifier->classifyPort(
                $part['Port'] ?? null,
                $part['InstalledItem'] ?? null
            );

            $part['Category'] = $classification[1] ?? null;

            return $part;
        }, $parts);
    }

    /**
     * Flatten parts tree to a collection.
     *
     * @return Collection<int, array>
     */
    public function flattenParts(array $parts): Collection
    {
        $flat = collect();

        foreach ($parts as $part) {
            $flat->push($part);

            if (isset($part['Parts'])) {
                $flat = $flat->concat($this->flattenParts($part['Parts']));
            }
        }

        return $flat;
    }

    /**
     * Build port summary from parts
     *
     * @param  array  $parts  Ship parts array
     * @return array Port summary with categorized ports
     */
    public function build(array $parts): array
    {
        return array_map(function ($config) use ($parts) {
            return $this->findPortsByConfig($parts, $config);
        }, $this->categoryDefinitions);
    }

    /**
     * Find ports matching a configuration
     */
    private function findPortsByConfig(array $parts, array $config): Collection
    {
        $predicate = $this->buildPredicate($config);

        $stopPredicate = null;
        if (! empty($config['excludeChildren'])) {
            $stopPredicate = fn ($x) => in_array($x['Category'] ?? '', $config['excludeChildren'], true);
        }

        $options = new PortFinderOptions(
            stopOnFind: true,
            stopPredicate: $stopPredicate
        );

        return $this->portFinder
            ->findInParts($parts, $predicate, $options)
            ->map(fn ($x) => $x[0]);
    }

    /**
     * Build predicate from configuration
     */
    private function buildPredicate(array $config): callable
    {
        if (isset($config['predicate'])) {
            return $config['predicate'];
        }

        if (isset($config['category'])) {
            return static fn ($x) => ($x['Category'] ?? '') === $config['category'];
        }

        if (isset($config['categories'])) {
            return static fn ($x) => in_array($x['Category'] ?? '', $config['categories'], true);
        }

        return static fn ($x) => false;
    }
}
