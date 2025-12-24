<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Context object containing all input data for vehicle calculators
 *
 * Immutable value object that passes data between calculators and the orchestrator.
 * Includes intermediate results from previous calculators for dependency resolution.
 */
final readonly class VehicleDataContext
{
    /**
     * @param  array  $standardisedParts  Standardised parts array combining Parts + Loadout
     * @param  array  $portSummary  Port summary from PortSummaryBuilder
     * @param  array|null  $ifcsLoadoutEntry  IFCS loadout entry for flight calculations
     * @param  float  $mass  Ship mass in kg (without loadout)
     * @param  float  $loadoutMass  Mass of all equipped items in kg
     * @param  bool  $isVehicle  True if this is a ground vehicle
     * @param  bool  $isGravlev  True if this is a gravlev vehicle
     * @param  bool  $isSpaceship  True if this is a spaceship
     * @param  array  $intermediateResults  Results from previously executed calculators
     */
    public function __construct(
        public array $standardisedParts,
        public array $portSummary,
        public ?array $ifcsLoadoutEntry,
        public float $mass,
        public float $loadoutMass,
        public bool $isVehicle,
        public bool $isGravlev,
        public bool $isSpaceship,
        public array $intermediateResults = [],
    ) {}

    /**
     * Create a new context with updated intermediate results
     *
     * @param  array  $intermediateResults  New intermediate results to merge
     * @return self New context instance with updated results
     */
    public function withIntermediateResults(array $intermediateResults): self
    {
        return new self(
            standardisedParts: $this->standardisedParts,
            portSummary: $this->portSummary,
            ifcsLoadoutEntry: $this->ifcsLoadoutEntry,
            mass: $this->mass,
            loadoutMass: $this->loadoutMass,
            isVehicle: $this->isVehicle,
            isGravlev: $this->isGravlev,
            isSpaceship: $this->isSpaceship,
            intermediateResults: $intermediateResults,
        );
    }
}
