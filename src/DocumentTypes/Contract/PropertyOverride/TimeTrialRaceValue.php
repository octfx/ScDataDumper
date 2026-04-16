<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class TimeTrialRaceValue extends RootDocument
{
    /**
     * @return list<float>
     */
    public function getTargetSplits(): array
    {
        return array_map(
            static fn ($node): float => (float) ($node->get('@targetSplit') ?? 0),
            $this->getAll('targetSplits/TimeTrialSplit'),
        );
    }
}
