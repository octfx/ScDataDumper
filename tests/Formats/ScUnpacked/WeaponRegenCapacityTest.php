<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Weapon;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Regen-fed weapons (maxAmmoCount=0, SWeaponRegenConsumerParams present) must
 * derive Capacity from requestedAmmoLoad / regenerationCostPerBullet BEFORE the
 * mode loop, so MaxDamagePerMagazine = Capacity x damage instead of 0.
 *
 * Shape mirrors import/.../vncl_gen2_laserbeam_s9.xml: beam fire action with
 * damagePerSecond/DamageInfo and ammo container reporting zero capacity.
 */
final class WeaponRegenCapacityTest extends ScDataTestCase
{
    private const string WEAPON_UUID = 'aaaaaaaa-0000-0000-0000-000000000020';

    private const string WEAPON_CLASS = 'TEST_REGEN_WEAPON';

    public function test_regen_fed_weapon_capacity_is_used_for_max_damage_per_magazine(): void
    {
        $class = self::WEAPON_CLASS;
        $uuid = self::WEAPON_UUID;

        $path = $this->writeFile(
            'records/entities/scitem/weapons/fps_weapons/test_regen_weapon.xml',
            <<<XML
            <EntityClassDefinition.{$class} __type="EntityClassDefinition" __ref="{$uuid}" __path="libs/foundry/records/entities/scitem/weapons/fps_weapons/test_regen_weapon.xml">
                <Components>
                    <SAmmoContainerComponentParams initialAmmoCount="0" maxAmmoCount="0" ammoContainerType="Primary" />
                    <SCItemWeaponComponentParams>
                        <weaponRegenConsumerParams>
                            <SWeaponRegenConsumerParams initialRegenPerSecond="33.3" requestedRegenPerSecond="33.3" requestedAmmoLoad="100" maxAmmoLoad="225" regenerationCooldown="0.432" regenerationCostPerBullet="1" />
                        </weaponRegenConsumerParams>
                        <fireActions>
                            <SWeaponActionFireBeamParams name="Beam" localisedName="@FireMode_Beam" aiShootingMode="Continuous" hasReloadModesOnUI="0" beamRange="900" heatPerShot="0">
                                <damagePerSecond>
                                    <DamageInfo DamagePhysical="0" DamageEnergy="27500" DamageDistortion="0" DamageThermal="0" DamageBiochemical="0" DamageStun="0" />
                                </damagePerSecond>
                            </SWeaponActionFireBeamParams>
                        </fireActions>
                    </SCItemWeaponComponentParams>
                </Components>
            </EntityClassDefinition.{$class}>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [self::WEAPON_UUID => self::WEAPON_CLASS],
            classToUuidMap: [self::WEAPON_CLASS => self::WEAPON_UUID],
        );

        $this->initializeMinimalItemServices(['FireMode_Beam' => '[BEAM]']);

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new Weapon($item))->toArray();

        self::assertNotNull($result);
        self::assertSame(100, $result['Capacity']);
        self::assertSame(100.0, $result['Consumption']['RequestedAmmoLoad']);
        self::assertSame(1.0, $result['Consumption']['CostPerBullet']);

        $mode = $result['Modes'][0];
        self::assertSame(27500.0, $mode['DamagePerSecond']);
        self::assertSame(2750000.0, $mode['MaxDamagePerMagazine']);
    }
}
