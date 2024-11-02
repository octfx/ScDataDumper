<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemInventoryContainerComponentParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getInventoryContainerService();

        $attributes = $this->attributesToArray();
        $attributes['inventoryContainer'] = $svc->getByReference($attributes['containerParams'])?->toArray();

        return $attributes;
    }
}
