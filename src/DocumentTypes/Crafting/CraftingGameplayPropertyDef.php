<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

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
}
