<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper;

use DOMNode;
use Octfx\ScDataDumper\Definitions\Element;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ElementDefinitionFactory
{
    private static ?ElementDefinitionFactory $instance = null;

    /**
     * Maps element names to Class Names from `Definitions` folder
     */
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

    public function getElementDefinition(DOMNode $element): ?Element
    {
        $path = $element->getNodePath();
        $name = $element->nodeName;

        if ($path) {
            $parts = explode('/', ltrim($path, '/'));

            $elementName = array_shift($parts);
            $elementName = explode('.', $elementName);
            $elementName = array_shift($elementName);

            if ($elementName) {
                array_unshift($parts, $elementName);
            } else {
                $parts = explode('/', ltrim($path, '/'));
            }

            $path = implode('/', $parts);
            $res = array_filter($this->definitions, static fn ($classPath) => str_ends_with($classPath, $path));

            if (! empty($res)) {
                /** @var Element $vals */
                $vals = array_values($res)[0];

                return new $vals($element);
            }
        }

        if (! isset($this->definitions[$name])) {
            return null;
        }

        return new $this->definitions[$element->nodeName]($element);
    }

    /**
     * Tries to load an XML Element representation from the Definitions folder.
     * If a prefix is provided, it first tries to match this against the folder structure, e.g. the prefix EntityClassDefinition\Components\Foo would search for a class Foo in EntityClassDefinition/Components
     *
     * If a prefix was provided but no class was found, the name of the provided element is used to search for a matching class definition
     */
    public static function getDefinition(DOMNode $element): ?Element
    {
        if (static::$instance === null) {
            static::$instance = new ElementDefinitionFactory;
        }

        return static::$instance->getElementDefinition($element);
    }
}
