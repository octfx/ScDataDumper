<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams\AttachDef;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class Localization extends Element
{
    protected array $locales = [
        'english' => 'English',
        // 'german_(germany)' => 'German',
    ];

    protected array $keys = [
        'Name',
        'ShortName',
        'Description',
    ];

    public function initialize(DOMDocument $document): void
    {
        if ($this->initialized) {
            return;
        }

        parent::initialize($document);

        $t = ServiceFactory::getLocalizationService();

        foreach ($this->locales as $locale => $element) {
            $translation = $document->createElement($element);

            $translation->setAttribute('Locale', $locale);

            foreach ($this->keys as $key) {
                $translation->setAttribute($key, $t->getTranslation($this->get($key), $locale));
            }

            $this->node->appendChild($translation);
        }
    }
}
