<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;

final class Food extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemConsumableParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $attachDef = $this->item->getAttachDef();

        if ($attachDef === null) {
            return null;
        }

        $consumable = $this->get();

        $descriptionData = ItemDescriptionParser::parse(
            $attachDef->get('Localization/English@Description', ''),
            [
                'NDR' => 'NutritionalDensityRating',
                'HEI' => 'HydrationEfficacyIndex',
                'Effects' => 'Effects',
                'Effect' => 'Effect',
            ]
        );

        $effects = $this->buildEffectsList($descriptionData['data'] ?? []);

        $containerType = $consumable->get('containerTypeTag');

        if ($containerType === '') {
            $containerType = null;
        }

        return [
            'NutritionalDensityRating' => $descriptionData['data']['NutritionalDensityRating'] ?? null,
            'HydrationEfficacyIndex' => $descriptionData['data']['HydrationEfficacyIndex'] ?? null,
            'Effects' => $effects,
            'Type' => $attachDef->get('Type'),
            'ConsumeVolume' => Item::convertToScu($consumable->get('Volume')),
            'ContainerClosed' => $consumable->get('containerClosed'),
            'ContainerType' => $containerType,
            'ContainerTypeTag' => $consumable->get('containerTypeTag'),
            'OneShotConsume' => $consumable->get('oneShotConsume'),
            'CanBeReclosed' => $consumable->get('canBeReclosed'),
            'DiscardWhenConsumed' => $consumable->get('discardWhenConsumed'),
        ];
    }

    private function buildEffectsList(array $data): ?array
    {
        $effects = [];

        foreach (['Effects', 'Effect'] as $key) {
            if (! empty($data[$key])) {
                $effects = array_merge($effects, array_map('trim', explode(',', $data[$key])));
            }
        }

        $effects = array_filter($effects, static fn ($effect) => $effect !== '');

        if (empty($effects)) {
            return null;
        }

        return array_values(array_unique($effects));
    }
}
