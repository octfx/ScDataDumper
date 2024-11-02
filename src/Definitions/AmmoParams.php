<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

final class AmmoParams extends Element
{
    public function toArray(): array
    {
        return $this->toArrayRecursive($this);
    }
}
