<?php

namespace Octfx\ScDataDumper\ValueObjects;

use Illuminate\Support\Collection;

final class InventoryContainerResult
{
    /** @var Collection<int, array> */
    public Collection $containers;

    /** @var float Total closed-container capacity in SCU */
    public float $stowageCapacity = 0.0;

    /** @var float Total capacity for all collected containers in SCU */
    public float $totalCapacity = 0.0;

    /** @var array<string> */
    public array $existingKeys = [];

    public function __construct()
    {
        $this->containers = collect();
    }

    public function addContainer(array $container, ?string $key, bool $countInStowage): void
    {
        if ($key !== null && in_array($key, $this->existingKeys, true)) {
            return;
        }

        if ($key !== null) {
            $this->existingKeys[] = $key;
        }

        $this->containers->push($container);

        $scu = (float) ($container['scu'] ?? 0);
        $this->totalCapacity += $scu;

        if ($countInStowage) {
            $this->stowageCapacity += $scu;
        }
    }
}
