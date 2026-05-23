<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class CraftingGlobalParams extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getDismantleBlacklistResourceUuids(): array
    {
        return $this->readReferenceValues('dismantleBlacklistResources');
    }

    /**
     * @return list<string>
     */
    public function getDismantleBlacklistEntityClassUuids(): array
    {
        return $this->readReferenceValues('dismantleBlacklistEntityClasses');
    }

    /**
     * @return list<string>
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

    /**
     * @return list<string>
     */
    private function readReferenceValues(string $containerName): array
    {
        $uuids = [];
        $container = $this->get($containerName);

        foreach ($container?->children() ?? [] as $reference) {
            $value = $reference->get('@value');

            if (is_string($value) && $value !== '') {
                $uuids[] = $value;
            }
        }

        return $uuids;
    }
}
