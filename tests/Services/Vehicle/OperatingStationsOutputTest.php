<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\Services\Vehicle\MountingPositionBuilder;
use Octfx\ScDataDumper\Services\Vehicle\OperatingStationResolver;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class OperatingStationsOutputTest extends ScDataTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // RootDocument/DOM cycles from fixture vehicles only break via the cycle collector.
        gc_collect_cycles();
    }

    public function test_300i_default_weapon_setup_publishes_pilot_station_for_gimbals(): void
    {
        $positions = $this->positions(
            '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
             <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
             <Part name="mount"><ItemPort defaultWeaponGroup="2"/></Part>',
            [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'WeaponController.UNDEFINED'),
                self::entry('mount', 'Turret.GunTurret') + ['ClassName' => 'Mount_Gimbal_S3', 'Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]],
        );
        self::assertSame('Gimbal', $positions[0]['MountType']);
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $positions[0]['OperatingStations'] ?? []);
        self::assertArrayNotHasKey('ControlRoutes', $positions[0]);
    }

    public function test_default_exclusive_pilot_weapon_access_resolves_loaded_guns(): void
    {
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="exclusive_control"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort defaultWeaponGroup="1"/></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'WeaponController.UNDEFINED'), self::entry('mount', 'WeaponGun.Gun')];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach (['no_control', 'observe_only', '10'] as $priority) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions(str_replace('defaultPriority="exclusive_control"', 'defaultPriority="'.$priority.'"', $parts), $loadout)[0]);
        }

        $otherSeat = str_replace(['name="pilot"', 'controllableTags="pilotSeat"'], ['name="crew"', 'controllableTags="crewSeat"'], substr($parts, 0, strpos($parts, '</Part>') + 7));
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts.$otherSeat, [...$loadout, self::entry('crew', 'Seat.Operator')])[0]);
    }

    public function test_aurora_mk2_matching_pilot_tags_and_default_groups_publish_the_pilot(): void
    {
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="pilotSeat"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/></ItemPort></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.GunTurret') + ['Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('<Part name="controller"><ItemPort><ControllerDef controllableTags="pilotSeat">', '<Part name="controller"><ItemPort><ControllerDef controllableTags="other">', $parts),
            str_replace('<Part name="mount"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/>', '<Part name="mount"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="other"/>', $parts),
            str_replace('<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat">', '<Part name="pilot"><ItemPort><ControllerDef controllableTags="other">', $parts),
            str_replace('itemType="WeaponController"/', 'itemType="WeaponController" defaultPriority="observe_only"/', $parts),
            str_replace('itemType="WeaponGun"/', 'itemType="WeaponGun" defaultPriority="10"/', $parts),
            str_replace('defaultWeaponGroup="1"', 'defaultWeaponGroup=""', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }
    }

    public function test_300i_default_missile_setup_publishes_pilot_station_for_both_loaded_racks(): void
    {
        $positions = $this->positions($this->missileParts(), $this->missileLoadout());
        self::assertCount(2, $positions);

        foreach ($positions as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
            self::assertArrayNotHasKey('ControlRoutes', $position);
        }
    }

    public function test_starfighter_pilot_seat_tag_alias_publishes_default_missile_pilot(): void
    {
        $parts = str_replace('controllableTags="pilotSeat"', 'controllableTags="Pilot_Seat"', $this->missileParts());
        $loadout = $this->missileLoadout();
        $loadout[0]['Type'] = 'Seat.UNDEFINED';

        foreach ($this->positions($parts, $loadout) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }
    }

    public function test_pilot_seat_tag_alias_rejects_near_miss_tags_and_competing_default_operators(): void
    {
        $parts = str_replace('controllableTags="pilotSeat"', 'controllableTags="Pilot_Seat"', $this->missileParts());
        $loadout = $this->missileLoadout();
        $loadout[0]['Type'] = 'Seat.UNDEFINED';

        foreach (['CoPilot_Seat', 'Pilot_Seat_Other', 'Pilot-Seat'] as $tag) {
            foreach ($this->positions(str_replace('controllableTags="Pilot_Seat"', 'controllableTags="'.$tag.'"', $parts), $loadout) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }

        $crew = '<Part name="crew"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';

        foreach ($this->positions($parts.$crew, [...$loadout, self::entry('crew', 'Seat.Operator')]) as $position) {
            self::assertArrayNotHasKey('OperatingStations', $position);
        }
    }

    public function test_pilot_seat_tag_alias_publishes_default_gun_pilot(): void
    {
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="Pilot_Seat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort defaultWeaponGroup="2"/></Part>';
        $loadout = [self::entry('pilot', 'Seat.UNDEFINED'), self::entry('controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.GunTurret') + ['ClassName' => 'Mount_Gimbal_S3', 'Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);
        $loadout[0]['Type'] = 'Seat.Operator';
        self::assertArrayNotHasKey('OperatingStations', $this->positions(str_replace('controllableTags="Pilot_Seat"', 'controllableTags="Pilot_Seat_Other"', $parts), $loadout)[0]);
    }

    public function test_stingray_pilot_exclusive_grant_publishes_default_guns_despite_seat_tag_mismatch(): void
    {
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="weaponPilot"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="weaponPilot"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="weaponPilot"/></ItemPort></Part>';
        $loadout = [self::entry('pilot', 'Seat.UNDEFINED'), self::entry('controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.GunTurret') + ['Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('value="exclusive_control"', 'value="10"', $parts),
            str_replace('tag="weaponPilot"', 'tag="other"', $parts),
            str_replace('value="exclusive_control"', 'value="no_control"', $parts),
            str_replace('itemType="WeaponGun"', 'itemType="Display"', $parts),
            str_replace('itemType="Turret"', 'itemType="Display"', $parts),
            str_replace('defaultWeaponGroup="1"', 'defaultWeaponGroup=""', $parts),
            $parts,
            str_replace('name="controller"', 'name="other"', $parts).'<Part name="other2"><ItemPort><ControllerDef controllableTags="weaponPilot"/></ItemPort></Part>',
        ] as $i => $xml) {
            $entries = $i === 6 ? [self::entry('pilot', 'Seat.UNDEFINED'), self::entry('controller', 'WeaponController.UNDEFINED'), self::entry('mount', 'Turret.GunTurret')] : [...$loadout, ...($i === 7 ? [self::entry('other', 'WeaponController.UNDEFINED'), self::entry('other2', 'WeaponController.UNDEFINED')] : [])];
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $entries)[0]);
        }

        $crew = '<Part name="crew"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="10"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts.$crew, [...$loadout, self::entry('crew', 'Seat.Operator')])[0]);
    }

    public function test_shiv_literal_observer_copilot_is_excluded_from_default_missile_operators(): void
    {
        $parts = $this->missileParts().'<Part name="copilot"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="observe_only"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        $loadout = [...$this->missileLoadout(), self::entry('copilot', 'Seat.Operator')];
        $loadout[0]['Type'] = 'Seat.UNDEFINED';

        foreach ($this->positions($parts, $loadout) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }

        foreach ([
            str_replace('defaultPriority="observe_only"', 'defaultPriority="10"', $parts),
            str_replace(' defaultPriority="observe_only"', '', $parts),
            str_replace('defaultPriority="observe_only"', 'defaultPriority="no_control"', $parts),
            str_replace('defaultPriority="observe_only"', 'defaultPriority="exclusive_control"', $parts),
            str_replace('defaultPriority="observe_only"', 'defaultPriority="observe_only" priorityOnly="1"', $parts),
            str_replace('<PriorityGroup itemType="MissileController" defaultPriority="observe_only"/>', '<PriorityGroup itemType="MissileController" defaultPriority="observe_only"><tags tag="dedicated"><Priority value="exclusive_control"/></tags></PriorityGroup>', $parts),
            str_replace('<PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="observe_only"/></PriorityGroups>', '<PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="observe_only"/><PriorityGroup itemType="MissileController" defaultPriority="observe_only"/></PriorityGroups>', $parts),
        ] as $xml) {
            foreach ($this->positions($xml, $loadout) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }
    }

    public function test_untagged_default_emp_publishes_pilot_without_default_weapon_group(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000e1';
        $emp = '00000000-0000-0000-0000-0000000000e2';
        $wc = '00000000-0000-0000-0000-0000000000e4';
        $this->writeCacheFiles(uuidToPathMap: [
            $seat => $this->writeFile('seat.xml', self::seatRecord($seat)),
            $emp => $this->writeFile('emp.xml', self::empRecord($emp)),
            $wc => $this->writeFile('wc.xml', self::itemRecord($wc)),
        ]);
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="EMP"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="device"><ItemPort/></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], self::entry('controller', 'WeaponController.UNDEFINED') + ['UUID' => $wc],
            self::entry('device', 'EMP.Device') + ['UUID' => $emp]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);
        $exclusive = str_replace('itemType="WeaponController"', 'itemType="WeaponController" defaultPriority="exclusive_control"', $parts);
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($exclusive, $loadout, false)[0]['OperatingStations'] ?? []);
        $observer = $parts.'<Part name="engineer"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="observe_only"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        $withObserver = [...$loadout, self::entry('engineer', 'Seat.Operator')];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($observer, $withObserver, false)[0]['OperatingStations'] ?? []);

        foreach ([
            [str_replace(['<Part name="controller"><ItemPort><ControllerDef>', '<Part name="device"><ItemPort/>'], ['<Part name="controller"><ItemPort><ControllerDef controllableTags="remote_turret">', '<Part name="device"><ItemPort><ControllerDef controllableTags="remote_turret"/></ItemPort>'], $parts), $loadout],
            [str_replace('<Part name="device"><ItemPort/>', '<Part name="device"><ItemPort><Controllers><Controller port="missing" active="1"/></Controllers></ItemPort>', $parts), $loadout],
            [$parts, [...$loadout, self::entry('orphan', 'WeaponController.UNDEFINED')]],
            [str_replace('itemType="EMP"', 'itemType="EMP" defaultPriority="10"', $parts), $loadout],
            [str_replace('itemType="EMP"', 'itemType="EMP" defaultPriority="exclusive_control"', $parts), $loadout],
            [str_replace('itemType="EMP"', 'itemType="EMP" maxTargets="2"', $parts), $loadout],
            [str_replace('itemType="EMP"', 'itemType="Turret"', $parts), $loadout],
            [str_replace('<UsableDef><PriorityGroups><PriorityGroup itemType="EMP"/></PriorityGroups></UsableDef>', '<UsableDef><ConditionalPriorities><ConditionalPriority itemType="EMP" defaultPriority="no_control" priorityWhenConditionMet="exclusive_control"><Condition_And><Condition_Tag tag="any"/><Condition_AllowedByRemoteTurretView/></Condition_And></ConditionalPriority></ConditionalPriorities></UsableDef>', $parts), $loadout],
            [str_replace('controllableTags="pilotSeat"', 'controllableTags="crewSeat"', $parts), [self::entry('pilot', 'Seat.Operator') + ['UUID' => $seat], $loadout[1], $loadout[2]]],
            [$parts, [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], $loadout[1], self::entry('device', 'EMP.Device') + ['UUID' => $seat]]],
            [$parts, [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], self::entry('controller', 'WeaponController.UNDEFINED'), $loadout[2]]],
            [$parts.'<Part name="console"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="10"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('console', 'TurretBase.MannedTurret')]],
        ] as [$xml, $entries]) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $entries, false)[0]);
        }

        $disabled = '00000000-0000-0000-0000-0000000000e3';
        $this->writeCacheFiles(uuidToPathMap: [$disabled => $this->writeFile('seat-disabled.xml', self::seatRecord($disabled, false))]);
        $seated = $loadout;
        $seated[0]['UUID'] = $disabled;
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $seated, false)[0]);
    }

    public function test_tagged_exclusive_salvage_publishes_the_exclusive_holder(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000s1';
        $this->writeCacheFiles(uuidToPathMap: [
            $seat => $this->writeFile('seat.xml', self::seatRecord($seat)),
            '00000000-0000-0000-0000-0000000000s2' => $this->writeFile('sc.xml', self::itemRecord('00000000-0000-0000-0000-0000000000s2')),
            '00000000-0000-0000-0000-0000000000s3' => $this->writeFile('arm.xml', self::itemRecord('00000000-0000-0000-0000-0000000000s3')),
        ]);
        $parts = '<Part name="holder"><ItemPort><ControllerDef controllableTags="copilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="no_control"><tags tag="mainsalvagearm"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="mainsalvagearm"><UsableDef><PriorityGroups><PriorityGroup itemType="ToolArm" defaultPriority="no_control"><tags tag="mainsalvagearm"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="mainsalvagearm"/></ItemPort></Part>';
        $loadout = [self::entry('holder', 'Seat.Operator') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000s2'],
            self::entry('mount', 'ToolArm.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000s3']];
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Holder seat']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);
        $emitterParts = str_replace('itemType="ToolArm"', 'itemType="SalvageFieldEmitter"', str_replace('ToolArm.UNDEFINED', 'SalvageFieldEmitter.UNDEFINED', $parts));
        $emitterLoadout = $loadout;
        $emitterLoadout[2]['Type'] = 'SalvageFieldEmitter.UNDEFINED';
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Holder seat']], $this->positions($emitterParts, $emitterLoadout, false)[0]['OperatingStations'] ?? []);
        $pilot = str_replace(['name="holder"', 'controllableTags="copilotSeat"'], ['name="pilot"', 'controllableTags="pilotSeat"'], substr($parts, 0, strpos($parts, '</Part>') + 7));
        self::assertSame('Pilot', $this->positions($pilot.str_replace('name="holder"', 'name="rest"', substr($parts, strpos($parts, '</Part>') + 7)), [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], $loadout[1], $loadout[2]], false)[0]['OperatingStations'][0]['Kind']);
        $nestedCondition = str_replace('<Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef>', '<Priority value="exclusive_control"><UnknownCondition/></Priority></tags></PriorityGroup></PriorityGroups></UsableDef>', $parts);
        $duplicateGroups = $parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController"/><PriorityGroup itemType="SalvageController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        $unknownShape = $parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="no_control" maxTargets="2"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        $numericHolder = str_replace('tag="mainsalvagearm"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef>', 'tag="mainsalvagearm"><Priority value="10"/></tags></PriorityGroup></PriorityGroups></UserDef>', substr($parts, 0, strpos($parts, '<Part name="controller"')));
        $competingController = $parts.'<Part name="rival_controller"><ItemPort><ControllerDef controllableTags="other"><UsableDef><PriorityGroups><PriorityGroup itemType="ToolArm" defaultPriority="no_control"><tags tag="mainsalvagearm"><Priority value="10"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>';

        foreach ([
            [str_replace('tag="mainsalvagearm"><Priority value="exclusive_control"', 'tag="mainsalvagearm"><Priority value="10"', $parts), $loadout],
            [str_replace('controllableTags="mainsalvagearm"/></ItemPort>', 'controllableTags="other"/></ItemPort>', $parts), $loadout],
            [str_replace('itemType="ToolArm"', 'itemType="SalvageHead"', $parts), $loadout],
            [str_replace('<Part name="mount"><ItemPort>', '<Part name="mount"><ItemPort><Controllers><Controller port="missing" active="1"/></Controllers>', $parts), $loadout],
            [$parts, [...$loadout, self::entry('orphan', 'SalvageController.UNDEFINED')]],
            [$parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="10"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [$parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [str_replace('itemType="ToolArm" defaultPriority="no_control"', 'itemType="ToolArm" defaultPriority="no_control" maxTargets="2"', $parts), $loadout],
            [str_replace('<tags tag="mainsalvagearm"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef>', '<tags tag="mainsalvagearm"><Priority value="exclusive_control"/></tags><tags tag="mainsalvagearm"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef>', $parts), $loadout],
            [$nestedCondition, $loadout],
            [$duplicateGroups, [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [$unknownShape, [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [$numericHolder.str_replace('name="holder"', 'name="rest"', substr($parts, strpos($parts, '<Part name="controller"'))), [self::entry('holder', 'Seat.Operator') + ['UUID' => $seat], $loadout[1], $loadout[2]]],
            [$competingController, [...$loadout, self::entry('rival_controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000s2']]],
            [$parts, [self::entry('holder', 'Seat.Operator') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED'), $loadout[2]]],
            [$parts, [self::entry('holder', 'Seat.Operator') + ['UUID' => $seat], $loadout[1], self::entry('mount', 'ToolArm.UNDEFINED')]],
        ] as [$xml, $entries]) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $entries, false)[0]);
        }
    }

    public function test_untagged_default_salvage_publishes_pilot_for_reviewed_root_shapes(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000d1';
        $this->writeCacheFiles(uuidToPathMap: [
            $seat => $this->writeFile('seat.xml', self::seatRecord($seat)),
            '00000000-0000-0000-0000-0000000000d2' => $this->writeFile('sc.xml', self::itemRecord('00000000-0000-0000-0000-0000000000d2')),
            '00000000-0000-0000-0000-0000000000d3' => $this->writeFile('arm.xml', self::itemRecord('00000000-0000-0000-0000-0000000000d3')),
            '00000000-0000-0000-0000-0000000000d4' => $this->writeFile('head.xml', self::itemRecord('00000000-0000-0000-0000-0000000000d4')),
            '00000000-0000-0000-0000-0000000000d5' => $this->writeFile('intake.xml', self::itemRecord('00000000-0000-0000-0000-0000000000d5')),
        ]);
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="ToolArm"/><PriorityGroup itemType="SalvageHead"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="arm"><ItemPort/></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000d2'],
            self::entry('arm', 'ToolArm.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000d3', 'Loadout' => [self::entry('head', 'SalvageHead.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000d4']]]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);
        $emitterParts = str_replace(['<PriorityGroup itemType="ToolArm"/><PriorityGroup itemType="SalvageHead"/>', 'name="arm"'], ['<PriorityGroup itemType="SalvageFieldEmitter"/>', 'name="intake"'], $parts);
        $emitterLoadout = [$loadout[0], $loadout[1], self::entry('intake', 'SalvageFieldEmitter.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000d5']];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($emitterParts, $emitterLoadout, false)[0]['OperatingStations'] ?? []);
        $bare = $loadout;
        unset($bare[2]['Loadout']);

        foreach ([
            [$parts, $bare],
            [str_replace('<PriorityGroup itemType="SalvageHead"/>', '', $parts), $loadout],
            [str_replace('<PriorityGroup itemType="ToolArm"/>', '', $parts), $loadout],
            [str_replace('<PriorityGroup itemType="ToolArm"/>', '<PriorityGroup itemType="ToolArm" defaultPriority="exclusive_control"/>', $parts), $loadout],
            [str_replace('<Part name="arm"><ItemPort/>', '<Part name="arm"><ItemPort><ControllerDef controllableTags="tagged"/></ItemPort>', $parts), $loadout],
            [str_replace('<Part name="controller"><ItemPort><ControllerDef>', '<Part name="controller"><ItemPort><ControllerDef controllableTags="tagged">', $parts), $loadout],
            [$parts, [...$loadout, self::entry('orphan', 'SalvageController.UNDEFINED')]],
            [str_replace('controllableTags="pilotSeat"', 'controllableTags="crewSeat"', $parts), [self::entry('pilot', 'Seat.Operator') + ['UUID' => $seat], $loadout[1], $loadout[2]]],
            [$parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [str_replace('<Part name="arm"><ItemPort/>', '<Part name="arm"><ItemPort><Controllers><Controller port="missing" active="1"/></Controllers></ItemPort>', $parts), $loadout],
            [str_replace('itemType="ToolArm"/', 'itemType="ToolArm" maxTargets="2"/', $parts), $loadout],
            [$emitterParts, [$loadout[0], $loadout[1], self::entry('intake', 'ToolArm.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000d5']]],
            [$parts, [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED'), $loadout[2]]],
            [$parts, [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], $loadout[1], self::entry('arm', 'ToolArm.UNDEFINED') + ['Loadout' => [self::entry('head', 'SalvageHead.UNDEFINED')]]]],
        ] as [$xml, $entries]) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $entries, false)[0]);
        }
    }

    public function test_tagged_exclusive_tractor_publishes_crew_independent_of_numeric_aim(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000t1';
        $this->writeCacheFiles(uuidToPathMap: [
            $seat => $this->writeFile('seat.xml', self::seatRecord($seat)),
            '00000000-0000-0000-0000-0000000000t3' => $this->writeFile('sc.xml', self::itemRecord('00000000-0000-0000-0000-0000000000t3')),
            '00000000-0000-0000-0000-0000000000t4' => $this->writeFile('beam.xml', self::itemRecord('00000000-0000-0000-0000-0000000000t4')),
            '00000000-0000-0000-0000-0000000000t5' => $this->writeFile('mount.xml', self::itemRecord('00000000-0000-0000-0000-0000000000t5')),
        ]);
        $parts = '<Part name="holder"><ItemPort><ControllerDef controllableTags="cargoseat"><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponController" defaultPriority="10"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="cargoseat"><UsableDef><PriorityGroups><PriorityGroup itemType="TractorBeam" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="cargoseat"/></ItemPort></Part>';
        $loadout = [self::entry('holder', 'Seat.UNDEFINED') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t3'],
            self::entry('mount', 'Turret.Utility') + ['UUID' => '00000000-0000-0000-0000-0000000000t5', 'Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t4']]]];
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Holder seat']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);
        $denyingRival = $parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="no_control"><tags tag="other"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Holder seat']], $this->positions($denyingRival, [...$loadout, self::entry('rival', 'Seat.Operator')], false)[0]['OperatingStations'] ?? []);

        foreach ([
            [str_replace('<PriorityGroup itemType="TractorBeam" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="exclusive_control"/></tags></PriorityGroup>', '<PriorityGroup itemType="TractorBeam" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="10"/></tags></PriorityGroup>', $parts), $loadout],
            [str_replace('<PriorityGroup itemType="TractorBeam" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="exclusive_control"/></tags></PriorityGroup>', '<PriorityGroup itemType="TractorBeam"/>', $parts), $loadout],
            [str_replace('controllableTags="cargoseat"/></ItemPort></Part>', 'controllableTags="other"/></ItemPort></Part>', $parts), $loadout],
            [$parts, [...$loadout, self::entry('orphan', 'SalvageController.UNDEFINED')]],
            [$parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController" defaultPriority="no_control"><tags tag="cargoseat"><Priority value="10"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [$parts.'<Part name="rival"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="SalvageController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival', 'Seat.Operator')]],
            [str_replace('<Part name="mount"><ItemPort>', '<Part name="mount"><ItemPort><Controllers><Controller port="missing" active="1"/></Controllers>', $parts), $loadout],
            [str_replace('</PriorityGroups></UsableDef>', '</PriorityGroups><ConditionalPriorities><ConditionalPriority itemType="TractorBeam" defaultPriority="no_control" priorityWhenConditionMet="exclusive_control"><Condition_And><Condition_Tag tag="cargoseat"/><Condition_AllowedByRemoteTurretView/></Condition_And></ConditionalPriority></ConditionalPriorities></UsableDef>', $parts), $loadout],
            [str_replace('itemType="TractorBeam" defaultPriority="no_control"', 'itemType="TractorBeam" defaultPriority="no_control" maxTargets="2"', $parts), $loadout],
            [$parts, [$loadout[0], $loadout[1], self::entry('mount', 'Turret.Utility') + ['Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t4']]]]],
            [$parts, [self::entry('holder', 'Seat.UNDEFINED') + ['UUID' => $seat], self::entry('controller', 'SalvageController.UNDEFINED'), $loadout[2]]],
            [$parts, [$loadout[0], $loadout[1], self::entry('mount', 'Turret.Utility') + ['UUID' => '00000000-0000-0000-0000-0000000000ff', 'Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t4']]]]],
            [$parts, [$loadout[0], $loadout[1], self::entry('mount', 'Turret.Utility') + ['UUID' => '00000000-0000-0000-0000-0000000000t5', 'Loadout' => [self::entry('mid', 'TurretBase.MannedTurret') + ['Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t4']]]]]]],
            [$parts.'<Part name="rival_controller"><ItemPort><ControllerDef controllableTags="other"><UsableDef><PriorityGroups><PriorityGroup itemType="TractorBeam" defaultPriority="no_control"/></PriorityGroups><ConditionalPriorities><ConditionalPriority itemType="TractorBeam" defaultPriority="no_control" priorityWhenConditionMet="10"><Condition_And><Condition_Tag tag="cargoseat"/><Condition_AllowedByRemoteTurretView/></Condition_And></ConditionalPriority></ConditionalPriorities></UsableDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('rival_controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t3']]],
            [str_replace('priorityWhenConditionMet="10"', 'priorityWhenConditionMet="exclusive_control"', $parts.'<Part name="rival_controller"><ItemPort><ControllerDef controllableTags="other"><UsableDef><PriorityGroups><PriorityGroup itemType="TractorBeam" defaultPriority="no_control"/></PriorityGroups><ConditionalPriorities><ConditionalPriority itemType="TractorBeam" defaultPriority="no_control" priorityWhenConditionMet="10"><Condition_And><Condition_Tag tag="cargoseat"/><Condition_AllowedByRemoteTurretView/></Condition_And></ConditionalPriority></ConditionalPriorities></UsableDef></ControllerDef></ItemPort></Part>'), [...$loadout, self::entry('rival_controller', 'SalvageController.UNDEFINED') + ['UUID' => '00000000-0000-0000-0000-0000000000t3']]],
            [$parts, [...$loadout, self::entry('stow', 'Seat.UNDEFINED')]],
        ] as [$xml, $entries]) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $entries, false)[0]);
        }

        $disabled = '00000000-0000-0000-0000-0000000000t2';
        $this->writeCacheFiles(uuidToPathMap: [$disabled => $this->writeFile('seat-disabled.xml', self::seatRecord($disabled, false))]);
        $seated = $loadout;
        $seated[0]['UUID'] = $disabled;
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $seated, false)[0]);
    }

    public function test_default_missile_operation_stays_unknown_when_another_seat_has_a_dedicated_grant(): void
    {
        $parts = $this->missileParts().'<Part name="crew"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController"><tags tag="dedicated"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';

        foreach ($this->positions($parts, [...$this->missileLoadout(), self::entry('crew', 'Seat.Operator')]) as $position) {
            self::assertArrayNotHasKey('OperatingStations', $position);
        }
    }

    public function test_default_missile_operation_requires_complete_unambiguous_evidence(): void
    {
        $parts = $this->missileParts();
        $loadout = $this->missileLoadout();
        $empty = $loadout;
        unset($empty[2]['Loadout'], $empty[3]['Loadout']);

        foreach ([
            [str_replace('itemType="MissileController"', 'itemType="WeaponController"', $parts), $loadout],
            [str_replace('itemType="MissileLauncher"', 'itemType="Turret"', $parts), $loadout],
            [str_replace('itemType="Missile"', 'itemType="WeaponGun"', $parts), $loadout],
            [str_replace('itemType="MissileController"/', 'itemType="MissileController" defaultPriority="observe_only"/', $parts), $loadout],
            [str_replace('itemType="Missile"/', 'itemType="Missile" defaultPriority="no_control"/', $parts), $loadout],
            [str_replace('<Part name="controller"><ItemPort><ControllerDef>', '<Part name="controller"><ItemPort><ControllerDef controllableTags="dedicated">', $parts), $loadout],
            [str_replace('<ItemPort/></Part>', '<ItemPort><ControllerDef controllableTags="dedicated"/></ItemPort></Part>', $parts), $loadout],
            [str_replace('<ItemPort/></Part>', '<ItemPort><Controllers><Controller port="missing" active="1"/></Controllers></ItemPort></Part>', $parts), $loadout],
            [$parts, $empty],
            [$parts, [$loadout[0], $loadout[2], $loadout[3]]],
            [$parts, [$loadout[1], $loadout[2], $loadout[3]]],
            [$parts, [...$loadout, $loadout[1]]],
            [$parts.'<Part name="other"><ItemPort/></Part>', [...$loadout, self::entry('other', 'MissileController.UNDEFINED')]],
            [str_replace('controllableTags="pilotSeat"', 'controllableTags="crewSeat"', $parts), [self::entry('pilot', 'Seat.Operator'), ...array_slice($loadout, 1)]],
            [$parts, [...$loadout, $loadout[0]]],
            [$parts.'<Part name="pilot"><ItemPort/></Part>', $loadout],
        ] as [$xml, $entries]) {
            foreach ($this->positions($xml, $entries) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }
    }

    public function test_default_missile_operation_accepts_a_sole_pilot_with_flat_numeric_default_priority(): void
    {
        $parts = str_replace('<PriorityGroup itemType="MissileController"/>', '<PriorityGroup itemType="MissileController" defaultPriority="1"/>', $this->missileParts());
        $loadout = $this->missileLoadout();

        foreach ($this->positions($parts, $loadout) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }

        // defaultPriority="exclusive_control" stays covered by the pre-existing default-group acceptance.
        foreach ($this->positions(str_replace('defaultPriority="1"', 'defaultPriority="exclusive_control"', $parts), $loadout) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }

        foreach (['0', '-1', 'no_control', 'observe_only'] as $priority) {
            $xml = str_replace('defaultPriority="1"', 'defaultPriority="'.$priority.'"', $parts);

            foreach ($this->positions($xml, $loadout) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }

        foreach ([
            str_replace('defaultPriority="1"', 'defaultPriority="1" priorityOnly="1"', $parts),
            str_replace('<PriorityGroup itemType="MissileController" defaultPriority="1"/>', '<PriorityGroup itemType="MissileController" defaultPriority="1"><tags tag="dedicated"><Priority value="exclusive_control"/></tags></PriorityGroup>', $parts),
            str_replace('<PriorityGroup itemType="MissileController" defaultPriority="1"/>', '<PriorityGroup itemType="MissileController" defaultPriority="1"/><PriorityGroup itemType="MissileController"/>', $parts),
        ] as $xml) {
            foreach ($this->positions($xml, $loadout) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }

        // The numeric route needs complete seat and controller evidence before the join.
        $console = '<Part name="console"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="1"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';

        foreach ([
            [$parts, [...$loadout, self::entry('orphan', 'MissileController.UNDEFINED')]],
            [$parts, [...$loadout, self::entry('stow', 'Seat.UNDEFINED')]],
            [$parts.$console, [...$loadout, self::entry('console', 'SeatAccess.UNDEFINED')]],
        ] as [$xml, $entries]) {
            foreach ($this->positions($xml, $entries) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }

        // The legacy bare-default route is unchanged: unbound extras never blocked it.
        foreach ($this->positions($this->missileParts(), [...$loadout, self::entry('orphan', 'MissileController.UNDEFINED'), self::entry('stow', 'Seat.UNDEFINED')]) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }
    }

    public function test_default_missile_operation_shares_default_pilot_and_numeric_copilot_claims(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000m1';
        $copilotSeat = '00000000-0000-0000-0000-0000000000m2';
        $this->writeCacheFiles(uuidToPathMap: [
            $seat => $this->writeFile('seat.xml', self::seatRecord($seat)),
            $copilotSeat => $this->writeFile('copilot-seat.xml', self::seatRecord($copilotSeat)),
        ]);
        $tail = substr($this->missileParts(), strpos($this->missileParts(), '<Part name="controller"'));
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="MissileController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="copilot"><ItemPort><ControllerDef controllableTags="copilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="1"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>'.$tail;
        $loadout = [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat], self::entry('copilot', 'Seat.UNDEFINED') + ['UUID' => $copilotSeat],
            self::entry('controller', 'MissileController.UNDEFINED'),
            self::entry('left', 'MissileLauncher.MissileRack') + ['Loadout' => [self::entry('missile_left', 'Missile.Missile')]],
            self::entry('right', 'MissileLauncher.MissileRack') + ['Loadout' => [self::entry('missile_right', 'Missile.Missile')]]];
        $expected = [
            ['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat'],
            ['Kind' => 'Crew', 'SeatPortId' => 'loadout.1', 'Name' => 'Copilot seat'],
        ];

        foreach ($this->positions($parts, $loadout, false) as $position) {
            self::assertSame($expected, $position['OperatingStations'] ?? []);
        }

        foreach ($this->positions(str_replace('controllableTags="copilotSeat"', 'controllableTags="CoPilot_Seat"', $parts), $loadout, false) as $position) {
            self::assertSame($expected, $position['OperatingStations'] ?? []);
        }

        $third = '<Part name="crew"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="1"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';

        foreach ([
            [str_replace('defaultPriority="1"', 'defaultPriority="0"', $parts), $loadout],
            [str_replace('defaultPriority="1"', 'defaultPriority="-2"', $parts), $loadout],
            [str_replace(' defaultPriority="1"', '', $parts), $loadout],
            [str_replace('defaultPriority="1"', 'defaultPriority="exclusive_control"', $parts), $loadout],
            [str_replace('defaultPriority="1"', 'defaultPriority="1" maxTargets="2"', $parts), $loadout],
            [str_replace('controllableTags="copilotSeat"', 'controllableTags="CoPilot_Seat_Other"', $parts), $loadout],
            [str_replace('<PriorityGroup itemType="MissileController"/>', '<PriorityGroup itemType="MissileController" defaultPriority="1"/>', substr($parts, 0, strpos($parts, '<Part name="copilot"'))).substr($parts, strpos($parts, '<Part name="copilot"')), $loadout],
            [$parts.$third, [...$loadout, self::entry('crew', 'Seat.Operator') + ['UUID' => $copilotSeat]]],
            [$parts.'<Part name="other"><ItemPort/></Part>', [...$loadout, self::entry('other', 'MissileController.UNDEFINED')]],
            [$parts, [...$loadout, self::entry('orphan', 'MissileController.UNDEFINED')]],
            [$parts, [...$loadout, self::entry('stow', 'Seat.UNDEFINED')]],
            [$parts.'<Part name="console"><ItemPort><ControllerDef><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="1"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>', [...$loadout, self::entry('console', 'SeatAccess.UNDEFINED')]],
        ] as $case => [$xml, $entries]) {
            foreach ($this->positions($xml, $entries, false) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position, 'missile-share rejection case '.$case);
            }
        }

        $disabled = '00000000-0000-0000-0000-0000000000m3';
        $this->writeCacheFiles(uuidToPathMap: [$disabled => $this->writeFile('seat-disabled.xml', self::seatRecord($disabled, false))]);

        foreach (['copilot', 'pilot'] as $index) {
            $seated = $loadout;
            $seated[$index === 'copilot' ? 1 : 0]['UUID'] = $disabled;

            foreach ($this->positions($parts, $seated, false) as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position);
            }
        }
    }

    public function test_redeemer_differently_tagged_pilot_controller_publishes_default_wing_guns(): void
    {
        $seat = '00000000-0000-0000-0000-0000000000r1';
        $this->writeCacheFiles(uuidToPathMap: [$seat => $this->writeFile('seat.xml', self::seatRecord($seat))]);
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="weapon_controller_pilot"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="pilot_controller"><ItemPort><ControllerDef controllableTags="weapon_controller_pilot"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="front_controller"><ItemPort><ControllerDef controllableTags="remote_front_turret"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret" defaultPriority="no_control"><tags tag="remote_front_turret"><Priority value="11"/></tags></PriorityGroup><PriorityGroup itemType="WeaponGun" defaultPriority="no_control"><tags tag="remote_front_turret"><Priority value="11"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="rear_controller"><ItemPort><ControllerDef controllableTags="remote_rear_turret"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret" defaultPriority="no_control"><tags tag="remote_rear_turret"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponGun" defaultPriority="no_control"><tags tag="remote_rear_turret"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="wing_right"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/></ItemPort></Part>
            <Part name="wing_left"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/></ItemPort></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot') + ['UUID' => $seat],
            self::entry('pilot_controller', 'WeaponController.UNDEFINED'), self::entry('front_controller', 'WeaponController.UNDEFINED'), self::entry('rear_controller', 'WeaponController.UNDEFINED'),
            self::entry('wing_right', 'Turret.GunTurret') + ['Loadout' => [self::entry('gun', 'WeaponGun.Gun')]],
            self::entry('wing_left', 'Turret.GunTurret') + ['Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        $positions = $this->positions($parts, $loadout, false);
        self::assertCount(2, $positions);

        foreach ($positions as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }

        $rivalSeat = '<Part name="crew"><ItemPort><ControllerDef controllableTags="front_seat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="weapon_controller_pilot"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        // A neutral seat with no WeaponController claim at all does not block the route.
        $neutral = $parts.'<Part name="crew"><ItemPort><ControllerDef controllableTags="crewSeat"/></ItemPort></Part>';

        foreach ($this->positions($neutral, [...$loadout, self::entry('crew', 'Seat.Operator') + ['UUID' => $seat]], false) as $position) {
            self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        }

        // Duplicate or malformed competing groups count as competitors unless they deny the tag outright.
        $duplicateGroups = $parts.'<Part name="crew"><ItemPort><ControllerDef controllableTags="crewSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"/><PriorityGroup itemType="WeaponController" defaultPriority="no_control"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>';
        $malformedHolder = str_replace('<PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="weapon_controller_pilot">', '<PriorityGroup itemType="WeaponController" defaultPriority="no_control" maxTargets="2"><tags tag="weapon_controller_pilot">', $parts);

        foreach ([
            [$duplicateGroups, [...$loadout, self::entry('crew', 'Seat.Operator') + ['UUID' => $seat]]],
            [$malformedHolder, $loadout],
        ] as $case => [$xml, $entries]) {
            $positions = $this->positions($xml, $entries, false);
            self::assertNotEmpty($positions, 'redeemer mutation rejection case '.$case);

            foreach ($positions as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position, 'redeemer mutation rejection case '.$case);
            }
        }

        foreach ([
            [str_replace('defaultWeaponGroup="1"', 'defaultWeaponGroup=""', $parts), $loadout],
            [str_replace(['<Part name="wing_right"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/>', '<Part name="wing_left"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="pilotSeat"/>'], ['<Part name="wing_right"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="other"/>', '<Part name="wing_left"><ItemPort defaultWeaponGroup="1"><ControllerDef controllableTags="other"/>'], $parts), $loadout],
            [str_replace('tag="weapon_controller_pilot"><Priority value="exclusive_control"/>', 'tag="weapon_controller_pilot"><Priority value="10"/>', $parts), $loadout],
            [str_replace('tag="weapon_controller_pilot"><Priority value="exclusive_control"/>', 'tag="weapon_controller_other"><Priority value="exclusive_control"/>', $parts), $loadout],
            [str_replace(['<PriorityGroup itemType="Turret" defaultPriority="no_control"><tags tag="remote_rear_turret"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponGun" defaultPriority="no_control"><tags tag="remote_rear_turret"><Priority value="exclusive_control"/></tags></PriorityGroup>'], ['<PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/>'], $parts), $loadout],
            [str_replace('tag="remote_rear_turret"><Priority value="exclusive_control"/>', 'tag="pilotSeat"><Priority value="11"/>', $parts), $loadout],
            [$parts.$rivalSeat, [...$loadout, self::entry('crew', 'Seat.Operator') + ['UUID' => $seat]]],
        ] as $case => [$xml, $entries]) {
            $positions = $this->positions($xml, $entries, false);
            self::assertNotEmpty($positions, 'redeemer rejection case '.$case);

            foreach ($positions as $position) {
                self::assertArrayNotHasKey('OperatingStations', $position, 'redeemer rejection case '.$case);
            }
        }

        // A duplicated hardpoint makes every binding ambiguous, so both wings stay unknown.
        foreach ($this->positions($parts, [...$loadout, $loadout[4]], false) as $position) {
            self::assertArrayNotHasKey('OperatingStations', $position);
        }

        // A gunless wing suppresses only its own position; the loaded wing still resolves.
        $gunless = $loadout;
        unset($gunless[4]['Loadout']);
        $positions = $this->positions($parts, $gunless, false);
        self::assertArrayNotHasKey('OperatingStations', $positions[0]);
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $positions[1]['OperatingStations'] ?? []);
        // An unresolved direct binding suppresses only its own mount; the clean wing still resolves.
        $direct = str_replace('<Part name="wing_right"><ItemPort defaultWeaponGroup="1">', '<Part name="wing_right"><ItemPort defaultWeaponGroup="1"><Controllers><Controller port="missing" active="1"/></Controllers>', $parts);
        $positions = $this->positions($direct, $loadout, false);
        self::assertArrayNotHasKey('OperatingStations', $positions[0]);
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $positions[1]['OperatingStations'] ?? []);
        $pdc = $loadout;
        $pdc[4]['Type'] = 'Turret.PDCTurret';
        // An autonomous mount suppresses only its own position; the human wing still resolves.
        $positions = $this->positions($parts, $pdc, false);
        self::assertNotEmpty($positions);
        self::assertArrayNotHasKey('OperatingStations', $positions[0]);
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $positions[1]['OperatingStations'] ?? []);
        $disabled = '00000000-0000-0000-0000-0000000000r2';
        $this->writeCacheFiles(uuidToPathMap: [$disabled => $this->writeFile('seat-disabled.xml', self::seatRecord($disabled, false))]);
        $seated = $loadout;
        $seated[0]['UUID'] = $disabled;

        foreach ($this->positions($parts, $seated, false) as $position) {
            self::assertArrayNotHasKey('OperatingStations', $position);
        }
    }

    public function test_freelancer_active_direct_bindings_require_unique_installed_seats(): void
    {
        $parts = '<Part name="pilot"><ItemPort/></Part><Part name="mount"><ItemPort><Controllers><Controller port="pilot" active="1" priority="0"/></Controllers></ItemPort></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('mount', 'WeaponGun.Gun')];
        $position = $this->positions($parts, $loadout)[0];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $position['OperatingStations'] ?? []);
        $emptyMount = $loadout;
        $emptyMount[1]['Type'] = 'Turret.Utility';
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $emptyMount)[0]);
        $tool = $loadout;
        $tool[1]['Type'] = 'TractorBeam.TractorBeam';
        self::assertSame(['Pilot'], array_column($this->positions($parts, $tool)[0]['OperatingStations'] ?? [], 'Kind'));
        $taggedPilot = $loadout;
        $taggedPilot[0]['Type'] = 'Seat.UNDEFINED';
        self::assertSame('Pilot', $this->positions(str_replace('<Part name="pilot"><ItemPort/>', '<Part name="pilot"><ItemPort><ControllerDef controllableTags="PiLoTsEaT"/></ItemPort>', $parts), $taggedPilot)[0]['OperatingStations'][0]['Kind']);

        foreach ([
            [str_replace('active="1"', 'active="0"', $parts), $loadout],
            [str_replace('port="pilot"', 'port="missing"', $parts), $loadout],
            [$parts, [...$loadout, self::entry('pilot', 'Seat.Pilot')]],
            [$parts.str_replace('name="mount"', 'name="other"', '<Part name="pilot"><ItemPort/></Part>'), $loadout],
            [$parts, [$loadout[1]]],
            [$parts, [...$loadout, self::entry('mount', 'WeaponGun.Gun')]],
        ] as [$xml, $entries]) {
            foreach ($this->positions($xml, $entries) as $unknown) {
                self::assertArrayNotHasKey('OperatingStations', $unknown);
            }
        }
    }

    public function test_carrack_excludes_pilot_downgrade_chain_but_keeps_exclusive_crew_station(): void
    {
        $parts = $this->chainParts();
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('crew', 'Seat.Operator') + ['PartDisplayName' => 'Right bridge seat'],
            self::entry('pilot_controller', 'WeaponController.UNDEFINED'), self::entry('crew_controller', 'WeaponController.UNDEFINED'), self::entry('mount', 'WeaponGun.Gun')];
        $position = $this->positions($parts, $loadout)[0];
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.1', 'Name' => 'Right bridge seat']], $position['OperatingStations'] ?? []);
        $loadout[1]['PartDisplayName'] = 'port_NameSeatCoPilot';
        $loadout[1]['HardpointName'] = 'crew';
        $loadout[1]['Name'] = 'TRGT. STATUS';
        self::assertSame('Crew seat', $this->positions($parts, $loadout)[0]['OperatingStations'][0]['Name']);
        $loadout[4]['Type'] = 'TractorBeam.UNDEFINED';
        self::assertArrayNotHasKey('OperatingStations', $this->positions(str_replace('itemType="WeaponGun"', 'itemType="TractorBeam"', $parts), $loadout)[0]);
        $loadout[4]['Type'] = 'WeaponGun.Gun';

        foreach ([
            str_replace('value="exclusive_control"', 'value="50"', $parts),
            str_replace('controllableTags="crew"', 'controllableTags="unrelated"', $parts),
            str_replace('tag="crew"', 'tag="other"', $parts),
            $parts.'<Part name="crew_controller"><ItemPort/></Part>',
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }
    }

    public function test_exclusive_remote_gun_chain_uses_controller_and_mount_tags_separately(): void
    {
        $parts = '<Part name="crew"><ItemPort><ControllerDef controllableTags="bridgeSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="bridgeController"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="bridgeController"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret" defaultPriority="no_control"><tags tag="remoteMount"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponGun" defaultPriority="no_control"><tags tag="remoteMount"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="remoteMount"/></ItemPort></Part>';
        $loadout = [self::entry('crew', 'Seat.Operator') + ['PartDisplayName' => 'Rear bridge seat'],
            self::entry('controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.GunTurret') + ['Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Rear bridge seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('value="exclusive_control"', 'value="100"', $parts),
            str_replace('itemType="WeaponGun"', 'itemType="Display"', $parts),
            str_replace('itemType="Turret"', 'itemType="Display"', $parts),
            str_replace('<tags tag="bridgeController">', '<tags tag="other">', $parts),
            str_replace('controllableTags="remoteMount"', 'controllableTags="other"', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }

        $otherSeat = str_replace('name="crew"', 'name="other"', substr($parts, 0, strpos($parts, '</Part>') + 7));
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts.$otherSeat, [...$loadout, self::entry('other', 'Seat.Operator')])[0]);
        unset($loadout[2]['Loadout']);
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $loadout)[0]);
    }

    public function test_exclusive_missile_controller_chain_resolves_a_tagged_loaded_rack(): void
    {
        $parts = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="MissileController" defaultPriority="no_control"><tags tag="missileController"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="missileController"><UsableDef><PriorityGroups><PriorityGroup itemType="MissileLauncher" defaultPriority="no_control"><tags tag="missileMount"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="Missile" defaultPriority="no_control"><tags tag="missileMount"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="missileMount"/></ItemPort></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'MissileController.UNDEFINED'),
            self::entry('mount', 'MissileLauncher.MissileRack') + ['Loadout' => [self::entry('missile', 'Missile.Missile')]]];
        self::assertSame([['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('value="exclusive_control"', 'value="10"', $parts),
            str_replace('itemType="MissileLauncher"', 'itemType="Turret"', $parts),
            str_replace('itemType="Missile"', 'itemType="WeaponGun"', $parts),
            str_replace('controllableTags="missileMount"', 'controllableTags="other"', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }
    }

    public function test_reclaimer_salvage_needs_side_specific_salvage_grant_in_addition_to_aim_chain(): void
    {
        $parts = str_replace('<PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="crew">', '<PriorityGroup itemType="SalvageController" defaultPriority="no_control"><tags tag="crew"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="crew">', $this->chainParts());
        $parts = str_replace('itemType="SalvageHead"', 'itemType="Turret"', $parts);
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('crew', 'Seat.Operator') + ['PortDisplayName' => 'Left salvage station'],
            self::entry('pilot_controller', 'WeaponController.UNDEFINED'), self::entry('crew_controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.Utility') + ['Loadout' => [self::entry('head', 'SalvageHead.UNDEFINED')]]];
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.1', 'Name' => 'Left salvage station']], $this->positions($parts, $loadout)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('itemType="SalvageController"', 'itemType="ShieldController"', $parts),
            str_replace('itemType="SalvageController" defaultPriority="no_control"><tags tag="crew"', 'itemType="SalvageController" defaultPriority="no_control"><tags tag="right"', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }
    }

    public function test_integrated_seat_requires_self_grants_for_installed_weapon_operation(): void
    {
        $uuid = '30e5fb7f-35c1-47af-92b9-0ebca8200464';
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $this->writeFile('Game2/libs/foundry/records/turret.xml', '<EntityClassDefinition.Turret __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SCItemSeatParams/></Components></EntityClassDefinition.Turret>')]);
        $loadout = [self::entry('mount', 'TurretBase.MannedTurret') + ['UUID' => $uuid, 'Loadout' => [self::entry('gun', 'WeaponGun.Gun')]]];
        $parts = '<Part name="mount"><ItemPort><ControllerDef controllableTags="turret"><UserDef><PriorityGroups><PriorityGroup itemType="TurretBase"><tags tag="turret"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef><UsableDef><PriorityGroups><PriorityGroup itemType="TurretBase"><tags tag="turret"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="WeaponGun"><tags tag="turret"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>';
        $position = $this->positions($parts, $loadout, false)[0];
        self::assertSame([['Kind' => 'InTurret', 'SeatPortId' => 'loadout.0', 'Name' => 'Seat inside turret']], $position['OperatingStations'] ?? []);
        $numericUser = str_replace('<UserDef><PriorityGroups><PriorityGroup itemType="TurretBase"><tags tag="turret"><Priority value="exclusive_control"/></tags></PriorityGroup>', '<UserDef><PriorityGroups><PriorityGroup itemType="TurretBase" defaultPriority="10"/>', $parts);
        self::assertSame([['Kind' => 'InTurret', 'SeatPortId' => 'loadout.0', 'Name' => 'Seat inside turret']], $this->positions($numericUser, $loadout, false)[0]['OperatingStations'] ?? []);
        self::assertArrayNotHasKey('OperatingStations', $this->positions(str_replace('defaultPriority="10"', 'defaultPriority="no_control"', $numericUser), $loadout, false)[0]);
        $otherUser = str_replace('<UserDef><PriorityGroups><PriorityGroup itemType="TurretBase"><tags tag="turret">', '<UserDef><PriorityGroups><PriorityGroup itemType="TurretBase"><tags tag="other">', $parts);
        self::assertArrayNotHasKey('OperatingStations', $this->positions($otherUser, $loadout, false)[0]);

        foreach (['', str_replace('itemType="WeaponGun"', 'itemType="Display"', $parts), str_replace('value="exclusive_control"', 'value="observe_only"', $parts)] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout, false)[0]);
        }

        $tractor = $loadout;
        $tractor[0]['Loadout'][0]['Type'] = 'TractorBeam.UNDEFINED';
        $tractorParts = str_replace('itemType="WeaponGun"', 'itemType="TractorBeam"', $parts);
        self::assertSame([['Kind' => 'InTurret', 'SeatPortId' => 'loadout.0', 'Name' => 'Seat inside turret']], $this->positions($tractorParts, $tractor, false)[0]['OperatingStations'] ?? []);
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $tractor, false)[0]);
        unset($loadout[0]['Loadout']);
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $loadout, false)[0]);
    }

    public function test_integrated_mining_cab_requires_exclusive_controller_and_laser_use(): void
    {
        $uuid = 'aa291be7-91cd-4d53-9902-195c0b0bcc2c';
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $this->writeFile('mining-cab.xml', '<EntityClassDefinition.Cab __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SCItemSeatParams/></Components></EntityClassDefinition.Cab>')]);
        $parts = '<Part name="cab"><ItemPort><ControllerDef controllableTags="miningCab"><UserDef><PriorityGroups><PriorityGroup itemType="UtilityTurret"><tags tag="miningCab"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="MiningController"><tags tag="miningCab"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef><UsableDef><PriorityGroups><PriorityGroup itemType="UtilityTurret"><tags tag="miningCab"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef controllableTags="miningCab"><UsableDef><PriorityGroups><PriorityGroup itemType="WeaponMining"><tags tag="miningCab"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>';
        $loadout = [self::entry('cab', 'UtilityTurret.MiningTurret') + ['UUID' => $uuid, 'Loadout' => [self::entry('laser', 'WeaponMining.Gun')]],
            self::entry('controller', 'MiningController.UNDEFINED')];
        self::assertSame([['Kind' => 'InTurret', 'SeatPortId' => 'loadout.0', 'Name' => 'Seat inside turret']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('itemType="WeaponMining"', 'itemType="MiningModifier"', $parts),
            str_replace('itemType="MiningController"', 'itemType="WeaponController"', $parts),
            str_replace('value="exclusive_control"', 'value="10"', $parts),
            str_replace('name="controller"', 'name="missing"', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout, false)[0]);
        }

        $loadout[0]['Loadout'][0]['Type'] = 'SalvageHead.UNDEFINED';
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts, $loadout, false)[0]);
    }

    public function test_default_weapon_group_is_not_a_binding_and_conflicting_routes_suppress_default_fire(): void
    {
        $base = '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"/><PriorityGroup itemType="WeaponGun"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort defaultWeaponGroup="1"/></Part>';
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'WeaponController.UNDEFINED'), self::entry('mount', 'WeaponGun.Gun')];

        foreach ([
            str_replace('defaultWeaponGroup="1"', '', $base),
            str_replace('defaultWeaponGroup="1"', 'defaultWeaponGroup=""', $base),
            str_replace('itemType="WeaponController"/', 'itemType="WeaponController" defaultPriority="10"/', $base),
            str_replace('defaultWeaponGroup="1"/', 'defaultWeaponGroup="1"><Controllers><Controller port="missing" active="1"/></Controllers></ItemPort', $base),
            str_replace('defaultWeaponGroup="1"/', 'defaultWeaponGroup="1"><ControllerDef controllableTags="dedicated"/></ItemPort', $base),
            str_replace('itemType="WeaponGun"/', 'itemType="WeaponGun" defaultPriority="no_control"/', $base),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout)[0]);
        }

        self::assertArrayNotHasKey('OperatingStations', $this->positions($base.'<Part name="other"><ItemPort><ControllerDef controllableTags="dedicated"/></ItemPort></Part>', [...$loadout, self::entry('other', 'WeaponController.UNDEFINED')])[0]);
        $loadout[2]['Type'] = 'TractorBeam.UNDEFINED';
        self::assertArrayNotHasKey('OperatingStations', $this->positions($base, $loadout)[0]);
    }

    public function test_module_aim_controller_routes_do_not_publish_tool_operation(): void
    {
        $uuid = 'c3af722a-daf9-4cf8-9721-6d0bc6781297';
        $path = $this->writeFile('Game2/libs/foundry/records/module.xml', '<EntityClassDefinition.Module __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SItemPortContainerComponentParams><Ports>
            <SItemPortDef Name="crew" controllableTag="tool"><control><SCItemControlSeatParams><userControl><SCItemControllableGroupParams><priorityGroups><SCItemPriorityGroupParam itemType="WeaponController" defaultPriority="255"><tags><SCItemPriorityTagParam tag="tool" priority="253"/></tags></SCItemPriorityGroupParam></priorityGroups></SCItemControllableGroupParams></userControl></SCItemControlSeatParams></control></SItemPortDef>
            <SItemPortDef Name="controller" controllableTag="tool"><control><SCItemControlPriorities><itemControlPriorities><SCItemControlPriorityTable itemType="Turret"><tagPrioritiesTable><SCItemControlPriorityTag tag="tool"/></tagPrioritiesTable></SCItemControlPriorityTable></itemControlPriorities></SCItemControlPriorities></control></SItemPortDef>
            <SItemPortDef Name="mount" controllableTag="tool"/>
            </Ports></SItemPortContainerComponentParams></Components></EntityClassDefinition.Module>');
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $path]);
        $positions = $this->positions('', [self::entry('module', 'Module.UNDEFINED') + ['UUID' => $uuid, 'Loadout' => [
            self::entry('crew', 'Seat.UNDEFINED'), self::entry('controller', 'WeaponController.UNDEFINED'),
            self::entry('mount', 'Turret.Utility') + ['Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED')]],
        ]]], false);
        self::assertCount(1, $positions);
        self::assertSame('loadout.0.loadout.2', $positions[0]['PortId']);
        self::assertArrayNotHasKey('OperatingStations', $positions[0]);
        self::assertArrayNotHasKey('ControlRoutes', $positions[0]);
    }

    public function test_multiple_verified_stations_are_deduplicated_by_installed_seat(): void
    {
        $parts = str_replace('<Part name="mount"><ItemPort>', '<Part name="mount"><ItemPort><Controllers><Controller port="pilot" active="1"/><Controller port="crew" active="1"/><Controller port="crew" active="1"/></Controllers>', $this->chainParts());
        $loadout = [self::entry('pilot', 'Seat.Pilot'), self::entry('crew', 'Seat.Operator') + ['PortDisplayName' => 'Crew station'],
            self::entry('pilot_controller', 'WeaponController.UNDEFINED'), self::entry('crew_controller', 'WeaponController.UNDEFINED'), self::entry('mount', 'WeaponGun.Gun')];
        self::assertSame([
            ['Kind' => 'Crew', 'SeatPortId' => 'loadout.1', 'Name' => 'Crew station'],
            ['Kind' => 'Pilot', 'SeatPortId' => 'loadout.0', 'Name' => 'Pilot seat'],
        ], $this->positions($parts, $loadout)[0]['OperatingStations']);
    }

    public function test_hull_c_conditional_tractor_use_publishes_the_crane_seat(): void
    {
        $uuid = 'f61dba32-ce22-49b3-947d-318d319ce1f9';
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $this->writeFile('tractor.xml', '<EntityClassDefinition.Tractor __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SCItemTurretParams><remoteTurret><SCItemTurretRemoteParams turretOnlyUsableInRemoteCamera="1"/></remoteTurret></SCItemTurretParams></Components></EntityClassDefinition.Tractor>')]);
        $loadout = [self::entry('crane', 'Seat.Operator') + ['PartDisplayName' => 'Crane seat'],
            self::entry('aim', 'WeaponController.UNDEFINED'), self::entry('tool', 'SalvageController.UNDEFINED'),
            self::entry('mount', 'Turret.Utility') + ['UUID' => $uuid, 'Loadout' => [self::entry('beam', 'TractorBeam.UNDEFINED')]]];
        $parts = '<Part name="crane"><ItemPort><ControllerDef controllableTags="tractorSeat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController"><tags tag="tractorSeat"><Priority value="exclusive_control"/></tags></PriorityGroup><PriorityGroup itemType="SalvageController"><tags tag="tractorSeat"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="aim"><ItemPort><ControllerDef controllableTags="tractorSeat"><UsableDef><PriorityGroups><PriorityGroup itemType="Turret"><tags tag="tractorSeat"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="tool"><ItemPort><ControllerDef controllableTags="tractorSeat"><UsableDef><ConditionalPriorities><ConditionalPriority itemType="TractorBeam" defaultPriority="no_control" priorityWhenConditionMet="exclusive_control"><Condition_And><Condition_Tag tag="tractorSeat"/><Condition_AllowedByRemoteTurretView/></Condition_And></ConditionalPriority></ConditionalPriorities></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="tractorSeat"/></ItemPort></Part>';
        self::assertSame([['Kind' => 'Crew', 'SeatPortId' => 'loadout.0', 'Name' => 'Crane seat']], $this->positions($parts, $loadout, false)[0]['OperatingStations'] ?? []);

        foreach ([
            str_replace('priorityWhenConditionMet="exclusive_control"', 'priorityWhenConditionMet="10"', $parts),
            str_replace('defaultPriority="no_control"', 'defaultPriority="observe_only"', $parts),
            str_replace('Condition_And', 'Condition_Or', $parts),
            str_replace('<Condition_AllowedByRemoteTurretView/>', '', $parts),
            str_replace('<Condition_AllowedByRemoteTurretView/>', '<Condition_AllowedByRemoteTurretView><TurretTag tag="tractorSeat"/></Condition_AllowedByRemoteTurretView>', $parts),
            str_replace('<Condition_Tag tag="tractorSeat"/>', '<Condition_Tag tag="other"/>', $parts),
            str_replace('<Condition_Tag tag="tractorSeat"/>', '<Condition_Tag tag="tractorSeat"/><Condition_Tag tag="tractorSeat"/>', $parts),
            str_replace('itemType="SalvageController"', 'itemType="ShieldController"', $parts),
            str_replace('itemType="Turret"', 'itemType="Display"', $parts),
            str_replace('itemType="TractorBeam"', 'itemType="WeaponGun"', $parts),
            str_replace('value="exclusive_control"', 'value="10"', $parts),
        ] as $xml) {
            self::assertArrayNotHasKey('OperatingStations', $this->positions($xml, $loadout, false)[0]);
        }

        $otherSeat = str_replace('name="crane"', 'name="other"', substr($parts, 0, strpos($parts, '</Part>') + 7));
        $contenders = [...$loadout, self::entry('other', 'Seat.Operator')];
        self::assertArrayNotHasKey('OperatingStations', $this->positions($parts.$otherSeat, $contenders, false)[0]);
    }

    private function missileParts(): string
    {
        return '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotSeat"><UserDef><PriorityGroups><PriorityGroup itemType="MissileController"/></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="controller"><ItemPort><ControllerDef><UsableDef><PriorityGroups><PriorityGroup itemType="MissileLauncher"/><PriorityGroup itemType="Missile"/></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="left"><ItemPort/></Part><Part name="right"><ItemPort/></Part>';
    }

    private function missileLoadout(): array
    {
        return [self::entry('pilot', 'Seat.Pilot'), self::entry('controller', 'MissileController.UNDEFINED'),
            self::entry('left', 'MissileLauncher.MissileRack') + ['Loadout' => [self::entry('missile_left', 'Missile.Missile')]],
            self::entry('right', 'MissileLauncher.MissileRack') + ['Loadout' => [self::entry('missile_right', 'Missile.Missile')]]];
    }

    private function chainParts(): string
    {
        return '<Part name="pilot"><ItemPort><ControllerDef controllableTags="pilotseat"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="pilot_weapons"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="crew"><ItemPort><ControllerDef controllableTags="crew"><UserDef><PriorityGroups><PriorityGroup itemType="WeaponController" defaultPriority="no_control"><tags tag="crew"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UserDef></ControllerDef></ItemPort></Part>
            <Part name="pilot_controller"><ItemPort><ControllerDef controllableTags="pilot_weapons"><UsableDef><PriorityGroups><PriorityGroup itemType="WeaponGun" defaultPriority="40"><tags tag="crew"><Priority value="25"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="crew_controller"><ItemPort><ControllerDef controllableTags="crew"><UsableDef><PriorityGroups><PriorityGroup itemType="WeaponGun" defaultPriority="100"/><PriorityGroup itemType="SalvageHead" defaultPriority="no_control"><tags tag="crew"><Priority value="exclusive_control"/></tags></PriorityGroup></PriorityGroups></UsableDef></ControllerDef></ItemPort></Part>
            <Part name="mount"><ItemPort><ControllerDef controllableTags="crew"/></ItemPort></Part>';
    }

    private static function seatRecord(string $uuid, bool $playerUsable = true): string
    {
        $slot = $playerUsable
            ? '<alignmentSlots><OperatorSeatAlignmentSlot available="1" canPlayerUse="1"/></alignmentSlots>'
            : '<alignmentSlots><OperatorSeatAlignmentSlot available="0" canPlayerUse="1"/></alignmentSlots>';

        return '<EntityClassDefinition.Seat __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SCItemSeatParams/><SActorUsableParams><useSlots><UsableUseSlot available="1"><useChannelInstances><UsableUseChannelInstance isEnabled="1">'.$slot.'</UsableUseChannelInstance></useChannelInstances></UsableUseSlot></useSlots></SActorUsableParams></Components></EntityClassDefinition.Seat>';
    }

    private static function itemRecord(string $uuid): string
    {
        return '<EntityClassDefinition.Item __type="EntityClassDefinition" __ref="'.$uuid.'"/>';
    }

    private static function empRecord(string $uuid): string
    {
        return '<EntityClassDefinition.EMP __type="EntityClassDefinition" __ref="'.$uuid.'"><Components><SCItemEMPParams chargeTime="10" unleashEnergyCost="1"/></Components></EntityClassDefinition.EMP>';
    }

    private static function entry(string $name, string $type): array
    {
        return ['HardpointName' => $name, 'Type' => $type, 'MinSize' => 1, 'MaxSize' => 3];
    }

    private function identifyChildren(array &$entry): void
    {
        foreach ($entry['Loadout'] ?? [] as $i => $child) {
            $child['PortId'] = $entry['PortId'].".loadout.$i";
            $this->identifyChildren($child);
            $entry['Loadout'][$i] = $child;
        }
    }

    private function sourcePort(array $entry): array
    {
        return ['Types' => [$entry['Type']], 'InstalledItem' => ['stdItem' => [
            'Type' => $entry['Type'], 'ClassName' => $entry['ClassName'] ?? '',
            'Ports' => array_map($this->sourcePort(...), $entry['Loadout'] ?? []),
        ]]];
    }

    private function positions(string $parts, array $loadout, bool $writeCache = true): array
    {
        if ($writeCache) {
            $this->writeCacheFiles();
        }

        $this->initializeMinimalItemServices();
        $vehicle = new Vehicle;
        $vehicle->load($this->writeFile('vehicle.xml', '<Vehicle><Parts>'.$parts.'</Parts></Vehicle>'));
        $ports = [];

        foreach ($loadout as $i => &$entry) {
            $entry['PortId'] = "loadout.$i";
            $this->identifyChildren($entry);
            $ports[] = $this->sourcePort($entry);
        }

        unset($entry);
        [$positions] = (new MountingPositionBuilder)->build($loadout, $ports, (new OperatingStationResolver)->resolve($vehicle, $loadout));

        return $positions;
    }
}
