<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Faction;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class Faction extends RootDocument {
    public function getName(): string
    {
        return $this->getString('@name');
    }

    public function getDescription(): string
    {
        return $this->getString('@description');
    }

    public function getDefaultReaction(): string
    {
        return $this->getString('@defaultReaction');
    }

    public function getFactionType(): string
    {
        return $this->getString('@factionType');
    }

    public function getAbleToArrest(): bool
    {
        return $this->getBool('@ableToArrest');
    }

    public function getPolicesLawfulTrespass(): bool
    {
        return $this->getBool('@policesLawfulTrespass');
    }

    public function getPolicesCriminality(): bool
    {
        return $this->getBool('@policesCriminality');
    }

    public function getNoLegalRight(): bool
    {
        return $this->getBool('@noLegalRights');
    }

    public function getFactionReputationReference(): ?string
    {
        return $this->getString('@factionReputationRef');
    }

    public function getFactionReputation(): ?FactionReputation
    {
        $reputationNode = $this->get('FactionReputation');

        if (! $reputationNode instanceof Element) {
            return null;
        }

        $reputation = FactionReputation::fromNode($reputationNode->getNode());

        return $reputation instanceof FactionReputation ? $reputation : null;
    }
}
