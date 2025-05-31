<?php

namespace Octfx\ScDataDumper\Helper;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;

class VehicleWrapper
{
    public function __construct(public readonly ?Vehicle $vehicle, public readonly VehicleDefinition $entity, public readonly array $loadout) {}

    public function getVehicleArray(): ?array
    {
        return $this->vehicle?->toArray();
    }

    public function getVehicleEntityArray(): array
    {
        return [
            ...$this->entity->toArray(),
            'ClassName' => $this->entity->getClassName(),
            '__ref' => $this->entity->getUuid(),
            '__type' => $this->entity->getType(),
        ];
    }
}
