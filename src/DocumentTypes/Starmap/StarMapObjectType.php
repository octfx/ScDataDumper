<?php

namespace Octfx\ScDataDumper\DocumentTypes\Starmap;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class StarMapObjectType extends RootDocument
{
    public function getName(): ?string
    {
        return $this->getString('@name');
    }

    public function getClassification(): ?string
    {
        return $this->getString('@classification');
    }

    public function spawnNavPoints(): bool
    {
        return $this->getBool('@spawnNavPoints');
    }

    public function validQuantumTravelDestination(): bool
    {
        return $this->getBool('@validQuantumTravelDestination');
    }
}
