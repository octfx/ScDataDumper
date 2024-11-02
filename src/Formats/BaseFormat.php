<?php

namespace Octfx\ScDataDumper\Formats;

use JsonException;
use Octfx\ScDataDumper\Definitions\Element;
use RuntimeException;
use SimpleXMLElement;

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

    public function __construct(protected readonly ?Element $item) {}

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
     * @return float|mixed|Element|string|null
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
     * Example: Passing `Components.IFCSParams` checks if `Components->IFCSParams->getName() === IFCSParams`
     *
     * @param  string  $key  Format Element.Child.Child...
     */
    public function has(string $key, ?string $elementName = null): bool
    {
        $parts = explode('.', $key);
        $elementName = $elementName ?? array_pop($parts);

        // Return key name if not found
        $obj = $this->get($key, $key);

        if ($obj === null) {
            return false;
        }

        if (! is_object($obj)) {
            return $obj !== $key;
        }

        return $obj instanceof SimpleXMLElement && $obj->getName() === $elementName;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
