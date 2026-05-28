<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Grenade;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class GrenadeTest extends ScDataTestCase
{
    // ------------------------------------------------------------------ //
    //  Pure explosion grenade (no hazard zone)                            //
    // ------------------------------------------------------------------ //

    public function test_simple_explosion_grenade(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $grenade = $this->makeGrenade(
            explosionMaxRadius: 7,
            explosionMinRadius: 0.25,
            physicalDamage: 14,
        );

        $result = $grenade->toArray();

        self::assertNotNull($result);
        self::assertEquals(7, $result['AreaOfEffect']);
        self::assertEquals(0.25, $result['MinAreaOfEffect']);
        self::assertEquals(14, $result['Damage']);
        self::assertEquals('Physical', $result['DamageType']);
        self::assertArrayNotHasKey('DamagePerTick', $result);
        self::assertArrayNotHasKey('DamagePeriod', $result);
        self::assertArrayNotHasKey('Duration', $result);
        self::assertArrayNotHasKey('IgnoreShields', $result);
    }

    // ------------------------------------------------------------------ //
    //  Hazard zone grenade (ksar plasma)                                  //
    // ------------------------------------------------------------------ //

    public function test_hazard_zone_grenade_uses_hazard_data(): void
    {
        $hazardUuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $this->setupServicesWithHazard($hazardUuid, thermalDamage: 10, damagePeriod: 0.4, radius: 4.25, duration: 10, ignoreShields: 1);

        $grenade = $this->makeGrenade(
            explosionMaxRadius: 5.5,
            explosionMinRadius: 4,
            thermalDamage: 2,
            spawnEntityUuid: $hazardUuid,
        );

        $result = $grenade->toArray();

        self::assertNotNull($result);
        // Hazard zone overrides explosion data
        self::assertEquals(4.25, $result['AreaOfEffect']);
        self::assertArrayNotHasKey('MinAreaOfEffect', $result);
        // Total potential damage: 10 * (10 / 0.4) = 250
        self::assertEquals(250, $result['Damage']);
        self::assertEquals('Thermal', $result['DamageType']);
        self::assertEquals(10, $result['DamagePerTick']);
        self::assertEquals(0.4, $result['DamagePeriod']);
        self::assertEquals(10, $result['Duration']);
        self::assertTrue($result['IgnoreShields']);
    }

    public function test_hazard_zone_grenade_total_damage_calculation(): void
    {
        $hazardUuid = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $this->setupServicesWithHazard($hazardUuid, thermalDamage: 5, damagePeriod: 0.5, radius: 3, duration: 6, ignoreShields: 0);

        $grenade = $this->makeGrenade(
            explosionMaxRadius: 4,
            thermalDamage: 1,
            spawnEntityUuid: $hazardUuid,
        );

        $result = $grenade->toArray();

        self::assertNotNull($result);
        // 5 * (6 / 0.5) = 60
        self::assertEquals(60, $result['Damage']);
        self::assertEquals(5, $result['DamagePerTick']);
        self::assertEquals(0.5, $result['DamagePeriod']);
        self::assertEquals(6, $result['Duration']);
        self::assertFalse($result['IgnoreShields']);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    private function makeGrenade(
        float $explosionMaxRadius = 7,
        float $explosionMinRadius = 0.25,
        float $physicalDamage = 0,
        float $thermalDamage = 0,
        ?string $spawnEntityUuid = null,
    ): Grenade {
        $spawnXml = '';
        if ($spawnEntityUuid !== null) {
            $spawnXml = <<<XML
                <STriggerableDevicesTriggerTimerParams name="Hazard Area" isAuthoritative="0" markerModelPath="" duration="0">
                    <blinkingParams enabled="0" minFrequency="2" maxFrequency="10" rampUpTime="0">
                        <lightEffectLink minRange="0" maxRange="1" />
                        <glowEffectLink minRange="0" maxRange="1" />
                    </blinkingParams>
                    <behavior>
                        <STriggerableDevicesBehaviorSpawnEntityParams name="Hazard Area" shouldBeDestroyed="1" entityToSpawn="{$spawnEntityUuid}" />
                    </behavior>
                </STriggerableDevicesTriggerTimerParams>
            XML;
        }

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.TEST_GRENADE __type="EntityClassDefinition" __ref="aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa" __path="libs/foundry/records/entities/scitem/weapons/throwable/test_grenade.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="WeaponPersonal" SubType="Grenade" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                            <inventoryOccupancyDimensions x="1" y="1" z="1" />
                            <inventoryOccupancyLocalBoundsMin x="-0.5" y="-0.5" z="0" />
                            <inventoryOccupancyLocalBoundsMax x="0.5" y="0.5" z="1" />
                            <inventoryOccupancyVolume>
                                <SMicroCargoUnit microSCU="1" />
                            </inventoryOccupancyVolume>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <EntityComponentTriggerableDevicesParams>
                        <triggers>
                            <STriggerableDevicesTriggerImpactParams name="Pre explosion" isAuthoritative="0" markerModelPath="" ignoreWaterCollision="1">
                                <blinkingParams enabled="0" minFrequency="2" maxFrequency="10" rampUpTime="0">
                                    <lightEffectLink minRange="0" maxRange="1" />
                                    <glowEffectLink minRange="0" maxRange="1" />
                                </blinkingParams>
                                <behavior>
                                    <STriggerableDevicesBehaviorExplosionParams name="Explosion" shouldBeDestroyed="0">
                                        <explosionParams friendlyFire="None" minRadius="{$explosionMinRadius}" maxRadius="{$explosionMaxRadius}" soundRadius="150" minPhysRadius="1" maxPhysRadius="9" angle="0" angleVertical="0" hitType="explosion" pressure="5" holeSize="0" terrainHoleSize="3" maxblurdist="10" effectScale="1" useRandomScale="0" effectScaleMin="1" effectScaleMax="1" particleStrength="-1">
                                            <damage>
                                                <DamageInfo DamagePhysical="{$physicalDamage}" DamageEnergy="0" DamageDistortion="0" DamageThermal="{$thermalDamage}" DamageBiochemical="0" DamageStun="0" />
                                            </damage>
                                            <Offset x="0" y="0" z="0" />
                                            <Direction x="0" y="0" z="1" />
                                        </explosionParams>
                                    </STriggerableDevicesBehaviorExplosionParams>
                                </behavior>
                            </STriggerableDevicesTriggerImpactParams>
                            {$spawnXml}
                        </triggers>
                    </EntityComponentTriggerableDevicesParams>
                </Components>
            </EntityClassDefinition.TEST_GRENADE>
        XML;

        $tmpFile = sys_get_temp_dir().'/grenade_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, trim($xml));

        try {
            $doc = new EntityClassDefinition;
            $doc->load($tmpFile);

            return new Grenade($doc);
        } finally {
            @unlink($tmpFile);
        }
    }

    private function setupServicesWithHazard(string $hazardUuid, float $thermalDamage, float $damagePeriod, float $radius, float $duration, int $ignoreShields): void
    {
        // Write the hazard zone entity XML and register it in all cache maps
        $normalizedUuid = strtolower($hazardUuid);
        $xml = $this->buildHazardZoneXml($hazardUuid, $thermalDamage, $damagePeriod, $radius, $duration, $ignoreShields);
        $path = $this->writeFile("Data/Libs/Foundry/Records/entities/hazardzones/{$normalizedUuid}.xml", $xml);

        $this->writeCacheFiles(
            uuidToClassMap: [$normalizedUuid => 'HazardZone_Test'],
            classToUuidMap: ['HazardZone_Test' => $normalizedUuid],
            uuidToPathMap: [$normalizedUuid => $path],
            classToPathMap: ['EntityClassDefinition' => ['HazardZone_Test' => $path]],
        );

        $this->initializeMinimalItemServices();
    }

    private function buildHazardZoneXml(string $uuid, float $thermalDamage, float $damagePeriod, float $radius, float $duration, int $ignoreShields): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.HazardZone_Test __type="EntityClassDefinition" __ref="{$uuid}" __path="libs/foundry/records/entities/hazardzones/test_hazard.xml">
                <Components>
                    <SInteractionStateMachineParams>
                        <StateTypes>
                            <SInteractionStateType StateTypeName="Default" bindingsMethod="None" asopReset="0" networkAuthority="Server">
                                <DefaultState value="SInteractionState[0A26]" />
                                <States>
                                    <SInteractionState StateName="Default">
                                        <StateAutoChange>
                                            <SStateAutoChange Delay="{$duration}" RunWhenStreamedOut="1">
                                                <NextState value="SInteractionState[0A27]" />
                                            </SStateAutoChange>
                                        </StateAutoChange>
                                    </SInteractionState>
                                    <SInteractionState StateName="DestroySelf" />
                                </States>
                            </SInteractionStateType>
                        </StateTypes>
                    </SInteractionStateMachineParams>
                    <HazardComponentParams damagePeriod="{$damagePeriod}" ignoreShields="{$ignoreShields}" useRadialFalloff="0" falloffStartRadius="0" ignoreVerticalFalloff="0" tagListBehavior="OneTagWillExempt">
                        <damagePerHit>
                            <DamageInfo DamagePhysical="0" DamageEnergy="0" DamageDistortion="0" DamageThermal="{$thermalDamage}" DamageBiochemical="0" DamageStun="0" />
                        </damagePerHit>
                        <hazardAreaShape>
                            <SSphereHazardAreaShapeParams radius="{$radius}" />
                        </hazardAreaShape>
                    </HazardComponentParams>
                </Components>
            </EntityClassDefinition.HazardZone_Test>
        XML;
    }
}
