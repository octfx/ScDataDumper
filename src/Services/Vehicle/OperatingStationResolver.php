<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Generator;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class OperatingStationResolver
{
    /** @return array<string, list<array{Kind: string, SeatPortId: string, Name: string}>> */
    public function resolve(?Vehicle $vehicle, array $loadout): array
    {
        $index = $this->indexBoundPorts($vehicle, $loadout);
        $stationsByPort = [];

        foreach ($index['ports'] as $mount) {
            $portId = $mount['Entry']['PortId'];

            foreach ($this->mountStations($mount, $index) as $station) {
                if ($station !== null) {
                    // A later rule can replace the label without moving the seat.
                    $stationsByPort[$portId][$station['SeatPortId']] = $station;
                }
            }
        }

        return array_map(array_values(...), $stationsByPort);
    }

    /**
     * Join unique installed loadout entries to their vehicle script ports and classify them.
     *
     * @return array{ports: array<string, array>, seats: array<string, array>, controllers: array<string, array>,
     *     missileControllers: array<string, array>, salvageControllers: array<string, array>,
     *     installedCounts: array<string, int>, ambiguous: bool, seatsComplete: bool}
     */
    private function indexBoundPorts(?Vehicle $vehicle, array $loadout): array
    {
        $installed = [];
        $installedCounts = [];

        foreach ($loadout as $entry) {
            $installed[$entry['HardpointName'] ?? ''][] = $entry;
            // An unbound seat or controller still counts against uniqueness.
            $root = $this->typeRoot($entry);
            $installedCounts[$root] = ($installedCounts[$root] ?? 0) + 1;
        }

        $bindings = [];

        foreach ($vehicle?->getControlBindings() ?? [] as $binding) {
            $bindings[$binding['HardpointName']][] = $binding;
        }

        $ports = $seats = $controllers = $missileControllers = $salvageControllers = [];

        foreach ($bindings as $name => $matches) {
            if (count($matches) !== 1 || count($installed[$name] ?? []) !== 1
                || ! isset($installed[$name][0]['Type'], $installed[$name][0]['PortId'])) {
                continue;
            }

            $port = $matches[0] + ['Entry' => $installed[$name][0]];
            $ports[$name] = $port;
            $type = strtolower($port['Entry']['Type']);

            if (str_starts_with($type, 'seat.')) {
                $seats[$name] = $port;
            } elseif (str_starts_with($type, 'weaponcontroller.')) {
                $controllers[$name] = $port;
            } elseif (str_starts_with($type, 'missilecontroller.')) {
                $missileControllers[$name] = $port;
            } elseif (str_starts_with($type, 'salvagecontroller.')) {
                $salvageControllers[$name] = $port;
            }
        }

        return [
            'ports' => $ports,
            'seats' => $seats,
            'controllers' => $controllers,
            'missileControllers' => $missileControllers,
            'salvageControllers' => $salvageControllers,
            'installedCounts' => $installedCounts,
            'ambiguous' => array_any($installed, static fn (array $entries) => count($entries) !== 1)
                || array_any($bindings, static fn (array $entries) => count($entries) !== 1),
            'seatsComplete' => ($installedCounts['seat'] ?? 0) === count($seats),
        ];
    }

    /** @return Generator<int, array{Kind: string, SeatPortId: string, Name: string}|null> */
    private function mountStations(array $mount, array $index): Generator
    {
        $tag = strtolower($mount['Tag']);
        $root = $this->typeRoot($mount['Entry']);

        yield $this->integratedSeatStation($mount, $index['ports'], $tag, $root);
        yield $this->taggedActionStation($mount, $index, $tag, $root);
        yield from $this->matchingTagSeatStations($mount, $index, $tag, $root);
        yield from $this->directBindingStations($mount, $index['seats']);
        yield from $this->defaultMissileStations($mount, $index, $tag, $root);
        yield $this->defaultEmpStation($mount, $index, $root);
        yield $this->taggedExclusiveToolStation($mount, $index, $tag, $root);
        yield $this->defaultSalvageStation($mount, $index, $root);
        yield $this->differentlyTaggedPilotStation($mount, $index, $tag);
        yield $this->defaultWeaponGroupStation($mount, $index, $tag);
    }

    private function integratedSeatStation(array $mount, array $ports, string $tag, string $root): ?array
    {
        if ($tag !== '' && ! $this->denies($mount['User'][$root] ?? [], $tag)
            && $this->exclusive($mount['Usable'][$root] ?? [], $tag)
            && $this->integratedAction($mount, $ports, $tag)
            && ServiceFactory::getItemService()->getByReference($mount['Entry']['UUID'] ?? null)?->hasIntegratedSeat()) {
            $id = $mount['Entry']['PortId'];

            return ['Kind' => 'InTurret', 'SeatPortId' => $id, 'Name' => 'Seat inside turret'];
        }

        return null;
    }

    private function taggedActionStation(array $mount, array $index, string $tag, string $root): ?array
    {
        $action = $this->hasPayloadType($mount['Entry'], 'weapongun') ? 'weapongun'
            : ($root === 'missilelauncher' && $this->hasPayloadType($mount['Entry'], 'missile') ? 'missile' : null);

        if ($index['ambiguous'] || $tag === '' || $action === null) {
            return null;
        }

        $actionControllers = array_filter($action === 'missile' ? $index['missileControllers'] : $index['controllers'],
            fn (array $controller) => $controller['Tag'] !== ''
                && $this->exclusive($controller['Usable'][$root] ?? [], $tag)
                && $this->exclusive($controller['Usable'][$action] ?? [], $tag));

        if (count($actionControllers) !== 1) {
            return null;
        }

        $controllerTag = strtolower(array_values($actionControllers)[0]['Tag']);
        $controlType = $action === 'missile' ? 'missilecontroller' : 'weaponcontroller';
        $operators = array_filter($index['seats'], fn (array $seat) => $this->exclusive($seat['User'][$controlType] ?? [], $controllerTag));

        if (count($operators) !== 1) {
            return null;
        }

        return $this->station(array_values($operators)[0]);
    }

    /** @return list<array{Kind: string, SeatPortId: string, Name: string}> */
    private function matchingTagSeatStations(array $mount, array $index, string $tag, string $root): array
    {
        if ($index['ambiguous'] || $tag === '') {
            return [];
        }

        $matchingControllers = array_filter($index['controllers'], static fn (array $controller) => strtolower($controller['Tag']) === $tag);

        if (count($matchingControllers) !== 1) {
            return [];
        }

        $controller = reset($matchingControllers);

        if (! $this->allows($controller['Usable'][$root] ?? [], $tag)) {
            return [];
        }

        $stations = [];

        foreach ($index['seats'] as $seat) {
            if ($this->exclusive($seat['User']['weaponcontroller'] ?? [], $tag)
                && $this->supportsAction($seat, $mount, ports: $index['ports'])) {
                $stations[] = $this->station($seat);
            }
        }

        return $stations;
    }

    /** @return list<array{Kind: string, SeatPortId: string, Name: string}> */
    private function directBindingStations(array $mount, array $seats): array
    {
        $stations = [];

        foreach ($mount['Controllers'] as $direct) {
            if ($direct['Active'] && isset($seats[$direct['Port']]) && $this->supportsAction($seats[$direct['Port']], $mount, direct: true)) {
                $stations[] = $this->station($seats[$direct['Port']]);
            }
        }

        return $stations;
    }

    /** @return list<array{Kind: string, SeatPortId: string, Name: string}> */
    private function defaultMissileStations(array $mount, array $index, string $tag, string $root): array
    {
        if ($root !== 'missilelauncher' || $tag !== '' || $mount['Controllers'] !== []
            || $index['ambiguous'] || count($index['missileControllers']) !== 1 || ! $this->hasPayloadType($mount['Entry'], 'missile')) {
            return [];
        }

        $controller = array_values($index['missileControllers'])[0];

        if ($controller['Tag'] !== '' || ! $this->defaultGroup($controller['Usable']['missilelauncher'] ?? [])
            || ! $this->defaultGroup($controller['Usable']['missile'] ?? [])) {
            return [];
        }

        // A strictly literal observe-only seat cannot compete for the default missile route; any other claim still blocks it.
        $operators = $this->potentialOperators($index['seats'], 'missilecontroller');

        if (count($operators) === 1) {
            $operator = array_values($operators)[0];

            if ($this->isPilot($operator) && ($this->defaultGroup($operator['User']['missilecontroller'])
                || ($this->missileEvidenceComplete($index, $operators) && $this->strictNumericDefault($operator['User']['missilecontroller'])))) {
                return [$this->station($operator)];
            }

            return [];
        }

        // Numeric and shared claims need complete evidence before the join: unbound seats or missile
        // controllers, and bound claims from non-seat ports, all stay unresolved.
        if (count($operators) === 2 && $this->missileEvidenceComplete($index, $operators)) {
            // Shared claim: the default pilot plus one copilot with a flat positive numeric default (Hammerhead pattern).
            $pilots = array_filter($operators, fn (array $seat) => $this->isPilot($seat)
                && $this->defaultGroup($seat['User']['missilecontroller']));
            $copilots = array_filter($operators, fn (array $seat) => $this->isCopilot($seat)
                && $this->strictNumericDefault($seat['User']['missilecontroller']));

            if (count($pilots) === 1 && count($copilots) === 1
                && $this->isHumanSeatPort(array_values($pilots)[0]) && $this->isHumanSeatPort(array_values($copilots)[0])) {
                return [$this->station(array_values($pilots)[0]), $this->station(array_values($copilots)[0])];
            }
        }

        return [];
    }

    private function missileEvidenceComplete(array $index, array $operators): bool
    {
        return $index['seatsComplete']
            && ($index['installedCounts']['missilecontroller'] ?? 0) === count($index['missileControllers'])
            && count($this->potentialOperators($index['ports'], 'missilecontroller')) === count($operators);
    }

    private function defaultWeaponGroupStation(array $mount, array $index, string $tag): ?array
    {
        if (trim((string) $mount['DefaultWeaponGroup']) === '' || $mount['Controllers'] !== []
            || $index['ambiguous'] || count($index['controllers']) !== 1 || ! $this->hasPayloadType($mount['Entry'], 'weapongun')) {
            return null;
        }

        $defaults = array_filter($index['controllers'], fn (array $controller) => strtolower($controller['Tag']) === $tag
            && $this->defaultGroup($controller['Usable']['turret'] ?? [])
            && $this->defaultGroup($controller['Usable']['weapongun'] ?? []));

        $pilots = array_filter($index['seats'], fn (array $seat) => ($this->isPilot($seat)
            && ($tag === '' || strtolower($seat['Tag']) === $tag)
            && $this->defaultGroup($seat['User']['weaponcontroller'] ?? []))
            // Sole-bound-seat restriction is verified for the Stingray's single human role and guards the tag-mismatch route only.
            || (count($index['seats']) === 1 && $tag !== '' && $this->isPilot($seat)
                && $this->exclusive($seat['User']['weaponcontroller'] ?? [], $tag)));

        $operators = array_filter($index['seats'], fn (array $seat) => $this->defaultGroup($seat['User']['weaponcontroller'] ?? [])
            || $this->allows($seat['User']['weaponcontroller'] ?? [], $tag));

        if (count($defaults) !== 1 || count($pilots) !== 1 || count($operators) !== 1) {
            return null;
        }

        return $this->station(array_values($pilots)[0]);
    }

    private function exclusive(array $groups, string $tag): bool
    {
        return count($groups) === 1 && ($groups[0]['Priorities'][$tag] ?? null) === 'exclusive_control';
    }

    private function denies(array $groups, string $tag): bool
    {
        return count($groups) > 1 || (count($groups) === 1
            && (in_array($groups[0]['Priorities'][$tag] ?? $groups[0]['DefaultPriority'], ['no_control', 'observe_only'], true)
                || ($groups[0]['Priorities'] !== [] && ! array_key_exists($tag, $groups[0]['Priorities']))));
    }

    private function allows(array $groups, string $tag): bool
    {
        return count($groups) === 1 && $this->granted($groups[0]['Priorities'][$tag] ?? $groups[0]['DefaultPriority']);
    }

    private function granted(mixed $priority): bool
    {
        return $priority === 'exclusive_control' || (is_numeric($priority) && (float) $priority > 0);
    }

    private function supportsAction(array $seat, array $mount, bool $direct = false, array $ports = []): bool
    {
        if (! $direct && $this->hasPayloadType($mount['Entry'], 'tractorbeam')) {
            return $this->conditionalTractorUse($seat, $mount, $ports);
        }

        if ($this->hasPayloadType($mount['Entry'], 'salvagehead') || $this->hasPayloadType($mount['Entry'], 'salvagefieldemitter')) {
            return $mount['Tag'] !== '' && $this->exclusive($seat['User']['salvagecontroller'] ?? [], strtolower($mount['Tag']));
        }

        // A weapon controller can aim a utility mount without granting use of its tool.
        return $this->hasPayloadType($mount['Entry'], 'weapongun')
            || $this->hasPayloadType($mount['Entry'], 'missile') || $this->hasPayloadType($mount['Entry'], 'bomb')
            || ($direct && array_any(['weaponmining', 'tractorbeam', 'towingbeam', 'toolarm'],
                fn (string $type) => $this->hasPayloadType($mount['Entry'], $type)));
    }

    private function integratedAction(array $mount, array $ports, string $tag): bool
    {
        if (array_any(['weapongun', 'tractorbeam'], fn (string $action) => $this->exclusive($mount['Usable'][$action] ?? [], $tag)
            && $this->hasPayloadType($mount['Entry'], $action))) {
            return true;
        }

        if (! $this->hasPayloadType($mount['Entry'], 'weaponmining') || ! $this->exclusive($mount['User']['miningcontroller'] ?? [], $tag)) {
            return false;
        }

        $controllers = array_filter($ports, static fn (array $port) => str_starts_with(strtolower($port['Entry']['Type']), 'miningcontroller.')
            && strtolower($port['Tag']) === $tag);

        return count($controllers) === 1 && $this->exclusive(array_values($controllers)[0]['Usable']['weaponmining'] ?? [], $tag);
    }

    private function conditionalTractorUse(array $seat, array $mount, array $ports): bool
    {
        $tag = strtolower($mount['Tag']);

        if ($tag === '' || strtolower($seat['Tag']) !== $tag
            || ! $this->exclusive($seat['User']['salvagecontroller'] ?? [], $tag)
            || ! ServiceFactory::getItemService()->getByReference($mount['Entry']['UUID'] ?? null)?->requiresRemoteTurretView()) {
            return false;
        }

        $aim = array_filter($ports, static fn (array $port) => str_starts_with(strtolower($port['Entry']['Type']), 'weaponcontroller.')
            && strtolower($port['Tag']) === $tag);

        $tools = array_filter($ports, static fn (array $port) => str_starts_with(strtolower($port['Entry']['Type']), 'salvagecontroller.')
            && strtolower($port['Tag']) === $tag);

        $stations = array_filter($ports, static fn (array $port) => str_starts_with(strtolower($port['Entry']['Type']), 'seat.')
            && strtolower($port['Tag']) === $tag);

        if (count($aim) !== 1 || count($tools) !== 1 || count($stations) !== 1) {
            return false;
        }

        $tool = array_values($tools)[0];

        // Require the same seat and target tag so either interpretation of Condition_Tag identifies this station.
        return $this->exclusive(array_values($aim)[0]['Usable']['turret'] ?? [], $tag)
            && ($tool['RemoteViewUse']['tractorbeam'] ?? []) === [$tag]
            && ! isset($tool['Usable']['tractorbeam']);
    }

    private function hasPayloadType(array $entry, string $type): bool
    {
        return $this->typeRoot($entry) === $type
            || array_any($entry['Loadout'] ?? [], fn (array $child) => $this->hasPayloadType($child, $type));
    }

    private function typeRoot(array $entry): string
    {
        return strtolower(explode('.', $entry['Type'] ?? '', 2)[0]);
    }

    private function defaultGroup(array $groups): bool
    {
        return count($groups) === 1 && ($groups[0]['DefaultOnly'] || ($groups[0]['DefaultExclusive'] ?? false));
    }

    private function isPilot(array $seat): bool
    {
        return strcasecmp($seat['Entry']['Type'], 'Seat.Pilot') === 0
            || strcasecmp($seat['Tag'], 'pilotseat') === 0
            || strcasecmp($seat['Tag'], 'pilot_seat') === 0;
    }

    private function isCopilot(array $seat): bool
    {
        return strcasecmp($seat['Entry']['Type'], 'Seat.Copilot') === 0
            || strcasecmp($seat['Tag'], 'copilotseat') === 0
            || strcasecmp($seat['Tag'], 'copilot_seat') === 0;
    }

    /** A single strictly well-formed group whose only claim is a positive numeric defaultPriority. */
    private function strictNumericDefault(array $groups): bool
    {
        return count($groups) === 1 && ($groups[0]['WellFormed'] ?? false)
            && ($groups[0]['Priorities'] ?? []) === []
            && is_numeric($groups[0]['DefaultPriority'] ?? null) && (float) $groups[0]['DefaultPriority'] > 0;
    }

    private function isAutonomousTurret(array $entry): bool
    {
        return in_array(strtolower($entry['Type'] ?? ''), ['turret.pdcturret', 'turret.missileturret', 'turretbase.unmanned'], true);
    }

    /**
     * Redeemer wing-gun pattern: the mount carries the pilot's physical seat tag and the default
     * weapon group, while a differently tagged controller supplies the bare default Turret/WeaponGun
     * groups and the pilot alone holds that controller tag exclusively. Every other installed
     * weapon controller must keep strictly tag-scoped claims; any default or mount-tag claim competes.
     */
    private function differentlyTaggedPilotStation(array $mount, array $index, string $tag): ?array
    {
        $controllers = $index['controllers'];

        if ($tag === '' || count($controllers) <= 1 || trim((string) $mount['DefaultWeaponGroup']) === '' || $mount['Controllers'] !== []
            || $index['ambiguous'] || ! $index['seatsComplete']
            || ($index['installedCounts']['weaponcontroller'] ?? 0) !== count($controllers)
            || ! $this->hasPayloadType($mount['Entry'], 'weapongun') || $this->isAutonomousTurret($mount['Entry'])) {
            return null;
        }

        $defaults = array_filter($controllers, fn (array $controller) => strtolower($controller['Tag']) !== $tag
            && $this->strictDefault($controller['Usable']['turret'] ?? [])
            && $this->strictDefault($controller['Usable']['weapongun'] ?? []));

        if (count($defaults) !== 1) {
            return null;
        }

        $controller = array_values($defaults)[0];
        $controllerTag = strtolower($controller['Tag']);

        if (array_any($controllers, fn (array $other) => $other !== $controller
            && ! $this->separatelyScopedController($other, $tag, $controllerTag))) {
            return null;
        }

        $holders = array_filter($index['seats'], fn (array $seat) => $this->wellFormedExclusive($seat['User']['weaponcontroller'] ?? [], $controllerTag));
        $pilots = array_filter($holders, fn (array $seat) => $this->isPilot($seat) && strtolower($seat['Tag']) === $tag
            && $this->isHumanSeatPort($seat));

        if (count($holders) !== 1 || count($pilots) !== 1) {
            return null;
        }

        $pilot = array_values($pilots)[0];
        // Any WeaponController claim competes unless its single well-formed group denies both tags outright.
        $competes = fn (array $seat) => $seat !== $pilot && isset($seat['User']['weaponcontroller'])
            && ! ($this->strictlyDeniesTag($seat['User']['weaponcontroller'], $tag)
                && $this->strictlyDeniesTag($seat['User']['weaponcontroller'], $controllerTag));

        if (array_any($index['seats'], $competes)) {
            return null;
        }

        return $this->station($pilot);
    }

    private function separatelyScopedController(array $controller, string $mountTag, string $controllerTag): bool
    {
        $otherTag = strtolower($controller['Tag']);

        if ($otherTag === '' || $otherTag === $mountTag || $otherTag === $controllerTag) {
            return false;
        }

        foreach (['turret', 'weapongun'] as $action) {
            foreach ($controller['Usable'][$action] ?? [] as $group) {
                if (! ($group['WellFormed'] ?? false)) {
                    return false;
                }

                if (($group['Priorities'] ?? []) === []) {
                    // Flat groups must deny outright; bare, numeric or symbolic defaults compete for the default group.
                    if (! in_array($group['DefaultPriority'], ['no_control', 'observe_only'], true)) {
                        return false;
                    }
                } elseif ($this->granted($group['Priorities'][$mountTag] ?? $group['DefaultPriority'])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function defaultEmpStation(array $mount, array $index, string $root): ?array
    {
        if ($root !== 'emp' || $mount['Tag'] !== '' || $mount['Controllers'] !== [] || $index['ambiguous'] || ! $index['seatsComplete']) {
            return null;
        }

        if (($index['installedCounts']['weaponcontroller'] ?? 0) !== 1
            || count($index['controllers']) !== 1) {
            return null;
        }

        $controller = array_values($index['controllers'])[0];

        if ($controller['Tag'] !== '' || ! $this->strictDefault($controller['Usable']['emp'] ?? [])
            || ($controller['RemoteViewUse']['emp'] ?? []) !== [] || ! $this->hasResolvableEntryRecord($controller['Entry'])) {
            return null;
        }

        if (! ServiceFactory::getItemService()->getByReference($mount['Entry']['UUID'] ?? null)?->hasEMPParams()) {
            return null;
        }

        $operators = $this->potentialOperators($index['ports'], 'weaponcontroller');

        if (count($operators) !== 1) {
            return null;
        }

        $seat = array_values($operators)[0];

        if (! str_starts_with(strtolower($seat['Entry']['Type']), 'seat.') || ! $this->isPilot($seat) || ! $this->isHumanSeatPort($seat)) {
            return null;
        }

        $membership = $seat['User']['weaponcontroller'] ?? [];

        if (! $this->strictDefault($membership) && ! $this->strictDefaultExclusive($membership)) {
            return null;
        }

        return $this->station($seat);
    }

    private function taggedExclusiveToolStation(array $mount, array $index, string $tag, string $root): ?array
    {
        if ($tag === '' || $mount['Controllers'] !== [] || $index['ambiguous'] || ! $index['seatsComplete']) {
            return null;
        }

        $action = match ($root) {
            'toolarm', 'salvagefieldemitter' => $root,
            default => $this->hasInstalledPayload($mount['Entry'], 'tractorbeam') ? 'tractorbeam' : null,
        };

        if ($action === null || ($action !== 'tractorbeam' && ! $this->hasInstalledPayload($mount['Entry'], $root))) {
            return null;
        }

        $salvageControllers = $index['salvageControllers'];

        if (($index['installedCounts']['salvagecontroller'] ?? 0) !== count($salvageControllers)) {
            return null;
        }

        // Every bound salvage controller with a numeric, default, conditional, unknown-shape or missing-record action claim competes.
        // A conditional claim always competes, even when the unconditional group flatly denies the tag.
        $claimants = array_filter($salvageControllers, fn (array $port) => ($port['RemoteViewUse'][$action] ?? []) !== []
            || (isset($port['Usable'][$action]) && (! $this->strictlyDeniesTag($port['Usable'][$action], $tag) || ! $this->hasResolvableEntryRecord($port['Entry']))));

        if (count($claimants) !== 1) {
            return null;
        }

        $controller = array_values($claimants)[0];

        if (strtolower($controller['Tag']) !== $tag || ! $this->hasResolvableEntryRecord($controller['Entry'])
            || ! $this->wellFormedExclusive($controller['Usable'][$action] ?? [], $tag)
            || ($controller['RemoteViewUse'][$action] ?? []) !== []) {
            return null;
        }

        $holders = array_filter($index['ports'], fn (array $port) => isset($port['User']['salvagecontroller'])
            && ! $this->strictlyDeniesTag($port['User']['salvagecontroller'], $tag));

        if (count($holders) !== 1) {
            return null;
        }

        $holder = array_values($holders)[0];

        if (! str_starts_with(strtolower($holder['Entry']['Type']), 'seat.') || ! $this->isHumanSeatPort($holder)
            || ! $this->wellFormedExclusive($holder['User']['salvagecontroller'] ?? [], $tag)) {
            return null;
        }

        return $this->station($holder);
    }

    private function defaultSalvageStation(array $mount, array $index, string $root): ?array
    {
        if ($mount['Tag'] !== '' || $mount['Controllers'] !== [] || $index['ambiguous'] || ! $index['seatsComplete']) {
            return null;
        }

        $actions = match ($root) {
            'toolarm' => $this->hasInstalledPayload($mount['Entry'], 'salvagehead') ? ['toolarm', 'salvagehead'] : null,
            'salvagefieldemitter' => ['salvagefieldemitter'],
            default => null,
        };

        if ($actions === null || ! $this->hasInstalledPayload($mount['Entry'], $root)) {
            return null;
        }

        $salvageControllers = $index['salvageControllers'];

        if (($index['installedCounts']['salvagecontroller'] ?? 0) !== count($salvageControllers)
            || count($salvageControllers) !== 1) {
            return null;
        }

        $controller = array_values($salvageControllers)[0];

        if ($controller['Tag'] !== '' || ! $this->hasResolvableEntryRecord($controller['Entry'])) {
            return null;
        }

        if (array_any($actions, fn($action) => !$this->strictDefault($controller['Usable'][$action] ?? [])
            || ($controller['RemoteViewUse'][$action] ?? []) !== [])) {
            return null;
        }

        $holders = array_filter($index['ports'], static fn (array $port) => isset($port['User']['salvagecontroller']));

        if (count($holders) !== 1) {
            return null;
        }

        $seat = array_values($holders)[0];

        if (! str_starts_with(strtolower($seat['Entry']['Type']), 'seat.') || ! $this->isPilot($seat) || ! $this->isHumanSeatPort($seat)) {
            return null;
        }

        $membership = $seat['User']['salvagecontroller'] ?? [];

        if (! $this->strictDefault($membership) && ! $this->strictDefaultExclusive($membership)) {
            return null;
        }

        return $this->station($seat);
    }

    private function strictDefault(array $groups): bool
    {
        return count($groups) === 1 && ($groups[0]['WellFormed'] ?? false) && ($groups[0]['DefaultOnly'] ?? false);
    }

    private function strictDefaultExclusive(array $groups): bool
    {
        return count($groups) === 1 && ($groups[0]['WellFormed'] ?? false) && ($groups[0]['DefaultExclusive'] ?? false);
    }

    private function wellFormedExclusive(array $groups, string $tag): bool
    {
        return count($groups) === 1 && ($groups[0]['WellFormed'] ?? false)
            && ($groups[0]['Priorities'][$tag] ?? null) === 'exclusive_control';
    }

    private function potentialOperators(array $ports, string $controlType): array
    {
        return array_filter($ports, static fn (array $port) => isset($port['User'][$controlType])
            && ! (count($port['User'][$controlType]) === 1 && ($port['User'][$controlType][0]['DefaultObserveOnly'] ?? false)));
    }

    private function strictlyDeniesTag(array $groups, string $tag): bool
    {
        if (count($groups) !== 1 || ! ($groups[0]['WellFormed'] ?? false)) {
            return false;
        }

        $priority = $groups[0]['Priorities'][$tag] ?? $groups[0]['DefaultPriority'];

        return $priority === 'no_control' || $priority === 'observe_only';
    }

    private function hasResolvableEntryRecord(array $entry): bool
    {
        $uuid = $entry['UUID'] ?? null;

        return is_string($uuid) && $uuid !== '' && ServiceFactory::getItemService()->getByReference($uuid) !== null;
    }

    private function hasInstalledPayload(array $entry, string $type): bool
    {
        // The installed ancestor chain must resolve to real records; a typed node under an unresolved ancestor is not proof.
        if (! $this->hasResolvableEntryRecord($entry)) {
            return false;
        }

        if ($this->typeRoot($entry) === $type) {
            return true;
        }

        return array_any($entry['Loadout'] ?? [], fn (array $child) => $this->hasInstalledPayload($child, $type));
    }

    private function isHumanSeatPort(array $port): bool
    {
        return ServiceFactory::getItemService()->getByReference($port['Entry']['UUID'] ?? null)?->isPlayerOperableSeat() ?? false;
    }

    private function station(array $seat): array
    {
        $pilot = $this->isPilot($seat);

        $name = null;

        foreach (['PartDisplayName', 'PortDisplayName'] as $key) {
            $label = trim($seat['Entry'][$key] ?? '');

            if ($label !== '' && ! str_starts_with($label, '@') && ! str_starts_with(strtolower($label), 'port_')
                && ! str_contains(strtolower($label), 'placeholder')) {
                $name = $label;

                break;
            }
        }

        if ($name === null) {
            $name = preg_replace('/^hardpoint_(seat_)?/', '', $seat['HardpointName']);
            $name = preg_replace(['/_r$/', '/_l$/'], ['_right', '_left'], $name);
            $name = ucfirst(str_replace(['tractorbeam', '_'], ['tractor beam', ' '], $name)).' seat';
        }

        return ['Kind' => $pilot ? 'Pilot' : 'Crew', 'SeatPortId' => $seat['Entry']['PortId'],
            'Name' => $pilot ? 'Pilot seat' : $name];
    }
}
