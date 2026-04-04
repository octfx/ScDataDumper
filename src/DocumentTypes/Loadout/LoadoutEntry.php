<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Loadout;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class LoadoutEntry extends RootDocument
{
    /**
     * @return list<self>
     */
    public static function fromEntriesNode(Element|\DOMNode|null $entriesNode, bool $referenceHydrationEnabled = false): array
    {
        if ($entriesNode instanceof \DOMNode && ! $entriesNode instanceof Element) {
            $entriesNode = new Element($entriesNode);
        }

        if (! $entriesNode instanceof Element) {
            return [];
        }

        $entries = [];

        foreach ($entriesNode->children() as $child) {
            if (! in_array($child->nodeName, ['SItemPortLoadoutEntryParams', 'item'], true)) {
                continue;
            }

            $entry = self::fromNode($child->getNode(), $referenceHydrationEnabled);

            if ($entry instanceof self) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function getPortName(): ?string
    {
        return $this->getString('@itemPortName') ?? $this->getString('@portName');
    }

    public function getEntityClassName(): ?string
    {
        return $this->getString('@entityClassName') ?? $this->getString('@itemName');
    }

    public function getEntityClassReference(): ?string
    {
        return $this->getString('@entityClassReference');
    }

    public function getInstalledItem(): ?EntityClassDefinition
    {
        $itemService = ServiceFactory::getItemService();
        $installedItem = $this->resolveRelatedDocument(
            'InstalledItem',
            EntityClassDefinition::class,
            $this->getEntityClassReference(),
            static fn (string $reference): ?EntityClassDefinition => $itemService->getByReference($reference)
                ?? $itemService->getByClassName($reference)
        );

        if ($installedItem instanceof EntityClassDefinition) {
            return $installedItem;
        }

        $className = $this->getEntityClassName();

        if ($className === null || $className === '') {
            return null;
        }

        return $itemService->getByClassName($className);
    }

    /**
     * @return list<LoadoutEntry>
     */
    public function getNestedEntries(): array
    {
        return self::fromEntriesNode(
            $this->get('loadout/SItemPortLoadoutManualParams/entries'),
            $this->isReferenceHydrationEnabled()
        );
    }

    /**
     * @return array{portName: ?string, className: ?string, classReference: ?string, entries: list<array>}
     */
    public function toDefinitionArray(): array
    {
        return [
            'portName' => $this->getPortName(),
            'className' => $this->getEntityClassName(),
            'classReference' => $this->getEntityClassReference(),
            'entries' => array_map(
                static fn (self $entry): array => $entry->toDefinitionArray(),
                $this->getNestedEntries()
            ),
        ];
    }
}
