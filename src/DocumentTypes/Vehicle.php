<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Generator;
use Octfx\ScDataDumper\Helper\Element;

final class Vehicle extends RootDocument
{
    /** @return Generator<array{HardpointName: string, Tag: string, User: array, Usable: array}> */
    public function getControlBindings(): Generator
    {
        if (($parts = $this->get('Parts')) !== null) {
            yield from $this->walkControlBindings($parts);
        }
    }

    private function walkControlBindings(Element $parts): Generator
    {
        foreach ($parts->children() as $part) {
            $controller = $part->get('./ItemPort/ControllerDef');
            $tag = $controller?->get('@controllableTags');
            $name = $part->get('@name');
            if (is_string($tag) && $tag !== '' && is_string($name)) {
                yield [
                    'HardpointName' => $name, 'Tag' => $tag,
                    'User' => $this->readControlGroups($controller->get('./UserDef/PriorityGroups')),
                    'Usable' => $this->readControlGroups($controller->get('./UsableDef/PriorityGroups')),
                ];
            }
            if (($children = $part->get('./Parts')) !== null) {
                yield from $this->walkControlBindings($children);
            }
        }
    }

    private function readControlGroups(?Element $groups): array
    {
        $result = [];
        foreach ($groups?->children() ?? [] as $group) {
            $type = strtolower((string) $group->get('@itemType'));
            $grants = [];
            $hasTargets = false;
            foreach ($group->children() as $target) {
                $hasTargets = true;
                $tag = $target->get('@tag');
                $priority = $target->get('./Priority@value');
                if (is_string($tag) && ($priority === 'exclusive_control' || (is_numeric($priority) && (float) $priority > 0))) {
                    $grants[$tag] = true;
                }
            }
            $result[$type][] = ['Grants' => $grants, 'DefaultOnly' => ! $hasTargets && $group->get('@defaultPriority') === null];
        }

        return $result;
    }

    /**
     * Returns the vehicle-level identity tags from the itemPortTags attribute.
     *
     * These tags represent the vehicle's identity in the SC data model.
     * Bespoke items declare these in their RequiredTags to restrict equippability.
     * Example: "AEGS_Avenger_Base" for the Avenger Stalker.
     */
    public function getPortTags(): array
    {
        $raw = trim((string) ($this->documentElement->attributes->getNamedItem('itemPortTags')?->nodeValue ?? ''));

        if ($raw !== '') {
            return array_values(array_filter(explode(' ', $raw)));
        }

        // Many ships lack the itemPortTags attribute in their implementation XML.
        // Fall back to the "name" attribute on the <Vehicle> element, which is the
        // identity tag that bespoke items reference in their RequiredTags (e.g. ORIG_100i).
        $name = trim((string) ($this->documentElement->attributes->getNamedItem('name')?->nodeValue ?? ''));

        return $name !== '' ? [$name] : [];
    }
}
