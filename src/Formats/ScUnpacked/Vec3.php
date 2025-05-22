<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use DOMNode;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class Vec3 extends BaseFormat
{
    public function __construct(DOMNode|Element|RootDocument|null $item, private readonly ?array $names = [])
    {
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return [
            $this->names['x'] ?? 'x' => $this->get('x'),
            $this->names['y'] ?? 'y' => $this->get('y'),
            $this->names['z'] ?? 'z' => $this->get('z'),
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'Vec3';
    }
}
