<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Reputation;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SReputationScopeParams extends RootDocument
{
    public function getScopeName(): ?string
    {
        return $this->getString('@scopeName');
    }

    public function getDisplayName(): ?string
    {
        return $this->getString('@displayName');
    }

    public function getDescription(): ?string
    {
        return $this->getString('@description');
    }

    public function getReputationCeiling(): ?int
    {
        return $this->getInt('standingMap@reputationCeiling');
    }

    public function getInitialReputation(): ?int
    {
        return $this->getInt('standingMap@initialReputation');
    }

    /**
     * @return list<string>
     */
    public function getStandingReferences(): array
    {
        return $this->queryAttributeValues('standingMap/standings/Reference', 'value');
    }

    /**
     * @return list<SReputationStandingParams>
     */
    public function getStandings(): array
    {
        $standings = $this->hydrateAll('standingMap/standings/Reference/Standing', SReputationStandingParams::class);

        return $this->resolveRelatedDocuments(
            $standings,
            $this->getStandingReferences(),
            static fn (string $reference): ?SReputationStandingParams => ServiceFactory::getFoundryLookupService()
                ->getReputationStandingByReference($reference)
        );
    }
}
