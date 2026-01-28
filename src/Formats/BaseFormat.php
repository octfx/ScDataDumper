<?php

namespace Octfx\ScDataDumper\Formats;

use DOMNode;
use InvalidArgumentException;
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
        $this->item = match (true) {
            $item instanceof Element => $item,
            $item instanceof RootDocument => $item,
            $item instanceof DOMNode => new Element($item),
            $item === null => null,
            // $item === null => throw new InvalidArgumentException('Cannot build format from null DOM node'),
            default => throw new InvalidArgumentException('Unsupported node type: '.get_debug_type($item)),
        };
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
     * Retrieve an element or attribute from the current item.
     *
     * This is a convenience wrapper around Element::get(), so it accepts XPath-like paths
     * and returns only the first match. Attribute lookups are triggered by an @ segment
     * (e.g. "@Code" or "Localization/@Code"). If the key is null, the method falls back
     * to $this->elementKey.
     *
     * Notes and edge cases:
     * - If both $key and $this->elementKey are null, a RuntimeException is thrown.
     * - This method assumes $this->item is non-null. If the format was constructed with
     *   null and you call get(), PHP will throw a fatal error (call on null). Prefer
     *   canTransform() or check $this->item before calling.
     * - Attribute values that are numeric strings are returned as float.
     * - If multiple nodes match the path, only the first match is returned.
     * - When $this->item is a RootDocument and the key starts with "@", the lookup is
     *   delegated to the documentElement (RootDocument has no attributes itself).
     * - XPath predicates are supported because Element::get() ignores @ signs inside
     *   predicates when splitting attribute queries.
     *
     * @param  string|null  $key  Relative path or attribute query.
     *                            Examples:
     *                            - "Components/EAPhaseActivePropComponentDef"
     *                            - "@Code" (attribute on current/root element)
     *                            - "Localization/@Code" (attribute on child)
     *                            - "Components/AttachDef[@Type='Weapon']"
     *                            - null to use $this->elementKey
     * @param  mixed  $default  Returned when no node/attribute is found.
     * @param  bool  $local  When true and $key is a single segment without "/" or "@",
     *                       "./" is prepended to force a direct-child lookup.
     * @return float|mixed|null|Element|string Element for element paths, string/float for attributes,
     *                                         or $default when no match is found.
     *
     * @throws RuntimeException When both $key and $this->elementKey are null
     *
     * @see BaseFormat::$elementKey Default key used when $key is null
     * @see BaseFormat::has() Check if element exists at path
     *
     * @example <caption>Basic Element Lookup</caption>
     * $entityFormat = new TestableFormat($entityRoot);
     * $component = $entityFormat->get('Components/EAPhaseActivePropComponentDef');
     * // Returns: Element with nodeName "EAPhaseActivePropComponentDef"
     * @example <caption>Attribute Lookup</caption>
     * $manufacturerFormat = new TestableFormat($manufacturerRoot);
     * $code = $manufacturerFormat->get('@Code');
     * // Returns: "ACAS"
     *
     * $invisible = $entityFormat->get('@Invisible');
     * // Returns: 0.0 (float)
     *
     * $childAttr = $manufacturerFormat->get('Localization/@Code');
     * // Returns: attribute value from Localization, or $default if missing
     * @example <caption>XPath Predicates</caption>
     * $weaponAttach = $entityFormat->get("Components/AttachDef[@Type='Weapon']");
     * // Returns: first matching Element
     * @example <caption>Default and Local Lookups</caption>
     * $missing = $manufacturerFormat->get('NonExistent/Path', 'fallback');
     * // Returns: "fallback"
     *
     * $localChild = $manufacturerFormat->get('Localization', null, true);
     * // Equivalent to "./Localization" (direct child only)
     * @example <caption>Using $elementKey</caption>
     * $formatWithKey = new class($entityRoot) extends TestableFormat {
     *     protected ?string $elementKey = 'Components/EAPhaseActivePropComponentDef';
     * };
     *
     * $element = $formatWithKey->get(null);
     * // Uses elementKey path
     *
     * $override = $formatWithKey->get('Components/OtherComponent');
     * // Explicit key overrides elementKey
     */
    public function get(?string $key = null, $default = null, ?bool $local = false): mixed
    {
        if ($key === null && $this->elementKey === null) {
            throw new RuntimeException('Either $key must be provided or $elementKey set');
        }

        $lookupKey = $key ?? $this->elementKey;

        if ($local && $lookupKey !== null && ! str_contains($lookupKey, '/') && ! str_contains($lookupKey, '@')) {
            $lookupKey = './'.$lookupKey;
        }

        // FIX: Handle attribute queries on RootDocument by delegating to documentElement
        if ($this->item instanceof RootDocument && str_starts_with($lookupKey, '@')) {
            return new Element($this->item->documentElement)->get($lookupKey, $default);
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
        $normalizedKey = str_starts_with($key, './') ? substr($key, 2) : $key;

        if (str_contains($normalizedKey, '.')) {
            throw new RuntimeException('Element key contains invalid format');
        }

        $parts = explode('/', $normalizedKey);
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
            $acronyms = ['Uuid' => 'UUID', 'Scu' => 'SCU', 'Ifcs' => 'IFCS', 'Emp' => 'EMP', 'StdItem' => 'stdItem'];

            return $acronyms[$value] ?? $value;
        }

        $value = str_replace(['_', '-'], ' ', $value);
        $result = str_replace(' ', '', ucwords($value));

        $acronyms = ['Uuid' => 'UUID', 'Scu' => 'SCU', 'Ifcs' => 'IFCS', 'Emp' => 'EMP', 'StdItem' => 'stdItem'];

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
