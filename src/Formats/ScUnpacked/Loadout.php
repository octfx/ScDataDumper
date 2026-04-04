<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\Loadout\LoadoutEntry;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Loadout extends BaseFormat
{
    protected ?string $elementKey = 'loadout';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        if ($this->item?->nodeName === 'SItemPortLoadoutManualParams') {
            return [
                'Entries' => $this->buildNestedEntries(),
            ];
        }

        $entry = LoadoutEntry::fromNode($this->item?->getNode());

        if (! $entry instanceof LoadoutEntry) {
            return null;
        }

        $loadout = [
            'PortName' => $entry->getPortName(),
            'ClassName' => $entry->getEntityClassName(),
            'Entries' => [],
        ];

        $entity = $entry->getInstalledItem();

        if ($entity instanceof EntityClassDefinition) {
            $loadout['InstalledItem'] = new Item($entity)->toArray();
        }

        $loadout['Entries'] = $this->buildEntriesFromDefinitions($entry->getNestedEntries());

        return $loadout;
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'SItemPortLoadoutManualParams' || $this->item?->nodeName === 'SItemPortLoadoutEntryParams';
    }

    /**
     * @param  list<LoadoutEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function buildEntriesFromDefinitions(array $entries): array
    {
        $results = [];

        foreach ($entries as $entry) {
            if ((int) ($entry->get('@skipPart') ?? 0) === 1) {
                continue;
            }

            $entryArray = new self($entry)->toArray();

            if (is_array($entryArray)) {
                $results[] = $entryArray;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildNestedEntries(): array
    {
        $entriesNode = $this->get('entries');

        if ($entriesNode === null) {
            return [];
        }

        $entries = [];

        foreach ($entriesNode->children() as $child) {
            $entry = LoadoutEntry::fromNode($child->getNode());

            if (! $entry instanceof LoadoutEntry || (int) ($entry->get('@skipPart') ?? 0) === 1) {
                continue;
            }

            $entryArray = new self($entry)->toArray();

            if (is_array($entryArray)) {
                $entries[] = $entryArray;
            }
        }

        return $entries;
    }
}
