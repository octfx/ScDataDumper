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
                                        </curveRecoil>
                                    </aimRecoilModifier>
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
    }
}
