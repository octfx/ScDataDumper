<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Reputation;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SReputationContextUI extends RootDocument
{
    public function getSortOrderScope(): ?string
    {
        return $this->getString('@sortOrderScope');
    }

    public function getPrimaryScopeReference(): ?string
    {
        return $this->getString('primaryScopeContext@scope');
    }

    public function getPrimaryScope(): ?SReputationScopeParams
    {
        $resolved = $this->resolveRelatedDocument(
            'PrimaryScope',
            SReputationScopeParams::class,
            $this->getPrimaryScopeReference(),
            static fn (string $reference): ?SReputationScopeParams => ServiceFactory::getFoundryLookupService()
                ->getReputationScopeByReference($reference)
        );

        return $resolved instanceof SReputationScopeParams ? $resolved : null;
    }

    public function getAdditionalScopeReferences(): array
    {
        return $this->queryAttributeValues('scopeContextList/SReputationScopeContextUI', 'scope');
    }

    public function getAdditionalScopes(): array
    {
        return $this->resolveRelatedDocuments(
            [],
            $this->getAdditionalScopeReferences(),
            static fn (string $reference): ?SReputationScopeParams => ServiceFactory::getFoundryLookupService()
                ->getReputationScopeByReference($reference)
        );
    }

    /**
     * The ladders that actually belong to this faction: scopes referenced only by this context (faction-specific).
     * Wikelo -> its Barter scope; HeadHunters has none, so it falls back to the allied scope.
     *
     * @param  ?string  $alliedScopeUuid  When no faction-specific ladder exists, the scope the FactionReputation record's alliedParams points
     * @return list<SReputationScopeParams>
     */
    public function getLadderScopes(?string $alliedScopeUuid = null): array
    {
        $candidates = [];
        $seen = [];

        $primary = $this->getPrimaryScope();

        if ($primary !== null) {
            $candidates[] = $primary;
            $seen[$primary->getUuid()] = true;
        }

        foreach ($this->getAdditionalScopes() as $scope) {
            if (! isset($seen[$scope->getUuid()])) {
                $candidates[] = $scope;
                $seen[$scope->getUuid()] = true;
            }
        }

        $service = ServiceFactory::getFoundryLookupService();
        $specific = array_values(array_filter(
            $candidates,
            static fn (SReputationScopeParams $scope): bool => $service->getReputationScopeContextCount($scope->getUuid()) === 1,
        ));

        if ($specific !== []) {
            return $specific;
        }

        // No faction-specific ladder
        if ($alliedScopeUuid !== null) {
            $key = strtolower($alliedScopeUuid);

            foreach ($candidates as $scope) {
                if (strtolower($scope->getUuid()) === $key) {
                    return [$scope];
                }
            }

            $byReference = $service->getReputationScopeByReference($alliedScopeUuid);

            if ($byReference !== null) {
                return [$byReference];
            }
        }

        return $primary !== null ? [$primary] : [];
    }
}
