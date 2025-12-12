<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class HackingChip extends BaseFormat
{
    /**
     * We only need to ensure the item has a Components node; all other checks are done in canTransform.
     */
    protected ?string $elementKey = 'Components';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $maxCharges = $this->get('Components/HackingChipParams@maxCharges');
        $errorChanceFromChipParams = $this->get('Components/HackingChipParams@errorChance');

        $removableValues = $this->get('Components/RemovableChipParams/values');

        if ($maxCharges === null) {
            $maxCharges = $this->extractValue($removableValues, 'maxCharges')
                ?? $this->extractValue($removableValues, 'MaxCharges');
        }

        $durationMultiplier = $this->extractValue($removableValues, 'Duration')
            ?? $this->extractValue($removableValues, 'duration');

        $errorChance = $this->extractValue($removableValues, 'ErrorChance')
            ?? $this->extractValue($removableValues, 'errorchance')
            ?? $errorChanceFromChipParams;

        $accessTag = $this->item->get('Components/SAttachableComponentParams/AttachDef@Tags');
        if (is_string($accessTag) && trim($accessTag) === '') {
            $accessTag = null;
        }

        $data = [
            'MaxCharges' => $maxCharges,
            'DurationMultiplier' => $durationMultiplier,
            'ErrorChance' => $errorChance,
            'AccessTag' => $accessTag,
        ];

        $cleaned = $this->removeNullValues($data);

        return empty($cleaned) ? null : $cleaned;
    }

    public function canTransform(): bool
    {
        if ($this->item === null) {
            return false;
        }

        $type = $this->item->getAttachType();
        $subType = $this->item->getAttachSubType();
        $isHackingChip = $type === 'RemovableChip'
            || ($type === 'FPS_Consumable' && $subType === 'Hacking');

        return $isHackingChip && $this->has('/Components');
    }

    private function extractValue(?Element $values, string $needle): float|string|null
    {
        if (! $values instanceof Element) {
            return null;
        }

        foreach ($values->children() as $value) {
            $name = $value->get('@name');
            if ($name === null || strcasecmp((string) $name, $needle) !== 0) {
                continue;
            }

            $val = $value->get('@value');

            return is_numeric($val) ? (float) $val : $val;
        }

        return null;
    }
}
