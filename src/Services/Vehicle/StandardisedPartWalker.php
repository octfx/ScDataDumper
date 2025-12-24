<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Generator;
use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\Arr;

/**
 * Unified walker for StandardisedParts hierarchy.
 * Yields both part-level data and installed items.
 * Replaces EquippedItemWalker and manual part recursion.
 */
final class StandardisedPartWalker
{
    /**
     * Walk parts hierarchy and yield all parts (including nested).
     * Useful for aggregations that need to process every part regardless of items.
     *
     * @param  array|Collection  $parts  StandardisedPart entries
     * @param  array  $path  Accumulated path for traceability
     * @return Generator<int, array{part: array, path: array}>
     */
    public function walkParts(array|Collection $parts, array $path = []): Generator
    {
        foreach ($parts as $part) {
            $partName = $part['Name'] ?? null;
            $currentPath = $path;

            if ($partName !== null) {
                $currentPath[] = $partName;
            }

            // Yield the part itself
            yield [
                'part' => $part,
                'path' => $currentPath,
            ];

            // Recurse into nested Parts
            if (! empty($part['Parts']) && is_array($part['Parts'])) {
                yield from $this->walkParts($part['Parts'], $currentPath);
            }
        }
    }

    /**
     * Walk parts hierarchy and yield installed items with full port context.
     *
     * @param  array|Collection  $parts  StandardisedPart entries
     * @param  array  $path  Accumulated path for traceability
     * @return Generator<int, array{Item: array, Port: array|null, Part: array, portName: string|null, path: array}>
     */
    public function walkItems(array|Collection $parts, array $path = [], ?bool $isItemPort = false): Generator
    {
        $prefix = $isItemPort ? '' : 'Port.';

        foreach ($parts as $part) {
            $partName = Arr::get($part, $isItemPort ? 'PortName' : 'Name');
            $currentPath = $path;

            if ($partName !== null) {
                $currentPath[] = $partName;
            }

            // Yield installed item if present with full Port metadata
            if (Arr::has($part, "{$prefix}InstalledItem")) {
                $portName = Arr::get($part, $isItemPort ? 'PortName' : 'Port.PortName', $partName);

                yield [
                    'Item' => Arr::get($part, "{$prefix}InstalledItem"),
                    'Port' => $part['Port'] ?? null,
                    'Part' => $part, // parent part
                    'portName' => $portName,
                    'path' => $currentPath,
                ];

                // Recurse into installed item's ports
                if (Arr::has($part, "{$prefix}InstalledItem.stdItem.Ports")) {
                    yield from $this->walkItems(
                        Arr::get($part, "{$prefix}InstalledItem.stdItem.Ports", []),
                        $currentPath,
                        true
                    );
                }
            }

            // Recurse into nested Parts
            if (! empty($part['Parts']) && is_array($part['Parts'])) {
                yield from $this->walkItems($part['Parts'], $currentPath);
            }
        }
    }
}
