<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

class ItemPort extends BaseFormat
{
    protected ?string $elementKey = 'SItemPortDef';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $port = $this->item;

        $stdPort = [
            'PortName' => $this instanceof VehiclePartPort ? $port->parentNode->attributes->getNamedItem('name')?->nodeValue : $port->get('@Name'),
            'DisplayName' => $this instanceof VehiclePartPort ? $port->get('@display_name') : null,
            'Size' => $port->get('@MaxSize') ?? $port->get('@maxSize'),
            'Flags' => $this->buildFlagsList($port),
            'Tags' => array_filter(explode(' ', $port->get('@PortTags'))),
            'RequiredTags' => array_filter(explode(' ', $port->get('@RequiredPortTags'))),
            'Types' => $this->buildTypesList($port),
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
            $major = $this instanceof VehiclePartPort ? $portType->get('@type') : $portType->get('@Type');

            $subtypeKey = $this instanceof VehiclePartPort ? '@subtypes' : '@SubTypes';

            if (! empty($portType->get($subtypeKey))) {
                $types[] = Item::buildTypeName($major, null);
            } else {
                foreach ($port->get('/SubTypes')?->children() ?? [] as $subType) {
                    $minor = $subType->get('value');
                    $types[] = Item::buildTypeName($major, $minor);
                }

                foreach (explode(',', $portType->get($subtypeKey)) as $subType) {
                    $types[] = Item::buildTypeName($major, $subType);
                }
            }
        }

        return $types;
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
}
