<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;

/**
 * Extracts the seat-to-turret control chain from vehicle implementation scripts
 * and spaceship entity definitions.
 *
 * Determines which turrets are "bridge-controllable" — i.e. controllable from
 * any cockpit/bridge position (pilot, copilot, bridge console, bridge turret seat)
 * rather than only from a dedicated turret seat.
 *
 * The control chain is defined in the vehicle implementation script XML:
 *
 *   Seat ControllerDef > UserDef > PriorityGroups > PriorityGroup(itemType="WeaponController")
 *     → tags tag="X" → Priority value > 0  (seat can control tag X)
 *
 *   Turret ControllerDef controllableTags="X"  (turret is controlled by tag X)
 *
 *   WeaponController ControllerDef controllableTags="Y" > UsableDef > PriorityGroups
 *     → PriorityGroup > tags tag="Z"  (controller bridges tag Y → tag Z)
 *
 * Priority values can be numeric ("100", "50") or string ("exclusive_control").
 * Any non-zero, non-"no_control" value means the seat can control that tag.
 */
final class TurretControlMapper
{
    /**
     * @return array<string> List of turret hardpoint names controllable from bridge positions
     */
    public function getBridgeControllableTurrets(?Vehicle $vehicle, ?VehicleDefinition $entity): array
    {
        if ($vehicle === null) {
            return [];
        }

        $chain = $this->buildControlChain($vehicle, $entity);

        return $this->resolveBridgeControllable($chain);
    }

    /**
     * Build the full control chain from vehicle script and spaceship entity.
     *
     * @return array{
     *     seats: array<string, array{isBridge: bool, controlledTags: array<string, bool>, controllableTag: string|null}>,
     *     turrets: array<string, string>,
     *     weaponControllers: array<string, array<string>>
     * }
     */
    private function buildControlChain(Vehicle $vehicle, ?VehicleDefinition $entity): array
    {
        $chain = [
            'seats' => [],
            'turrets' => [],
            'weaponControllers' => [],
        ];

        // 1. Walk the vehicle implementation script
        $parts = $vehicle->get('Parts');
        if ($parts !== null) {
            $this->walkPartsForControlChain($parts, $chain, isUnderTurret: false);
        }

        // 2. Walk the spaceship entity for weapon controller bridge data (Paladin pattern)
        if ($entity !== null) {
            $this->extractEntityControlChain($entity, $chain);
        }

        return $chain;
    }

    /**
     * Recursively walk Parts to extract control chain data.
     */
    private function walkPartsForControlChain(Element $partsContainer, array &$chain, bool $isUnderTurret): void
    {
        foreach ($partsContainer->children() as $part) {
            $partName = $part->get('@name');
            if ($partName === null) {
                continue;
            }

            $partNameStr = (string) $partName;
            $isTurret = $this->isTurretPart($partNameStr, $part);
            $childIsUnderTurret = $isUnderTurret || $isTurret;

            $itemPort = $part->get('./ItemPort');
            if ($itemPort !== null) {
                $this->extractPortControlData($itemPort, $partNameStr, $childIsUnderTurret, $chain);
            }

            // Recurse into child parts
            $childParts = $part->get('./Parts');
            if ($childParts !== null) {
                $this->walkPartsForControlChain($childParts, $chain, $childIsUnderTurret);
            }
        }
    }

    /**
     * Check if a part is a turret hardpoint by name or port type.
     * Used to track whether child seats are "under a turret" (dedicated).
     */
    private function isTurretPart(string $partName, Element $part): bool
    {
        $lower = strtolower($partName);

        // Named turret hardpoints (but not console seats or seats)
        if ((str_contains($lower, 'remote_turret')
            || str_contains($lower, 'manned_turret')
            || str_contains($lower, '_turret'))
            && !str_contains($lower, 'turret_console')
            && !str_contains($lower, 'console')
            && !str_contains($lower, 'seat')) {
            return true;
        }

        // Check port types for turret types
        $itemPort = $part->get('./ItemPort');
        if ($itemPort !== null) {
            foreach ($itemPort->get('./Types')?->children() ?? [] as $portType) {
                $type = $portType->get('@type');
                if ($type !== null) {
                    $typeStr = (string) $type;
                    if (str_starts_with($typeStr, 'Turret') || str_contains($typeStr, 'Turret')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract control chain data from a single ItemPort element.
     */
    private function extractPortControlData(Element $itemPort, string $partName, bool $isUnderTurret, array &$chain): void
    {
        $controllableTag = $this->getControllableTag($itemPort);
        $isSeat = $this->isSeatPort($itemPort, $partName);
        $isWeaponController = $this->isWeaponControllerPort($itemPort);

        if ($isSeat) {
            $controlledTags = $this->extractWeaponControllerPriorities($itemPort);

            $chain['seats'][$partName] = [
                'isBridge' => !$isUnderTurret,
                'controlledTags' => $controlledTags,
                'controllableTag' => $controllableTag,
            ];
        }

        if ($isWeaponController) {
            if ($controllableTag !== null) {
                $mappedTags = $this->extractWeaponControllerMappedTags($itemPort);
                if (!empty($mappedTags)) {
                    $chain['weaponControllers'][$controllableTag] = $mappedTags;
                }
            }
        }

        // Turret hardpoint: has controllableTag, is not a seat or weapon controller,
        // and is actually a turret port type (not salvage/light/countermeasure controllers).
        if ($controllableTag !== null && !$isSeat && !$isWeaponController && $this->isTurretPort($itemPort)) {
            $chain['turrets'][$partName] = $controllableTag;
        }
    }

    /**
     * Get the controllableTag from a port's ControllerDef.
     */
    private function getControllableTag(Element $itemPort): ?string
    {
        $tag = $itemPort->get('./ControllerDef@controllableTags');
        if ($tag !== null && is_string($tag) && $tag !== '') {
            return $tag;
        }

        return null;
    }

    /**
     * Check if this ItemPort is a seat type.
     */
    private function isSeatPort(Element $itemPort, string $partName): bool
    {
        $lower = strtolower($partName);

        if (str_contains($lower, 'seat')
            || str_contains($lower, 'turret_console')
            || str_contains($lower, 'hardpoint_bridge')) {
            return true;
        }

        foreach ($itemPort->get('./Types')?->children() ?? [] as $portType) {
            $type = $portType->get('@type');
            if ($type !== null && str_starts_with((string) $type, 'Seat')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this ItemPort is a weapon controller type.
     */
    private function isWeaponControllerPort(Element $itemPort): bool
    {
        foreach ($itemPort->get('./Types')?->children() ?? [] as $portType) {
            $type = $portType->get('@type');
            if ($type !== null && str_contains((string) $type, 'WeaponController')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this ItemPort is a turret type (Turret, TurretBase, GunTurret, etc.).
     * Used to filter turret hardpoints from other controllableTag-bearing ports
     * (salvage controllers, light controllers, countermeasure launchers, etc.).
     */
    private function isTurretPort(Element $itemPort): bool
    {
        foreach ($itemPort->get('./Types')?->children() ?? [] as $portType) {
            $type = $portType->get('@type');
            if ($type === null) {
                continue;
            }
            $typeStr = (string) $type;
            if (str_starts_with($typeStr, 'Turret') || $typeStr === 'TurretBase') {
                return true;
            }
            // Check subtypes for GunTurret, RemoteTurret, MannedTurret
            $subtype = $portType->get('@subtypes');
            if ($subtype !== null && preg_match('/GunTurret|RemoteTurret|MannedTurret/i', (string) $subtype)) {
                return true;
            }
            $subTypesElement = $portType->get('./SubTypes');
            if ($subTypesElement !== null) {
                foreach ($subTypesElement->children() as $sub) {
                    $val = $sub->get('@value');
                    if ($val !== null && preg_match('/GunTurret|RemoteTurret|MannedTurret/i', (string) $val)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract tags the seat can control via WeaponController PriorityGroups.
     *
     * Searches both UserDef and UsableDef PriorityGroups for
     * PriorityGroup itemType="WeaponController" entries with tag priorities > 0.
     *
     * @return array<string, bool> Map of tag => true for tags with control priority
     */
    private function extractWeaponControllerPriorities(Element $itemPort): array
    {
        $tags = [];

        $controllerDef = $itemPort->get('./ControllerDef');
        if ($controllerDef === null) {
            return $tags;
        }

        // Check UserDef > PriorityGroups and UsableDef > PriorityGroups
        foreach (['./UserDef', './UsableDef'] as $defPath) {
            $def = $controllerDef->get($defPath);
            if ($def === null) {
                continue;
            }

            $priorityGroups = $def->get('./PriorityGroups');
            if ($priorityGroups === null) {
                continue;
            }

            foreach ($priorityGroups->children() as $group) {
                $itemType = $group->get('@itemType');
                if ($itemType !== 'WeaponController') {
                    continue;
                }

                foreach ($group->children() as $tagEntry) {
                    $tag = $tagEntry->get('@tag');
                    if ($tag === null) {
                        continue;
                    }

                    $priority = $tagEntry->get('./Priority@value');
                    if ($this->isControlGranted($priority)) {
                        $tags[(string) $tag] = true;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Extract mapped tags from a weapon controller's PriorityGroups.
     *
     * Weapon controllers bridge seat tags to turret tags. The mapping is in
     * UsableDef > PriorityGroups > PriorityGroup > tags entries.
     *
     * @return array<string>
     */
    private function extractWeaponControllerMappedTags(Element $itemPort): array
    {
        $tags = [];

        $controllerDef = $itemPort->get('./ControllerDef');
        if ($controllerDef === null) {
            return $tags;
        }

        // Weapon controllers typically store mappings in UsableDef
        foreach (['./UsableDef', './UserDef'] as $defPath) {
            $def = $controllerDef->get($defPath);
            if ($def === null) {
                continue;
            }

            $priorityGroups = $def->get('./PriorityGroups');
            if ($priorityGroups === null) {
                continue;
            }

            foreach ($priorityGroups->children() as $group) {
                foreach ($group->children() as $tagEntry) {
                    $tag = $tagEntry->get('@tag');
                    if ($tag !== null && is_string($tag) && $tag !== '') {
                        $tags[] = (string) $tag;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Check if a priority value grants control.
     *
     * Priority values: numeric > 0, or strings like "exclusive_control" grant control.
     * "no_control", 0, null do NOT grant control.
     */
    private function isControlGranted(mixed $priority): bool
    {
        if ($priority === null) {
            return false;
        }

        if (is_numeric($priority)) {
            return (float) $priority > 0;
        }

        if (is_string($priority)) {
            $lower = strtolower($priority);

            return $lower !== 'no_control' && $lower !== '0' && trim($lower) !== '';
        }

        return false;
    }

    /**
     * Extract control chain data from the spaceship entity XML.
     *
     * Some ships (e.g. Paladin) define the seat-to-turret bridge in the entity's
     * SItemPortContainerComponentParams rather than the vehicle script.
     */
    private function extractEntityControlChain(VehicleDefinition $entity, array &$chain): void
    {
        $ports = $entity->get('Components/SItemPortContainerComponentParams/Ports');
        if ($ports === null) {
            return;
        }

        // First pass: collect weapon controller definitions
        foreach ($ports->children() as $portDef) {
            $portName = $portDef->get('@Name');
            if ($portName === null) {
                continue;
            }

            $controllableTag = $this->getEntityPortControllableTag($portDef);
            $turretTags = $this->getEntityPortWeaponControllerTags($portDef);

            if ($controllableTag !== null && !empty($turretTags)) {
                $chain['weaponControllers'][$controllableTag] = $turretTags;
            }
        }

        // Second pass: collect seat data
        foreach ($ports->children() as $portDef) {
            $portName = $portDef->get('@Name');
            if ($portName === null) {
                continue;
            }

            $portNameStr = (string) $portName;
            if (!$this->isEntitySeatPort($portDef, $portNameStr)) {
                continue;
            }

            $controllableTag = $this->getEntityPortControllableTag($portDef);
            $controlledTags = $this->getEntitySeatWeaponControllerPriorities($portDef);
            $isBridge = $this->isEntityBridgeSeat($portNameStr);

            $chain['seats'][$portNameStr] = [
                'isBridge' => $isBridge,
                'controlledTags' => $controlledTags,
                'controllableTag' => $controllableTag,
            ];
        }

        // Third pass: collect turret hardpoints
        foreach ($ports->children() as $portDef) {
            $portName = $portDef->get('@Name');
            if ($portName === null) {
                continue;
            }

            $portNameStr = (string) $portName;
            $controllableTag = $this->getEntityPortControllableTag($portDef);
            if ($controllableTag !== null && !isset($chain['seats'][$portNameStr])) {
                if (!isset($chain['turrets'][$portNameStr])) {
                    $chain['turrets'][$portNameStr] = $controllableTag;
                }
            }
        }
    }

    private function getEntityPortControllableTag(Element $portDef): ?string
    {
        $tag = $portDef->get('./SItemPortDefParams/ControllerDef@controllableTags');
        if ($tag !== null && is_string($tag) && $tag !== '') {
            return $tag;
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function getEntityPortWeaponControllerTags(Element $portDef): array
    {
        $tags = [];

        $controllableParams = $portDef->get('./SItemPortDefParams/SCItemControllableGroupParams');
        if ($controllableParams === null) {
            return $tags;
        }

        $priorityGroups = $controllableParams->get('./priorityGroups');
        if ($priorityGroups === null) {
            return $tags;
        }

        foreach ($priorityGroups->children() as $group) {
            $tag = $group->get('@tag');
            if ($tag !== null && is_string($tag) && $tag !== '') {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private function isEntitySeatPort(Element $portDef, string $portName): bool
    {
        $lower = strtolower($portName);

        if (str_contains($lower, 'seat') || str_contains($lower, 'turret_console') || str_contains($lower, 'bridge')) {
            return true;
        }

        foreach ($portDef->get('./Types')?->children() ?? [] as $portType) {
            $type = $portType->get('@type');
            if ($type !== null && str_starts_with((string) $type, 'Seat')) {
                return true;
            }
        }

        return false;
    }

    private function isEntityBridgeSeat(string $portName): bool
    {
        $lower = strtolower($portName);

        // Dedicated turret seats
        if (preg_match('/hardpoint_.*_remote_turret_seat/i', $portName)) {
            return false;
        }
        if (preg_match('/hardpoint_turret_.*_seat/i', $portName)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    private function getEntitySeatWeaponControllerPriorities(Element $portDef): array
    {
        $tags = [];

        $params = $portDef->get('./SItemPortDefParams');
        if ($params === null) {
            return $tags;
        }

        $controllerDef = $params->get('./ControllerDef');
        if ($controllerDef === null) {
            return $tags;
        }

        foreach (['./UserDef', './UsableDef'] as $defPath) {
            $def = $controllerDef->get($defPath);
            if ($def === null) {
                continue;
            }

            $priorityGroups = $def->get('./PriorityGroups');
            if ($priorityGroups === null) {
                continue;
            }

            foreach ($priorityGroups->children() as $group) {
                $itemType = $group->get('@itemType');
                if ($itemType !== 'WeaponController') {
                    continue;
                }

                foreach ($group->children() as $tagEntry) {
                    $tag = $tagEntry->get('@tag');
                    if ($tag === null) {
                        continue;
                    }

                    $priority = $tagEntry->get('./Priority@value');
                    if ($this->isControlGranted($priority)) {
                        $tags[(string) $tag] = true;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Resolve which turrets are bridge-controllable from the control chain.
     *
     * A turret is bridge-controllable if ANY bridge seat has WeaponController
     * priority for the turret's controllableTag (directly or via weapon controller bridge).
     *
     * @param  array{seats: array<string, array{isBridge: bool, controlledTags: array<string, bool>, controllableTag: string|null}>, turrets: array<string, string>, weaponControllers: array<string, array<string>>}  $chain
     * @return array<string>
     */
    private function resolveBridgeControllable(array $chain): array
    {
        // Collect all tags that bridge seats can control via WeaponController
        $bridgeControlledTags = [];

        foreach ($chain['seats'] as $seatData) {
            if (!$seatData['isBridge']) {
                continue;
            }

            foreach ($seatData['controlledTags'] as $tag => $_) {
                $bridgeControlledTags[$tag] = true;

                // Expand through weapon controller bridges
                if (isset($chain['weaponControllers'][$tag])) {
                    foreach ($chain['weaponControllers'][$tag] as $turretTag) {
                        $bridgeControlledTags[$turretTag] = true;
                    }
                }
            }
        }

        // Match turret hardpoints to bridge-controllable tags
        $bridgeControllable = [];
        foreach ($chain['turrets'] as $hardpointName => $turretTag) {
            if (isset($bridgeControlledTags[$turretTag])) {
                $bridgeControllable[] = $hardpointName;
            }
        }

        return $bridgeControllable;
    }
}
