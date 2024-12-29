<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class MinMaxList extends BaseFormat
{
    public function __construct(RootDocument|Element $item, private readonly string $key, private readonly int $numChildren)
    {
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $out = [];

        for ($i = 0; $i < 6; $i++) {
            $minMax = new MinMax($this->item->children()[$i]);
            if ($minMax->canTransform()) {
                $out[self::$resistanceKeys[$i]] = $minMax->toArray();
            }
        }

        return $out;
    }

    public function canTransform(): bool
    {
        return $this->has($this->key) && count($this->item?->children() ?? []) === $this->numChildren;
    }
}
