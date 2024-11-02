<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SAmmoContainerComponentParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getAmmoParamsService();

        $attributes = $this->attributesToArray();
        $attributes['ammoParams'] = $svc->getByReference($attributes['ammoParamsRecord']);
        $attributes['secondaryAmmoParams'] = $svc->getByReference($attributes['secondaryAmmoParamsRecord']);

        return $attributes;
    }
}
