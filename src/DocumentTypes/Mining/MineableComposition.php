<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MineableComposition extends RootDocument
{
    public function getDepositName(): ?string
    {
        return $this->getString('@depositName');
    }

    public function getMinimumDistinctElements(): ?int
    {
        return $this->getInt('@minimumDistinctElements');
    }

    /**
     * @return list<MineableCompositionPart>
     */
    public function getParts(): array
    {
        return $this->hydrateAll('compositionArray/MineableCompositionPart', MineableCompositionPart::class, $this->isReferenceHydrationEnabled());
    }
}
