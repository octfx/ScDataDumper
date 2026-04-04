<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;

final class MineableElement extends RootDocument
{
    public function getResourceTypeReference(): ?string
    {
        return $this->getString('@resourceType');
    }

    public function getResourceType(): ?ResourceType
    {
        return $this->getHydratedDocument('ResourceType', ResourceType::class);
    }

    public function getInstability(): ?float
    {
        return $this->getFloat('@elementInstability');
    }

    public function getResistance(): ?float
    {
        return $this->getFloat('@elementResistance');
    }
}
