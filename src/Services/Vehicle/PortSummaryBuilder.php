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
            'upThrusters' => [
                'predicate' => fn ($x) => $this->isDirectionalManeuverThruster($x, ['bottom', 'down', 'lower']),
                'excludeChildren' => [],
            ],
            'downThrusters' => [
                'predicate' => fn ($x) => $this->isDirectionalManeuverThruster($x, ['top', 'upper', 'up']),
                'excludeChildren' => [],
            ],
            'strafeThrusters' => [
                'predicate' => fn ($x) => $this->isStrafeThruster($x),
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
            $stopPredicate = static fn ($x) => in_array($x['Category'] ?? '', $config['excludeChildren'], true);
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

    private function isDirectionalManeuverThruster(array $item, array $tokens): bool
    {
        if (! $this->isManeuverThruster($item)) {
            return false;
        }

        if ($this->matchesPortName($item, ['vtol'])) {
            return false;
        }

        return $this->matchesPortName($item, $tokens);
    }

    private function isStrafeThruster(array $item): bool
    {
        if (! $this->isManeuverThruster($item)) {
            return false;
        }

        if ($this->matchesPortName($item, ['vtol'])) {
            return false;
        }

        if ($this->matchesPortName($item, ['side', 'strafe', 'lateral'])) {
            return true;
        }

        $name = $this->getPortName($item);
        if ($name === '') {
            return false;
        }

        $hasLeftRight = str_contains($name, 'left') || str_contains($name, 'right');
        $hasVertical = str_contains($name, 'top') || str_contains($name, 'bottom') || str_contains($name, 'upper') || str_contains($name, 'lower');

        return $hasLeftRight && ! $hasVertical;
    }

    private function isManeuverThruster(array $item): bool
    {
        if (($item['Category'] ?? null) === 'Maneuvering thrusters') {
            return true;
        }

        $installed = $item['InstalledItem'] ?? null;
        if (! is_array($installed)) {
            return false;
        }

        $classification = $installed['classification'] ?? null;

        return is_string($classification) && str_contains($classification, 'ManneuverThruster');
    }

    private function matchesPortName(array $item, array $tokens): bool
    {
        $name = $this->getPortName($item);
        if ($name === '') {
            return false;
        }

        return array_any($tokens, fn ($token) => $token !== '' && str_contains($name, $token));
    }

    private function getPortName(array $item): string
    {
        $name = $item['PortName'] ?? null;

        if (! $name && isset($item['Port']) && is_array($item['Port'])) {
            $name = $item['Port']['PortName'] ?? $item['Port']['Name'] ?? null;
        }

        if (! $name) {
            $name = $item['Name'] ?? null;
        }

        if (! $name && isset($item['Port']) && is_array($item['Port'])) {
            $name = $item['Port']['DisplayName'] ?? null;
        }

        return strtolower((string) $name);
    }
}
