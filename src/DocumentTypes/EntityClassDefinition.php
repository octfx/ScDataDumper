<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class EntityClassDefinition extends RootDocument
{
    public function getAttachDef(): ?Element
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef');
    }

    public function getClassification(): ?string
    {
        return ServiceFactory::getItemClassifierService()->classify($this);
    }

    public function getAttachType(): ?string
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef@Type');
    }

    public function getAttachSubType(): ?string
    {
        return $this->get('Components/SAttachableComponentParams/AttachDef@SubType');
    }

    public function getTagList(): array
    {
        $tags = $this->get('Components/SAttachableComponentParams/AttachDef@Tags', '') ?? '';

        return array_filter(array_map(static fn ($tag) => trim($tag), explode(' ', $tags)));
    }
}
