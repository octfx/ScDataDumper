<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\EngineeringBoost;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Services\ManufacturerService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class EngineeringBoostTest extends ScDataTestCase
{
    private const MANUFACTURER_UUID = '11111111-1111-1111-1111-111111111111';

    private const MODIFIER_UUID = '89046b69-d94c-4204-9867-bd7fa0a87a0f';

    private const MODIFIER_CLASS = 'Engineering_Buff_Modifier_TEST';

    /**
     * @throws JsonException
     */
    public function test_engineering_boost_extracts_regen_modifiers(): void
    {
        $modifierPath = $this->writeModifierEntityFile();
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    self::MODIFIER_CLASS => $modifierPath,
                ],
                'SCItemManufacturer' => [
                    'FALLBACK' => $manufacturerPath,
                ],
            ],
            uuidToClassMap: [
                self::MANUFACTURER_UUID => 'FALLBACK',
                self::MODIFIER_UUID => self::MODIFIER_CLASS,
            ],
            classToUuidMap: [
                'FALLBACK' => self::MANUFACTURER_UUID,
                self::MODIFIER_CLASS => self::MODIFIER_UUID,
            ],
            uuidToPathMap: [
                self::MANUFACTURER_UUID => $manufacturerPath,
                self::MODIFIER_UUID => $modifierPath,
            ],
        );
        $this->writeLocalizationFile();
        $this->configureServiceFactory();

        // Load the modifier entity and test the format directly
        $modifierEntity = new EntityClassDefinition;
        $modifierEntity->load($modifierPath);

        $format = new EngineeringBoost($modifierEntity);
        $result = $format->toArray();

        self::assertNotNull($result);
        self::assertSame(self::MODIFIER_UUID, $result['UUID']);
        self::assertSame(2.9, $result['PowerRatio']);
        self::assertSame(4.0, $result['MaxAmmoLoad']);
        self::assertSame(1.5, $result['MaxRegenPerSec']);
    }

    /**
     * @throws JsonException
     */
    public function test_engineering_boost_returns_null_when_all_defaults(): void
    {
        $modifierPath = $this->writeModifierEntityFile(
            powerRatio: 1.0,
            maxAmmoLoad: 1.0,
            maxRegenPerSec: 1.0,
        );
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    self::MODIFIER_CLASS => $modifierPath,
                ],
                'SCItemManufacturer' => [
                    'FALLBACK' => $manufacturerPath,
                ],
            ],
            uuidToClassMap: [
                self::MANUFACTURER_UUID => 'FALLBACK',
                self::MODIFIER_UUID => self::MODIFIER_CLASS,
            ],
            classToUuidMap: [
                'FALLBACK' => self::MANUFACTURER_UUID,
                self::MODIFIER_CLASS => self::MODIFIER_UUID,
            ],
            uuidToPathMap: [
                self::MANUFACTURER_UUID => $manufacturerPath,
                self::MODIFIER_UUID => $modifierPath,
            ],
        );
        $this->writeLocalizationFile();
        $this->configureServiceFactory();

        $modifierEntity = new EntityClassDefinition;
        $modifierEntity->load($modifierPath);

        $format = new EngineeringBoost($modifierEntity);
        $result = $format->toArray();

        self::assertNull($result);
    }

    /**
     * @throws JsonException
     */
    public function test_ship_includes_engineering_boost_when_port_present(): void
    {
        $modifierPath = $this->writeModifierEntityFile();
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    self::MODIFIER_CLASS => $modifierPath,
                ],
                'SCItemManufacturer' => [
                    'FALLBACK' => $manufacturerPath,
                ],
            ],
            uuidToClassMap: [
                self::MANUFACTURER_UUID => 'FALLBACK',
                self::MODIFIER_UUID => self::MODIFIER_CLASS,
            ],
            classToUuidMap: [
                'FALLBACK' => self::MANUFACTURER_UUID,
                self::MODIFIER_CLASS => self::MODIFIER_UUID,
            ],
            uuidToPathMap: [
                self::MANUFACTURER_UUID => $manufacturerPath,
                self::MODIFIER_UUID => $modifierPath,
            ],
        );
        $this->writeLocalizationFile();
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityWithEngineeringBuff());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, []));
        $result = $ship->toArray();

        self::assertArrayHasKey('EngineeringBoost', $result);
        self::assertSame(2.9, $result['EngineeringBoost']['PowerRatio']);
        self::assertSame(4.0, $result['EngineeringBoost']['MaxAmmoLoad']);
        self::assertSame(1.5, $result['EngineeringBoost']['MaxRegenPerSec']);
    }

    /**
     * @throws JsonException
     */
    public function test_ship_omits_engineering_boost_when_port_absent(): void
    {
        $manufacturerPath = $this->writeManufacturerFile();
        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [],
                'SCItemManufacturer' => [
                    'FALLBACK' => $manufacturerPath,
                ],
            ],
            uuidToClassMap: [
                self::MANUFACTURER_UUID => 'FALLBACK',
            ],
            classToUuidMap: [
                'FALLBACK' => self::MANUFACTURER_UUID,
            ],
            uuidToPathMap: [
                self::MANUFACTURER_UUID => $manufacturerPath,
            ],
        );
        $this->writeLocalizationFile();
        $this->configureServiceFactory();

        $entity = new VehicleDefinition;
        $entity->load($this->writeVehicleEntityFile());

        $vehicle = new Vehicle;
        $vehicle->load($this->writeVehicleImplementationFile());

        $ship = new Ship(new VehicleWrapper($vehicle, $entity, []));
        $result = $ship->toArray();

        self::assertArrayNotHasKey('EngineeringBoost', $result);
    }

    private function writeModifierEntityFile(
        float $powerRatio = 2.9,
        float $maxAmmoLoad = 4.0,
        float $maxRegenPerSec = 1.5,
    ): string {
        $uuid = self::MODIFIER_UUID;
        $className = self::MODIFIER_CLASS;

        return $this->writeFile(
            'records/modifier/engineering_buff_test.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.{$className} __type="EntityClassDefinition" __ref="{$uuid}" __path="libs/foundry/records/entities/scitem/ships/utility/engineering/engineering_buff_test.xml">
                <Components>
                    <EntityComponentAttachableModifierParams activationMethod="ActivateOnAttach" __type="DataForgeComponentParams">
                        <modifiers>
                            <ItemportTraversingModifiersParams>
                                <modifiers>
                                    <ItemWeaponModifiersParams targetType="WeaponGun">
                                        <weaponModifier>
                                            <weaponStats>
                                                <regenModifier powerRatioMultiplier="{$powerRatio}" maxAmmoLoadMultiplier="{$maxAmmoLoad}" maxRegenPerSecMultiplier="{$maxRegenPerSec}" />
                                            </weaponStats>
                                        </weaponModifier>
                                    </ItemWeaponModifiersParams>
                                </modifiers>
                            </ItemportTraversingModifiersParams>
                        </modifiers>
                    </EntityComponentAttachableModifierParams>
                </Components>
            </EntityClassDefinition.{$className}>
            XML
        );
    }

    private function writeManufacturerFile(): string
    {
        return $this->writeFallbackManufacturerFile();
    }

    private function writeVehicleEntityWithEngineeringBuff(): string
    {
        $modifierUuid = self::MODIFIER_UUID;
        $manufacturerUuid = self::MANUFACTURER_UUID;

        return $this->writeFile(
            'records/entity/test_ship_engbuff.xml',
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP_ENGBUFF __type="EntityClassDefinition" __ref="ship-engbuff-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship_engbuff.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="3" manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" vehicleCareer="@vehicle_career" vehicleRole="@vehicle_role" />
                    <SItemPortContainerComponentParams>
                        <Ports>
                            <SItemPortDef Name="engineeringBuff">
                                <defaultItem entityClass="{$modifierUuid}" />
                            </SItemPortDef>
                        </Ports>
                    </SItemPortContainerComponentParams>
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="{$manufacturerUuid}" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.TEST_SHIP_ENGBUFF>
            XML
        );
    }

    private function writeVehicleEntityFile(): string
    {
        return $this->writeFile(
            'records/entity/test_ship_noengbuff.xml',
            <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <VehicleDefinition.TEST_SHIP_NOENGBUFF __type="EntityClassDefinition" __ref="ship-noengbuff-uuid" __path="libs/foundry/records/entityclassdefinition/test_ship_noengbuff.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Vehicle" SubType="Ship" Size="1" manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization Name="@vehicle_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <VehicleComponentParams vehicleName="@vehicle_name" vehicleDescription="@vehicle_description" vehicleCareer="@vehicle_career" vehicleRole="@vehicle_role" />
                </Components>
                <StaticEntityClassData>
                    <SEntityInsuranceProperties>
                        <displayParams manufacturer="11111111-1111-1111-1111-111111111111" />
                    </SEntityInsuranceProperties>
                </StaticEntityClassData>
            </VehicleDefinition.TEST_SHIP_NOENGBUFF>
            XML
        );
    }

    private function writeVehicleImplementationFile(): string
    {
        return $this->writeStandardVehicleImplementationFile();
    }

    private function writeLocalizationFile(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            "manufacturer_name=Fallback Industries\nvehicle_name=Test Ship\nvehicle_description=Test description\nvehicle_career=Combat\nvehicle_role=Patrol\nLOC_EMPTY="
        );
    }

    /**
     * @throws JsonException
     */
    private function configureServiceFactory(): void
    {
        $manufacturerService = new ManufacturerService($this->tempDir);
        $manufacturerService->initialize();

        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $localizationService = new LocalizationService($this->tempDir);
        $localizationService->initialize();

        $this->setPrivateProperty(ServiceFactory::class, 'initialized', true);
        $this->setPrivateProperty(ServiceFactory::class, 'activeScDataPath', $this->tempDir);
        $this->setPrivateProperty(ServiceFactory::class, 'services', [
            'ManufacturerService' => $manufacturerService,
            'ItemService' => $itemService,
            'LocalizationService' => $localizationService,
        ]);
    }
}
