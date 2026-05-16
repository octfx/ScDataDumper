<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @deprecated Eager hydration is deprecated. This hydrator will be removed once ECD component hydration is migrated into EntityClassDefinition.
 */
class SCItemRadarComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getFoundryLookupService();
        $radarSystem = $svc->getRadarSystemParamsByReference($this->get('@sharedParams'));

        if ($this->get('RadarSystem@__ref') !== $this->get('@sharedParams')) {
            $this->appendNode($document, $radarSystem, 'RadarSystem');
        }
    }
}
