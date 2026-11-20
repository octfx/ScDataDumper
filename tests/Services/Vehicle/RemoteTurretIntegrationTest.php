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
        $this->assertTurretGunIsNotFixed('AEGS_tiburon_SCItem_Remote_Turret_mid_right', isTopLevelPort: false);
    }

    public function test_pilot_controlled_nose_turret_is_remote_and_keeps_pilot_dps(): void
    {
        $this->assertTurretGunIsNotFixed('MISC_Hull_C_Nose_Turret_S5', isPilotControlled: true);
    }

    public function test_autonomous_turret_gun_is_not_fixed(): void
    {
        $this->assertTurretGunIsNotFixed('AEGS_Idris_SCItem_AI_Turret_Top_Left', category: 'Autonomous turrets');
    }

    private function assertTurretGunIsNotFixed(
        string $className,
        bool $isTopLevelPort = true,
        bool $isPilotControlled = false,
        string $category = 'Remote turrets',
    ): void {
        $classifier = new PortClassifierService(new ItemTypeResolver);
        $gun = $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0);
        $gunPort = ['PortName' => 'gun_mount', 'Types' => ['WeaponGun.Gun'], 'Uneditable' => false, 'InstalledItem' => $gun];
        [, $gunPort['Category']] = $classifier->classifyPort($gunPort, $gun);

        $turret = [
            'stdItem' => [
                'UUID' => 'turret-uuid',
                'Type' => 'Turret.GunTurret',
                'ClassName' => $className,
                'Ports' => [$gunPort],
            ],
        ];
        $turretPort = ['PortName' => 'hardpoint_turret_mid_right', 'Types' => ['Turret.GunTurret'], 'Uneditable' => false, 'InstalledItem' => $turret];
        [$group, $turretPort['Category']] = $classifier->classifyPort($turretPort, $turret, isTopLevelPort: $isTopLevelPort);
        self::assertSame('Weapons', $group);
        self::assertSame($category, $turretPort['Category']);
        self::assertSame('Weapon hardpoints', $gunPort['Category']);

        $parts = [[
            'Name' => 'root',
            'Port' => $isTopLevelPort ? $turretPort : ['PortName' => 'root', 'InstalledItem' => ['stdItem' => ['Ports' => [$turretPort + ['Port' => $turretPort]]]]],
            'Parts' => [[
                'Name' => 'separate_part',
                'Port' => ['PortName' => 'separate_hardpoint', 'Category' => 'Weapon hardpoints'],
                'Parts' => [],
            ]],
        ]];
        $summary = (new PortSummaryBuilder(new PortFinder))->build($parts);
        self::assertCount($category === 'Remote turrets' ? 1 : 0, $summary['remoteTurrets']);
        self::assertSame(['separate_hardpoint'], $summary['weaponHardpoints']->pluck('Port.PortName')->all());

        $context = new VehicleDataContext(
            standardisedParts: $parts,
            portSummary: $summary,
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: $isPilotControlled ? [$turretPort['PortName']] : [],
        );
        $weaponry = (new WeaponDpsAggregator(new StandardisedPartWalker))->calculate($context)['Weaponry'];
        self::assertArrayNotHasKey('FixedWeapons', $weaponry);
        self::assertCount(1, $weaponry['Turrets']);
        self::assertSame('hardpoint_turret_mid_right', $weaponry['Turrets'][0]['HardpointName']);
        self::assertSame(420.0, $weaponry['Turrets'][0]['DpsTotal']);
        self::assertSame(420.0, $weaponry[$isPilotControlled ? 'PilotDps' : 'TurretDps']);
        self::assertSame('Laser_S3_UUID', $weaponry['Turrets'][0]['Weapons'][0]['UUID']);
    }
}
