<?php

namespace Octfx\ScDataDumper\Formats;

use DOMNode;
use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use RuntimeException;

abstract class BaseFormat
{
    /**
     * XML Elements containing resistances / absorptions refer to resistances using numeric indexes
     * Their order "should" always be the same as defined here in the constructor
     *
     * @var array|string[]
     */
    protected static array $resistanceKeys = [
        'Physical',
        'Energy',
        'Distortion',
        'Thermal',
        'Biochemical',
        'Stun',
    ];

    /**
     * Access key in dot notation from the constructor element
     * Used for checking if element exists in canTransform
     *
     * @see {BaseFormat::canTransform}
     */
    protected ?string $elementKey = null;

    public function __construct(protected RootDocument|Element|DOMNode|null $item)
    {
        if ($item && ! $item instanceof Element) {
            $this->item = new Element($item);
        }
    }

    abstract public function toArray(): ?array;

    public function canTransform(): bool
    {
        if ($this->elementKey === null) {
            throw new RuntimeException('Element key cannot be null');
        }

        return $this->item !== null && $this->has($this->elementKey);
    }

    /**
     * Retrieve an element or attribute from `$this->item`.
     * Key defaults to `$this->elementKey` if not set.
     *
     * @param  mixed  $default  Value returned when the key is not found
     * @param  bool  $local  When true and the key has no path separator, treat it as a child of the current node
     * @return float|mixed|null|Element|string
     */
    public function get(?string $key = null, $default = null, ?bool $local = false): mixed
    {
        if ($key === null && $this->elementKey === null) {
            throw new RuntimeException('Either $key must be provided or $elementKey set');
        }

        $lookupKey = $key ?? $this->elementKey;

        if ($local && $lookupKey !== null && ! str_contains($lookupKey, '/') && ! str_contains($lookupKey, '@')) {
            // Force lookup to be treated as a child element of the current node, not an attribute on the root item
            $lookupKey = '/'.$lookupKey;
        }

        return $this->item->get($lookupKey, $default);
    }

    /**
     * Checks if the retrieved element's name is equal to the last key part, or if an attribute with this name exist
     *
     * Example: Passing `Components/IFCSParams` checks if `Components->IFCSParams->nodeName === IFCSParams`
     *
     * @param  string  $key  Format Element/Child/Child...
     */
    public function has(string $key, ?string $elementName = null): bool
    {
        if (str_contains($key, '.')) {
            throw new RuntimeException('Element key contains invalid format');
        }

        $parts = explode('/', $key);
        $elementName = $elementName ?? array_pop($parts);

        // Return key name if not found
        $obj = $this->get($key, $key);

        if ($obj === null) {
            return false;
        }

        if (! is_object($obj)) {
            return $obj !== $key;
        }

        return $obj instanceof Element && $obj->nodeName === $elementName;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Converts snake_case or camelCase strings to PascalCase
     *
     * @param  string  $value  The string to convert (e.g., 'drive_speed' or 'driveSpeed')
     * @return string PascalCase string (e.g., 'DriveSpeed')
     */
    protected function toPascalCase(string $value): string
    {
        if (ctype_upper($value[0]) && ! str_contains($value, '_') && ! str_contains($value, '-')) {
            $acronyms = ['Uuid' => 'UUID', 'Scu' => 'SCU', 'Ifcs' => 'IFCS', 'Emp' => 'EMP'];

            return $acronyms[$value] ?? $value;
        }

        $value = str_replace(['_', '-'], ' ', $value);
        $result = str_replace(' ', '', ucwords($value));

        $acronyms = ['Uuid' => 'UUID', 'Scu' => 'SCU', 'Ifcs' => 'IFCS', 'Emp' => 'EMP'];

        return $acronyms[$result] ?? $result;
    }

    /**
     * Recursively transforms all array keys to PascalCase
     *
     * @param  array|BaseFormat|null  $data  The array with mixed case keys
     * @return array Array with all keys in PascalCase
     */
    protected function transformArrayKeysToPascalCase(array|null|BaseFormat $data): array
    {
        if ($data === null) {
            return [];
        }

        if ($data instanceof self) {
            return $data->toArray() ?? [];
        }

        $result = [];

        foreach ($data as $key => $value) {
            $pascalKey = is_string($key) ? $this->toPascalCase($key) : $key;
            $result[$pascalKey] = is_array($value)
                ? $this->transformArrayKeysToPascalCase($value)
                : $value;
        }

        return $result;
    }

    /**
     * Recursively removes null values and empty arrays from a data structure
     * Modifies the array in-place for efficiency
     *
     * @param  array  $data  The array to clean
     * @return array The cleaned array with null and empty array values removed
     */
    protected function removeNullValues(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);

                if ($value === []) {
                    unset($data[$key]);

                    continue;
                }
            }

            if ($value === null) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
