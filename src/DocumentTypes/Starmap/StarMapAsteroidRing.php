<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Starmap;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class StarMapAsteroidRing extends RootDocument
{
    public function getDensityScale(): ?float
    {
        return $this->getFloat('@densityScale');
    }

    public function getSizeScale(): ?float
    {
        return $this->getFloat('@sizeScale');
    }

    public function getInnerRadius(): ?float
    {
        return $this->getFloat('@innerRadius');
    }

    public function getOuterRadius(): ?float
    {
        return $this->getFloat('@outerRadius');
    }

    public function getDepth(): ?float
    {
        return $this->getFloat('@depth');
    }
}
