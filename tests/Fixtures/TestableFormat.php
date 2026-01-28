<?php

namespace Octfx\ScDataDumper\Tests\Fixtures;

use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * TestableFormat - Concrete implementation of BaseFormat for testing purposes
 *
 * This class provides a minimal implementation suitable for testing all BaseFormat methods.
 */
class TestableFormat extends BaseFormat
{
    /**
     * Element key used for testing get() and has() methods
     * Set to test accessing attributes or child elements
     */
    protected ?string $elementKey = '@Code';

    /**
     * Convert the element data to an array structure for testing
     *
     * @return array|null Test data structure from the XML element
     */
    public function toArray(): ?array
    {
        if ($this->item === null) {
            return null;
        }

        return [
            'code' => $this->get('@Code'),
            'name' => $this->get('@__type'),
            'description' => $this->get('Localization/@Description'),
        ];
    }

    /**
     * Public wrapper for toPascalCase() - converts string to PascalCase
     *
     * @param  string  $value  The string to convert
     * @return string PascalCase string
     */
    public function testableToPascalCase(string $value): string
    {
        return $this->toPascalCase($value);
    }

    /**
     * Public wrapper for transformArrayKeysToPascalCase() - recursively transforms all array keys to PascalCase
     *
     * @param  array|null|BaseFormat  $data  The array with mixed case keys
     * @return array Array with all keys in PascalCase
     */
    public function testableTransformArrayKeysToPascalCase(array|null|BaseFormat $data): array
    {
        return $this->transformArrayKeysToPascalCase($data);
    }

    /**
     * Public wrapper for removeNullValues() - recursively removes null values and empty arrays from data structure
     *
     * @param  array  $data  The array to clean
     * @return array The cleaned array with null and empty array values removed
     */
    public function testableRemoveNullValues(array $data): array
    {
        return $this->removeNullValues($data);
    }
}
