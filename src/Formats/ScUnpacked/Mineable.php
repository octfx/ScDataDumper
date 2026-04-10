<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableCompositionPart;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class Mineable extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->canTransform() || ! $this->item instanceof EntityClassDefinition) {
            return null;
        }

        $mineableParams = $this->item->getMineableParams();
        $composition = $mineableParams?->getComposition();

        if ($mineableParams === null || $composition === null) {
            return null;
        }

        return $this->removeNullValues([
            'GlobalParamsReference' => $mineableParams->getGlobalParamsReference(),
            'CompositionReference' => $mineableParams->getCompositionReference(),
            'DepositName' => ServiceFactory::getLocalizationService()->translateValue($composition->getDepositName()),
            'MinimumDistinctElements' => $composition->getMinimumDistinctElements(),
            'Composition' => array_values(array_filter(
                array_map(fn (MineableCompositionPart $part): ?array => $this->formatCompositionPart($part), $composition->getParts())
            )),
            'Signature' => $this->item->getRsSignature(),
        ]);
    }

    public function canTransform(): bool
    {
        return $this->item instanceof EntityClassDefinition
            && $this->item->getMineableParams() !== null;
    }

    private function formatCompositionPart(MineableCompositionPart $part): ?array
    {
        $mineableElement = $part->getMineableElement();
        $resourceType = $mineableElement?->getResourceType();

        return $this->removeNullValues([
            'UUID' => $part->getMineableElementReference(),
            'ResourceTypeReference' => $mineableElement?->getResourceTypeReference(),
            'ResourceTypeClassName' => $resourceType?->getClassName(),
            'ResourceTypeDisplayName' => ServiceFactory::getLocalizationService()->translateValue($resourceType?->get('@displayName')),
            'MinPercentage' => $part->getMinPercentage(),
            'MaxPercentage' => $part->getMaxPercentage(),
            'Probability' => $part->getProbability(),
            'QualityScale' => $part->getQualityScale(),
            'CurveExponent' => $part->getCurveExponent(),
            'Instability' => $mineableElement?->getInstability(),
            'Resistance' => $mineableElement?->getResistance(),
        ]);
    }
}
