<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemRadarComponentParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getRadarSystemService();

        $attributes = $this->attributesToArray();
        $attributes['RadarSystem'] = $svc->getByReference($attributes['sharedParams'])?->toArray();

        return $attributes;
    }
}
