<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item as ScUnpackedItem;
use Octfx\ScDataDumper\Formats\ScUnpacked\ItemPort;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\PortClassifierService;

/**
 * Builds StandardisedPart hierarchy with ports and installed items
 *
 * StandardisedPart structure:
 * - Parts have nested Port with InstalledItem
 * - InstalledItem has its own Ports with recursive InstalledItems
 */
final class StandardisedPartBuilder
{
    use ItemResolutionTrait;

    /** @var array<string, Element> */
    private array $entityPortDefinitions;

    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemClassifierService $classifierService,
        private readonly PortClassifierService $portClassifier,
        ?Element $entityPorts = null
    ) {
        $this->entityPortDefinitions = $this->collectEntityPortDefinitions($entityPorts);
    }

    /**
     * Build parts list from Vehicle Parts with loadout installed.
     *
     * @param  iterable  $parts  Vehicle Part elements
     * @param  array  $loadout  Loadout entries from CompleteLoadoutBuilder
     * @return array List of StandardisedPart arrays
     */
    public function buildPartList(iterable $parts, array $loadout, bool $injectVirtualPorts = true): array
    {
        $result = [];

        foreach ($parts as $part) {
            if ($part->get('@skipPart') === '1' || $part->get('skipPart') === '1') {
                continue;
            }

            $standardisedPart = $this->buildPart($part, $loadout);
            if ($standardisedPart !== null) {
                $result[] = $standardisedPart;
            }
        }

        if (! $injectVirtualPorts || $loadout === []) {
            return $result;
        }

        $virtualParts = $this->buildVirtualParts($result, $loadout);

        return array_merge($result, $virtualParts);
    }

    /**
     * Build a single StandardisedPart with port and children.
     */
    private function buildPart(Element $part, array $loadout): ?array
    {
        $name = $part->get('@name') ?? $part->get('name');

        if ($name === null) {
            return null;
        }

        $mass = $this->normalizeMass($part->get('@mass') ?? $part->get('mass'));
        $damageMax = (float) ($part->get('@damageMax') ?? $part->get('damageMax') ?? $part->get('damagemax') ?? 0);

        $standardisedPart = [
            'Name' => $name,
            'DisplayName' => $part->get('@display_name')
                ?? $part->get('@DisplayName')
                ?? $part->get('display_name')
                ?? $part->get('DisplayName'),
            'Mass' => $mass,
            'MaximumDamage' => $damageMax > 0 ? $damageMax : null,
            'ShipDestructionDamage' => $this->calculateShipDestructionDamage($part, $damageMax),
            'PartDetachDamage' => $this->calculatePartDetachDamage($part, $damageMax),
            'Port' => null,
            'Parts' => [],
        ];

        // Build port if present
        $itemPort = $part->get('/ItemPort');
        if ($itemPort !== null) {
            $portName = $this->getPortName($part, $itemPort);
            $loadoutEntry = $this->findLoadoutEntry($portName, $loadout);
            $standardisedPart['Port'] = $this->buildPort($itemPort, $portName, $loadoutEntry);
        }

        // Build child parts recursively
        $childParts = $part->get('/Parts');
        if ($childParts !== null) {
            $standardisedPart['Parts'] = $this->buildPartList($childParts->children(), $loadout, false);
        }

        return $standardisedPart;
    }

    private function collectEntityPortDefinitions(?Element $entityPorts): array
    {
        if ($entityPorts === null) {
            return [];
        }

        $definitions = [];
        foreach ($entityPorts->children() as $portDef) {
            $name = $portDef->get('@Name') ?? $portDef->get('Name');
            if ($name === null) {
                continue;
            }

            $definitions[strtolower($name)] = $portDef;
        }

        return $definitions;
    }

    /**
     * Build virtual parts for loadout entries that have no matching implementation part.
     */
    private function buildVirtualParts(array $existingParts, array $loadout): array
    {
        $usedPorts = [];
        $this->collectUsedPortNames($existingParts, $usedPorts);

        $virtualParts = [];

        foreach ($loadout as $entry) {
            $portName = $entry['portName'] ?? null;
            if ($portName === null) {
                continue;
            }

            $lowerPortName = strtolower($portName);
            if (isset($usedPorts[$lowerPortName])) {
                continue;
            }

            $virtualParts[] = $this->buildVirtualPart($portName, $entry);
            $usedPorts[$lowerPortName] = true;
        }

        return $virtualParts;
    }

    /**
     * Recursively collect port names already present in the parts tree.
     */
    private function collectUsedPortNames(array $parts, array &$names): void
    {
        foreach ($parts as $part) {
            $portName = $part['Port']['PortName'] ?? null;
            if ($portName !== null) {
                $names[strtolower((string) $portName)] = true;
            }

            if (! empty($part['Parts'])) {
                $this->collectUsedPortNames($part['Parts'], $names);
            }
        }
    }

    private function buildVirtualPart(string $portName, array $loadoutEntry): array
    {
        $portDef = $this->entityPortDefinitions[strtolower($portName)] ?? null;

        return [
            'Name' => $portName,
            'DisplayName' => $portDef?->get('@display_name')
                ?? $portDef?->get('@DisplayName')
                ?? $portDef?->get('display_name')
                ?? $portDef?->get('DisplayName'),
            'Mass' => null,
            'MaximumDamage' => null,
            'ShipDestructionDamage' => null,
            'PartDetachDamage' => null,
            'Port' => $this->buildVirtualPort($portName, $loadoutEntry, $portDef),
            'Parts' => [],
        ];
    }

    private function buildVirtualPort(string $portName, array $loadoutEntry, ?Element $portDef): array
    {
        $portData = [];
        if ($portDef !== null) {
            $portData = new ItemPort($portDef)->toArray() ?? [];
        }

        $flags = $portData['Flags'] ?? [];
        $types = $portData['Types'] ?? [];

        $port = [
            'PortName' => $portName,
            'DisplayName' => $portData['DisplayName'] ?? null,
            'Size' => (int) ($portData['MaxSize'] ?? $portData['Size'] ?? 0),
            'MinSize' => (int) ($portData['MinSize'] ?? $portData['Size'] ?? 0),
            'MaxSize' => (int) ($portData['MaxSize'] ?? $portData['Size'] ?? 0),
            'Types' => $types,
            'Flags' => $flags,
            'RequiredTags' => $portData['RequiredTags'] ?? [],
            'Category' => null,
            'Loadout' => $loadoutEntry['className'] ?? null,
            'InstalledItem' => $this->buildInstalledItem($loadoutEntry),
        ];

        $port['Uneditable'] = in_array('$uneditable', $port['Flags'], true)
            || in_array('uneditable', $port['Flags'], true)
            || in_array('invisible', $port['Flags'], true);

        $port['Category'] = $this->portClassifier->classifyPort($port, $port['InstalledItem'] ?? null);
        $port['Category'] = $port['Category'][1] ?? null;

        return $port;
    }

    /**
     * Build a StandardisedItemPort from an ItemPort element.
     */
    private function buildPort(Element $itemPort, ?string $portName, ?array $loadoutEntry): array
    {
        $port = [
            'PortName' => $portName,
            'DisplayName' => $itemPort->get('@display_name') ?? $itemPort->get('@DisplayName'),
            'Size' => (int) ($itemPort->get('@maxSize') ?? $itemPort->get('@MaxSize') ?? 0),
            'MinSize' => (int) ($itemPort->get('@minSize') ?? $itemPort->get('@MinSize') ?? 0),
            'MaxSize' => (int) ($itemPort->get('@maxSize') ?? $itemPort->get('@MaxSize') ?? 0),
            'Types' => $this->extractTypes($itemPort),
            'Flags' => $this->extractFlags($itemPort),
            'RequiredTags' => $this->extractRequiredTags($itemPort),
            'Category' => null, // Will be set by PortClassifier
            'Loadout' => $loadoutEntry['className'] ?? null,
            'InstalledItem' => null,
        ];

        $port['Uneditable'] = in_array('$uneditable', $port['Flags'], true)
            || in_array('uneditable', $port['Flags'], true);

        // Install item if loadout entry exists
        if ($loadoutEntry !== null) {
            $port['InstalledItem'] = $this->buildInstalledItem($loadoutEntry);
        }

        $port['Category'] = $this->portClassifier->classifyPort($port, $port['InstalledItem'] ?? null);
        $port['Category'] = $port['Category'][1] ?? null;

        return $port;
    }

    /**
     * Build StandardisedItem from a loadout entry.
     */
    private function buildInstalledItem(array $loadoutEntry): ?array
    {
        // Check if item data is already resolved
        if (! empty($loadoutEntry['Item'])) {
            $item = $loadoutEntry['Item'];

            // Ensure ports have installed items from nested entries
            if (! empty($loadoutEntry['entries']) && ! empty($item['stdItem']['Ports'])) {
                $item['stdItem']['Ports'] = $this->installLoadoutIntoPorts(
                    $item['stdItem']['Ports'],
                    $loadoutEntry['entries']
                );
            }

            return $item;
        }

        // Resolve item from className/classReference
        $entity = $this->resolveItem(
            $loadoutEntry['classReference'] ?? null,
            $loadoutEntry['className'] ?? null
        );

        if ($entity === null) {
            return null;
        }

        $item = new ScUnpackedItem($entity)->toArray();
        $item['Classification'] = $this->classifierService->classify($item);

        // Install nested loadout into item's ports
        if (! empty($loadoutEntry['entries']) && ! empty($item['stdItem']['Ports'])) {
            $item['stdItem']['Ports'] = $this->installLoadoutIntoPorts(
                $item['stdItem']['Ports'],
                $loadoutEntry['entries']
            );
        }

        return $item;
    }

    /**
     * Install loadout entries into ports.
     */
    private function installLoadoutIntoPorts(array $ports, array $loadoutEntries): array
    {
        foreach ($ports as &$port) {
            $portName = $port['PortName'] ?? $port['Name'] ?? null;

            if ($portName === null) {
                continue;
            }

            $loadoutEntry = $this->findLoadoutEntry($portName, $loadoutEntries);

            if ($loadoutEntry === null) {
                continue;
            }

            $port['Loadout'] = $loadoutEntry['className'] ?? null;
            $port['InstalledItem'] = $this->buildInstalledItem($loadoutEntry);
        }

        return $ports;
    }

    /**
     * Extract type list from ItemPort.
     */
    private function extractTypes(Element $itemPort): array
    {
        $types = [];

        foreach ($itemPort->get('/Types')?->children() ?? [] as $portType) {
            $major = $portType->get('@type') ?? $portType->get('@Type');

            if (empty($major)) {
                continue;
            }

            $subtypeKey = $portType->get('@subtypes') ?? $portType->get('@SubTypes');
            $subTypesElement = $portType->get('/SubTypes');

            $hasSubTypes = $subTypesElement !== null && $subTypesElement->getNode()->childNodes->count() > 0;
            $hasSubTypeAttr = ! empty($subtypeKey);

            if (! $hasSubTypes && ! $hasSubTypeAttr) {
                $types[] = $major;
            } else {
                if ($hasSubTypes) {
                    foreach ($subTypesElement->children() as $subType) {
                        $minor = $subType->get('value');
                        if (! empty($minor)) {
                            $types[] = "{$major}.{$minor}";
                        }
                    }
                }

                if ($hasSubTypeAttr) {
                    foreach (explode(',', $subtypeKey) as $subType) {
                        $trimmed = trim($subType);
                        if (! empty($trimmed)) {
                            $types[] = "{$major}.{$trimmed}";
                        }
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Extract flags from ItemPort.
     */
    private function extractFlags(Element $itemPort): array
    {
        $rawFlags = trim((string) ($itemPort->get('@Flags') ?? $itemPort->get('@flags') ?? ''));

        return $rawFlags === '' ? [] : array_filter(explode(' ', $rawFlags));
    }

    /**
     * Extract required tags from ItemPort.
     */
    private function extractRequiredTags(Element $itemPort): array
    {
        $rawTags = trim((string) ($itemPort->get('@RequiredPortTags') ?? ''));

        return $rawTags === '' ? [] : array_filter(explode(' ', $rawTags));
    }

    /**
     * Get port name from part or itemPort.
     */
    private function getPortName(Element $part, Element $itemPort): ?string
    {
        // Vehicle ItemPorts use the parent part's name as port name
        return $part->get('@name') ?? $part->get('name');
    }

    /**
     * Calculate damage required to destroy ship from this part.
     */
    private function calculateShipDestructionDamage(Element $part, float $damageMax): ?float
    {
        foreach ($part->get('/DamageBehaviors')?->children() ?? [] as $behavior) {
            if ($behavior->get('/Group@name') !== 'Destroy') {
                continue;
            }

            $ratio = (float) ($behavior->get('damageRatioMin') ?? 1.0);

            return $ratio * $damageMax;
        }

        return null;
    }

    /**
     * Calculate damage required to detach this part.
     */
    private function calculatePartDetachDamage(Element $part, float $damageMax): ?float
    {
        $detachRatio = (float) ($part->get('@detachRatio') ?? $part->get('detachRatio') ?? 0);

        if ($damageMax === 0.0 || $detachRatio === 0.0) {
            return null;
        }

        return $damageMax * $detachRatio;
    }

    /**
     * Normalize mass value.
     */
    private function normalizeMass(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        if (is_string($raw)) {
            $clean = preg_replace('/[^0-9+\-eE.]/u', '', $raw);

            if (is_numeric($clean)) {
                return (float) $clean;
            }
        }

        return null;
    }
}
