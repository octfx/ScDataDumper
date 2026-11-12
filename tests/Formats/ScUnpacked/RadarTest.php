<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Radar;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\TestCase;

final class RadarTest extends TestCase
{
    private const string XML_TEMPLATE = <<<'XML'
        <EntityClassDefinition.Radar_Test>
            <Components>
                <SCItemRadarComponentParams>
                    <signatureDetection>
                        <SCItemRadarSignatureDetectionProperties sensitivity="0.5" piercing="0.1" permitPassiveDetection="1" permitActiveDetection="1" />
                    </signatureDetection>
                    <sensitivityModifiers>%s</sensitivityModifiers>
                </SCItemRadarComponentParams>
            </Components>
        </EntityClassDefinition.Radar_Test>
        XML;

    private const string GV_GROUP_MODIFIER = <<<'XML'
        <SCItemRadarSensitivityModifier sensitivityAddition="-0.65">
            <modifierType>
                <SCItemRadarSensitivityModifierTypeContactGroups>
                    <contactGroups>
                        <Reference value="0ea6278c-4230-4325-95d8-13b68af5415e" />
                    </contactGroups>
                </SCItemRadarSensitivityModifierTypeContactGroups>
            </modifierType>
        </SCItemRadarSensitivityModifier>
        XML;

    private const string OTHER_GROUP_MODIFIER = <<<'XML'
        <SCItemRadarSensitivityModifier sensitivityAddition="-0.10">
            <modifierType>
                <SCItemRadarSensitivityModifierTypeContactGroups>
                    <contactGroups>
                        <Reference value="8c71c1ea-8221-4c15-a1e2-11e04cbcbde2" />
                    </contactGroups>
                </SCItemRadarSensitivityModifierTypeContactGroups>
            </modifierType>
        </SCItemRadarSensitivityModifier>
        XML;

    public function test_single_ground_vehicle_modifier_exports_addition_and_detection_sensitivity(): void
    {
        $radar = $this->buildRadarFromXml(sprintf(self::XML_TEMPLATE, self::GV_GROUP_MODIFIER));

        $out = $radar->toArray();

        self::assertSame(-0.65, $out['GroundVehicleSensitivityAddition']);
        // 10 ** -0.65 = 0.2238..., quantized to the 0.25 step
        self::assertSame(0.25, $out['GroundVehicleDetectionSensitivity']['IR']);
    }

    public function test_multiple_modifiers_pick_ground_vehicle_group_not_first_modifier(): void
    {
        $xml = sprintf(
            self::XML_TEMPLATE,
            self::OTHER_GROUP_MODIFIER."\n".self::GV_GROUP_MODIFIER
        );

        $radar = $this->buildRadarFromXml($xml);

        $out = $radar->toArray();

        self::assertSame(-0.65, $out['GroundVehicleSensitivityAddition']);
        self::assertSame(0.25, $out['GroundVehicleDetectionSensitivity']['IR']);
    }

    public function test_modifiers_without_ground_vehicle_group_fall_back_to_first_modifier_addition(): void
    {
        $xml = sprintf(self::XML_TEMPLATE, self::OTHER_GROUP_MODIFIER);

        $radar = $this->buildRadarFromXml($xml);

        $out = $radar->toArray();

        self::assertSame(-0.10, $out['GroundVehicleSensitivityAddition']);
    }

    private function buildRadarFromXml(string $xml): Radar
    {
        $tmpFile = sys_get_temp_dir().'/radar_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, $xml);

        try {
            $doc = new TestRootDocument;
            $doc->load($tmpFile);

            return new Radar($doc);
        } finally {
            @unlink($tmpFile);
        }
    }
}
