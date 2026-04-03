<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

use DOMDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingCost_Resource extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('ResourceType') !== null) {
            return;
        }

        parent::initialize($document);

        $resourceType = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($this->get('@resource'));

        if ($resourceType === null) {
            return;
        }

        $this->appendNode($document, $resourceType, 'ResourceType');
    }
}
