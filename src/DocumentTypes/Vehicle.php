<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Generator;
use Octfx\ScDataDumper\Helper\Element;

final class Vehicle extends RootDocument
{
    /** @return Generator<array{HardpointName: string, Tag: string, DefaultWeaponGroup: mixed, Controllers: array, User: array, Usable: array, RemoteViewUse: array}> */
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
            $port = $part->get('./ItemPort');

            if ($port !== null && is_string($name)) {
                $direct = [];

                foreach ($port->get('./Controllers')?->children() ?? [] as $binding) {
                    $direct[] = [
                        'Port' => (string) $binding->get('@port'),
                        'Active' => (string) $binding->get('@active') === '1',
                    ];
                }

                yield [
                    'HardpointName' => $name, 'Tag' => is_string($tag) ? $tag : '',
                    'DefaultWeaponGroup' => $port->get('@defaultWeaponGroup'),
                    'Controllers' => $direct,
                    'User' => $this->readControlGroups($controller?->get('./UserDef/PriorityGroups')),
                    'Usable' => $this->readControlGroups($controller?->get('./UsableDef/PriorityGroups')),
                    'RemoteViewUse' => $this->readRemoteViewUse($controller?->get('./UsableDef/ConditionalPriorities')),
                ];
            }

            if (($children = $part->get('./Parts')) !== null) {
                yield from $this->walkControlBindings($children);
            }
        }
    }

    private function readRemoteViewUse(?Element $groups): array
    {
        $result = [];

        foreach ($groups?->children() ?? [] as $group) {
            $type = strtolower((string) $group->get('@itemType'));
            $condition = $group->get('self::ConditionalPriority[count(@*) = 3 and @itemType and @defaultPriority = "no_control" and @priorityWhenConditionMet = "exclusive_control" and count(*) = 1]/Condition_And[not(@*) and count(*) = 2 and Condition_Tag[count(@*) = 1 and @tag and not(*)] and Condition_AllowedByRemoteTurretView[not(@*) and not(*)]]');
            $result[$type][] = $condition === null ? null : strtolower((string) $condition->get('Condition_Tag@tag'));
        }

        return $result;
    }

    private function readControlGroups(?Element $groups): array
    {
        $result = [];

        foreach ($groups?->children() ?? [] as $group) {
            $type = strtolower((string) $group->get('@itemType'));
            $grants = [];
            $hasTargets = false;
            $priorities = [];
            $wellFormed = $group->get('self::PriorityGroup[not(@*[name() != "itemType" and name() != "defaultPriority"])]') !== null;
            $seenTargets = [];

            foreach ($group->children() as $target) {
                $hasTargets = true;
                $tag = $target->get('@tag');
                $priority = $target->get('./Priority@value');
                $wellFormed = $wellFormed && $target->get('self::tags[count(@*) = 1 and @tag and count(*) = 1 and Priority[count(@*) = 1 and @value and not(*)]]') !== null;

                if (is_string($tag)) {
                    $wellFormed = $wellFormed && ! isset($seenTargets[strtolower($tag)]);
                    $seenTargets[strtolower($tag)] = true;
                    $priorities[strtolower($tag)] = $priority;
                }

                if (is_string($tag) && ($priority === 'exclusive_control' || (is_numeric($priority) && (float) $priority > 0))) {
                    $grants[$tag] = true;
                }
            }

            $result[$type][] = ['Grants' => $grants, 'DefaultOnly' => ! $hasTargets && $group->get('@defaultPriority') === null,
                'DefaultExclusive' => ! $hasTargets && $group->get('self::PriorityGroup[count(@*) = 2 and @itemType and @defaultPriority = "exclusive_control"]') !== null,
                'DefaultObserveOnly' => ! $hasTargets && $group->get('self::PriorityGroup[count(@*) = 2 and @itemType and @defaultPriority = "observe_only"]') !== null,
                // Strict shape for new tool rules: only itemType/defaultPriority attributes and well-formed unconditional tag targets.
                'WellFormed' => $wellFormed,
                'Priorities' => $priorities, 'DefaultPriority' => $group->get('@defaultPriority')];
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
