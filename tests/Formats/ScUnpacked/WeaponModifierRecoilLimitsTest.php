<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\WeaponModifier;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Barrel-stabilizer pattern (arma_barrel_stab_s1_02): the aim-recoil
 * curveRecoil carries minLimitsModifier/maxLimitsModifier vectors that
 * must appear in the exported CurveRecoil map.
 */
final class WeaponModifierRecoilLimitsTest extends ScDataTestCase
{
    public function test_aim_recoil_curve_exports_limits_modifiers(): void
    {
        $path = $this->writeFile(
            'records/entities/scitem/weapons/weapon_modifier/test_barrel_stab.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_BARREL_STAB Category="Default" __type="EntityClassDefinition" __ref="aaaaaaaa-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/scitem/weapons/weapon_modifier/test_barrel_stab.xml">
                <Components>
                    <SWeaponModifierComponentParams activateOnAttach="1" ignoreWear="0" barrelEffectsStrength="0.5">
                        <modifier>
                            <weaponStats fireRateMultiplier="1.3" damageMultiplier="0.9">
                                <recoilModifier decayMultiplier="1" endDecayMultiplier="1">
                                    <aimRecoilModifier randomPitchMultiplier="1" randomYawMultiplier="1" decayMultiplier="1" endDecayMultiplier="1">
                                        <maxMultiplier x="1" y="1" />
                                        <curveRecoil yawMaxDegreesModifier="1.5" pitchMaxDegreesModifier="3" rollMaxDegreesModifier="1.5" maxFireTimeModifier="1" recoilSmoothTimeModifier="1" decayStartTimeModifier="1" minDecayTimeModifier="1" maxDecayTimeModifier="1">
                                            <minLimitsModifier x="3" y="3" z="1" />
                                            <maxLimitsModifier x="3" y="3" z="1" />
                                            <noiseCurvesModifier yawNoiseMaxValueModifier="0" pitchNoiseMaxValueModifier="1.1" rollNoiseMaxValueModifier="2.2" />
                                        </curveRecoil>
                                    </aimRecoilModifier>
                                    <curveRecoil recoilTimeModifier="0.85" minDecayTimeModifier="0" maxDecayTimeModifier="2">
                                        <positionModifiers xMaxValueModifier="0" yMaxValueModifier="0.7" zMaxValueModifier="1.5">
                                            <minLimitsModifier x="4" y="1" z="0" />
                                            <maxLimitsModifier x="2" y="3" z="4" />
                                            <noiseModifier xNoiseModifier="0" yNoiseModifier="0.7" zNoiseModifier="0.8" />
                                        </positionModifiers>
                                        <rotationModifiers xMaxValueModifier="2" yMaxValueModifier="3" zMaxValueModifier="4">
                                            <noiseModifier xNoiseModifier="1" yNoiseModifier="2" zNoiseModifier="3" />
                                        </rotationModifiers>
                                        <positionDecayModifiers>
                                            <decayTimeMultiplierModifier x="0" y="2" z="3" />
                                            <decayMaxValueModifier x="4" y="5" z="6" />
                                            <decayMinScalingFactorModifier x="7" y="8" z="9" />
                                        </positionDecayModifiers>
                                        <rotationDecayModifiers><decayMaxValueModifier x="9" y="8" z="7" /></rotationDecayModifiers>
                                    </curveRecoil>
                                </recoilModifier>
                            </weaponStats>
                        </modifier>
                    </SWeaponModifierComponentParams>
                </Components>
            </EntityClassDefinition.TEST_BARREL_STAB>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($path);

        $result = (new WeaponModifier($item))->toArray();

        self::assertNotNull($result);
        $curveRecoil = $result['WeaponStats']['Recoil']['AimRecoil']['CurveRecoil'];

        self::assertEquals(['X' => 3, 'Y' => 3, 'Z' => 1], $curveRecoil['MinLimitsModifier']);
        self::assertEquals(['X' => 3, 'Y' => 3, 'Z' => 1], $curveRecoil['MaxLimitsModifier']);
        self::assertSame(1.5, $curveRecoil['YawMaxDegreesModifier']);
        self::assertSame(['YawNoiseMaxValueModifier' => 0.0, 'PitchNoiseMaxValueModifier' => 1.1, 'RollNoiseMaxValueModifier' => 2.2], $curveRecoil['NoiseCurvesModifier']);
        $recoil = $result['WeaponStats']['Recoil'];
        self::assertSame(1.0, $recoil['DecayMultiplier']);
        self::assertSame([
            'RecoilTimeModifier' => 0.85,
            'MinDecayTimeModifier' => 0.0,
            'MaxDecayTimeModifier' => 2.0,
            'PositionModifiers' => [
                'XMaxValueModifier' => 0.0, 'YMaxValueModifier' => 0.7, 'ZMaxValueModifier' => 1.5,
                'MinLimitsModifier' => ['X' => 4.0, 'Y' => 1.0, 'Z' => 0.0],
                'MaxLimitsModifier' => ['X' => 2.0, 'Y' => 3.0, 'Z' => 4.0],
                'NoiseModifier' => ['XNoiseModifier' => 0.0, 'YNoiseModifier' => 0.7, 'ZNoiseModifier' => 0.8],
            ],
            'RotationModifiers' => [
                'XMaxValueModifier' => 2.0, 'YMaxValueModifier' => 3.0, 'ZMaxValueModifier' => 4.0,
                'NoiseModifier' => ['XNoiseModifier' => 1.0, 'YNoiseModifier' => 2.0, 'ZNoiseModifier' => 3.0],
            ],
            'PositionDecayModifiers' => [
                'DecayTimeMultiplierModifier' => ['X' => 0.0, 'Y' => 2.0, 'Z' => 3.0],
                'DecayMaxValueModifier' => ['X' => 4.0, 'Y' => 5.0, 'Z' => 6.0],
                'DecayMinScalingFactorModifier' => ['X' => 7.0, 'Y' => 8.0, 'Z' => 9.0],
            ],
            'RotationDecayModifiers' => ['DecayMaxValueModifier' => ['X' => 9.0, 'Y' => 8.0, 'Z' => 7.0]],
        ], $recoil['CurveRecoil']);
    }

    public function test_missing_recoil_curve_elements_are_omitted(): void
    {
        $path = $this->writeFile('missing_recoil.xml', <<<'XML'
            <EntityClassDefinition.TEST __type="EntityClassDefinition">
                <Components><SWeaponModifierComponentParams><modifier><weaponStats>
                    <recoilModifier decayMultiplier="0"><aimRecoilModifier><curveRecoil yawMaxDegreesModifier="0" /></aimRecoilModifier><curveRecoil /></recoilModifier>
                </weaponStats></modifier></SWeaponModifierComponentParams></Components>
            </EntityClassDefinition.TEST>
            XML);
        $item = new EntityClassDefinition;
        $item->load($path);

        $recoil = (new WeaponModifier($item))->toArray()['WeaponStats']['Recoil'];
        self::assertSame(['DecayMultiplier' => 0.0, 'AimRecoil' => ['CurveRecoil' => ['YawMaxDegreesModifier' => 0.0]]], $recoil);
    }
}
