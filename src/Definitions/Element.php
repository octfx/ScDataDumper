<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Definitions;

use Exception;
use JsonException;
use Octfx\ScDataDumper\ElementDefinitionFactory;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use SimpleXMLElement;

abstract class Element extends SimpleXMLElement
{
    protected static string $element = '';

    /**
     * @throws Exception
     */
    public static function fromElement(SimpleXMLElement $parent): ?static
    {
        $element = static::$element;

        if ($element === '') {
            $parts = explode('\\', static::class);
            $element = array_pop($parts);
        }

        $element = $parent->{$element};

        if (! ($element instanceof SimpleXMLElement)) {
            return null;
        }

        return new static($element[0]->asXML());
    }

    /**
     * Checks that the instantiated definition matches the xml one
     */
    public function checkValidity(): void
    {
        if (! str_contains($this->getName(), '.')) {
            throw new RuntimeException('Invalid definition name');
        }

        $parts = explode('.', $this->getName());

        if (! str_contains(static::class, $parts[0])) {
            throw new RuntimeException(sprintf('Tried instantiating %s while definition is %s', static::class, $parts[0]));
        }
    }

    /**
     * First part of root node name, split by `.`.
     */
    public function getClassName(): string
    {
        return explode('.', $this->getName())[1];
    }

    /**
     * Value from `__type` or empty string if not found
     */
    public function getType(): string
    {
        return (string) ($this['__type'] ?? '');
    }

    /**
     * Value from `__ref` or empty string if not found
     */
    public function getUuid(): string
    {
        return (string) ($this['__ref'] ?? '');
    }

    public function toArray(): array
    {
        return $this->toArrayRecursive($this);
    }

    /**
     * Retrieve a child or attribute name by dot notation, e.g. `Components.AttachDef` to retrieve the `AttachDef` child from `Components`.
     *
     * @return $this|float|mixed|Element|string|null
     */
    public function get(string $key, $default = null): mixed
    {
        $return = $this;
        $keyParts = explode('.', $key);
        $target = $keyParts[array_key_last($keyParts)];

        foreach ($keyParts as $segment) {
            // Child exists and segment is not found in attributes
            if ($return->{$segment} instanceof SimpleXMLElement && ! isset($return[$segment])) {
                $return = $return->{$segment};
            } elseif (isset($return[$segment])) {
                $data = (string) $return[$segment];

                if (is_numeric($data)) {
                    $data = (float) $data;
                }

                return $data === 'null' ? $default : $data;
            } else {
                return $default;
            }
        }

        if ($return?->getName() !== $target) {
            return $default;
        }

        return $return;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Recursively walks the given xml $element and turns it into an array.
     * If a node name matches a class in Definitions, it is passed to the matching definition and `toArray()` is called on the instance
     *
     * @see ElementDefinitionFactory
     */
    protected function toArrayRecursive(SimpleXMLElement $element, ?string $prefix = ''): array
    {
        $cleanedName = str_contains($element->getName(), '.') ? explode('.', $element->getName())[0] : $element->getName();

        $data = $this->attributesToArray($element);

        if (count($element) > 0) {
            $prf = $prefix ? sprintf('%s\\%s', $prefix, $cleanedName) : $cleanedName;

            $isArray = false;

            foreach ($element as $child) {
                if (isset($data[$child->getName()])) {
                    $isArray = true;
                    $data = [
                        $data[$child->getName()],
                    ];
                }

                if ($isArray) {
                    $data[] = $this->toArrayRecursive($child, $prf);
                } else {
                    $data[$child->getName()] = $this->toArrayRecursive($child, $prf);
                }
            }
        }

        $definition = ElementDefinitionFactory::getDefinition($element, $prefix);
        if ($definition && $definition->getName() !== $element->getName()) {
            $data = array_merge($data, $definition->toArray());
        }

        unset($data['@attributes']);

        return $data;
    }

    /**
     * Turns Element Attributes into an array
     */
    public function attributesToArray(?SimpleXMLElement $element = null, ?array $unsetKeys = []): array
    {
        $element = $element ?? $this;

        if (! count($element->attributes() ?? [])) {
            return [];
        }

        $attributes = ((array) $element->attributes())['@attributes'];
        unset($attributes['__type'], $attributes['__polymorphicType']);

        foreach ($unsetKeys as $unsetKey) {
            unset($attributes[$unsetKey]);
        }

        foreach ($attributes as $name => $value) {
            if (is_numeric($value)) {
                $attributes[$name] = (float) $value;
            } elseif (str_starts_with((string) $value, '@')) {
                $attributes[$name] = ['key' => (string) $value] + ServiceFactory::getLocalizationService()->getTranslations((string) $value);
            }
        }

        return $attributes;
    }
}
