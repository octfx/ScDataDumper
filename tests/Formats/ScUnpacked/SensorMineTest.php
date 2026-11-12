<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\SensorMine;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class SensorMineTest extends ScDataTestCase
{
    // ------------------------------------------------------------------ //
    //  Proximity mine (behr_prx_kinetic_01 shape)                         //
    // ------------------------------------------------------------------ //

    public function test_proximity_mine_exports_trigger_geometry(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $mine = $this->makeSensorMine(triggerXml: <<<'XML'
            <TriggerType>
                <SSensorMineProximityTrigger MaxRaysPerMine="20" Radius="2.5" WarningRadius="3" AngleVertical="180" AngleHorizontal="90" GuideLaserLength="1">
                    <Front x="0" y="1" z="0" />
                    <Up x="0" y="0" z="1" />
                    <Center x="0" y="0" z="0.06" />
                </SSensorMineProximityTrigger>
            </TriggerType>
        XML);

        $result = $mine->toArray();

        self::assertNotNull($result);
        self::assertEquals(2.5, $result['Radius']);
        self::assertEquals(3, $result['WarningRadius']);
        self::assertEquals(20, $result['MaxRaysPerMine']);
        self::assertEquals(180, $result['AngleVertical']);
        self::assertEquals(90, $result['AngleHorizontal']);
        self::assertEquals(5, $result['AreaOfEffect']);
        self::assertEquals(35, $result['Damage']);
        self::assertEquals('Physical', $result['DamageType']);
        self::assertArrayNotHasKey('LaserLength', $result);
    }

    // ------------------------------------------------------------------ //
    //  Laser trip mine (behr_ltp_kinetic_01 shape)                        //
    // ------------------------------------------------------------------ //

    public function test_laser_mine_still_exports_laser_length(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $mine = $this->makeSensorMine(triggerXml: <<<'XML'
            <TriggerType>
                <SSensorMineLaserTrigger LaserLength="2" LaserHitOffset="0.3">
                    <MineFront x="0" y="1" z="0" />
                    <LaserOffset x="0" y="0" z="0" />
                </SSensorMineLaserTrigger>
            </TriggerType>
        XML, explosionMinRadius: 0.5, physicalDamage: 210);

        $result = $mine->toArray();

        self::assertNotNull($result);
        self::assertEquals(2, $result['LaserLength']);
        self::assertEquals(5, $result['AreaOfEffect']);
        self::assertEquals(0.5, $result['MinAreaOfEffect']);
        self::assertEquals(210, $result['Damage']);
        self::assertEquals('Physical', $result['DamageType']);
        self::assertArrayNotHasKey('Radius', $result);
        self::assertArrayNotHasKey('WarningRadius', $result);
        self::assertArrayNotHasKey('AngleVertical', $result);
        self::assertArrayNotHasKey('AngleHorizontal', $result);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    private function makeSensorMine(
        string $triggerXml,
        float $explosionMinRadius = 0,
        float $explosionMaxRadius = 5,
        float $physicalDamage = 35,
    ): SensorMine {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.TEST_MINE __type="EntityClassDefinition" __ref="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa" __path="libs/foundry/records/entities/scitem/weapons/mines/test_mine.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponPersonal" SubType="Grenade" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                            <inventoryOccupancyDimensions x="1" y="1" z="1" />
                            <inventoryOccupancyVolume>
                                <SMicroCargoUnit microSCU="1" />
                            </inventoryOccupancyVolume>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SSensorMineComponentParams FriendlyFire="1">
                        {$triggerXml}
                    </SSensorMineComponentParams>
                    <SHealthComponentParams Health="1">
                        <DeathExplosionParams>
                            <ExplosionParams friendlyFire="None" minRadius="{$explosionMinRadius}" maxRadius="{$explosionMaxRadius}" soundRadius="50" minPhysRadius="1" maxPhysRadius="5" angle="100" angleVertical="180" hitType="explosion" pressure="850" holeSize="0" terrainHoleSize="3" maxblurdist="10" effectScale="1" useRandomScale="0" effectScaleMin="1" effectScaleMax="1" particleStrength="-1">
                                <damage>
                                    <DamageInfo DamagePhysical="{$physicalDamage}" DamageEnergy="0" DamageDistortion="0" DamageThermal="0" DamageBiochemical="0" DamageStun="0" />
                                </damage>
                                <explosionFront x="0" y="1" z="0" />
                                <explosionUp x="0" y="0" z="1" />
                            </ExplosionParams>
                        </DeathExplosionParams>
                    </SHealthComponentParams>
                </Components>
            </EntityClassDefinition.TEST_MINE>
        XML;

        $tmpFile = sys_get_temp_dir().'/sensor_mine_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, trim($xml));

        try {
            $doc = new EntityClassDefinition;
            $doc->load($tmpFile);

            return new SensorMine($doc);
        } finally {
            @unlink($tmpFile);
        }
    }
}
