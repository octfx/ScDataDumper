<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

final class BlueprintPoolRecord extends RootDocument
{
    /**
     * @return array<int, string>
     */
    public function getBlueprintRewardReferences(): array
    {
        $references = [];
        $blueprintRewards = $this->get('blueprintRewards');

        foreach ($blueprintRewards?->children() ?? [] as $reward) {
            $value = $reward->get('@blueprintRecord');

            if (is_string($value) && $value !== '') {
                $references[] = $value;
            }
        }

        return $references;
    }
}
