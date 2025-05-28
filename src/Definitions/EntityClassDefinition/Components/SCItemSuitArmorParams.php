<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SCItemSuitArmorParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getDamageResistanceMacroService();
        $damageResistance = $svc->getByReference($this->get('@damageResistance'));

        if ($this->get('DamageResistance@__ref') !== $this->get('@damageResistance')) {
            $this->appendNode($document, $damageResistance, 'DamageResistance');
        }
    }
}
