<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemRadarComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->initialized) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getRadarSystemService();
        $radarSystem = $svc->getByReference($this->get('@sharedParams'));

        $this->appendNode($document, $radarSystem, 'RadarSystem');
    }
}
