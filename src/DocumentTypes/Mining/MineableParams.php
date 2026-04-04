<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MineableParams extends RootDocument
{
    public function getGlobalParamsReference(): ?string
    {
        return $this->getString('@globalParams');
    }

    public function getCompositionReference(): ?string
    {
        return $this->getString('@composition');
    }

    public function getGlobalParams(): ?MiningGlobalParams
    {
        return $this->getHydratedDocument('MiningGlobalParams', MiningGlobalParams::class);
    }

    public function getComposition(): ?MineableComposition
    {
        return $this->getHydratedDocument('MineableComposition', MineableComposition::class);
    }
}
