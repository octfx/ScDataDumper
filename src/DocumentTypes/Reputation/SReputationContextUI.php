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
}
