<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SMeleeWeaponComponentParams extends Element
{
    public function toArray(): array
    {
        $svc = ServiceFactory::getMeleeCombatConfigService();

        $attributes = $this->attributesToArray();
        $attributes['MeleeCombatConfig'] = $svc->getByReference($attributes['meleeCombatConfig'])?->toArray();

        return $attributes;
    }
}
