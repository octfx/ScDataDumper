<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Mining;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MineableCompositionPart extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $reference = $this->get('@mineableElement');

        if ($reference === null || $this->get('MineableElement@__ref') === $reference) {
            return;
        }

        $mineableElement = ServiceFactory::getFoundryLookupService()->getMineableElementByReference($reference);

        if ($mineableElement !== null) {
            $this->appendNode($document, $mineableElement, 'MineableElement');
        }
    }
}
