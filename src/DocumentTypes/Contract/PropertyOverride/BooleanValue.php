<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class BooleanValue extends RootDocument
{
    public function getValue(): bool
    {
        return $this->getBool('@value');
    }
}
