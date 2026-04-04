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
        $resolved = $this->resolveRelatedDocument(
            'MiningGlobalParams',
            MiningGlobalParams::class,
            $this->getGlobalParamsReference(),
            static fn (string $reference): ?MiningGlobalParams => ServiceFactory::getFoundryLookupService()
                ->getMiningGlobalParamsByReference($reference)
        );

        return $resolved instanceof MiningGlobalParams ? $resolved : null;
    }

    public function getComposition(): ?MineableComposition
    {
        $resolved = $this->resolveRelatedDocument(
            'MineableComposition',
            MineableComposition::class,
            $this->getCompositionReference(),
            static fn (string $reference): ?MineableComposition => ServiceFactory::getFoundryLookupService()
                ->getMineableCompositionByReference($reference)
        );

        return $resolved instanceof MineableComposition ? $resolved : null;
    }
}
