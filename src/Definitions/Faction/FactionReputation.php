<?php

namespace Octfx\ScDataDumper\Definitions\Faction;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class FactionReputation extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();
        $contextReference = $this->get('@reputationContextPropertiesUI');

        if (is_string($contextReference) && $contextReference !== '' && $this->get('ReputationContextUI@__ref') !== $contextReference) {
            $context = $lookup->getReputationContextByReference($contextReference);
            if ($context !== null) {
                $this->appendNode($document, $context, 'ReputationContextUI');
            }
        }

        $this->hydrateScope($document, 'hostilityParams', $this->get('hostilityParams@scope'));
        $this->hydrateStanding($document, 'hostilityParams', $this->get('hostilityParams@standing'));
        $this->hydrateScope($document, 'alliedParams', $this->get('alliedParams@scope'));
        $this->hydrateStanding($document, 'alliedParams', $this->get('alliedParams@standing'));
    }

    private function hydrateScope(DOMDocument $document, string $nodePath, mixed $reference): void
    {
        if (! is_string($reference) || $reference === '') {
            return;
        }

        $node = $this->get($nodePath);
        if (! $node instanceof Element || $node->get('Scope@__ref') === $reference) {
            return;
        }

        $scope = ServiceFactory::getFoundryLookupService()->getReputationScopeByReference($reference);
        if ($scope !== null) {
            $node->appendNode($document, $scope, 'Scope');
        }
    }

    private function hydrateStanding(DOMDocument $document, string $nodePath, mixed $reference): void
    {
        if (! is_string($reference) || $reference === '') {
            return;
        }

        $node = $this->get($nodePath);
        if (! $node instanceof Element || $node->get('Standing@__ref') === $reference) {
            return;
        }

        $standing = ServiceFactory::getFoundryLookupService()->getReputationStandingByReference($reference);
        if ($standing !== null) {
            $node->appendNode($document, $standing, 'Standing');
        }
    }
}
