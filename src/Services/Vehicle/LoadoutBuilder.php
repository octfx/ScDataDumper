<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Loadout\LoadoutEntry;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item as ScUnpackedItem;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ItemService;

/**
 * Builds complete loadout with recursive item resolution
 */
final class LoadoutBuilder
{
    use ItemResolutionTrait;

    private const int MAX_RECURSION_DEPTH = 50;

    private array $formattedCache = [];

    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemClassifierService $classifierService
    ) {}

    /**
     * Build complete loadout with recursive item resolution.
     *
     * @param  Element|RootDocument  $loadoutParams  The SItemPortLoadoutManualParams element
     * @return array List of loadout entries with fully resolved items
     */
    public function build(Element|RootDocument $loadoutParams): array
    {
        $entries = [];
        $entriesNode = $loadoutParams->get('./entries');

        if ($entriesNode === null) {
            return $entries;
        }

        foreach ($entriesNode->children() as $cigEntryNode) {
            if ($cigEntryNode->nodeName !== 'SItemPortLoadoutEntryParams') {
                continue;
            }

            $cigEntry = LoadoutEntry::fromNode($cigEntryNode->getNode());

            if (! $cigEntry instanceof LoadoutEntry) {
                continue;
            }

            $entry = $this->buildEntry($cigEntry);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Build a single loadout entry with recursive item resolution.
     *
     * @param  LoadoutEntry  $cigEntry  The SItemPortLoadoutEntryParams element
     * @param  array  $visited  Visited item keys to prevent circular references
     * @param  int  $depth  Current recursion depth
     * @return array|null The built loadout entry or null if invalid
     */
    private function buildEntry(LoadoutEntry $cigEntry, array $visited = [], int $depth = 0): ?array
    {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return null;
        }

        $portName = $cigEntry->getPortName();

        if ($portName === null) {
            return null;
        }

        $entry = [
            'portName' => $portName,
            'className' => $cigEntry->getEntityClassName(),
            'classReference' => $cigEntry->getEntityClassReference(),
            'entries' => [],
        ];

        $entity = $this->resolveItem($entry['classReference'], $entry['className']);

        if ($entity !== null) {
            // Check for circular reference
            $itemKey = $entity->getUuid() ?: $entity->getClassName();
            if (isset($visited[$itemKey])) {
                $entry['ItemRaw'] = $entity->toArray();
                $entry['Item'] = $this->formatItem($entity);

                return $entry;
            }

            $visited[$itemKey] = true;

            $entry['ItemRaw'] = $entity->toArray();
            $entry['Item'] = $this->formatItem($entity);

            // Load item's own default loadout entries
            $itemLoadoutEntries = $this->loadItemLoadout($entity);

            // Merge nested entries from cigEntry with item's default loadout
            $nestedEntries = $this->extractNestedEntries($cigEntry);
            $mergedEntries = $this->mergeLoadoutEntries($nestedEntries, $itemLoadoutEntries);

            // Get item's ports and install loadouts recursively
            $itemPorts = $entry['Item']['stdItem']['Ports'] ?? [];
            $entry['Item']['stdItem']['Ports'] = $this->installLoadoutIntoPorts($itemPorts, $mergedEntries, $visited, $depth + 1);

            foreach ($mergedEntries as $nestedEntry) {
                $builtEntry = $this->buildEntryFromArray($nestedEntry, $visited, $depth + 1);
                if ($builtEntry !== null) {
                    $entry['entries'][] = $builtEntry;
                }
            }
        } else {
            // No item found, but still process nested entries from cigEntry
            foreach ($this->extractNestedEntries($cigEntry) as $nestedEntry) {
                $builtEntry = $this->buildEntryFromArray($nestedEntry, $visited, $depth + 1);
                if ($builtEntry !== null) {
                    $entry['entries'][] = $builtEntry;
                }
            }
        }

        return $entry;
    }

    /**
     * Build entry from array (for recursive calls with merged entries).
     */
    private function buildEntryFromArray(array $entryData, array $visited = [], int $depth = 0): ?array
    {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return null;
        }

        $portName = $entryData['portName'] ?? null;

        if ($portName === null) {
            return null;
        }

        $entry = [
            'portName' => $portName,
            'className' => $entryData['className'] ?? null,
            'classReference' => $entryData['classReference'] ?? null,
            'entries' => [],
        ];

        $entity = $this->resolveItem($entry['classReference'], $entry['className']);

        if ($entity !== null) {
            $itemKey = $entity->getUuid() ?: $entity->getClassName();
            if (isset($visited[$itemKey])) {
                $entry['ItemRaw'] = $entity->toArray();
                $entry['Item'] = $this->formatItem($entity);

                return $entry;
            }

            $visited[$itemKey] = true;

            $entry['ItemRaw'] = $entity->toArray();
            $entry['Item'] = $this->formatItem($entity);

            $itemLoadoutEntries = $this->loadItemLoadout($entity);

            $nestedEntries = $entryData['entries'] ?? [];
            $mergedEntries = $this->mergeLoadoutEntries($nestedEntries, $itemLoadoutEntries);

            $itemPorts = $entry['Item']['stdItem']['Ports'] ?? [];
            $entry['Item']['stdItem']['Ports'] = $this->installLoadoutIntoPorts($itemPorts, $mergedEntries, $visited, $depth + 1);

            foreach ($mergedEntries as $nestedEntry) {
                $builtEntry = $this->buildEntryFromArray($nestedEntry, $visited, $depth + 1);
                if ($builtEntry !== null) {
                    $entry['entries'][] = $builtEntry;
                }
            }
        }

        return $entry;
    }

    /**
     * Install loadout entries into ports, adding InstalledItem data.
     *
     * @param  array  $ports  The item's ports array
     * @param  array  $loadoutEntries  Loadout entries to install
     * @param  array  $visited  Visited item keys to prevent circular references
     * @param  int  $depth  Current recursion depth
     * @return array Ports with InstalledItem data populated
     */
    private function installLoadoutIntoPorts(array $ports, array $loadoutEntries, array $visited = [], int $depth = 0): array
    {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return $ports;
        }

        foreach ($ports as &$port) {
            $portName = $port['PortName'] ?? $port['Name'] ?? null;

            if ($portName === null) {
                continue;
            }

            $loadoutEntry = $this->findLoadoutEntry($portName, $loadoutEntries);

            if ($loadoutEntry === null) {
                continue;
            }

            $className = $loadoutEntry['className'] ?? null;
            $classReference = $loadoutEntry['classReference'] ?? null;

            if (empty($className) && empty($classReference)) {
                continue;
            }

            $port['Loadout'] = $className;

            $entity = $this->resolveItem($classReference, $className);

            if ($entity === null) {
                continue;
            }

            $itemKey = $entity->getUuid() ?: $entity->getClassName();
            if (isset($visited[$itemKey])) {
                $installedItem = $this->formatItem($entity);
                $port['InstalledItem'] = $installedItem;

                continue;
            }

            $visited[$itemKey] = true;

            $installedItem = $this->formatItem($entity);

            $itemLoadoutEntries = $this->loadItemLoadout($entity);
            $nestedEntries = $loadoutEntry['entries'] ?? [];
            $mergedEntries = $this->mergeLoadoutEntries($nestedEntries, $itemLoadoutEntries);

            $itemPorts = $installedItem['stdItem']['Ports'] ?? [];
            $installedItem['stdItem']['Ports'] = $this->installLoadoutIntoPorts($itemPorts, $mergedEntries, $visited, $depth + 1);

            $port['InstalledItem'] = $installedItem;
        }

        return $ports;
    }

    /**
     * Load an item's own default loadout entries.
     *
     * @param  EntityClassDefinition  $entity  The item entity
     * @return array Loadout entries from the item's SEntityComponentDefaultLoadoutParams
     */
    private function loadItemLoadout(EntityClassDefinition $entity): array
    {
        return array_map(
            static fn (LoadoutEntry $entry): array => $entry->toDefinitionArray(),
            $entity->getDefaultLoadoutEntries()
        );
    }

    /**
     * Extract nested entries from a loadout entry element.
     */
    private function extractNestedEntries(LoadoutEntry $cigEntry): array
    {
        return array_map(
            static fn (LoadoutEntry $entry): array => $entry->toDefinitionArray(),
            $cigEntry->getNestedEntries()
        );
    }

    /**
     * Merge two loadout entry arrays, preferring entries from the first array.
     *
     * @param  array  $primary  Primary entries (from parent loadout)
     * @param  array  $secondary  Secondary entries (from item's default loadout)
     * @return array Merged entries
     */
    private function mergeLoadoutEntries(array $primary, array $secondary): array
    {
        $merged = [];
        $usedPorts = [];

        foreach ($primary as $entry) {
            $portName = strtolower($entry['portName'] ?? '');
            $merged[] = $entry;
            $usedPorts[$portName] = true;
        }

        // secondary entries that don't conflict
        foreach ($secondary as $entry) {
            $portName = strtolower($entry['portName'] ?? '');
            if (! isset($usedPorts[$portName])) {
                $merged[] = $entry;
                $usedPorts[$portName] = true;
            }
        }

        return $merged;
    }

    /**
     * Format an entity as ScUnpackedItem array with caching.
     */
    private function formatItem(EntityClassDefinition $entity): array
    {
        $cacheKey = $entity->getUuid() ?: $entity->getClassName();

        if ($cacheKey !== null && isset($this->formattedCache[$cacheKey])) {
            return $this->formattedCache[$cacheKey];
        }

        $formatted = new ScUnpackedItem($entity)->toArray();
        $this->applyCanonicalClassification($formatted);

        if ($cacheKey !== null) {
            $this->formattedCache[$cacheKey] = $formatted;
        }

        return $formatted;
    }

    private function applyCanonicalClassification(array &$item): void
    {
        unset($item['Classification']);

        $classification = $this->classifierService->classify($item);

        if (! is_string($classification) || trim($classification) === '') {
            unset($item['classification']);

            return;
        }

        $item['classification'] = strtolower($classification);
    }
}
