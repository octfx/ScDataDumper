<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams;

use DOMDocument;
use Octfx\ScDataDumper\Helper\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * @deprecated Eager hydration is deprecated. This hydrator will be removed once ECD component hydration is migrated into EntityClassDefinition.
 */
class AttachDef extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized()) {
            return;
        }

        parent::initialize($document);

        if ($this->get('Manufacturer@__ref') !== $this->get('@Manufacturer')) {
            $manufacturer = ServiceFactory::getManufacturerService()->getByReference($this->get('@Manufacturer'));
            $this->appendNode($document, $manufacturer, 'Manufacturer');
        }
    }
}
