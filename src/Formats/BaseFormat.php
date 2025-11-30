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
     * @return float|mixed|null|Element|string
     */
    public function get(?string $key = null, $default = null): mixed
    {
        if ($key === null && $this->elementKey === null) {
            throw new RuntimeException('Either $key must be provided or $elementKey set');
        }

        return $this->item->get($key ?? $this->elementKey, $default);
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
        $value = str_replace(['_', '-'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    /**
     * Capitalizes the first letter of each array key
     * Useful for transforming camelCase map keys to PascalCase
     *
     * @param  array  $attributes  Array with camelCase keys
     * @return array Array with PascalCase keys
     */
    protected function capitalizeArrayKeys(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $key => $value) {
            $out[ucfirst($key)] = $value;
        }

        return $out;
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
