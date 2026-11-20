<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\TurretControlMapper;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ModuleControlRoutesTest extends ScDataTestCase
{
    public function test_explicit_grants_stay_in_the_installed_module_and_conditional_targets_do_not_grant_routes(): void
    {
        $uuid = 'c3af722a-daf9-4cf8-9721-6d0bc6781297';
        $path = $this->writeFile('Game2/libs/foundry/records/module.xml', <<<XML
            <EntityClassDefinition.Module __type="EntityClassDefinition" __ref="$uuid">
                <Components><SItemPortContainerComponentParams><Ports>
                    <SItemPortDef Name="console" controllableTag="Turret_01">
                        <control><SCItemControlSeatParams><userControl><SCItemControllableGroupParams><priorityGroups>
                            <SCItemPriorityGroupParam itemType="WeaponController" defaultPriority="255">
                                <tags><SCItemPriorityTagParam tag="Turret_01" priority="253" /></tags>
                            </SCItemPriorityGroupParam>
                        </priorityGroups></SCItemControllableGroupParams></userControl></SCItemControlSeatParams></control>
                    </SItemPortDef>
                    <SItemPortDef Name="controller" controllableTag="Turret_01">
                        <control><SCItemControlPriorities><itemControlPriorities>
                            <SCItemControlPriorityTable itemType="Turret"><tagPrioritiesTable>
                                <SCItemControlPriorityTag tag="Turret_01" />
                                <SCItemControlPriorityTag tag="Turret_02"><condition /></SCItemControlPriorityTag>
                            </tagPrioritiesTable></SCItemControlPriorityTable>
                        </itemControlPriorities></SCItemControlPriorities></control>
                    </SItemPortDef>
                    <SItemPortDef Name="tractor_left" controllableTag="Turret_01" />
                    <SItemPortDef Name="tractor_right" controllableTag="Turret_02" />
                </Ports></SItemPortContainerComponentParams></Components>
            </EntityClassDefinition.Module>
            XML);
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $path]);
        $this->initializeMinimalItemServices();

        $module = static fn (string $id): array => [
            'PortId' => $id, 'UUID' => $uuid, 'Type' => 'Module.UNDEFINED',
            'Loadout' => [
                ['PortId' => "$id.loadout.0", 'HardpointName' => 'console', 'Type' => 'Seat.UNDEFINED', 'UUID' => 'seat'],
                ['PortId' => "$id.loadout.1", 'HardpointName' => 'controller', 'Type' => 'WeaponController.UNDEFINED', 'UUID' => 'controller'],
                ['PortId' => "$id.loadout.2", 'HardpointName' => 'tractor_left', 'Type' => 'Turret.Utility', 'UUID' => 'turret'],
                ['PortId' => "$id.loadout.3", 'HardpointName' => 'tractor_right', 'Type' => 'Turret.Utility', 'UUID' => 'turret'],
            ],
        ];
        self::assertSame([
            'loadout.0.loadout.2' => [[
                'Kind' => 'Controller', 'Operator' => 'Crew', 'SeatPortId' => 'loadout.0.loadout.0',
                'ControllerPortId' => 'loadout.0.loadout.1', 'Control' => 'Aim',
            ]],
            'loadout.1.loadout.2' => [[
                'Kind' => 'Controller', 'Operator' => 'Crew', 'SeatPortId' => 'loadout.1.loadout.0',
                'ControllerPortId' => 'loadout.1.loadout.1', 'Control' => 'Aim',
            ]],
        ], (new TurretControlMapper)->getInstalledModuleRoutes([$module('loadout.0'), $module('loadout.1')]));
    }
}
