<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @deprecated Eager hydration is deprecated. This hydrator will be removed once ECD component hydration is migrated into EntityClassDefinition.
 */
class SEntityComponentMiningLaserParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getFoundryLookupService();
        $globalParams = $svc->getMiningLaserGlobalParamsByReference($this->get('@globalParams'));

        if ($this->get('MiningLaserGlobalParams@__ref') !== $this->get('@globalParams')) {
            $this->appendNode($document, $globalParams, 'MiningLaserGlobalParams');
        }
    }
}
