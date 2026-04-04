<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Harvestable;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class HarvestablePreset extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $reference = $this->get('@entityClass');

        if ($reference === null || $this->get('EntityClass@__ref') === $reference) {
            return;
        }

        $entityClass = ServiceFactory::getItemService()->getByReference($reference);

        if ($entityClass !== null) {
            $this->appendNode($document, $entityClass, 'EntityClass');
        }
    }
}
