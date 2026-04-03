<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Reputation;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SReputationContextUI extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();
        $scopeReference = $this->get('primaryScopeContext@scope');

        if (! is_string($scopeReference) || $scopeReference === '') {
            return;
        }

        if ($this->get('PrimaryScope@__ref') === $scopeReference) {
            return;
        }

        $scope = $lookup->getReputationScopeByReference($scopeReference);
        if ($scope !== null) {
            $this->appendNode($document, $scope, 'PrimaryScope');
        }
    }
}
