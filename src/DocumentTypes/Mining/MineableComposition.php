<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\Definitions\Element;
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
        $parts = [];

        foreach ($this->getAll('compositionArray/MineableCompositionPart') as $node) {
            if (! $node instanceof Element) {
                continue;
            }

            $part = MineableCompositionPart::fromNode($node->getNode());

            if ($part instanceof MineableCompositionPart) {
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
