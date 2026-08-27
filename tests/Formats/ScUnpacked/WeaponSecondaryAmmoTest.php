<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item;
use Octfx\ScDataDumper\Formats\ScUnpacked\Weapon;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Volt shotgun / HDGW rifle pattern: magazine carries primary + secondary ammo
 * records, fire actions select between them via SProjectileLauncher@projectileType.
 * Volt wraps the actions in SWeaponActionDynamicConditionParams, HDGW lists them
 * at the top level of <fireActions>.
 */
final class WeaponSecondaryAmmoTest extends ScDataTestCase
{
    private const string PRIMARY_AMMO_UUID = 'aaaaaaaa-0000-0000-0000-000000000001';

    private const string SECONDARY_AMMO_UUID = 'aaaaaaaa-0000-0000-0000-000000000002';

    private const string MAGAZINE_UUID = 'aaaaaaaa-0000-0000-0000-000000000003';

    private const string WEAPON_UUID = 'aaaaaaaa-0000-0000-0000-000000000004';

    /**
     * Volt-style weapon: fire actions wrapped in SWeaponActionDynamicConditionParams.
     * Slug (Secondary, 1 pellet, 50 Energy) as conditional action,
     * Single (Primary, 8 pellets, 5.75 Energy) as default action.
     */
    private function writeVoltStyleWeapon(): string
    {
        return $this->writeFile(
            'records/entities/scitem/weapons/test_wrapped_weapon.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_WRAPPED_WEAPON __type="EntityClassDefinition" __ref="aaaaaaaa-0000-0000-0000-000000000004" __path="libs/foundry/records/entities/scitem/weapons/test_wrapped_weapon.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponPersonal" SubType="Shotgun" Size="2" Grade="1" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SCItemWeaponComponentParams ammoContainerRecord="aaaaaaaa-0000-0000-0000-000000000003">
                        <fireActions>
                            <SWeaponActionDynamicConditionParams name="RapidBeam" localisedName="@LOC_PLACEHOLDER" aiShootingMode="Single" hasReloadModesOnUI="0">
                                <conditionalWeaponActions>
                                    <SConditionalWeaponAction>
                                        <condition>
                                            <SWeaponConditionHeatLevel>
                                                <comparer mode="GreaterOrEqual" value="0.4" />
                                            </SWeaponConditionHeatLevel>
                                        </condition>
                                        <weaponAction>
                                            <SWeaponActionFireSingleParams name="Slug" localisedName="@FireMode_Single" aiShootingMode="Single" hasReloadModesOnUI="0" fireRate="200" heatPerShot="10" wearPerShot="0">
                                                <launchParams>
                                                    <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="1" damageMultiplier="1" soundRadius="150" projectileType="Secondary">
                                                        <spreadParams min="0" max="0" firstAttack="0" attack="0" decay="0" />
                                                    </SProjectileLauncher>
                                                </launchParams>
                                                <fireAudioTrigger audioTrigger="Play_Test_Slug" />
                                            </SWeaponActionFireSingleParams>
                                        </weaponAction>
                                    </SConditionalWeaponAction>
                                </conditionalWeaponActions>
                                <defaultWeaponAction>
                                    <SWeaponActionFireSingleParams name="Single" localisedName="@FireMode_Single" aiShootingMode="Single" hasReloadModesOnUI="0" fireRate="385" heatPerShot="8.5" wearPerShot="0">
                                        <launchParams>
                                            <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="8" damageMultiplier="1" soundRadius="150" projectileType="Primary">
                                                <spreadParams min="2" max="4" firstAttack="2" attack="1" decay="0.5" />
                                            </SProjectileLauncher>
                                        </launchParams>
                                        <fireAudioTrigger audioTrigger="Play_Test_Shell" />
                                    </SWeaponActionFireSingleParams>
                                </defaultWeaponAction>
                            </SWeaponActionDynamicConditionParams>
                        </fireActions>
                    </SCItemWeaponComponentParams>
                </Components>
            </EntityClassDefinition.TEST_WRAPPED_WEAPON>
            XML
        );
    }

    /**
     * HDGW-style weapon: fire actions at the top level, the pellet mode uses Secondary.
     */
    private function writeHdgwStyleWeapon(): string
    {
        return $this->writeFile(
            'records/entities/scitem/weapons/test_flat_weapon.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_FLAT_WEAPON __type="EntityClassDefinition" __ref="aaaaaaaa-0000-0000-0000-000000000005" __path="libs/foundry/records/entities/scitem/weapons/test_flat_weapon.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponPersonal" SubType="Rifle" Size="2" Grade="1" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SCItemWeaponComponentParams ammoContainerRecord="aaaaaaaa-0000-0000-0000-000000000003">
                        <fireActions>
                            <SWeaponActionFireSingleParams name="Slug" localisedName="@FireMode_Single" aiShootingMode="Single" hasReloadModesOnUI="0" fireRate="85" heatPerShot="10" wearPerShot="0">
                                <launchParams>
                                    <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="1" damageMultiplier="1" soundRadius="150" projectileType="Primary">
                                        <spreadParams min="0" max="1" firstAttack="1" attack="1" decay="3" />
                                    </SProjectileLauncher>
                                </launchParams>
                                <fireAudioTrigger audioTrigger="Play_Test_Slug" />
                            </SWeaponActionFireSingleParams>
                            <SWeaponActionFireSingleParams name="Single" localisedName="@FireMode_Single" aiShootingMode="Single" hasReloadModesOnUI="0" fireRate="85" heatPerShot="8.5" wearPerShot="0">
                                <launchParams>
                                    <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="8" damageMultiplier="1" soundRadius="150" projectileType="Secondary">
                                        <spreadParams min="2" max="4" firstAttack="2" attack="1" decay="0.5" />
                                    </SProjectileLauncher>
                                </launchParams>
                                <fireAudioTrigger audioTrigger="Play_Test_Shell" />
                            </SWeaponActionFireSingleParams>
                        </fireActions>
                    </SCItemWeaponComponentParams>
                </Components>
            </EntityClassDefinition.TEST_FLAT_WEAPON>
            XML
        );
    }

    private function writeAmmoFixtures(): void
    {
        $primary = $this->writeFile(
            'records/ammoparams/test_primary_ammo.xml',
            <<<'XML'
            <AmmoParams.TEST_PRIMARY size="1" bulletType="2" speed="300" lifetime="2" __type="AmmoParams" __ref="aaaaaaaa-0000-0000-0000-000000000001" __path="libs/foundry/records/ammoparams/test_primary_ammo.xml">
                <projectileParams>
                    <BulletProjectileParams>
                        <damage>
                            <DamageInfo DamagePhysical="0" DamageEnergy="5.75" DamageDistortion="0" DamageThermal="0" DamageBiochemical="0" DamageStun="0" />
                        </damage>
                    </BulletProjectileParams>
                </projectileParams>
            </AmmoParams.TEST_PRIMARY>
            XML
        );

        $secondary = $this->writeFile(
            'records/ammoparams/test_slug_ammo.xml',
            <<<'XML'
            <AmmoParams.TEST_SLUG size="1" bulletType="2" speed="300" lifetime="2" __type="AmmoParams" __ref="aaaaaaaa-0000-0000-0000-000000000002" __path="libs/foundry/records/ammoparams/test_slug_ammo.xml">
                <projectileParams>
                    <BulletProjectileParams>
                        <damage>
                            <DamageInfo DamagePhysical="0" DamageEnergy="50" DamageDistortion="0" DamageThermal="0" DamageBiochemical="0" DamageStun="0" />
                        </damage>
                    </BulletProjectileParams>
                </projectileParams>
            </AmmoParams.TEST_SLUG>
            XML
        );

        $magazine = $this->writeFile(
            'records/entities/scitem/weapons/magazines/test_mag.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_MAG __type="EntityClassDefinition" __ref="aaaaaaaa-0000-0000-0000-000000000003" __path="libs/foundry/records/entities/scitem/weapons/magazines/test_mag.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponAttachment" SubType="Magazine" Size="1" Grade="1" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SAmmoContainerComponentParams initialAmmoCount="20" maxAmmoCount="20" ammoParamsRecord="aaaaaaaa-0000-0000-0000-000000000001" secondaryAmmoParamsRecord="aaaaaaaa-0000-0000-0000-000000000002" />
                </Components>
            </EntityClassDefinition.TEST_MAG>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                self::PRIMARY_AMMO_UUID => 'TEST_PRIMARY',
                self::SECONDARY_AMMO_UUID => 'TEST_SLUG',
                self::MAGAZINE_UUID => 'TEST_MAG',
                self::WEAPON_UUID => 'TEST_WRAPPED_WEAPON',
                'aaaaaaaa-0000-0000-0000-000000000005' => 'TEST_FLAT_WEAPON',
            ],
            classToUuidMap: [
                'TEST_PRIMARY' => self::PRIMARY_AMMO_UUID,
                'TEST_SLUG' => self::SECONDARY_AMMO_UUID,
                'TEST_MAG' => self::MAGAZINE_UUID,
                'TEST_WRAPPED_WEAPON' => self::WEAPON_UUID,
                'TEST_FLAT_WEAPON' => 'aaaaaaaa-0000-0000-0000-000000000005',
            ],
            uuidToPathMap: [
                self::PRIMARY_AMMO_UUID => $primary,
                self::SECONDARY_AMMO_UUID => $secondary,
                self::MAGAZINE_UUID => $magazine,
            ]
        );
    }

    private function loadWeapon(string $path): EntityClassDefinition
    {
        $item = new EntityClassDefinition;
        $item->load($path);

        return $item;
    }

    // ------------------------------------------------------------------ //
    //  Dynamic condition wrapper (Volt pattern)                          //
    // ------------------------------------------------------------------ //

    public function test_wrapped_fire_actions_emit_modes(): void
    {
        $this->writeAmmoFixtures();
        $this->initializeMinimalItemServices(['FireMode_Single' => '[SEMI]']);

        $result = (new Weapon($this->loadWeapon($this->writeVoltStyleWeapon())))->toArray();

        self::assertNotNull($result);
        $names = array_column($result['Modes'], 'Name');
        self::assertSame(['Slug', 'Single'], $names);
    }

    public function test_wrapped_secondary_mode_uses_secondary_ammo(): void
    {
        $this->writeAmmoFixtures();
        $this->initializeMinimalItemServices(['FireMode_Single' => '[SEMI]']);

        $result = (new Weapon($this->loadWeapon($this->writeVoltStyleWeapon())))->toArray();

        $modes = array_column($result['Modes'], null, 'Name');

        // Slug: 1 pellet * 50 Energy secondary ammo
        self::assertEquals(50, $modes['Slug']['DamagePerShot']);
        self::assertEquals(50, $modes['Slug']['AlphaEnergy']);
        self::assertEquals(0, $modes['Slug']['AlphaPhysical']);
        // 50 * 200/60 = 166.7
        self::assertEqualsWithDelta(166.7, $modes['Slug']['DamagePerSecond'], 0.01);

        // Single: 8 pellets * 5.75 Energy primary ammo = 46
        self::assertEquals(46, $modes['Single']['DamagePerShot']);
        self::assertEquals(46, $modes['Single']['AlphaEnergy']);
        // 46 * 385/60 = 295.17
        self::assertEqualsWithDelta(295.2, $modes['Single']['DamagePerSecond'], 0.01);
    }

    public function test_wrapped_fire_action_without_conditionals_only_uses_default(): void
    {
        $this->writeAmmoFixtures();
        $this->initializeMinimalItemServices(['FireMode_Single' => '[SEMI]']);

        // Volt LMG pattern: wrapper carries no conditionalWeaponActions at all
        $path = $this->writeFile(
            'records/entities/scitem/weapons/test_wrapped_default_only.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_WRAPPED_DEFAULT_ONLY __type="EntityClassDefinition" __ref="aaaaaaaa-0000-0000-0000-000000000006" __path="libs/foundry/records/entities/scitem/weapons/test_wrapped_default_only.xml">
                <Components>
                    <SCItemWeaponComponentParams ammoContainerRecord="aaaaaaaa-0000-0000-0000-000000000003">
                        <fireActions>
                            <SWeaponActionDynamicConditionParams name="Rapid" localisedName="@LOC_PLACEHOLDER" aiShootingMode="Rapid" hasReloadModesOnUI="0">
                                <defaultWeaponAction>
                                    <SWeaponActionFireRapidParams name="Rapid" localisedName="@FireMode_Single" aiShootingMode="Rapid" hasReloadModesOnUI="0" fireRate="600" heatPerShot="0.3" wearPerShot="0">
                                        <launchParams>
                                            <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="1" damageMultiplier="1" soundRadius="150" projectileType="Primary">
                                                <spreadParams min="1" max="3" firstAttack="1" attack="1" decay="2" />
                                            </SProjectileLauncher>
                                        </launchParams>
                                    </SWeaponActionFireRapidParams>
                                </defaultWeaponAction>
                                <startFragment forceWeaponController="0" />
                            </SWeaponActionDynamicConditionParams>
                        </fireActions>
                    </SCItemWeaponComponentParams>
                </Components>
            </EntityClassDefinition.TEST_WRAPPED_DEFAULT_ONLY>
            XML
        );

        $result = (new Weapon($this->loadWeapon($path)))->toArray();

        self::assertNotNull($result);
        $names = array_column($result['Modes'], 'Name');
        self::assertSame(['Rapid'], $names);
        self::assertEquals(5.75, $result['Modes'][0]['DamagePerShot']);
    }

    // ------------------------------------------------------------------ //
    //  Flat fire actions with Secondary pellet mode (HDGW pattern)        //
    // ------------------------------------------------------------------ //

    public function test_flat_secondary_mode_uses_secondary_ammo(): void
    {
        $this->writeAmmoFixtures();
        $this->initializeMinimalItemServices(['FireMode_Single' => '[SEMI]']);

        $result = (new Weapon($this->loadWeapon($this->writeHdgwStyleWeapon())))->toArray();

        $modes = array_column($result['Modes'], null, 'Name');

        // Slug: primary ammo, 1 pellet, 5.75
        self::assertEquals(5.75, $modes['Slug']['DamagePerShot']);
        self::assertEquals(5.75, $modes['Slug']['AlphaEnergy']);

        // Single: secondary ammo, 8 pellets * 50 = 400
        self::assertEquals(400, $modes['Single']['DamagePerShot']);
        self::assertEquals(400, $modes['Single']['AlphaEnergy']);
        self::assertEquals(0, $modes['Single']['AlphaPhysical']);
    }

    // ------------------------------------------------------------------ //
    //  stdItem.Ammunition / stdItem.SecondaryAmmunition                  //
    // ------------------------------------------------------------------ //

    public function test_std_item_exposes_primary_and_secondary_ammunition(): void
    {
        $this->writeAmmoFixtures();
        $this->initializeMinimalItemServices(['FireMode_Single' => '[SEMI]']);

        $result = (new Item($this->loadWeapon($this->writeVoltStyleWeapon())))->toArray();

        $primary = $result['stdItem']['Ammunition'];
        self::assertSame(self::PRIMARY_AMMO_UUID, $primary['UUID']);
        self::assertEquals(5.75, $primary['ImpactDamage']['Energy']);

        $secondary = $result['stdItem']['SecondaryAmmunition'];
        self::assertSame(self::SECONDARY_AMMO_UUID, $secondary['UUID']);
        self::assertEquals(50, $secondary['ImpactDamage']['Energy']);
        self::assertEquals(20, $secondary['Capacity']);
    }
}
