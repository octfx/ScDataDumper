<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class SItemPortLoadoutXMLParams extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->get('@loadoutPath') === null) {
            return;
        }

        $path = $this->get('@loadoutPath');

        $loadOutSvc = ServiceFactory::getLoadoutFileService();

        $file = $loadOutSvc->getByLoadoutPath($path);

        if ($file === null || ! $file->get('//Loadout/Items')?->getNode()->hasChildNodes()) {
            return;
        }

        if ($this->get('InstalledItem')) {
            return;
        }

        parent::initialize($document);

        $svc = ServiceFactory::getItemService();

        $parentEl = new Element($this->node->parentNode);
        if ($parentEl->get('SItemPortLoadoutManualParams')) {
            return;
        }

        $manualParams = $parentEl->get('SItemPortLoadoutManualParams');

        foreach ($this->node->parentNode->childNodes as $childNode) {
            if ($childNode->nodeName === 'SItemPortLoadoutManualParams') {
                return;
            }
        }

        if (! $manualParams) {
            $manualParams = $document->createElement('SItemPortLoadoutManualParams');

            $entries = $document->createElement('entries');
            $manualParams->appendChild($entries);

            $this->node->parentNode->appendChild($manualParams);
        } else {
            $entries = $manualParams->get('entries');
        }

        foreach ($file->get('//Loadout/Items')?->children() as $node) {
            $item = $svc->getByClassName($node->get('@itemName'));

            if (! $item) {
                continue;
            }

            $importedNode = $document->importNode($item->documentElement, true);

            $port = $document->createElement('SItemPortLoadoutEntryParams');
            $port->setAttribute('itemPortName', $node->get('@portName'));
            $port->setAttribute('entityClassReference', $node->get('@itemName'));
            $element = $document->createElement('InstalledItem');

            if ($item->firstChild?->attributes) {
                foreach ($item->firstChild?->attributes as $name => $attribute) {
                    $element->setAttribute($name, $attribute->nodeValue);
                }
            }

            $element->append(...$importedNode->childNodes);

            $port->appendChild($element);
            $entries->appendChild($port);

            $this->node->parentNode->appendChild($manualParams);
        }
    }
}
