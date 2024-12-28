<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions\EntityClassDefinition\Components\SAttachableComponentParams\AttachDef;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ServiceFactory;

class Localization extends Element
{
    public function initialize(DOMDocument $document): void
    {
        if ($this->initialized) {
            return;
        }

        parent::initialize($document);

        $t = ServiceFactory::getLocalizationService();

        $locales = [
            'english' => 'English',
            // 'german_(germany)' => 'German',
        ];

        $keys = [
            'Name',
            'ShortName',
            'Description',
        ];

        foreach ($locales as $locale => $element) {
            $translation = $document->createElement($element);

            $translation->setAttribute('Locale', $locale);

            foreach ($keys as $key) {
                $translation->setAttribute($key, $t->getTranslation($this->get($key), $locale));
            }

            $this->node->appendChild($translation);
        }
    }
}
