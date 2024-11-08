<?php

namespace Octfx\ScDataDumper\Helper;

use Octfx\ScDataDumper\Definitions\EntityClassDefinition\VehicleDefinition;
use Octfx\ScDataDumper\Definitions\Vehicle;

class VehicleWrapper
{
    public function __construct(public readonly Vehicle $vehicle, public readonly VehicleDefinition $entity, public readonly array $loadout) {}

    public function getVehicleArray(): array
    {
        return $this->vehicle->toArray();
    }

    public function getVehicleEntityArray(): array
    {
        return $this->entity->toArray();
    }
}
