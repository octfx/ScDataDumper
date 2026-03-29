<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

use DOMDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingCost_Item extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('InputEntity') !== null) {
            return;
        }

        parent::initialize($document);

        $item = ServiceFactory::getItemService()->getByReference($this->get('@entityClass'));

        if ($item === null) {
            return;
        }

        $this->appendNode($document, $item, 'InputEntity');
    }
}
