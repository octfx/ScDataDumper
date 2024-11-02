<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams\AttachDef;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class Localization extends Element
{
    public function toArray(): array
    {
        $t = ServiceFactory::getLocalizationService();

        return [
            'en' => [
                'Name' => $t->getTranslation((string) $this->attributes()->Name),
                'ShortName' => $t->getTranslation((string) $this->attributes()->ShortName),
                'Description' => $t->getTranslation((string) $this->attributes()->Description),
            ],
        ] + $this->attributesToArray();
    }
}
