<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MineableCompositionPart extends RootDocument
{
    public function getMineableElementReference(): ?string
    {
        return $this->getString('@mineableElement');
    }

    public function getMinPercentage(): ?float
    {
        return $this->getFloat('@minPercentage');
    }

    public function getMaxPercentage(): ?float
    {
        return $this->getFloat('@maxPercentage');
    }

    public function getProbability(): ?float
    {
        return $this->getFloat('@probability');
    }

    public function getCurveExponent(): ?float
    {
        return $this->getFloat('@curveExponent');
    }

    public function getQualityScale(): ?float
    {
        return $this->getFloat('@qualityScale');
    }

    public function getMineableElement(): ?MineableElement
    {
        return $this->getHydratedDocument('MineableElement', MineableElement::class);
    }
}
