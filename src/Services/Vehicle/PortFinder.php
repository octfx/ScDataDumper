<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\ValueObjects\PortFinderOptions;

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
     * @param  array  $parts  List of parts to search
     * @param  callable  $predicate  Function(array $part): bool to match ports
     * @param  PortFinderOptions  $options  Search options
     * @param  int  $depth  Current recursion depth
     * @return Collection<int, array{0: array, 1: int}> Collection of [port, depth] tuples
     */
    public function findInParts(array $parts, callable $predicate, PortFinderOptions $options, int $depth = 0): Collection
    {
        $results = collect();

        foreach ($parts as $part) {
            if (isset($part['Port'])) {
                // Check stop predicate
                if ($options->shouldStop($part)) {
                    continue;
                }

                // Check if matches predicate
                if ($predicate($part)) {
                    $results->push([$part, $depth]);
                    if ($options->stopOnFind) {
                        continue;
                    }
                }

                // Search in installed item
                if (isset($part['Port']['InstalledItem'])) {
                    $itemMatches = $this->findInItem(
                        $part['Port']['InstalledItem'],
                        $predicate,
                        $options,
                        $depth + 1
                    );
                    $results = $results->merge($itemMatches);
                }
            }

            // Search child parts
            if (isset($part['Parts'])) {
                $partMatches = $this->findInParts($part['Parts'], $predicate, $options, $depth + 1);
                $results = $results->merge($partMatches);
            }
        }

        return $results;
    }

    /**
     * Find item ports in an installed item
     *
     * @param  array  $item  The item to search
     * @param  callable  $predicate  Function(array $port): bool to match ports
     * @param  PortFinderOptions  $options  Search options
     * @param  int  $depth  Current recursion depth
     * @return Collection<int, array{0: array, 1: int}> Collection of [port, depth] tuples
     */
    private function findInItem(array $item, callable $predicate, PortFinderOptions $options, int $depth): Collection
    {
        $results = collect();

        if (! Arr::has($item, 'stdItem.Ports')) {
            return $results;
        }

        foreach (Arr::get($item, 'stdItem.Ports') as $port) {
            if ($options->shouldStop($port)) {
                continue;
            }

            if ($predicate($port)) {
                $results->push([$port, $depth]);
                if ($options->stopOnFind) {
                    continue;
                }
            }

            if (isset($port['InstalledItem'])) {
                $matches = $this->findInItem($port['InstalledItem'], $predicate, $options, $depth + 1);
                $results = $results->merge($matches);
            }
        }

        return $results;
    }
}
