<?php

namespace Octfx\ScDataDumper\Definitions;

final class DamageResistanceMacro extends Element
{
    public function toArray(): array
    {
        return $this->toArrayRecursive($this);
    }
}
