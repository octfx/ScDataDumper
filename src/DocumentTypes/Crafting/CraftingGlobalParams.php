<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class CraftingGlobalParams extends RootDocument
{
    /**
     * @return array<int, string>
     */
    public function getDefaultBlueprintReferences(): array
    {
        $references = [];
        $blueprintRecords = $this->get('defaultBlueprintSelection/DefaultBlueprintSelection_Whitelist/blueprintRecords');

        foreach ($blueprintRecords?->children() ?? [] as $reference) {
            $value = $reference->get('@value');

            if (is_string($value) && $value !== '') {
                $references[] = $value;
            }
        }

        return $references;
    }
}
