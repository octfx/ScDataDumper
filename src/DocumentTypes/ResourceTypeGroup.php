<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

final class ResourceTypeGroup extends RootDocument
{
    /**
     * @return list<string> UUIDs of direct resource members
     */
    public function getResourceReferences(): array
    {
        return $this->queryAttributeValues('resources/Reference', 'value');
    }

    /**
     * @return list<string> UUIDs of child groups
     */
    public function getChildGroupReferences(): array
    {
        return $this->queryAttributeValues('groups/Reference', 'value');
    }
}
