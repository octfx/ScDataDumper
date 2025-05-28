<?php

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SItemPortLoadoutEntryParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->isInitialized() || $this->get('InstalledItem')) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getItemService();

        $className = $this->get('@entityClassName');
        $uuid = $this->get('@entityClassReference');

        if ($uuid && $uuid !== '00000000-0000-0000-0000-000000000000') {
            $item = $svc->getByReference($uuid);
        } else {
            $item = $svc->getByClassName($className);
        }

        if ($item) {
            if ($this->get('InstalledItem@__ref') === $item->getUuid()) {
                return;
            }

            $importedNode = $document->importNode($item->documentElement, true);
            $element = $document->createElement('InstalledItem');

            if ($item->firstChild?->attributes) {
                foreach ($item->firstChild?->attributes as $name => $attribute) {
                    $element->setAttribute($name, $attribute->nodeValue);
                }
            }

            $element->append(...$importedNode->childNodes);
            $this->node->appendChild($element);
        }
    }
}
