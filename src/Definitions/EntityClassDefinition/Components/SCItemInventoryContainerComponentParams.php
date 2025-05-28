<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemInventoryContainerComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getInventoryContainerService();
        $containerParams = $svc->getByReference($this->get('@containerParams'));

        if ($this->get('inventoryContainer@__ref') === $this->get('@containerParams')) {
            return;
        }

        $this->appendNode($document, $containerParams, 'inventoryContainer');
    }
}
