<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\DOMElementProxy;

final class MinMax extends BaseFormat
{
    public function __construct(RootDocument|DOMElementProxy $item, private readonly ?string $minKey = 'Min', private readonly ?string $maxKey = 'Max')
    {
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            'Minimum' => $this->get($this->minKey),
            'Maximum' => $this->get($this->maxKey),
        ];
    }

    public function canTransform(): bool
    {
        return $this->has($this->minKey) && $this->has($this->maxKey);
    }
}
