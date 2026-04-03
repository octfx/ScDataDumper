<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Reputation;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

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
        $scopeNode = $this->get('PrimaryScope');

        if (! $scopeNode instanceof Element) {
            return null;
        }

        $scope = SReputationScopeParams::fromNode($scopeNode->getNode());

        return $scope instanceof SReputationScopeParams ? $scope : null;
    }
}
