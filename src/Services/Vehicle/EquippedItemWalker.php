<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Generator;

/**
 * Recursively walks loadout entries and yields every installed item instance.
 */
final class EquippedItemWalker
{
    /**
     * Walk through the nested loadout tree.
     *
     * @param  array  $entries  Loadout entries (each may contain nested 'entries')
     * @param  array  $path  Accumulated port path (for traceability)
     * @return Generator<int, array{Item: array, portName: string|null, path: array}>
     */
    public function walk(array $entries, array $path = []): Generator
    {
        foreach ($entries as $entry) {
            $portName = $entry['portName'] ?? null;
            $currentPath = $path;

            if ($portName !== null) {
                $currentPath[] = $portName;
            }

            if (isset($entry['Item']) && is_array($entry['Item'])) {
                yield [
                    'Item' => $entry['Item'],
                    'portName' => $portName,
                    'path' => $currentPath,
                ];
            }

            if (! empty($entry['entries']) && is_array($entry['entries'])) {
                yield from $this->walk($entry['entries'], $currentPath);
            }
        }
    }
}
