<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemWeaponComponentParams extends Element
{
    public function initialize(\DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getItemService();
        $magazine = $svc->getByReference($this->get('@ammoContainerRecord'));

        if ($this->get('ammoContainer@__ref') !== $this->get('@ammoContainerRecord')) {
            $this->appendNode($document, $magazine, 'Magazine');
        }
    }
}
