<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Extracts the relay power-distribution topology from a ship entity.
 *
 * Reads SInternalHardpointLink elements to build the relay->hardpoint graph,
 * enriches relay entries with fuse slot counts from the loadout, and maps
 * optional room names from SVehicleObjectContainerParams.
 *
 * @extends BaseFormat<RootDocument>
 */
final class RelayNetwork extends BaseFormat
{
    protected ?string $elementKey = 'Components/SItemPortContainerComponentParams/InternalHardpointLinks';

    /** @var array Raw loadout array from VehicleWrapper (entries with portName, className, Item, etc.) */
    private readonly array $loadout;

    /**
     * @param  RootDocument|Element  $entity  Ship entity (VehicleDefinition)
     * @param  array  $loadout  Raw loadout entries from VehicleWrapper
     */
    public function __construct(RootDocument|Element $entity, array $loadout)
    {
        parent::__construct($entity);
        $this->loadout = $loadout;
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $entity = $this->item;
        $linkElements = $entity->getAll(
            'Components/SItemPortContainerComponentParams/InternalHardpointLinks/SInternalHardpointLink'
        );

        if ($linkElements === []) {
            return null;
        }

        $links = [];
        $relayNames = [];

        foreach ($linkElements as $linkEl) {
            if (! ($linkEl instanceof Element)) {
                continue;
            }

            $from = $linkEl->get('@port1');
            $to = $linkEl->get('@port2');

            if ($from === null || $to === null) {
                continue;
            }

            $links[] = ['From' => $from, 'To' => $to];

            if (str_starts_with($from, 'hardpoint_relay')) {
                $relayNames[$from] = true;
            }

            if (str_starts_with($to, 'hardpoint_relay')) {
                $relayNames[$to] = true;
            }
        }

        if ($links === []) {
            return null;
        }

        $roomMap = $this->buildRoomMap();

        $loadoutByPort = $this->buildLoadoutLookup();

        $connectedByRelay = [];

        foreach ($links as $link) {
            $from = $link['From'];
            $to = $link['To'];

            if (str_starts_with($from, 'hardpoint_relay')) {
                $connectedByRelay[$from][] = $to;
            }
        }

        $relays = [];
        $totalFuses = 0;

        foreach (array_keys($relayNames) as $relayName) {
            $loadoutEntry = $loadoutByPort[strtolower($relayName)] ?? null;
            $className = $loadoutEntry['className'] ?? null;
            $fuseSlots = $this->countFuseSlots($loadoutEntry, $className);

            $relays[] = [
                'HardpointName' => $relayName,
                'ClassName' => $className,
                'FuseSlots' => $fuseSlots,
                'Room' => $roomMap[$relayName] ?? null,
                'ConnectedHardpoints' => $connectedByRelay[$relayName] ?? [],
            ];

            $totalFuses += $fuseSlots;
        }

        return [
            'Relays' => $relays,
            'Links' => $links,
            'TotalFuses' => $totalFuses,
        ];
    }

    /**
     * Build a map from relay hardpoint name to room (boneName).
     *
     * Reads SVehicleObjectContainerParams entries that have a
     * resourceNetworkItemportName attribute and maps it to boneName.
     *
     * @return array<string, string>
     */
    private function buildRoomMap(): array
    {
        $map = [];

        $ocRefs = $this->item->getAll(
            'Components/VehicleComponentParams/objectContainers/SVehicleObjectContainerParams'
        );

        foreach ($ocRefs as $ocRef) {
            if (! ($ocRef instanceof Element)) {
                continue;
            }

            $portName = $ocRef->get('@resourceNetworkItemportName');
            $boneName = $ocRef->get('@boneName');

            if ($portName !== null && $boneName !== null) {
                $map[$portName] = $boneName;
            }
        }

        return $map;
    }

    /**
     * Build a lookup table from lowercase port name to loadout entry.
     *
     * Walks the raw loadout recursively to find entries whose portName
     * starts with "hardpoint_relay".
     *
     * @return array<string, array>
     */
    private function buildLoadoutLookup(): array
    {
        $lookup = [];
        $this->walkLoadout($this->loadout, $lookup);

        return $lookup;
    }

    /**
     * Recursively walk loadout entries, collecting relay entries by port name.
     *
     * @param  array  $entries  Loadout entries to walk
     * @param  array  &$lookup  Accumulator: lowercase port name -> entry
     */
    private function walkLoadout(array $entries, array &$lookup): void
    {
        foreach ($entries as $entry) {
            $portName = $entry['portName'] ?? null;

            if ($portName !== null && str_starts_with($portName, 'hardpoint_relay')) {
                $lookup[strtolower($portName)] = $entry;
            }

            $nested = $entry['entries'] ?? [];

            if ($nested !== []) {
                $this->walkLoadout($nested, $lookup);
            }
        }
    }

    /**
     * Count fuse slots for a relay from its loadout entry.
     *
     * Strategy 1: Count ports named "$slot_fuse_*" in the installed item's port list.
     * Strategy 2: Parse the relay class name (e.g. "RELAY_1slot" -> 1).
     *
     * @param  array|null  $loadoutEntry  Raw loadout entry for the relay
     * @param  string|null  $className  Relay entity class name (e.g. "RELAY_1slot")
     */
    private function countFuseSlots(?array $loadoutEntry, ?string $className): int
    {
        if ($loadoutEntry !== null) {
            $ports = $loadoutEntry['Item']['stdItem']['Ports'] ?? [];

            $fuseCount = 0;

            foreach ($ports as $port) {
                $portName = $port['PortName'] ?? $port['Name'] ?? '';

                if (str_starts_with($portName, '$slot_fuse')) {
                    $fuseCount++;
                }
            }

            if ($fuseCount > 0) {
                return $fuseCount;
            }
        }

        if ($className !== null && preg_match('/RELAY_(\d+)slot/i', $className, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }
}
