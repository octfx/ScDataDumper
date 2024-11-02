<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper;

use Octfx\ScDataDumper\Definitions\Element;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SimpleXMLElement;

class ElementDefinitionFactory
{
    private static ?ElementDefinitionFactory $instance = null;

    private array $definitions;

    public function __construct()
    {
        $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/Definitions'));
        $phpFiles = new RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            $className = str_replace(
                [
                    __DIR__,
                    '.php',
                    DIRECTORY_SEPARATOR,
                ],
                [
                    '',
                    '',
                    '\\',
                ],
                $phpFile->getRealPath()
            );

            $className = 'Octfx\\ScDataDumper'.$className;

            $elementName = str_replace('.php', '', $phpFile->getFilename());

            $this->definitions[$elementName] = $className;
            $this->definitions[strtolower($elementName)] = $className;
        }
    }

    public function getElementDefinition(SimpleXMLElement $element, ?string $prefix = null): ?Element
    {
        $name = $element->getName();

        if ($prefix) {
            $path = sprintf('%s\\%s', $prefix, $name);
            $res = array_filter($this->definitions, static fn ($classPath) => str_ends_with($classPath, $path));

            if (! empty($res)) {

                $vals = array_values($res)[0];

                return new $vals($element->asXML());
            }

        }

        if (! isset($this->definitions[$name])) {
            return null;
        }

        return new $this->definitions[$element->getName()]($element->asXML());
    }

    /**
     * Tries to load an XML Element representation from the Definitions folder.
     * If a prefix is provided, it first tries to match this against the folder structure, e.g. the prefix EntityClassDefinition\Components\Foo would search for a class Foo in EntityClassDefinition/Components
     *
     * If a prefix was provided but no class was found, the name of the provided element is used to search for a matching class definition
     */
    public static function getDefinition(SimpleXMLElement $element, ?string $prefix = null): ?Element
    {
        if (static::$instance === null) {
            static::$instance = new ElementDefinitionFactory;
        }

        return static::$instance->getElementDefinition($element, $prefix);
    }
}
