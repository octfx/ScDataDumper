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

    /** @var float Ore pod capacity in SCU (mining ships only, tracked separately from cargo) */
    public float $oreCapacity = 0;

    /** @var Collection<int, array> Standardised cargo grid data */
    public Collection $grids;

    /** @var array<string> UUIDs of cargo grids already found (prevents double-counting) */
    public array $existingGridUuids = [];

    /** @var array<string> Lowercased class names of cargo grids found by the loadout strategy */
    public array $loadoutGridClassNames = [];

    /** @var int Number of remaining cargo grid slots to fill */
    public int $remainingSlots = 0;

    /** @var array<int, mixed> Fallback cargo containers found via strategies */
    public array $fallbackContainers = [];

    /** Tracks whether we know how many cargo grid ports to expect */
    private bool $hasExpectedSlots = false;

    /** Tracks whether the loadout strategy discovered any cargo grids */
    private bool $loadoutFoundGrids = false;

    /** Tracks whether the vehicle has any cargo-related infrastructure (ports/items) */
    private bool $hasCargoInfra = false;

    public function __construct()
    {
        $this->grids = collect();
    }

    /**
     * Check if the cargo grid resolution is satisfied.
     *
     * Resolution is complete when capacity is positive and all expected slots
     * are filled (remainingSlots <= 0).
     *
     * Without a known expected slot count (e.g. loadout has no cargo grid ports),
     * always returns false so all strategies run.
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
     * Add ore pod capacity (tracked separately from regular cargo)
     *
     * Ore pods are mining-specific containers with restricted resources.
     * They should not be included in the general cargo total.
     *
     * @param  float  $capacity  The ore capacity to add in SCU
     */
    public function addOreCapacity(float $capacity): void
    {
        $this->oreCapacity += $capacity;
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
     * Mark that the loadout strategy discovered cargo grids
     *
     * When the loadout found grids, convention/prefix/base strategies should
     * only add grids that match the specific vehicle variant (not sibling variants).
     */
    public function markLoadoutFoundGrids(): void
    {
        $this->loadoutFoundGrids = true;
    }

    /**
     * Check if the loadout strategy found any cargo grids
     */
    public function loadoutFoundGrids(): bool
    {
        return $this->loadoutFoundGrids;
    }

    /**
     * Mark that the vehicle has cargo infrastructure (cargo-related ports or items)
     */
    public function markHasCargoInfrastructure(): void
    {
        $this->hasCargoInfra = true;
    }

    /**
     * Check if the vehicle has any cargo-related infrastructure
     *
     * When true, convention/prefix/base strategies may legitimately need to discover
     * grids not found in the loadout (e.g., 890 Jump's rear grid, 135c's convention grid).
     * When false (no cargo ports, no cargo items in loadout), those strategies should
     * not add grids since they would only find grids from sibling variants.
     */
    public function hasCargoInfrastructure(): bool
    {
        return $this->hasCargoInfra || $this->loadoutFoundGrids;
    }

    /**
     * Check if cargo grid search should continue.
     *
     * Returns true when capacity is still zero or slots remain unfilled.
     */
    public function shouldContinueSearching(): bool
    {
        if (! $this->hasExpectedSlots) {
            return true;
        }

        return $this->totalCapacity <= 0 || $this->remainingSlots > 0;
    }
}
