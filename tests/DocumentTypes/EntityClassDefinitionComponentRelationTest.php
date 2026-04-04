<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\MiningLaser;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class EntityClassDefinitionComponentRelationTest extends ScDataTestCase
{
    private const ENTITY_UUID = '10000000-0000-0000-0000-000000000001';

    private const MAGAZINE_UUID = '10000000-0000-0000-0000-000000000002';

    private const AMMO_UUID = '10000000-0000-0000-0000-000000000003';

    private const SECONDARY_AMMO_UUID = '10000000-0000-0000-0000-000000000004';

    private const RADAR_UUID = '10000000-0000-0000-0000-000000000005';

    private const LASER_UUID = '10000000-0000-0000-0000-000000000006';

    private const MELEE_UUID = '10000000-0000-0000-0000-000000000007';

    private const DAMAGE_RESISTANCE_UUID = '10000000-0000-0000-0000-000000000008';

    private const CONTAINER_UUID = '10000000-0000-0000-0000-000000000009';

    private const MANUFACTURER_UUID = '10000000-0000-0000-0000-000000000010';

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $entityPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/test_component_item.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_COMPONENT_ITEM __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/items/test_component_item.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponMining" SubType="Rifle" Size="2" Manufacturer="10000000-0000-0000-0000-000000000010">
                    <Localization>
                      <English Name="Test Component Item" Description="" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SCItemWeaponComponentParams ammoContainerRecord="10000000-0000-0000-0000-000000000002">
                  <fireActions>
                    <SWeaponActionFireBeamParams hitType="Fracture" fullDamageRange="10" zeroDamageRange="20" hitRadius="2">
                      <damagePerSecond>
                        <DamageInfo DamagePhysical="4" />
                      </damagePerSecond>
                    </SWeaponActionFireBeamParams>
                  </fireActions>
                </SCItemWeaponComponentParams>
                <SAmmoContainerComponentParams ammoParamsRecord="10000000-0000-0000-0000-000000000003" secondaryAmmoParamsRecord="10000000-0000-0000-0000-000000000004" initialAmmoCount="3" maxAmmoCount="8" />
                <SCItemRadarComponentParams sharedParams="10000000-0000-0000-0000-000000000005" />
                <SEntityComponentMiningLaserParams globalParams="10000000-0000-0000-0000-000000000006" throttleMinimum="0.5" usesPowerThrottle="1" />
                <SMeleeWeaponComponentParams meleeCombatConfig="10000000-0000-0000-0000-000000000007" />
                <SCItemSuitArmorParams damageResistance="10000000-0000-0000-0000-000000000008">
                  <protectedBodyParts>
                    <BodyPart value="Chest" />
                  </protectedBodyParts>
                </SCItemSuitArmorParams>
                <SCItemInventoryContainerComponentParams containerParams="10000000-0000-0000-0000-000000000009" />
              </Components>
            </EntityClassDefinition.TEST_COMPONENT_ITEM>
            XML
        );

        $magazinePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/test_magazine.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_MAGAZINE __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000002" __path="libs/foundry/records/entities/items/test_magazine.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAttachment" SubType="Magazine" Size="1" Manufacturer="10000000-0000-0000-0000-000000000010">
                    <Localization>
                      <English Name="Test Magazine" Description="" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SAmmoContainerComponentParams ammoParamsRecord="10000000-0000-0000-0000-000000000003" initialAmmoCount="5" maxAmmoCount="10" />
              </Components>
            </EntityClassDefinition.TEST_MAGAZINE>
            XML
        );

        $manufacturerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/scitemmanufacturer/test_manufacturer.xml',
            <<<'XML'
            <SCItemManufacturer.TEST_MANUFACTURER Code="TST" __type="SCItemManufacturer" __ref="10000000-0000-0000-0000-000000000010" __path="libs/foundry/records/scitemmanufacturer/test_manufacturer.xml">
              <Localization>
                <English Name="Test Manufacturer" />
              </Localization>
            </SCItemManufacturer.TEST_MANUFACTURER>
            XML
        );

        $containerPath = $this->writeFile(
            'Data/Libs/Foundry/Records/inventorycontainers/test_container.xml',
            <<<'XML'
            <InventoryContainer.TEST_CONTAINER __type="InventoryContainer" __ref="10000000-0000-0000-0000-000000000009" __path="libs/foundry/records/inventorycontainers/test_container.xml">
              <interiorDimensions x="1" y="2" z="3" />
              <inventoryType>
                <InventoryClosedContainerType>
                  <capacity>
                    <SStandardCargoUnit standardCargoUnits="2" />
                  </capacity>
                </InventoryClosedContainerType>
              </inventoryType>
            </InventoryContainer.TEST_CONTAINER>
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/ammoparams/test_ammo.xml',
            <<<'XML'
            <AmmoParams.TEST_AMMO size="1" lifetime="2" speed="500" __type="AmmoParams" __ref="10000000-0000-0000-0000-000000000003" __path="libs/foundry/records/ammoparams/test_ammo.xml">
              <projectileParams>
                <BulletProjectileParams>
                  <damage>
                    <DamageInfo DamagePhysical="10" />
                  </damage>
                </BulletProjectileParams>
              </projectileParams>
            </AmmoParams.TEST_AMMO>
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/ammoparams/test_secondary_ammo.xml',
            <<<'XML'
            <AmmoParams.TEST_SECONDARY_AMMO size="1" lifetime="1" speed="300" __type="AmmoParams" __ref="10000000-0000-0000-0000-000000000004" __path="libs/foundry/records/ammoparams/test_secondary_ammo.xml" />
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/radarsystem/test_radar.xml',
            <<<'XML'
            <RadarSystemSharedParams.TEST_RADAR __type="RadarSystemSharedParams" __ref="10000000-0000-0000-0000-000000000005" __path="libs/foundry/records/radarsystem/test_radar.xml">
              <pingProperties cooldownTime="7" />
              <signatureDetection>
                <SignatureDetection sensitivity="1" piercing="2" />
                <SignatureDetection sensitivity="3" piercing="4" />
                <SignatureDetection sensitivity="5" piercing="6" />
                <SignatureDetection sensitivity="7" piercing="8" />
                <SignatureDetection sensitivity="9" piercing="10" />
              </signatureDetection>
              <sensitivityModifiers>
                <SCItemRadarSensitivityModifier sensitivityAddition="0" />
              </sensitivityModifiers>
            </RadarSystemSharedParams.TEST_RADAR>
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/mining/lasers/test_laser.xml',
            <<<'XML'
            <MiningLaserGlobalParams.TEST_LASER throttleHoldAccFactor="0.25" __type="MiningLaserGlobalParams" __ref="10000000-0000-0000-0000-000000000006" __path="libs/foundry/records/mining/lasers/test_laser.xml" />
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/melee/test_melee.xml',
            <<<'XML'
            <MeleeCombatConfig.TEST_MELEE __type="MeleeCombatConfig" __ref="10000000-0000-0000-0000-000000000007" __path="libs/foundry/records/melee/test_melee.xml">
              <attackCategoryParams>
                <AttackCategory windupTime="0.25">
                  <damageInfo>
                    <DamageInfo DamagePhysical="12" />
                  </damageInfo>
                </AttackCategory>
              </attackCategoryParams>
            </MeleeCombatConfig.TEST_MELEE>
            XML
        );

        $this->writeFile(
            'Game2/libs/foundry/records/armor/test_damage_resistance.xml',
            <<<'XML'
            <DamageResistanceMacro.TEST_ARMOR __type="DamageResistanceMacro" __ref="10000000-0000-0000-0000-000000000008" __path="libs/foundry/records/armor/test_damage_resistance.xml" />
            XML
        );

        $this->writeCacheFiles(
            classToTypeMap: [
                'TEST_CONTAINER' => 'InventoryContainer',
            ],
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TEST_COMPONENT_ITEM' => $entityPath,
                    'TEST_MAGAZINE' => $magazinePath,
                ],
                'InventoryContainer' => [
                    'TEST_CONTAINER' => $containerPath,
                ],
                'AmmoParams' => [
                    'TEST_AMMO' => $this->tempDir.'/Game2/libs/foundry/records/ammoparams/test_ammo.xml',
                    'TEST_SECONDARY_AMMO' => $this->tempDir.'/Game2/libs/foundry/records/ammoparams/test_secondary_ammo.xml',
                ],
                'RadarSystemSharedParams' => [
                    'TEST_RADAR' => $this->tempDir.'/Game2/libs/foundry/records/radarsystem/test_radar.xml',
                ],
                'MiningLaserGlobalParams' => [
                    'TEST_LASER' => $this->tempDir.'/Game2/libs/foundry/records/mining/lasers/test_laser.xml',
                ],
                'MeleeCombatConfig' => [
                    'TEST_MELEE' => $this->tempDir.'/Game2/libs/foundry/records/melee/test_melee.xml',
                ],
                'DamageResistanceMacro' => [
                    'TEST_ARMOR' => $this->tempDir.'/Game2/libs/foundry/records/armor/test_damage_resistance.xml',
                ],
            ],
            uuidToClassMap: [
                self::ENTITY_UUID => 'TEST_COMPONENT_ITEM',
                self::MAGAZINE_UUID => 'TEST_MAGAZINE',
                self::AMMO_UUID => 'TEST_AMMO',
                self::SECONDARY_AMMO_UUID => 'TEST_SECONDARY_AMMO',
                self::RADAR_UUID => 'TEST_RADAR',
                self::LASER_UUID => 'TEST_LASER',
                self::MELEE_UUID => 'TEST_MELEE',
                self::DAMAGE_RESISTANCE_UUID => 'TEST_ARMOR',
                self::CONTAINER_UUID => 'TEST_CONTAINER',
                self::MANUFACTURER_UUID => 'TEST_MANUFACTURER',
            ],
            classToUuidMap: [
                'TEST_COMPONENT_ITEM' => self::ENTITY_UUID,
                'TEST_MAGAZINE' => self::MAGAZINE_UUID,
                'TEST_AMMO' => self::AMMO_UUID,
                'TEST_SECONDARY_AMMO' => self::SECONDARY_AMMO_UUID,
                'TEST_RADAR' => self::RADAR_UUID,
                'TEST_LASER' => self::LASER_UUID,
                'TEST_MELEE' => self::MELEE_UUID,
                'TEST_ARMOR' => self::DAMAGE_RESISTANCE_UUID,
                'TEST_CONTAINER' => self::CONTAINER_UUID,
                'TEST_MANUFACTURER' => self::MANUFACTURER_UUID,
            ],
            uuidToPathMap: [
                self::ENTITY_UUID => $entityPath,
                self::MAGAZINE_UUID => $magazinePath,
                self::AMMO_UUID => $this->tempDir.'/Game2/libs/foundry/records/ammoparams/test_ammo.xml',
                self::SECONDARY_AMMO_UUID => $this->tempDir.'/Game2/libs/foundry/records/ammoparams/test_secondary_ammo.xml',
                self::RADAR_UUID => $this->tempDir.'/Game2/libs/foundry/records/radarsystem/test_radar.xml',
                self::LASER_UUID => $this->tempDir.'/Game2/libs/foundry/records/mining/lasers/test_laser.xml',
                self::MELEE_UUID => $this->tempDir.'/Game2/libs/foundry/records/melee/test_melee.xml',
                self::DAMAGE_RESISTANCE_UUID => $this->tempDir.'/Game2/libs/foundry/records/armor/test_damage_resistance.xml',
                self::CONTAINER_UUID => $containerPath,
                self::MANUFACTURER_UUID => $manufacturerPath,
            ],
        );

        $this->initializeBlueprintDefinitionServices();
    }

    public function test_resolves_component_relations_when_reference_hydration_is_disabled(): void
    {
        $document = (new EntityClassDefinition)
            ->setReferenceHydrationEnabled(false);
        $document->load($this->tempDir.'/Data/Libs/Foundry/Records/entities/items/test_component_item.xml');

        self::assertSame(self::MAGAZINE_UUID, $document->getMagazine()?->getUuid());
        self::assertSame(self::AMMO_UUID, $document->getAmmoParams()?->getUuid());
        self::assertSame(self::SECONDARY_AMMO_UUID, $document->getSecondaryAmmoParams()?->getUuid());
        self::assertSame(self::RADAR_UUID, $document->getRadarSystem()?->getUuid());
        self::assertSame(self::LASER_UUID, $document->getMiningLaserGlobalParams()?->getUuid());
        self::assertSame(self::MELEE_UUID, $document->getMeleeCombatConfig()?->getUuid());
        self::assertSame(self::DAMAGE_RESISTANCE_UUID, $document->getDamageResistance()?->getUuid());
        self::assertSame(self::CONTAINER_UUID, $document->getInventoryContainer()?->getUuid());
        self::assertSame(self::MANUFACTURER_UUID, $document->getManufacturer()?->getUuid());
    }

    public function test_formats_mining_laser_global_params_without_fatal_error(): void
    {
        $document = new EntityClassDefinition;
        $document->load($this->tempDir.'/Data/Libs/Foundry/Records/entities/items/test_component_item.xml');

        $formatted = (new MiningLaser($document))->toArray();

        self::assertSame(4.0, $formatted['PowerTransfer'] ?? null);
        self::assertSame(1.0, $formatted['MinPowerTransfer'] ?? null);
        self::assertSame(0.25, $formatted['GlobalParams']['ThrottleHoldAccFactor'] ?? null);
    }
}
