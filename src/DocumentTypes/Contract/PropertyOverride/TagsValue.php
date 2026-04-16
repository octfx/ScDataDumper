<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\RootDocument;

class TagsValue extends RootDocument
{
    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->queryAttributeValues('tags/tags/Reference', 'value');
    }

    /**
     * @return list<string>
     */
    public function getNegativeTags(): array
    {
        return $this->queryAttributeValues('negativeTags/tags/Reference', 'value');
    }
}
