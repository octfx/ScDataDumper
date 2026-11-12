<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Weapon;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * ADS spread: aimAction aimModifier weaponStats spreadModifier holds the aim
 * multipliers as ATTRIBUTES on a self-closing element (klwe_pistol_energy_01.xml
 * line 1517). extractAdsSpread must read them via '@' queries and must apply
 * them to the hip-fire spread keys 'Minimum'/'Maximum' returned by extractSpread.
 */
final class WeaponAdsSpreadTest extends ScDataTestCase
{
    private const string WEAPON_UUID = 'aaaaaaaa-0000-0000-0000-000000000010';

    private const string WEAPON_CLASS = 'TEST_ADS_WEAPON';

    /**
     * Klwe pistol shape: hip-fire spread plus an aim modifier under
     * aimAction/SWeaponActionAimSimpleParams.
     *
     * @param  string  $weaponStatsModifier  XML inserted into <weaponStats>
     */
    private function writeWeapon(string $weaponStatsModifier): string
    {
        $class = self::WEAPON_CLASS;
        $uuid = self::WEAPON_UUID;

        return $this->writeFile(
            'records/entities/scitem/weapons/fps_weapons/test_ads_weapon.xml',
            <<<XML
            <EntityClassDefinition.{$class} __type="EntityClassDefinition" __ref="{$uuid}" __path="libs/foundry/records/entities/scitem/weapons/fps_weapons/test_ads_weapon.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponPersonal" SubType="Pistol" Size="1" Grade="1" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SCItemWeaponComponentParams>
                        <fireActions>
                            <SWeaponActionFireRapidParams name="Rapid" localisedName="@FireMode_Rapid" aiShootingMode="Rapid" hasReloadModesOnUI="0" fireRate="340" heatPerShot="1.2" wearPerShot="0">
                                <launchParams>
                                    <SProjectileLauncher fireHelper="muzzle_flash" ammoCost="1" pelletCount="1" damageMultiplier="1" soundRadius="150" projectileType="Primary">
                                        <spreadParams min="0.08" max="2.94" firstAttack="1.24" attack="1.24" decay="3" />
                                    </SProjectileLauncher>
                                </launchParams>
                                <fireAudioTrigger audioTrigger="Play_Test_Rapid" />
                            </SWeaponActionFireRapidParams>
                        </fireActions>
                        <aimAction>
                            <SWeaponActionAimSimpleParams localisedName="@LOC_PLACEHOLDER" aiShootingMode="Single" hasReloadModesOnUI="0" zoomScale="1.2" zoomTime="0.15" toggleZoomOverride="0">
                                <aimModifier>
                                    <SWeaponModifierParams>
                                        <weaponStats fireRate="0" fireRateMultiplier="1" damageMultiplier="1" projectileSpeedMultiplier="1" pellets="0" burstShots="0" ammoCost="0" ammoCostMultiplier="1" heatGenerationMultiplier="1" soundRadiusMultiplier="1" chargeTimeMultiplier="1" useAlternateProjectileVisuals="0" useAugmentedRealityProjectiles="0" disableMisfire="0">
                                            <recoilModifier decayMultiplier="1" endDecayMultiplier="1" fireRecoilTimeMultiplier="1" fireRecoilStrengthFirstMultiplier="1" fireRecoilStrengthMultiplier="1" angleRecoilStrengthMultiplier="1" randomnessMultiplier="1" randomnessBackPushMultiplier="1" frontalOscillationRotationMultiplier="1" frontalOscillationStrengthMultiplier="1" frontalOscillationDecayMultiplier="1" frontalOscillationRandomnessMultiplier="1" animatedRecoilMultiplier="1" disableRecoil="0" />
                                            {$weaponStatsModifier}
                                            <aimModifier zoomScale="1" secondZoomScale="1" zoomTimeScale="1" hideWeaponInADS="0" fstopMultiplier="1" />
                                            <regenModifier powerRatioMultiplier="1" maxAmmoLoadMultiplier="1" maxRegenPerSecMultiplier="1" />
                                        </weaponStats>
                                    </SWeaponModifierParams>
                                </aimModifier>
                            </SWeaponActionAimSimpleParams>
                        </aimAction>
                    </SCItemWeaponComponentParams>
                </Components>
            </EntityClassDefinition.{$class}>
            XML
        );
    }

    private function loadWeapon(string $path): EntityClassDefinition
    {
        $this->writeCacheFiles(
            uuidToClassMap: [self::WEAPON_UUID => self::WEAPON_CLASS],
            classToUuidMap: [self::WEAPON_CLASS => self::WEAPON_UUID],
        );

        $this->initializeMinimalItemServices(['FireMode_Rapid' => '[RAPID]', 'LOC_EMPTY' => '']);

        $item = new EntityClassDefinition;
        $item->load($path);

        return $item;
    }

    public function test_zeroed_aim_spread_modifier_yields_zero_ads_spread(): void
    {
        $result = (new Weapon($this->loadWeapon($this->writeWeapon(
            '<spreadModifier minMultiplier="0" maxMultiplier="0" firstAttackMultiplier="0" attackMultiplier="0" decayMultiplier="0" additiveModifier="0" />'
        ))))->toArray();

        self::assertNotNull($result);
        $mode = $result['Modes'][0];

        self::assertSame(
            ['Minimum' => 0.08, 'Maximum' => 2.94, 'FirstAttack' => 1.24, 'Attack' => 1.24, 'Decay' => 3.0],
            $mode['Spread']
        );
        // Fully accurate when aimed: every multiplier is applied, none may default to 1.0.
        self::assertSame(
            ['Minimum' => 0.0, 'Maximum' => 0.0, 'FirstAttack' => 0.0, 'Attack' => 0.0, 'Decay' => 0.0],
            $mode['AdsSpread']
        );
        self::assertSame(
            ['Minimum' => 0.0, 'Maximum' => 0.0, 'FirstAttack' => 0.0, 'Attack' => 0.0, 'Decay' => 0.0],
            $result['AdsSpread']
        );
    }

    public function test_partial_aim_spread_multipliers_are_applied(): void
    {
        $result = (new Weapon($this->loadWeapon($this->writeWeapon(
            '<spreadModifier minMultiplier="0.65" maxMultiplier="0.65" firstAttackMultiplier="0.65" attackMultiplier="0.65" decayMultiplier="0.65" additiveModifier="0" />'
        ))))->toArray();

        self::assertNotNull($result);
        $ads = $result['Modes'][0]['AdsSpread'];

        self::assertEqualsWithDelta(0.08 * 0.65, $ads['Minimum'], 0.0001);
        self::assertEqualsWithDelta(2.94 * 0.65, $ads['Maximum'], 0.0001);
        self::assertEqualsWithDelta(1.24 * 0.65, $ads['FirstAttack'], 0.0001);
        self::assertEqualsWithDelta(1.24 * 0.65, $ads['Attack'], 0.0001);
        self::assertEqualsWithDelta(3 * 0.65, $ads['Decay'], 0.0001);
    }

    public function test_missing_aim_spread_modifier_keeps_hip_fire_spread(): void
    {
        $result = (new Weapon($this->loadWeapon($this->writeWeapon(
            '<aimModifier zoomScale="1" secondZoomScale="1" zoomTimeScale="1" hideWeaponInADS="0" fstopMultiplier="1" />'
        ))))->toArray();

        self::assertNotNull($result);
        $mode = $result['Modes'][0];

        self::assertSame($mode['Spread'], $mode['AdsSpread']);
    }
}
