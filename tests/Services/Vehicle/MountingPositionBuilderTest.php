<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\Vehicle\MountingPositionBuilder;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class MountingPositionBuilderTest extends ScDataTestCase
{
    public function test_module_ports_own_their_payload_even_when_the_parent_accepts_a_turret(): void
    {
        $moduleId = 'loadout.0';
        $rackId = "$moduleId.loadout.0";
        $bombId = "$rackId.loadout.0";
        $bomb = $this->entry($bombId, 'bomb', 'Bomb.Utility', 'Bomb');
        $rack = $this->entry($rackId, 'rack', 'BombLauncher.BombRack', 'Rack', ['Loadout' => [$bomb]]);
        $loadout = [$this->entry($moduleId, 'module', 'Container.Cargo', 'Module', ['Loadout' => [$rack]])];
        $bombPort = $this->port(['Bomb.Utility'], ['stdItem' => ['Type' => 'Bomb.Utility']], null);
        $rackPort = $this->port(['BombLauncher.BombRack'], ['stdItem' => ['Type' => 'BombLauncher.BombRack', 'Ports' => [$bombPort]]], null);
        $modulePort = $this->port(['Container.CargoGrid', 'TurretBase.MannedTurret'], ['stdItem' => ['Type' => 'Container.Cargo', 'Ports' => [$rackPort]]], 'Manned turrets');

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, [$modulePort], [], []);

        self::assertSame([], $issues);
        self::assertCount(2, $positions);
        $byId = array_column($positions, null, 'PortId');
        self::assertNull($byId[$moduleId]['MountType']);
        self::assertNull($byId[$moduleId]['TurretRole']);
        self::assertSame([], $byId[$moduleId]['Payload']);
        self::assertSame('Rack', $byId[$rackId]['MountType']);
        self::assertSame('Rack', $loadout[0]['Loadout'][0]['MountType']);
        self::assertSame([$bombId], array_column($byId[$rackId]['Payload'], 'PortId'));
    }

    public function test_empty_remote_housing_is_classified_without_inventing_a_route(): void
    {
        $uuid = '30e5fb7f-35c1-47af-92b9-0ebca8200464';
        $path = $this->writeFile('Game2/libs/foundry/records/housing.xml', <<<XML
            <EntityClassDefinition.Housing __type="EntityClassDefinition" __ref="$uuid">
                <Components>
                    <SCItemTurretParams>
                        <remoteTurret><SCItemTurretRemoteParams /></remoteTurret>
                        <movementList>
                            <SCItemTurretJointMovementParams jointName="rotate">
                                <yawAxis><SCItemTurretJointMovementAxisParams /></yawAxis>
                            </SCItemTurretJointMovementParams>
                            <SCItemTurretJointMovementParams jointName="pivot">
                                <pitchAxis><SCItemTurretJointMovementAxisParams /></pitchAxis>
                            </SCItemTurretJointMovementParams>
                        </movementList>
                    </SCItemTurretParams>
                </Components>
            </EntityClassDefinition.Housing>
            XML);
        $this->writeCacheFiles(uuidToPathMap: [$uuid => $path]);
        $this->initializeMinimalItemServices();
        $type = 'Turret.GunTurret';
        $loadout = [$this->entry('loadout.0', 'housing', $type, 'Housing') + ['UUID' => $uuid]];
        $port = $this->port([$type], ['stdItem' => ['Type' => $type, 'Ports' => []]], null);

        [[$position]] = (new MountingPositionBuilder)->build($loadout, [$port], [], []);

        self::assertSame('Turret', $position['MountType']);
        self::assertSame('Remote', $position['TurretRole']);
        self::assertSame([], $position['Payload']);
        self::assertArrayNotHasKey('ControlRoutes', $position);
    }

    public function test_legacy_bucket_membership_is_carried_with_stable_ordinal(): void
    {
        $loadout = [$this->entry('loadout.0', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'turret_top'])];
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], ['stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret', 'Size' => 5, 'Ports' => []]], 'Manned turrets')];
        $legacy = ['MannedTurrets' => [
            ['HardpointName' => 'turret_top', 'PartName' => 'turret_top', 'TurretClassName' => 'TURR_TOP', 'Size' => 5],
        ]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertSame([], $issues);
        self::assertCount(1, $positions);
        self::assertSame('MannedTurrets', $positions[0]['LegacyBucket']);
        self::assertSame(0, $positions[0]['LegacyOrdinal']);
        self::assertArrayNotHasKey('LegacySize', $positions[0], 'derivable size must not be duplicated');
    }

    public function test_vacant_legacy_member_keeps_membership_without_classification(): void
    {
        $loadout = [$this->entry('loadout.1', 'hardpoint_rear_turret_tail', null, null, ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'hardpoint_rear_turret_tail'])];
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], null, 'Manned turrets')];
        $legacy = ['MannedTurrets' => [
            ['HardpointName' => 'hardpoint_rear_turret_tail', 'PartName' => 'hardpoint_rear_turret_tail', 'TurretClassName' => null, 'Size' => 5],
        ]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertSame([], $issues);
        self::assertFalse($positions[0]['Occupied']);
        self::assertNull($positions[0]['MountType']);
        self::assertNull($positions[0]['TurretRole']);
        self::assertSame('MannedTurrets', $positions[0]['LegacyBucket']);
        self::assertSame(0, $positions[0]['LegacyOrdinal']);
    }

    public function test_legacy_size_is_emitted_only_when_not_derivable(): void
    {
        // Installed adapter declares size 3 on a size-2 port, like Mount_Gimbal_S3 under the Vanguard nose turret.
        $adapterPort = ['PortName' => 'turret_left', 'Types' => ['Turret.GunTurret'], 'MinSize' => 2, 'MaxSize' => 2,
            'InstalledItem' => ['stdItem' => ['ClassName' => 'Mount_Gimbal_S3', 'Type' => 'Turret.GunTurret', 'Size' => 3, 'Ports' => []]]];
        $turretPort = $this->port(['TurretBase.MannedTurret'], ['stdItem' => [
            'ClassName' => 'TURR_NOSE', 'Type' => 'TurretBase.MannedTurret', 'Size' => 6, 'Ports' => [$adapterPort],
        ]], 'Manned turrets');
        $loadout = [
            $this->entry('loadout.0', 'hardpoint_turret', 'TurretBase.MannedTurret', 'TURR_NOSE', ['MinSize' => 6, 'MaxSize' => 6, 'PartName' => 'hardpoint_turret', 'Loadout' => [
                $this->entry('loadout.0.loadout.0', 'turret_left', 'Turret.GunTurret', 'Mount_Gimbal_S3', ['MinSize' => 2, 'MaxSize' => 2]),
            ]]),
        ];
        $legacy = ['MannedTurrets' => [
            ['HardpointName' => 'hardpoint_turret', 'PartName' => 'hardpoint_turret', 'TurretClassName' => 'TURR_NOSE', 'Size' => 6],
        ]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, [$turretPort], [], [], $legacy);

        self::assertSame([], $issues);
        self::assertArrayNotHasKey('LegacySize', $positions[0], 'a derivable legacy size must not be duplicated');

        // A legacy size no position field can reproduce is carried explicitly.
        $legacy['MannedTurrets'][0]['Size'] = 9;
        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, [$turretPort], [], [], $legacy);
        self::assertSame([], $issues);
        self::assertSame(9, $positions[0]['LegacySize']);
    }

    public function test_legacy_size_uses_the_linked_loadout_size(): void
    {
        $loadout = [$this->entry('loadout.0', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 2, 'MaxSize' => 2])];
        $loadout[0]['InstalledSize'] = 3;
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], ['Size' => 3, 'stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret']], 'Manned turrets')];
        $legacy = ['MannedTurrets' => [['HardpointName' => 'turret_top', 'TurretClassName' => 'TURR_TOP', 'Size' => 3]]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertSame([], $issues);
        self::assertNull($positions[0]['InstalledSize']);
        self::assertArrayNotHasKey('LegacySize', $positions[0]);
    }

    public function test_ambiguous_identity_reports_collision_instead_of_attribution(): void
    {
        $loadout = [
            $this->entry('loadout.0', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'turret_top']),
            $this->entry('loadout.1', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'turret_top']),
        ];
        $sourcePorts = [
            $this->port(['TurretBase.MannedTurret'], ['stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret', 'Size' => 5, 'Ports' => []]], 'Manned turrets'),
            $this->port(['TurretBase.MannedTurret'], ['stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret', 'Size' => 5, 'Ports' => []]], 'Manned turrets'),
        ];
        $legacy = ['MannedTurrets' => [
            ['HardpointName' => 'turret_top', 'PartName' => 'turret_top', 'TurretClassName' => 'TURR_TOP', 'Size' => 5],
        ]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertSame([[
            'Reason' => 'UnresolvedLegacyTurret',
            'Bucket' => 'MannedTurrets',
            'Ordinal' => 0,
            'HardpointName' => 'turret_top',
            'PortIds' => ['loadout.0', 'loadout.1'],
        ]], $issues);
        foreach ($positions as $position) {
            self::assertArrayNotHasKey('LegacyBucket', $position);
        }
    }

    public function test_unmatched_legacy_turret_reports_issue(): void
    {
        $loadout = [$this->entry('loadout.0', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'turret_top'])];
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], ['stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret', 'Size' => 5, 'Ports' => []]], 'Manned turrets')];
        $legacy = ['RemoteTurrets' => [
            ['HardpointName' => 'turret_tail', 'PartName' => 'turret_tail', 'TurretClassName' => 'TURR_TAIL', 'Size' => 2],
        ]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertSame([[
            'Reason' => 'UnresolvedLegacyTurret',
            'Bucket' => 'RemoteTurrets',
            'Ordinal' => 0,
            'HardpointName' => 'turret_tail',
            'PortIds' => [],
        ]], $issues);
        self::assertArrayNotHasKey('LegacyBucket', $positions[0]);
    }

    public function test_name_only_legacy_match_is_rejected_even_when_unique(): void
    {
        $loadout = [$this->entry('loadout.0', 'turret_top', null, null)];
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], null, 'Manned turrets')];
        $legacy = ['MannedTurrets' => [['HardpointName' => 'turret_top', 'Size' => 0]]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertArrayNotHasKey('LegacyBucket', $positions[0]);
        self::assertSame([[
            'Reason' => 'UnresolvedLegacyTurret',
            'Bucket' => 'MannedTurrets',
            'Ordinal' => 0,
            'HardpointName' => 'turret_top',
            'PortIds' => ['loadout.0'],
        ]], $issues);
    }

    public function test_double_claimed_position_is_rejected(): void
    {
        $loadout = [$this->entry('loadout.0', 'turret_top', 'TurretBase.MannedTurret', 'TURR_TOP', ['MinSize' => 5, 'MaxSize' => 5, 'PartName' => 'turret_top'])];
        $sourcePorts = [$this->port(['TurretBase.MannedTurret'], ['stdItem' => ['ClassName' => 'TURR_TOP', 'Type' => 'TurretBase.MannedTurret', 'Size' => 5, 'Ports' => []]], 'Manned turrets')];
        $row = ['HardpointName' => 'turret_top', 'PartName' => 'turret_top', 'TurretClassName' => 'TURR_TOP', 'Size' => 5];
        $legacy = ['MannedTurrets' => [$row], 'RemoteTurrets' => [$row]];

        [$positions, $issues] = (new MountingPositionBuilder)->build($loadout, $sourcePorts, [], [], $legacy);

        self::assertArrayNotHasKey('LegacyBucket', $positions[0]);
        self::assertCount(2, $issues);
        self::assertSame(['loadout.0'], $issues[0]['PortIds']);
        self::assertSame(['loadout.0'], $issues[1]['PortIds']);
    }

    private function entry(string $id, string $name, ?string $type, ?string $className, array $extra = []): array
    {
        $entry = ['PortId' => $id, 'HardpointName' => $name, 'MinSize' => $extra['MinSize'] ?? 0, 'MaxSize' => $extra['MaxSize'] ?? 0];
        if ($type !== null) {
            $entry['Type'] = $type;
        }
        if ($className !== null) {
            $entry['ClassName'] = $className;
        }
        // Root loadout entries carry their owning part's name, as Ship::buildLoadoutEntry emits it.
        $entry['PartName'] = $extra['PartName'] ?? null;
        if (isset($extra['Loadout'])) {
            $entry['Loadout'] = $extra['Loadout'];
        }

        return $entry;
    }

    private function port(array $types, ?array $installedItem, ?string $category): array
    {
        $port = ['Types' => $types, 'Category' => $category];
        if ($installedItem !== null) {
            $port['InstalledItem'] = $installedItem;
        }

        return $port;
    }
}
