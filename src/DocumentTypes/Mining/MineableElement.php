<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class MineableElement extends RootDocument
{
    public function getResourceTypeReference(): ?string
    {
        return $this->getString('@resourceType');
    }

    public function getResourceType(): ?ResourceType
    {
        $resourceType = $this->getHydratedDocument('ResourceType', ResourceType::class);

        if ($resourceType instanceof ResourceType) {
            return $resourceType;
        }

        $reference = $this->getResourceTypeReference();

        if ($reference === null) {
            return null;
        }

        $resolved = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference);

        return $resolved instanceof ResourceType ? $resolved : null;
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
