<?php

namespace Octfx\ScDataDumper\DocumentTypes\Starmap;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class StarMapAmenityTypeEntry extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }
}
