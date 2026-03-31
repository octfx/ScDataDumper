<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\WeaponSystemAnalyzer;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class WeaponSystemAnalyzerTest extends ScDataTestCase
{
    public function test_analyze_turrets_includes_part_names_and_recursively_resolves_remote_gun_sizes(): void
    {
        $analyzer = new WeaponSystemAnalyzer;

        $result = $analyzer->analyzeTurrets(collect([[
            'Name' => 'hardpoint_remote_turret',
            'DisplayName' => 'Remote Turret',
            'Port' => $this->makePort(
                portName: 'hardpoint_remote_turret',
                size: 3,
                installedItem: $this->makeInstalledItem(
                    type: 'Turret.GunTurret',
                    size: 3,
                    className: 'RSI_Scorpius_SCItem_Remote_Turret',
                    ports: [
                        $this->makePort(
                            portName: 'turret_top_left',
                            size: 3,
                            minSize: 3,
                            maxSize: 3,
                            types: ['WeaponGun.Gun', 'Turret.GunTurret'],
                            installedItem: $this->makeInstalledItem(
                                type: 'Turret.GunTurret',
                                size: 3,
                                className: 'Mount_Gimbal_S3',
                                ports: [
                                    $this->makePort(
                                        portName: 'hardpoint_class_2',
                                        size: 3,
                                        minSize: 2,
                                        maxSize: 3,
                                        types: ['WeaponGun.Gun'],
                                        installedItem: $this->makeInstalledItem(
                                            type: 'WeaponGun.Gun',
                                            size: 3,
                                            className: 'KLWE_LaserRepeater_S3',
                                        )
                                    ),
                                ],
                            )
                        ),
                        $this->makePort(
                            portName: 'turret_top_right',
                            size: 3,
                            minSize: 3,
                            maxSize: 3,
                            types: ['WeaponGun.Gun', 'Turret.GunTurret'],
                            installedItem: $this->makeInstalledItem(
                                type: 'Turret.GunTurret',
                                size: 3,
                                className: 'Mount_Gimbal_S3',
                                ports: [
                                    $this->makePort(
                                        portName: 'hardpoint_class_2',
                                        size: 3,
                                        minSize: 2,
                                        maxSize: 3,
                                        types: ['WeaponGun.Gun'],
                                        installedItem: $this->makeInstalledItem(
                                            type: 'WeaponGun.Gun',
                                            size: 3,
                                            className: 'KLWE_LaserRepeater_S3',
                                        )
                                    ),
                                ],
                            )
                        ),
                    ],
                ),
            ),
        ]]));

        self::assertCount(1, $result);
        self::assertSame('hardpoint_remote_turret', $result[0]['PartName']);
        self::assertSame('hardpoint_remote_turret', $result[0]['HardpointName']);
        self::assertSame(3, $result[0]['Size']);
        self::assertSame('RSI_Scorpius_SCItem_Remote_Turret', $result[0]['TurretClassName']);
        self::assertSame('Turret.GunTurret', $result[0]['TurretType']);
        self::assertTrue($result[0]['Gimballed']);
        self::assertFalse($result[0]['Turret']);
        self::assertSame([3, 3], $result[0]['WeaponSizes']);
        self::assertSame([3, 3], $result[0]['PayloadSizes']);
        self::assertSame(2, $result[0]['MountCount']);
        self::assertSame('turret_top_left', $result[0]['Mounts'][0]['HardpointName']);
        self::assertSame('Mount_Gimbal_S3', $result[0]['Mounts'][0]['MountClassName']);
        self::assertSame(['WeaponGun.Gun'], $result[0]['Mounts'][0]['PayloadTypes']);
        self::assertSame(['KLWE_LaserRepeater_S3'], $result[0]['Mounts'][0]['PayloadClassNames']);
        self::assertSame([3], $result[0]['Mounts'][0]['WeaponSizes']);
    }

    public function test_calculate_weapon_fitting_keeps_utility_turrets_out_of_fixed_fallback(): void
    {
        $analyzer = new WeaponSystemAnalyzer;

        $result = $analyzer->calculateWeaponFitting($this->makePort(
            portName: 'hardpoint_remote_turret_salvage_left',
            size: 0,
            installedItem: $this->makeInstalledItem(
                type: 'Turret.Utility',
                size: 5,
                className: 'AEGS_Reclaimer_SCItem_Remote_Salvage_Turret_Left',
                ports: [
                    $this->makePort(
                        portName: 'hardpoint_weapon_salvage',
                        size: 2,
                        minSize: 1,
                        maxSize: 2,
                        types: ['SalvageHead'],
                    ),
                    $this->makePort(
                        portName: 'hardpoint_interior',
                        size: 4,
                        minSize: 1,
                        maxSize: 4,
                        types: ['Room'],
                    ),
                ],
            ),
        ));

        self::assertSame(5, $result['Size']);
        self::assertFalse($result['Gimballed']);
        self::assertTrue($result['Turret']);
        self::assertArrayNotHasKey('Fixed', $result);
        self::assertSame([2], $result['PayloadSizes']);
        self::assertSame(['SalvageHead'], $result['PayloadTypes']);
        self::assertSame(1, $result['MountCount']);
        self::assertSame('hardpoint_weapon_salvage', $result['Mounts'][0]['HardpointName']);
        self::assertSame(['SalvageHead'], $result['Mounts'][0]['PayloadTypes']);
    }

    public function test_calculate_weapon_fitting_uses_installed_turret_size_when_host_port_size_is_zero(): void
    {
        $analyzer = new WeaponSystemAnalyzer;

        $result = $analyzer->calculateWeaponFitting($this->makePort(
            portName: 'hardpoint_remote_turret',
            size: 0,
            installedItem: $this->makeInstalledItem(
                type: 'Turret.TopTurret',
                size: 3,
                className: 'RSI_Perseus_Remote_Turret_Top_S3',
                ports: [
                    $this->makePort(
                        portName: 'hardpoint_gimbal_left',
                        size: 3,
                        minSize: 3,
                        maxSize: 3,
                        types: ['WeaponGun.Gun', 'Turret.GunTurret'],
                    ),
                    $this->makePort(
                        portName: 'hardpoint_gimbal_right',
                        size: 3,
                        minSize: 3,
                        maxSize: 3,
                        types: ['WeaponGun.Gun', 'Turret.GunTurret'],
                    ),
                ],
            ),
        ));

        self::assertSame(3, $result['Size']);
        self::assertFalse($result['Gimballed']);
        self::assertTrue($result['Turret']);
        self::assertSame([3, 3], $result['WeaponSizes']);
        self::assertSame(2, $result['MountCount']);
    }

    public function test_calculate_includes_pdc_turrets(): void
    {
        $analyzer = new WeaponSystemAnalyzer;

        $context = new VehicleDataContext(
            standardisedParts: [],
            portSummary: [
                'mannedTurrets' => new Collection,
                'pdcTurrets' => collect([[
                    'Name' => 'hardpoint_pdc_top',
                    'DisplayName' => 'PDC Top',
                    'Port' => $this->makePort(
                        portName: 'hardpoint_pdc_top',
                        size: 2,
                        installedItem: $this->makeInstalledItem(
                            type: 'Turret.PDCTurret',
                            size: 2,
                            className: 'Turret_PDC_BEHR_G',
                            ports: [
                                $this->makePort(
                                    portName: 'hardpoint_weapon',
                                    size: 1,
                                    minSize: 1,
                                    maxSize: 1,
                                    types: ['WeaponGun.Gun'],
                                ),
                            ],
                        ),
                    ),
                ]]),
                'remoteTurrets' => new Collection,
            ],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        );

        $result = $analyzer->calculate($context);

        self::assertArrayHasKey('PdcTurrets', $result);
        self::assertCount(1, $result['PdcTurrets']);
        self::assertSame('hardpoint_pdc_top', $result['PdcTurrets'][0]['HardpointName']);
        self::assertSame('Turret.PDCTurret', $result['PdcTurrets'][0]['TurretType']);
        self::assertSame([1], $result['PdcTurrets'][0]['WeaponSizes']);
    }

    public function test_display_names_are_translated_when_localization_service_is_available(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'vehicle_remote_turret' => 'Remote Turret',
            'port_NameTurretGunSlot01' => 'Turret Gun Slot 01',
        ]);

        $analyzer = new WeaponSystemAnalyzer;

        $result = $analyzer->analyzeTurrets(collect([[
            'Name' => 'hardpoint_remote_turret',
            'DisplayName' => '@vehicle_remote_turret',
            'Port' => $this->makePort(
                portName: 'hardpoint_remote_turret',
                size: 3,
                installedItem: $this->makeInstalledItem(
                    type: 'Turret.GunTurret',
                    size: 3,
                    className: 'RSI_Scorpius_SCItem_Remote_Turret',
                    ports: [
                        $this->makePort(
                            portName: 'turret_top_left',
                            size: 3,
                            minSize: 3,
                            maxSize: 3,
                            types: ['WeaponGun.Gun'],
                            displayName: '@port_NameTurretGunSlot01',
                        ),
                    ],
                ),
            ),
        ]]));

        self::assertSame('Remote Turret', $result[0]['DisplayName']);
        self::assertSame('Turret Gun Slot 01', $result[0]['Mounts'][0]['DisplayName']);
    }

    private function makePort(
        string $portName,
        int $size,
        ?int $minSize = null,
        ?int $maxSize = null,
        array $types = [],
        ?array $installedItem = null,
        ?string $displayName = null,
    ): array {
        return array_filter([
            'PortName' => $portName,
            'DisplayName' => $displayName,
            'Size' => $size,
            'MinSize' => $minSize,
            'MaxSize' => $maxSize,
            'Types' => $types,
            'InstalledItem' => $installedItem,
        ], static fn ($value) => $value !== null);
    }

    private function makeInstalledItem(string $type, int $size, string $className, array $ports = []): array
    {
        return [
            'stdItem' => [
                'Type' => $type,
                'Size' => $size,
                'ClassName' => $className,
                'Ports' => $ports,
            ],
        ];
    }
}
