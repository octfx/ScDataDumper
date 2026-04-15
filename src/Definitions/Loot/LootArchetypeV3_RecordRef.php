<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Loot;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class LootArchetypeV3_RecordRef extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $this->hydrateFoundryReference(
            $document,
            referencePath: '@lootArchetypeRecord',
            childNodeName: 'LootArchetypeV3',
            record: ServiceFactory::getFoundryLookupService()
                ->getLootArchetypeV3ByReference($this->get('@lootArchetypeRecord'))
        );
    }
}
