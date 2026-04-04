<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Harvestable;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SubHarvestableSlot extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@harvestable',
            childNodeName: 'Harvestable',
            record: ServiceFactory::getFoundryLookupService()->getHarvestablePresetByReference($this->get('@harvestable'))
        );
    }
}
