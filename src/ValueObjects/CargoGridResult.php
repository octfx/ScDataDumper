<?php

namespace Octfx\ScDataDumper\ValueObjects;

use Illuminate\Support\Collection;

/**
 * Result object for cargo grid resolution
 *
 * Holds the accumulated cargo capacity, discovered grids, and tracking information
 * for the multi-strategy cargo grid detection process.
 */
final class CargoGridResult
{
    /** @var float Total cargo capacity in SCU */
    public float $totalCapacity = 0;

    /** @var Collection<int, array> Standardised cargo grid data */
    public Collection $grids;

    /** @var array<string> UUIDs of cargo grids already found (prevents double-counting) */
    public array $existingGridUuids = [];

    /** @var int Number of remaining cargo grid slots to fill */
    public int $remainingSlots = 0;

    /** @var array<int, mixed> Fallback cargo containers found via strategies */
    public array $fallbackContainers = [];

    /** Tracks whether we know how many cargo grid ports to expect */
    private bool $hasExpectedSlots = false;

    public function __construct()
    {
        $this->grids = collect();
    }

    /**
     * Check if the cargo grid resolution is satisfied
     *
     * When we know how many cargo grid ports to expect, resolution is
     * considered complete once we have positive capacity AND all expected
     * slots are filled (remainingSlots <= 0).
     *
     * If we do not know the expected slot count (common when the loadout
     * doesn't expose cargo grid ports), we intentionally never mark the
     * result as satisfied so that all strategies get a chance to run.
     */
    public function isSatisfied(): bool
    {
        if (! $this->hasExpectedSlots) {
            return false;
        }

        return $this->totalCapacity > 0 && $this->remainingSlots <= 0;
    }

    /**
     * Add a cargo grid to the result if it hasn't been added already
     *
     * @param  string  $uuid  The UUID of the cargo grid
     * @param  mixed  $container  The container object
     * @param  float  $scu  The SCU capacity of the container
     * @return bool True if the container was added, false if it was a duplicate
     */
    public function addContainer(string $uuid, mixed $container, float $scu): bool
    {
        if (in_array($uuid, $this->existingGridUuids, true)) {
            return false;
        }

        $this->existingGridUuids[] = $uuid;
        $this->fallbackContainers[] = $container;
        $this->totalCapacity += $scu;
        $this->remainingSlots = max(0, $this->remainingSlots - 1);

        return true;
    }

    /**
     * Add cargo capacity without a specific container
     *
     * Used for ResourceContainer-based cargo that doesn't have a grid object
     *
     * @param  float  $capacity  The capacity to add in SCU
     */
    public function addCapacity(float $capacity): void
    {
        $this->totalCapacity += $capacity;
    }

    /**
     * Set the initial expected number of cargo grid slots
     *
     * @param  int  $slots  Number of expected cargo grid ports from the vehicle loadout
     */
    public function setExpectedSlots(int $slots): void
    {
        $this->hasExpectedSlots = $slots > 0;
        $this->remainingSlots = max(0, $slots - count($this->existingGridUuids));
    }

    /**
     * Check if we should continue searching for cargo grids
     *
     * Continue if:
     * - We have no capacity yet, OR
     * - We still have unfilled slots
     */
    public function shouldContinueSearching(): bool
    {
        if (! $this->hasExpectedSlots) {
            return true;
        }

        return $this->totalCapacity <= 0 || $this->remainingSlots > 0;
    }
}
