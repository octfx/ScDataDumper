<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Mining;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MiningGlobalParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $reference = $this->get('@wasteResourceType');

        if ($reference === null || $this->get('ResourceType@__ref') === $reference) {
            return;
        }

        $resourceType = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference);

        if ($resourceType !== null) {
            $this->appendNode($document, $resourceType, 'ResourceType');
        }
    }
}
