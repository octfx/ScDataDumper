<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class CraftingGameplayPropertyDef extends RootDocument
{
    public function getPropertyKey(): string
    {
        $source = pathinfo($this->getPath(), PATHINFO_FILENAME);

        if ($source === '') {
            $source = $this->getClassName();
        }

        $normalized = strtolower($source);

        if (str_starts_with($normalized, 'gpp_')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized;
    }

    public function getNormalizedPropertyKey(): ?string
    {
        $key = $this->getPropertyKey();

        return $key === '' ? null : $key;
    }

    public static function resolveFromModifier(Element $modifier): ?self
    {
        $property = $modifier->get('GameplayProperty');

        if ($property instanceof Element) {
            $resolved = self::fromNode($property->getNode());

            if ($resolved instanceof self) {
                return $resolved;
            }
        }

        $reference = $modifier->get('@gameplayPropertyRecord');

        return is_string($reference) && $reference !== ''
            ? ServiceFactory::getFoundryLookupService()->getCraftingGameplayPropertyByReference($reference)
            : null;
    }
}
