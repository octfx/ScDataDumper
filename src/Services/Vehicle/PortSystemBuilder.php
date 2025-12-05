<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\Arr;

/**
 * Build port system from multiple sources
 *
 * Handles port building from Elements (XML nodes), array definitions, and parts hierarchy.
 */
final class PortSystemBuilder
{
    public function __construct(
        private readonly PortMapper $portMapper,
        private readonly CompatibleTypesExtractor $typesExtractor
    ) {}

    /**
     * Build ports from Element nodes
     *
     * @param  iterable  $ports  Port Element nodes
     * @param  Collection  $loadouts  Loadout entries
     * @return array Built ports
     */
    public function buildFromElements(iterable $ports, Collection $loadouts): array
    {
        $mapped = [];

        foreach ($ports as $port) {
            $portName = $port->get('Name');
            $loadout = $loadouts->first(fn ($x) => strcasecmp($x['portName'] ?? '', $portName ?? '') === 0);

            $childPorts = [];
            if ($loadout && ! empty($loadout['entries'])) {
                $childPorts = $this->buildFromArrayDefs(
                    Arr::get($loadout, 'Item.Components.SItemPortContainerComponentParams.Ports', []),
                    $loadout['entries']
                );
            }

            $mapped[] = $this->portMapper->mapPort(
                [
                    'name' => $portName,
                    'min' => $port->get('MinSize'),
                    'max' => $port->get('MaxSize'),
                    'types' => $this->typesExtractor->fromElement($port),
                ],
                $loadout,
                $childPorts
            );
        }

        // Add unmatched loadouts
        $matchedPortNames = array_map(static fn ($port) => $port['name'] ?? null, $mapped);
        foreach ($loadouts as $loadout) {
            $loadoutPortName = $loadout['portName'] ?? null;
            if ($loadoutPortName === null) {
                continue;
            }

            $wasMatched = false;
            foreach ($matchedPortNames as $matchedName) {
                if (strcasecmp($matchedName, $loadoutPortName) === 0) {
                    $wasMatched = true;
                    break;
                }
            }

            if (! $wasMatched) {
                $childPorts = [];
                if (! empty($loadout['entries'])) {
                    $childPorts = $this->buildFromArrayDefs(
                        Arr::get($loadout, 'Item.Components.SItemPortContainerComponentParams.Ports', []),
                        $loadout['entries']
                    );
                }

                $mapped[] = $this->portMapper->mapPort(
                    ['name' => $loadoutPortName],
                    $loadout,
                    $childPorts
                );
            }
        }

        return $mapped;
    }

    /**
     * Build ports from array definitions
     *
     * @param  array  $portDefs  Port definition arrays
     * @param  array  $loadouts  Loadout entries
     * @return array Built ports
     */
    public function buildFromArrayDefs(array $portDefs, array $loadouts): array
    {
        $mapped = [];
        $loadoutCollection = collect($loadouts);

        foreach ($portDefs as $port) {
            $portName = $port['Name'] ?? $port['name'] ?? null;

            if ($portName === null) {
                continue;
            }

            $loadout = $loadoutCollection->first(fn ($x) => strcasecmp($x['portName'] ?? '', $portName) === 0);

            $childPorts = [];
            if ($loadout && ! empty($loadout['entries'])) {
                $childPorts = $this->buildFromArrayDefs(
                    Arr::get($loadout, 'Item.Components.SItemPortContainerComponentParams.Ports', []),
                    $loadout['entries']
                );
            }

            $mapped[] = $this->portMapper->mapPort(
                [
                    'name' => $portName,
                    'min' => Arr::get($port, 'MinSize'),
                    'max' => Arr::get($port, 'MaxSize'),
                    'types' => $this->typesExtractor->fromArray($port),
                ],
                $loadout,
                $childPorts
            );
        }

        // Add unmatched loadouts
        foreach ($loadouts as $loadout) {
            if ($loadoutCollection->first(fn ($x) => strcasecmp($x['portName'] ?? '', $loadout['portName'] ?? '') === 0, null) === null) {
                $mapped[] = $this->portMapper->mapPort(
                    ['name' => $loadout['portName'] ?? null],
                    $loadout,
                    []
                );
            }
        }

        return $mapped;
    }

    /**
     * Extract ports from parts hierarchy
     *
     * @param  array  $parts  Parts array
     * @return array Extracted port definitions
     */
    public function extractFromParts(array $parts): array
    {
        $ports = [];

        foreach ($parts as $part) {
            if (isset($part['Port']) && is_array($part['Port'])) {
                $portName = $part['Port']['PortName'] ?? $part['Port']['Name'] ?? $part['Name'] ?? null;

                if ($portName !== null) {
                    $ports[] = [
                        'name' => $portName,
                        'sizes' => [
                            'min' => $part['Port']['MinSize'] ?? null,
                            'max' => $part['Port']['MaxSize'] ?? null,
                        ],
                        'class_name' => null,
                        'health' => null,
                        'compatible_types' => $part['Port']['CompatibleTypes'] ?? [],
                    ];
                }
            }

            // Recursively process child parts
            if (isset($part['Parts']) && is_array($part['Parts'])) {
                $childPorts = $this->extractFromParts($part['Parts']);
                $ports = array_merge($ports, $childPorts);
            }
        }

        return $ports;
    }
}
