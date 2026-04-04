<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

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
        $globalParams = $this->getHydratedDocument('MiningGlobalParams', MiningGlobalParams::class);

        if ($globalParams instanceof MiningGlobalParams) {
            return $globalParams;
        }

        $reference = $this->getGlobalParamsReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getMiningGlobalParamsByReference($reference);

        return $resolved instanceof MiningGlobalParams ? $resolved : null;
    }

    public function getComposition(): ?MineableComposition
    {
        $composition = $this->getHydratedDocument('MineableComposition', MineableComposition::class);

        if ($composition instanceof MineableComposition) {
            return $composition;
        }

        $reference = $this->getCompositionReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getMineableCompositionByReference($reference);

        return $resolved instanceof MineableComposition ? $resolved : null;
    }
}
