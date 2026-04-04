<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Loadout\LoadoutEntry;
use RuntimeException;

final class Loadout extends RootDocument
{
    public function checkValidity(?string $nodeName = null): void
    {
        $rootName = $nodeName ?? $this->documentElement->nodeName;

        if (str_contains($rootName, '.')) {
            parent::checkValidity($rootName);

            return;
        }

        if ($rootName === 'Loadout' || $this->getLoadoutElement() instanceof \DOMElement) {
            return;
        }

        throw new RuntimeException('Invalid loadout structure');
    }

    /**
     * @return list<LoadoutEntry>
     */
    public function getEntries(): array
    {
        return LoadoutEntry::fromEntriesNode(
            $this->getItemsElement(),
            $this->isReferenceHydrationEnabled()
        );
    }

    public function hasLoadoutItems(): bool
    {
        return $this->getItemsElement() instanceof Element;
    }

    private function getItemsElement(): ?Element
    {
        $loadout = $this->getLoadoutElement();

        if (! $loadout instanceof \DOMElement) {
            return null;
        }

        foreach ($loadout->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'Items') {
                return new Element($child);
            }
        }

        return null;
    }

    private function getLoadoutElement(): ?\DOMElement
    {
        $root = $this->documentElement;

        if (! $root instanceof \DOMElement) {
            return null;
        }

        if ($root->nodeName === 'Loadout') {
            return $root;
        }

        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'Loadout') {
                return $child;
            }
        }

        foreach ($root->getElementsByTagName('Loadout') as $loadout) {
            if ($loadout instanceof \DOMElement) {
                return $loadout;
            }
        }

        return null;
    }
}
