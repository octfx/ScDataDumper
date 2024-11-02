<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemWeaponComponentParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getItemService();

        $attributes = $this->attributesToArray();
        $attributes['Magazine'] = $svc->getByReference($attributes['ammoContainerRecord'])?->toArray();

        return $attributes;
    }
}
