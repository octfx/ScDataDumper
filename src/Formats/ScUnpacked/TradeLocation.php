<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class TradeLocation extends BaseFormat
{
    public function toArray(): ?array
    {
        if (! $this->item instanceof MissionLocationTemplate) {
            return null;
        }

        if (! $this->item->hasTradeTags()) {
            return null;
        }

        $template = $this->item;

        $producesPositive = $this->resolveTags($template->getProducesPositiveTagReferences());
        $producesNegative = $this->resolveTags($template->getProducesNegativeTagReferences());
        $consumesPositive = $this->resolveTags($template->getConsumesPositiveTagReferences());
        $consumesNegative = $this->resolveTags($template->getConsumesNegativeTagReferences());

        $displayName = $template->getDisplayName();
        if ($displayName !== null) {
            $displayName = ServiceFactory::getLocalizationService()->translateValue($displayName);
        }

        return $this->transformArrayKeysToPascalCase([
            'uuid' => $template->getUuid(),
            'className' => $template->getClassName(),
            'displayName' => $displayName,
            'disabled' => $template->isDisabled(),
            'producesTags' => [
                'positive' => $producesPositive,
                'negative' => $producesNegative,
            ],
            'consumesTags' => [
                'positive' => $consumesPositive,
                'negative' => $consumesNegative,
            ],
        ]);
    }

    /**
     * @param  list<string>  $uuids
     * @return list<array{uuid: string, name: ?string}>
     */
    private function resolveTags(array $uuids): array
    {
        $tagService = ServiceFactory::getTagDatabaseService();
        $result = [];

        foreach ($uuids as $uuid) {
            $result[] = [
                'uuid' => $uuid,
                'name' => $tagService->getTagName($uuid),
            ];
        }

        return $result;
    }
}
