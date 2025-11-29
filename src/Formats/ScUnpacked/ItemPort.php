<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use DOMNode;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\RootDocument;
use Octfx\ScDataDumper\Formats\BaseFormat;

class ItemPort extends BaseFormat
{
    protected ?string $elementKey = 'SItemPortDef';

    /**
     * @param  RootDocument|Element|DOMNode|null  $item  The port DOMNode
     * @param  array<string, string>  $loadoutMap  Map of lowercase port name to item UUID
     */
    public function __construct(
        protected RootDocument|Element|DOMNode|null $item,
        protected array $loadoutMap = []
    ) {
        parent::__construct($item);
    }

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $port = $this->item;

        $portName = $this instanceof VehiclePartPort
            ? $port->parentNode->attributes->getNamedItem('name')?->nodeValue
            : $port->get('@Name');

        $stdPort = [
            'PortName' => $portName,
            'DisplayName' => $this instanceof VehiclePartPort ? $port->get('@display_name') : null,
            'Size' => $port->get('@MaxSize') ?? $port->get('@maxSize'),
            'MinSize' => $port->get('@MinSize') ?? $port->get('@minSize'),
            'MaxSize' => $port->get('@MaxSize') ?? $port->get('@maxSize'),
            'Flags' => $this->buildFlagsList($port),
            'Tags' => array_filter(explode(' ', $port->get('@PortTags'))),
            'RequiredTags' => array_filter(explode(' ', $port->get('@RequiredPortTags'))),
            'Types' => $this->buildTypesList($port),
            'CompatibleTypes' => $this->buildCompatibleTypes($port),
            'EquippedItem' => $this->getEquippedItemUuid($port, $portName ?? ''),
            'Position' => $this->detectPosition(
                $this instanceof VehiclePartPort ? $port->get('@display_name') : null
            ),
        ];

        $stdPort['Uneditable'] = in_array('$uneditable', $stdPort['Flags'], true) || in_array('uneditable', $stdPort['Flags'], true);

        return $stdPort;
    }

    /**
     * Checks if the port accepts the specified item type pattern.
     *
     * @param  string  $typePattern  Item type pattern to check (e.g. "WeaponGun", "Turret.*")
     * @return bool True if the port accepts the specified type pattern
     */
    public static function accepts(array $port, string $typePattern): bool
    {
        if ($port['Types'] === null) {
            return false;
        }

        return self::typeMatch($port['Types'], $typePattern);
    }

    /**
     * Match a type pattern against a list of types.
     *
     * @param  array  $types  The list of types to match against
     * @param  string  $typePattern  The pattern to match
     * @return bool True if any of the types match the pattern
     */
    private static function typeMatch(array $types, string $typePattern): bool
    {
        if ($types === null) {
            return false;
        }

        foreach ($types as $type) {
            if (self::matchSingleType($type, $typePattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a single type against a pattern.
     *
     * @param  string  $type  The type to check
     * @param  string  $typePattern  The pattern to match against
     * @return bool True if the type matches the pattern
     */
    private static function matchSingleType(string $type, string $typePattern): bool
    {
        $patternSplit = explode('.', $typePattern, 2);

        $patternType = $patternSplit[0];
        if ($patternType === '*') {
            $patternType = null;
        }

        $patternSubType = $patternSplit[1] ?? null;
        if ($patternSubType === '*') {
            $patternSubType = null;
        }

        $typeSplit = explode('.', $type, 2);
        $typeType = $typeSplit[0];
        $typeSubType = $typeSplit[1] ?? null;

        if (! empty($patternType) && strcasecmp($patternType, $typeType) !== 0) {
            return false;
        }

        if (! empty($patternSubType) && strcasecmp($patternSubType, $typeSubType) !== 0) {
            return false;
        }

        return true;
    }

    private function buildTypesList($port): array
    {
        $types = [];

        foreach ($port->get('/Types')?->children() ?? [] as $portType) {
            $major = $this instanceof VehiclePartPort
                ? $portType->get('@type')
                : $portType->get('@Type');

            $subtypeKey = $this instanceof VehiclePartPort ? '@subtypes' : '@SubTypes';
            /** @var Element $subTypesElement */
            $subTypesElement = $portType->get('/SubTypes');

            $hasSubTypes = $subTypesElement !== null && $subTypesElement->getNode()->childNodes->count() > 0;
            $hasSubTypeAttr = ! empty($portType->get($subtypeKey));

            if (! $hasSubTypes && ! $hasSubTypeAttr) {
                $types[] = Item::buildTypeName($major, null);
            } else {
                if ($hasSubTypes) {
                    foreach ($subTypesElement->children() as $subType) {
                        $minor = $subType->get('value');
                        if (! empty($minor)) {
                            $types[] = Item::buildTypeName($major, $minor);
                        }
                    }
                }

                if ($hasSubTypeAttr) {
                    foreach (explode(',', $portType->get($subtypeKey)) as $subType) {
                        $trimmed = trim($subType);
                        if (! empty($trimmed)) {
                            $types[] = Item::buildTypeName($major, $trimmed);
                        }
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Build structured compatible types list with type and sub_types
     *
     * @param Element $port  The port element
     * @return array Array of compatible types with structure: [['type' => string, 'sub_types' => array]]
     */
    private function buildCompatibleTypes(Element $port): array
    {
        $compatibleTypes = [];

        foreach ($port->get('/Types')?->children() ?? [] as $portType) {
            $major = $this instanceof VehiclePartPort
                ? $portType->get('@type')
                : $portType->get('@Type');

            if (empty($major)) {
                continue;
            }

            $subtypeKey = $this instanceof VehiclePartPort ? '@subtypes' : '@SubTypes';
            /** @var Element $subTypesElement */
            $subTypesElement = $portType->get('/SubTypes');

            $subTypes = [];

            if ($subTypesElement !== null && $subTypesElement->getNode()->childNodes->count() > 0) {
                foreach ($subTypesElement->children() as $subType) {
                    $value = $subType->get('value');
                    if (! empty($value)) {
                        $subTypes[] = $value;
                    }
                }
            }

            $subtypeAttr = $portType->get($subtypeKey);
            if (! empty($subtypeAttr)) {
                foreach (explode(',', $subtypeAttr) as $subType) {
                    $trimmed = trim($subType);
                    if (! empty($trimmed)) {
                        $subTypes[] = $trimmed;
                    }
                }
            }

            $subTypes = array_values(array_unique(array_filter($subTypes)));

            $compatibleTypes[] = [
                'type' => $major,
                'sub_types' => $subTypes,
            ];
        }

        return $compatibleTypes;
    }

    private function buildFlagsList($port): ?array
    {
        $flags = explode(' ', $port->get('@Flags') ?? $port->get('@flags'));

        return array_filter(array_map('trim', $flags));
    }

    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'SItemPortDef';
    }

    /**
     * Detect port position/category from DisplayName
     *
     * @param  string|null  $displayName  The port's display name
     * @return string|null Position identifier or null if not detected
     */
    private function detectPosition(?string $displayName): ?string
    {
        if (empty($displayName)) {
            return null;
        }

        $displayNameLower = strtolower($displayName);

        // Order matters here
        if (stripos($displayNameLower, 'magazine') !== false) {
            return 'magazine_well';
        }

        if (stripos($displayNameLower, 'optics') !== false) {
            return 'optics';
        }

        if (stripos($displayNameLower, 'underbarrel') !== false) {
            return 'underbarrel';
        }

        if (stripos($displayNameLower, 'barrel') !== false) {
            return 'barrel';
        }

        return null;
    }

    /**
     * Get equipped item UUID from port data or loadout map
     *
     * @param  Element  $port  The port element
     * @param  string  $portName  The port name (for loadout lookup)
     * @return string|null The equipped item UUID or null
     */
    private function getEquippedItemUuid(Element $port, string $portName): ?string
    {
        $equippedUuid = $port->get('@EquippedItemUuid');
        if (! empty($equippedUuid) && $equippedUuid !== '00000000-0000-0000-0000-000000000000') {
            return $equippedUuid;
        }

        $portNameLower = strtolower($portName);

        return $this->loadoutMap[$portNameLower] ?? null;
    }
}
