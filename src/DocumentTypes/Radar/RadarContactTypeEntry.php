<?php

namespace Octfx\ScDataDumper\DocumentTypes\Radar;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

class RadarContactTypeEntry extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }

    public function getTagReference(): ?string
    {
        return $this->getString('@tag');
    }

    public function getTagName(): ?string
    {
        return ServiceFactory::getTagDatabaseService()->getTagName($this->getTagReference());
    }

    public function isObjectOfInterest(): bool
    {
        return $this->getBool('@isObjectOfInterest');

    }
}
