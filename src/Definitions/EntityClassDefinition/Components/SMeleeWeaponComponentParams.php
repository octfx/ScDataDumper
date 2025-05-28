<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SMeleeWeaponComponentParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getMeleeCombatConfigService();
        $meleeCombatConfig = $svc->getByReference($this->get('@meleeCombatConfig'));

        if ($this->get('MeleeCombatConfig@__ref') !== $this->get('@meleeCombatConfig')) {
            $this->appendNode($document, $meleeCombatConfig, 'MeleeCombatConfig');
        }
    }
}
