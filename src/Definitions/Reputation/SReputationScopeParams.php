<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\Reputation;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class SReputationScopeParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $lookup = ServiceFactory::getFoundryLookupService();

        foreach ($this->getAll('standingMap/standings/Reference') as $reference) {
            if (! $reference instanceof Element) {
                continue;
            }

            $uuid = $reference->get('@value');
            if (! is_string($uuid) || $uuid === '') {
                continue;
            }

            if ($reference->get('Standing@__ref') === $uuid) {
                continue;
            }

            $standing = $lookup->getReputationStandingByReference($uuid);
            if ($standing !== null) {
                $reference->appendNode($document, $standing, 'Standing');
            }
        }
    }
}
