<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemSuitArmorParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getDamageResistanceMacroService();

        $attributes = $this->attributesToArray();
        $attributes['DamageResistance'] = $svc->getByReference($attributes['damageResistance'])?->toArray();

        return $attributes;
    }
}
