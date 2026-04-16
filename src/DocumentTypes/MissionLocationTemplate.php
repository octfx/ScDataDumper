<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;

final class MissionLocationTemplate extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getProducesPositiveTagReferences(): array
    {
        return $this->queryAttributeValues('locationData/producesTags/positiveTags/Reference', 'value');
    }

    /**
     * @return list<string>
     */
    public function getProducesNegativeTagReferences(): array
    {
        return $this->queryAttributeValues('locationData/producesTags/negativeTags/Reference', 'value');
    }

    /**
     * @return list<string>
     */
    public function getConsumesPositiveTagReferences(): array
    {
        return $this->queryAttributeValues('locationData/consumesTags/positiveTags/Reference', 'value');
    }

    /**
     * @return list<string>
     */
    public function getConsumesNegativeTagReferences(): array
    {
        return $this->queryAttributeValues('locationData/consumesTags/negativeTags/Reference', 'value');
    }

    /**
     * @return list<string>
     */
    public function getGeneralTagReferences(): array
    {
        return $this->queryAttributeValues('locationData/generalTags/tags/Reference', 'value');
    }

    public function getMissionLimitTags(): array
    {
        return $this->queryAttributeValues('locationData/missionLimits/LocationMissionLimit', 'tag');
    }

    public function isDisabled(): bool
    {
        return $this->getBool('locationData@disabled');
    }

    /**
     * @return list<array{tag: string, string: string}>
     */
    public function getStringVariants(): array
    {
        $variants = [];

        foreach ($this->getAll('locationData/stringVariants/variants/MissionStringVariant') as $node) {
            if ($node instanceof Element) {
                $tag = $node->get('@tag');
                $string = $node->get('@string');
                if (is_string($tag) && is_string($string)) {
                    $variants[] = ['tag' => $tag, 'string' => $string];
                }
            }
        }

        return $variants;
    }

    public function getDisplayName(): ?string
    {
        $variants = $this->getStringVariants();

        foreach ($variants as $variant) {
            if ($variant['string'] !== '' && str_starts_with($variant['string'], '@')) {
                return $variant['string'];
            }
        }

        return null;
    }

    public function hasTradeTags(): bool
    {
        return $this->getProducesPositiveTagReferences() !== [] || $this->getConsumesPositiveTagReferences() !== [];
    }
}
