<?php

namespace Octfx\ScDataDumper\Definitions;

namespace Octfx\ScDataDumper\Definitions;

final class MeleeCombatConfig extends Element
{
    public function toArray(): array
    {
        return $this->toArrayRecursive($this);
    }
}
