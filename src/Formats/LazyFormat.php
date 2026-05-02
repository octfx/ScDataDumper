<?php

namespace Octfx\ScDataDumper\Formats;

/**
 * Lazy wrapper that defers sub-formatter instantiation until toArray() is called.
 *
 * This avoids the cost of constructing 50+ BaseFormat objects per item when most
 * will return null from canTransform(). The wrapper is stored directly in the data
 * array; processArray() calls toArray() on it just like a real BaseFormat instance.
 *
 * @see Item::toArray()
 */
final class LazyFormat
{
    private ?BaseFormat $inner = null;

    /**
     * @param  callable(): BaseFormat  $factory
     */
    public function __construct(
        private readonly mixed $factory,
    ) {}

    /**
     * Instantiate the real formatter (once) and delegate toArray().
     * Returns null when the formatter's canTransform() is false.
     */
    public function toArray(): ?array
    {
        $this->inner ??= ($this->factory)();

        return $this->inner->toArray();
    }
}
