<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Harvestable;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class HarvestableElement extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@harvestable',
            childNodeName: 'Harvestable',
            record: $lookup->getHarvestablePresetByReference($this->get('@harvestable'))
        );

        $this->hydrateEntityReference($document);

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@clustering',
            childNodeName: 'Clustering',
            record: $lookup->getHarvestableClusterPresetByReference($this->get('@clustering'))
        );

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@harvestableSetup',
            childNodeName: 'HarvestableSetup',
            record: $lookup->getHarvestableSetupByReference($this->get('@harvestableSetup'))
        );
    }

    private function hydrateEntityReference(DOMDocument $document): void
    {
        $reference = $this->get('@harvestableEntityClass');

        if ($reference === null || $this->get('HarvestableEntity@__ref') === $reference) {
            return;
        }

        $entity = ServiceFactory::getItemService()->getByReference($reference);

        if ($entity !== null) {
            $this->appendNode($document, $entity, 'HarvestableEntity');
        }
    }
}
