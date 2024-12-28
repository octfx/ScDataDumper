<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SAmmoContainerComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->initialized) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getAmmoParamsService();
        $ammoParamsRecord = $svc->getByReference($this->get('@ammoParamsRecord'));
        $secondaryAmmoParamsRecord = $svc->getByReference($this->get('@secondaryAmmoParamsRecord'));

        $this->appendNode($document, $ammoParamsRecord, 'ammoParams');
        $this->appendNode($document, $secondaryAmmoParamsRecord, 'secondaryAmmoParams');
    }
}
