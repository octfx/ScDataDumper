<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item;
use Octfx\ScDataDumper\Formats\ScUnpacked\WeaponAttachment;
use Octfx\ScDataDumper\Services\LoadoutFileService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use ReflectionClass;

final class TypedHelperMigrationTest extends ScDataTestCase
{
    public function test_item_ports_use_typed_default_loadout_entries_from_xml_loadout_path(): void
    {
        $installedItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/installed_item.xml',
            <<<'XML'
            <EntityClassDefinition.INSTALLED_ITEM __type="EntityClassDefinition" __ref="uuid-installed-item" __path="libs/foundry/records/entities/items/installed_item.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Bed" SubType="Captain">
                    <Localization>
                      <English Name="Installed Item" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.INSTALLED_ITEM>
            XML
        );
        $hostItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/host_item.xml',
            <<<'XML'
            <EntityClassDefinition.HOST_ITEM __type="EntityClassDefinition" __ref="uuid-host-item" __path="libs/foundry/records/entities/items/host_item.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Seat" SubType="Pilot">
                    <Localization>
                      <English Name="Host Item" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SItemPortContainerComponentParams>
                  <Ports>
                    <SItemPortDef Name="BedPort" DisplayName="BedPort" MaxSize="1" MinSize="1">
                      <Types>
                        <Type Type="Bed" SubTypes="Captain" />
                      </Types>
                    </SItemPortDef>
                  </Ports>
                </SItemPortContainerComponentParams>
                <SEntityComponentDefaultLoadoutParams>
                  <loadout>
                    <SItemPortLoadoutXMLParams loadoutPath="Scripts/Loadouts/item_loadout.xml" />
                  </loadout>
                </SEntityComponentDefaultLoadoutParams>
              </Components>
            </EntityClassDefinition.HOST_ITEM>
            XML
        );
        $this->writeFile(
            'Data/Scripts/Loadouts/item_loadout.xml',
            <<<'XML'
            <Loadout>
              <Items>
                <item portName="BedPort" itemName="INSTALLED_ITEM" />
              </Items>
            </Loadout>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'HOST_ITEM' => $hostItemPath,
                    'INSTALLED_ITEM' => $installedItemPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-host-item' => 'HOST_ITEM',
                'uuid-installed-item' => 'INSTALLED_ITEM',
            ],
            classToUuidMap: [
                'HOST_ITEM' => 'uuid-host-item',
                'INSTALLED_ITEM' => 'uuid-installed-item',
            ],
            uuidToPathMap: [
                'uuid-host-item' => $hostItemPath,
                'uuid-installed-item' => $installedItemPath,
            ],
        );

        $this->initializeMinimalItemServices();
        $this->initializeLoadoutFileService();

        $host = new EntityClassDefinition;
        $host->load($hostItemPath);

        $formatted = (new Item($host))->toArray();

        self::assertSame('uuid-installed-item', $formatted['stdItem']['Ports'][0]['EquippedItem']);
    }

    public function test_weapon_attachment_flashlight_modes_use_typed_default_loadout_entries(): void
    {
        $lightModePath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/light_mode_narrow.xml',
            <<<'XML'
            <EntityClassDefinition.LIGHT_MODE_NARROW __type="EntityClassDefinition" __ref="uuid-light-mode" __path="libs/foundry/records/entities/items/light_mode_narrow.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="Light" SubType="Mode">
                    <Localization>
                      <English Name="Light Mode Narrow" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <LightComponentParams lightType="Spot" useTemperature="1">
                  <sizeParams lightRadius="35" />
                  <defaultState intensity="12" temperature="6500">
                    <color>
                      <RGB r="1" g="0.5" b="0.25" />
                    </color>
                  </defaultState>
                </LightComponentParams>
              </Components>
            </EntityClassDefinition.LIGHT_MODE_NARROW>
            XML
        );
        $attachmentPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/utility_attachment.xml',
            <<<'XML'
            <EntityClassDefinition.UTILITY_ATTACHMENT __type="EntityClassDefinition" __ref="uuid-attachment" __path="libs/foundry/records/entities/items/utility_attachment.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAttachment" SubType="Utility">
                    <Localization>
                      <English Name="Utility Attachment" Description="Utility Attachment" />
                    </Localization>
                  </AttachDef>
                </SAttachableComponentParams>
                <SEntityComponentDefaultLoadoutParams>
                  <loadout>
                    <SItemPortLoadoutXMLParams loadoutPath="Scripts/Loadouts/attachment_loadout.xml" />
                  </loadout>
                </SEntityComponentDefaultLoadoutParams>
              </Components>
            </EntityClassDefinition.UTILITY_ATTACHMENT>
            XML
        );
        $this->writeFile(
            'Data/Scripts/Loadouts/attachment_loadout.xml',
            <<<'XML'
            <Loadout>
              <Items>
                <item portName="mode_narrow" itemName="LIGHT_MODE_NARROW" />
              </Items>
            </Loadout>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'UTILITY_ATTACHMENT' => $attachmentPath,
                    'LIGHT_MODE_NARROW' => $lightModePath,
                ],
            ],
            uuidToClassMap: [
                'uuid-attachment' => 'UTILITY_ATTACHMENT',
                'uuid-light-mode' => 'LIGHT_MODE_NARROW',
            ],
            classToUuidMap: [
                'UTILITY_ATTACHMENT' => 'uuid-attachment',
                'LIGHT_MODE_NARROW' => 'uuid-light-mode',
            ],
            uuidToPathMap: [
                'uuid-attachment' => $attachmentPath,
                'uuid-light-mode' => $lightModePath,
            ],
        );

        $this->initializeMinimalItemServices();
        $this->initializeLoadoutFileService();

        $attachment = new EntityClassDefinition;
        $attachment->load($attachmentPath);

        $formatted = (new WeaponAttachment($attachment))->toArray();

        self::assertCount(1, $formatted['Flashlight']);
        self::assertSame('LIGHT_MODE_NARROW', $formatted['Flashlight'][0]['ClassName']);
        self::assertSame('mode_narrow', $formatted['Flashlight'][0]['PortName']);
        self::assertSame('Narrow', $formatted['Flashlight'][0]['Name']);
    }

    public function test_weapon_attachment_uses_localized_description_when_english_description_is_missing(): void
    {
        $attachmentPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/utility_attachment_localized.xml',
            <<<'XML'
            <EntityClassDefinition.UTILITY_ATTACHMENT_LOCALIZED __type="EntityClassDefinition" __ref="uuid-attachment-localized" __path="libs/foundry/records/entities/items/utility_attachment_localized.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAttachment" SubType="Utility">
                    <Localization Name="@utility_attachment_name" Description="@utility_attachment_description" />
                  </AttachDef>
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.UTILITY_ATTACHMENT_LOCALIZED>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'UTILITY_ATTACHMENT_LOCALIZED' => $attachmentPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-attachment-localized' => 'UTILITY_ATTACHMENT_LOCALIZED',
            ],
            classToUuidMap: [
                'UTILITY_ATTACHMENT_LOCALIZED' => 'uuid-attachment-localized',
            ],
            uuidToPathMap: [
                'uuid-attachment-localized' => $attachmentPath,
            ],
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'utility_attachment_name' => 'Localized Utility Attachment',
            'utility_attachment_description' => "Class: Medical\n\nSpecialized utility mount.",
        ]);

        $attachment = new EntityClassDefinition;
        $attachment->load($attachmentPath);

        $formatted = (new WeaponAttachment($attachment))->toArray();

        self::assertSame('Medical', $formatted['UtilityClass']);
    }

    private function initializeLoadoutFileService(): void
    {
        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        $factory = new ReflectionClass(ServiceFactory::class);
        $services = $factory->getProperty('services')->getValue();
        $services['LoadoutFileService'] = $service;
        $factory->getProperty('services')->setValue(null, $services);
    }
}
