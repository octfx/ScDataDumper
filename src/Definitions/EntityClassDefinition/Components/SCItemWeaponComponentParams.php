<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @deprecated Eager hydration is deprecated. This hydrator will be removed once ECD component hydration is migrated into EntityClassDefinition.
 */
class SCItemWeaponComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
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
