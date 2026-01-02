<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class MinMaxList extends BaseFormat
{
    public function __construct(RootDocument|Element $item, private readonly string $key)
    {
        $this->elementKey = $this->key;
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $out = [];
        $i = 0;

        foreach ($this->item->children() as $child) {
            $minMax = new MinMax($child);
            if ($minMax->canTransform()) {
                $out[self::$resistanceKeys[$i++]] = $minMax->toArray();
            }
        }

        return $out;
    }
}
