<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

use DOMDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingGameplayPropertyModifierCommon extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('GameplayProperty') !== null) {
            return;
        }

        parent::initialize($document);

        $property = ServiceFactory::getCraftingGameplayPropertyService()->getByReference($this->get('@gameplayPropertyRecord'));

        if ($property === null) {
            return;
        }

        $this->appendNode($document, $property, 'GameplayProperty');
    }
}
