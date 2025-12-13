<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class SelfDestruct extends BaseFormat
{
    protected ?string $elementKey = 'Components/SSCItemSelfDestructComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();

        $attributes = $component?->attributesToArray([
            'engageSelfDestructInteraction',
            'disengageSelfDestructInteraction',
            '__type',
            '__polymorphicType',
        ]) ?? [];

        $data = [];

        foreach ($attributes as $key => $value) {
            $data[$this->toPascalCase((string) $key)] = $value;
        }

        return $this->removeNullValues($data);
    }
}
