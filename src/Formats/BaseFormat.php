<?php

namespace Octfx\ScDataDumper\Formats;

use DOMNode;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Helper\DOMElementProxy;
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

    public function __construct(protected RootDocument|DOMElementProxy|DOMNode|null $item)
    {
        if ($item && ! $item instanceof DOMElementProxy) {
            $this->item = new DOMElementProxy($item);
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
     * @return float|mixed|null|DOMElementProxy|string
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
     * Example: Passing `Components.IFCSParams` checks if `Components->IFCSParams->nodeName === IFCSParams`
     *
     * @param  string  $key  Format Element.Child.Child...
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

        return $obj instanceof DOMElementProxy && $obj->nodeName === $elementName;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
