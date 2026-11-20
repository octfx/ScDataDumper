<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\Vehicle\ItemTypeResolver;
use Octfx\ScDataDumper\Services\Vehicle\PortFinder;
use Octfx\ScDataDumper\Services\Vehicle\PortSummaryBuilder;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\WeaponDpsAggregator;
use Octfx\ScDataDumper\Tests\Fixtures\BuildsTestItems;
use PHPUnit\Framework\TestCase;

final class RemoteTurretIntegrationTest extends TestCase
{
    use BuildsTestItems;

    public function test_nested_installed_remote_turret_gun_is_not_fixed(): void
    {
        $classifier = new PortClassifierService(new ItemTypeResolver);
        $gun = $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0);
        $gunPort = ['PortName' => 'gun_mount', 'Types' => ['WeaponGun.Gun'], 'Uneditable' => false, 'InstalledItem' => $gun];
        [, $gunPort['Category']] = $classifier->classifyPort($gunPort, $gun);

        $turret = [
            'stdItem' => [
                'UUID' => 'turret-uuid',
                'Type' => 'Turret.GunTurret',
                'ClassName' => 'AEGS_tiburon_SCItem_Remote_Turret_mid_right',
                'Ports' => [$gunPort],
            ],
        ];
        $turretPort = ['PortName' => 'hardpoint_turret_mid_right', 'Types' => ['Turret.GunTurret'], 'Uneditable' => false, 'InstalledItem' => $turret];
        [$group, $turretPort['Category']] = $classifier->classifyPort($turretPort, $turret);
        self::assertSame('Weapons', $group);
        self::assertSame('Remote turrets', $turretPort['Category']);
        self::assertSame('Weapon hardpoints', $gunPort['Category']);

        $parts = [[
            'Name' => 'root',
            'Port' => ['PortName' => 'root', 'InstalledItem' => ['stdItem' => ['Ports' => [$turretPort + ['Port' => $turretPort]]]]],
            'Parts' => [],
        ]];
        $summary = (new PortSummaryBuilder(new PortFinder))->build($parts);
        self::assertCount(1, $summary['remoteTurrets']);
        self::assertCount(0, $summary['weaponHardpoints']);

        $context = new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: $summary,
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: [],
        );
        $weaponry = (new WeaponDpsAggregator(new StandardisedPartWalker))->calculate($context)['Weaponry'];
        self::assertArrayNotHasKey('FixedWeapons', $weaponry);
        self::assertCount(1, $weaponry['Turrets']);
        self::assertSame('hardpoint_turret_mid_right', $weaponry['Turrets'][0]['HardpointName']);
        self::assertSame(420.0, $weaponry['Turrets'][0]['DpsTotal']);
        self::assertSame('Laser_S3_UUID', $weaponry['Turrets'][0]['Weapons'][0]['UUID']);
    }
}
