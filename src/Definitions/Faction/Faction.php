<?php

namespace Octfx\ScDataDumper\Definitions\Faction;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class Faction extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();
        $factionReputationReference = $this->get('@factionReputationRef');

        $factionReputation = $lookup->getFactionReputationByReference($factionReputationReference);
        if ($factionReputation !== null && $this->get('FactionReputation@__ref') !== $factionReputationReference) {
            $this->appendNode($document, $factionReputation, 'FactionReputation');
        }
    }
}
