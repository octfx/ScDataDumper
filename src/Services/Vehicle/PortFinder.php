<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Finds item ports in parts and items matching predicates
 *
 * Provides unified recursive search across parts hierarchies and installed items.
 */
final class PortFinder
{
    /**
     * Find item ports in parts that match the predicate
     *
     * Matched parts are not traversed any deeper; $stop skips a node and its
     * children entirely.
     *
     * @param  array  $parts  List of parts to search
     * @param  callable  $predicate  Function(array $part): bool to match ports
     * @param  callable|null  $stop  Function(array $node): bool, skip node and subtree
     * @return Collection<int, array> Matched parts / wrapped ports
     */
    public function findInParts(array $parts, callable $predicate, ?callable $stop = null): Collection
    {
        $results = collect();

        foreach ($parts as $part) {
            if (isset($part['Port'])) {
                if ($stop !== null && $stop($part)) {
                    continue;
                }

                if ($predicate($part)) {
                    $results->push($part);

                    continue;
                }

                // Search in installed item
                if (isset($part['Port']['InstalledItem'])) {
                    $results = $results->merge(
                        $this->findInItem($part['Port']['InstalledItem'], $predicate, $stop)
                    );
                }
            }

            // Search child parts
            if (isset($part['Parts'])) {
                $results = $results->merge($this->findInParts($part['Parts'], $predicate, $stop));
            }
        }

        return $results;
    }

    /**
     * Find item ports in an installed item
     *
     * @param  array  $item  The item to search
     * @param  callable  $predicate  Function(array $port): bool to match ports
     * @param  callable|null  $stop  Function(array $node): bool, skip node and subtree
     * @return Collection<int, array> Matched ports wrapped as ['Port' => ...]
     */
    private function findInItem(array $item, callable $predicate, ?callable $stop): Collection
    {
        $results = collect();

        if (! Arr::has($item, 'stdItem.Ports')) {
            return $results;
        }

        foreach (Arr::get($item, 'stdItem.Ports') as $port) {
            if ($stop !== null && $stop($port)) {
                continue;
            }

            if ($predicate($port)) {
                $results->push(['Port' => $port]);

                continue;
            }

            if (isset($port['InstalledItem'])) {
                $results = $results->merge($this->findInItem($port['InstalledItem'], $predicate, $stop));
            }
        }

        return $results;
    }
}
