<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\CraftingBlueprintRecord\blueprint\CraftingBlueprint\processSpecificData;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingProcess_Creation extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('OutputEntity') !== null) {
            return;
        }

        parent::initialize($document);

        $item = ServiceFactory::getItemService()->getByReference($this->get('@entityClass'));

        if ($item === null) {
            return;
        }

        $this->appendNode($document, $item, 'OutputEntity');
    }
}
