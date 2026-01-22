<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprint;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class BlueprintCategory extends BaseFormat
{
    private ?string $categoryUuid = null;

    protected ?string $elementKey = '/BlueprintCategory';

    public function __construct(Element|string|null $item)
    {
        if ($item instanceof CraftingBlueprint) {
            $this->categoryUuid = $item->getCategoryUuid();
        } elseif (is_string($item)) {
            $this->categoryUuid = $item;
        }

        parent::__construct(null);
    }

    public function toArray(): ?array
    {
        if ($this->categoryUuid === null) {
            return null;
        }

        $tagName = ServiceFactory::getTagDatabaseService()->getTagName($this->categoryUuid);

        if ($tagName === null) {
            return null;
        }

        return $this->removeNullValues([
            'UUID' => $this->categoryUuid,
            'Name' => $tagName,
        ]);
    }
}
