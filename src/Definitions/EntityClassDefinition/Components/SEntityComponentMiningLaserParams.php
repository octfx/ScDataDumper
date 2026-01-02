<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SEntityComponentMiningLaserParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getMiningLaserGlobalParamsService();
        $globalParams = $svc->getByReference($this->get('@globalParams'));

        if ($this->get('MiningLaserGlobalParams@__ref') !== $this->get('@globalParams')) {
            $this->appendNode($document, $globalParams, 'MiningLaserGlobalParams');
        }
    }
}
