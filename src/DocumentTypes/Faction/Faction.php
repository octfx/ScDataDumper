<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Faction;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

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
        $resolved = $this->resolveRelatedDocument(
            'FactionReputation',
            FactionReputation::class,
            $this->getFactionReputationReference(),
            static fn (string $reference): ?FactionReputation => ServiceFactory::getFoundryLookupService()
                ->getFactionReputationByReference($reference)
        );

        return $resolved instanceof FactionReputation ? $resolved : null;
    }
}
