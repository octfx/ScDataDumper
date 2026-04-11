<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use JsonException;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\LoadoutFileService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\LoadoutBuilder;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use ReflectionClass;

final class LoadoutBuilderTest extends ScDataTestCase
{
    /**
     * @throws JsonException
     */
    public function test_build_merges_primary_nested_entries_over_item_default_loadout(): void
    {
        $seatPath = $this->writeItemFixture(
            'SEAT_PARENT',
            'uuid-seat-parent',
            'Seat',
            'Pilot',
            'Pilot Seat',
            25.0,
            [
                ['name' => 'BedPort', 'type' => 'Bed', 'subType' => 'Captain'],
                ['name' => 'StoragePort', 'type' => 'Container', 'subType' => 'Cargo'],
            ],
            [
                ['portName' => 'BedPort', 'className' => 'BED_DEFAULT'],
                ['portName' => 'StoragePort', 'className' => 'STORAGE_DEFAULT'],
            ]
        );
        $bedDefaultPath = $this->writeItemFixture('BED_DEFAULT', 'uuid-bed-default', 'Bed', 'Captain', 'Default Bed', 10.0);
        $bedOverridePath = $this->writeItemFixture('BED_OVERRIDE', 'uuid-bed-override', 'Bed', 'Captain', 'Override Bed', 12.0);
        $storagePath = $this->writeItemFixture('STORAGE_DEFAULT', 'uuid-storage-default', 'Container', 'Cargo', 'Default Locker', 8.0);

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SEAT_PARENT' => $seatPath,
                    'BED_DEFAULT' => $bedDefaultPath,
                    'BED_OVERRIDE' => $bedOverridePath,
                    'STORAGE_DEFAULT' => $storagePath,
                ],
            ],
            uuidToClassMap: [
                'uuid-seat-parent' => 'SEAT_PARENT',
                'uuid-bed-default' => 'BED_DEFAULT',
                'uuid-bed-override' => 'BED_OVERRIDE',
                'uuid-storage-default' => 'STORAGE_DEFAULT',
            ],
            classToUuidMap: [
                'SEAT_PARENT' => 'uuid-seat-parent',
                'BED_DEFAULT' => 'uuid-bed-default',
                'BED_OVERRIDE' => 'uuid-bed-override',
                'STORAGE_DEFAULT' => 'uuid-storage-default',
            ],
            uuidToPathMap: [
                'uuid-seat-parent' => $seatPath,
                'uuid-bed-default' => $bedDefaultPath,
                'uuid-bed-override' => $bedOverridePath,
                'uuid-storage-default' => $storagePath,
            ],
        );
        $this->initializeMinimalItemServices();

        $builder = new LoadoutBuilder(ServiceFactory::getItemService(), new ItemClassifierService);
        $result = $builder->build($this->loadXmlDocument(<<<'XML'
            <SItemPortLoadoutManualParams>
                <entries>
                    <SItemPortLoadoutEntryParams itemPortName="seat_mount" entityClassName="SEAT_PARENT">
                        <loadout>
                            <SItemPortLoadoutManualParams>
                                <entries>
                                    <SItemPortLoadoutEntryParams itemPortName="BedPort" entityClassName="BED_OVERRIDE" />
                                </entries>
                            </SItemPortLoadoutManualParams>
                        </loadout>
                    </SItemPortLoadoutEntryParams>
                </entries>
            </SItemPortLoadoutManualParams>
            XML));

        self::assertCount(1, $result);
        self::assertSame('seat_mount', $result[0]['portName']);
        self::assertSame(['BedPort', 'StoragePort'], array_column($result[0]['entries'], 'portName'));
        self::assertSame('BED_OVERRIDE', $result[0]['entries'][0]['Item']['stdItem']['ClassName']);
        self::assertSame('STORAGE_DEFAULT', $result[0]['entries'][1]['Item']['stdItem']['ClassName']);
        self::assertSame('BED_OVERRIDE', $result[0]['Item']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName']);
        self::assertSame('STORAGE_DEFAULT', $result[0]['Item']['stdItem']['Ports'][1]['InstalledItem']['stdItem']['ClassName']);
    }

    /**
     * @throws JsonException
     */
    public function test_build_stops_recursive_expansion_on_circular_item_references(): void
    {
        $loopPath = $this->writeItemFixture(
            'LOOP_ITEM',
            'uuid-loop-item',
            'Seat',
            'Pilot',
            'Loop Seat',
            10.0,
            [['name' => 'LoopPort', 'type' => 'Seat', 'subType' => 'Pilot']]
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'LOOP_ITEM' => $loopPath,
                ],
            ],
            uuidToClassMap: [
                'uuid-loop-item' => 'LOOP_ITEM',
            ],
            classToUuidMap: [
                'LOOP_ITEM' => 'uuid-loop-item',
            ],
            uuidToPathMap: [
                'uuid-loop-item' => $loopPath,
            ],
        );
        $this->initializeMinimalItemServices();

        $builder = new LoadoutBuilder(ServiceFactory::getItemService(), new ItemClassifierService);
        $result = $builder->build($this->loadXmlDocument(<<<'XML'
            <SItemPortLoadoutManualParams>
                <entries>
                    <SItemPortLoadoutEntryParams itemPortName="seat_mount" entityClassName="LOOP_ITEM">
                        <loadout>
                            <SItemPortLoadoutManualParams>
                                <entries>
                                    <SItemPortLoadoutEntryParams itemPortName="LoopPort" entityClassName="LOOP_ITEM" />
                                </entries>
                            </SItemPortLoadoutManualParams>
                        </loadout>
                    </SItemPortLoadoutEntryParams>
                </entries>
            </SItemPortLoadoutManualParams>
            XML));

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]['entries']);
        self::assertSame('LoopPort', $result[0]['entries'][0]['portName']);
        self::assertSame('LOOP_ITEM', $result[0]['entries'][0]['Item']['stdItem']['ClassName']);
        self::assertSame([], $result[0]['entries'][0]['entries']);
        self::assertSame('LOOP_ITEM', $result[0]['Item']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName']);
    }

    /**
     * @throws JsonException
     */
    public function test_build_reads_default_loadout_entries_from_xml_loadout_path(): void
    {
        $seatPath = $this->writeFile(
            'records/entity/seat_xml.xml',
            <<<'XML'
            <EntityClassDefinition.SEAT_XML __type="EntityClassDefinition" __ref="uuid-seat-xml" __path="libs/foundry/records/entityclassdefinition/seat_xml.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Seat" SubType="Pilot" Size="1" Grade="A" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization>
                                <English Name="Seat XML" Description="" />
                            </Localization>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="25" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
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
                            <SItemPortLoadoutXMLParams loadoutPath="Scripts/Loadouts/test_loadout.xml" />
                        </loadout>
                    </SEntityComponentDefaultLoadoutParams>
                </Components>
            </EntityClassDefinition.SEAT_XML>
            XML
        );
        $bedPath = $this->writeItemFixture('BED_DEFAULT', 'uuid-bed-default', 'Bed', 'Captain', 'Default Bed', 10.0);
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout.xml',
            <<<'XML'
            <Loadout.TEST>
                <Loadout>
                    <Items>
                        <item portName="BedPort" itemName="BED_DEFAULT" />
                    </Items>
                </Loadout>
            </Loadout.TEST>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SEAT_XML' => $seatPath,
                    'BED_DEFAULT' => $bedPath,
                ],
                'InventoryContainer' => [],
                'CargoGrid' => [],
            ],
            uuidToClassMap: [
                'uuid-seat-xml' => 'SEAT_XML',
                'uuid-bed-default' => 'BED_DEFAULT',
            ],
            classToUuidMap: [
                'SEAT_XML' => 'uuid-seat-xml',
                'BED_DEFAULT' => 'uuid-bed-default',
            ],
            uuidToPathMap: [
                'uuid-seat-xml' => $seatPath,
                'uuid-bed-default' => $bedPath,
            ],
        );
        $this->initializeMinimalItemServices();
        $this->initializeLoadoutFileService();

        $builder = new LoadoutBuilder(ServiceFactory::getItemService(), new ItemClassifierService);
        $result = $builder->build($this->loadXmlDocument(<<<'XML'
            <SItemPortLoadoutManualParams>
                <entries>
                    <SItemPortLoadoutEntryParams itemPortName="seat_mount" entityClassName="SEAT_XML" />
                </entries>
            </SItemPortLoadoutManualParams>
            XML));

        self::assertCount(1, $result);
        self::assertSame('SEAT_XML', $result[0]['Item']['stdItem']['ClassName']);
        self::assertSame(['BedPort'], array_column($result[0]['entries'], 'portName'));
        self::assertSame('BED_DEFAULT', $result[0]['entries'][0]['Item']['stdItem']['ClassName']);
        self::assertSame('BED_DEFAULT', $result[0]['Item']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName']);
    }

    /**
     * @throws JsonException
     */
    public function test_build_reads_default_loadout_entries_from_bare_root_xml_loadout_path(): void
    {
        $seatPath = $this->writeFile(
            'records/entity/seat_xml_bare.xml',
            <<<'XML'
            <EntityClassDefinition.SEAT_XML_BARE __type="EntityClassDefinition" __ref="uuid-seat-xml-bare" __path="libs/foundry/records/entityclassdefinition/seat_xml_bare.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Seat" SubType="Pilot" Size="1" Grade="A" Manufacturer="00000000-0000-0000-0000-000000000000">
                            <Localization>
                                <English Name="Seat XML Bare" Description="" />
                            </Localization>
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SEntityPhysicsControllerParams>
                        <PhysType>
                            <SEntityRigidPhysicsControllerParams Mass="25" />
                        </PhysType>
                    </SEntityPhysicsControllerParams>
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
                            <SItemPortLoadoutXMLParams loadoutPath="Scripts/Loadouts/test_loadout_bare.xml" />
                        </loadout>
                    </SEntityComponentDefaultLoadoutParams>
                </Components>
            </EntityClassDefinition.SEAT_XML_BARE>
            XML
        );
        $bedPath = $this->writeItemFixture('BED_DEFAULT', 'uuid-bed-default', 'Bed', 'Captain', 'Default Bed', 10.0);
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout_bare.xml',
            <<<'XML'
            <Loadout>
                <Items>
                    <item portName="BedPort" itemName="BED_DEFAULT" />
                </Items>
            </Loadout>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SEAT_XML_BARE' => $seatPath,
                    'BED_DEFAULT' => $bedPath,
                ],
                'InventoryContainer' => [],
                'CargoGrid' => [],
            ],
            uuidToClassMap: [
                'uuid-seat-xml-bare' => 'SEAT_XML_BARE',
                'uuid-bed-default' => 'BED_DEFAULT',
            ],
            classToUuidMap: [
                'SEAT_XML_BARE' => 'uuid-seat-xml-bare',
                'BED_DEFAULT' => 'uuid-bed-default',
            ],
            uuidToPathMap: [
                'uuid-seat-xml-bare' => $seatPath,
                'uuid-bed-default' => $bedPath,
            ],
        );
        $this->initializeMinimalItemServices();
        $this->initializeLoadoutFileService();

        $builder = new LoadoutBuilder(ServiceFactory::getItemService(), new ItemClassifierService);
        $result = $builder->build($this->loadXmlDocument(<<<'XML'
            <SItemPortLoadoutManualParams>
                <entries>
                    <SItemPortLoadoutEntryParams itemPortName="seat_mount" entityClassName="SEAT_XML_BARE" />
                </entries>
            </SItemPortLoadoutManualParams>
            XML));

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]['entries']);
        self::assertSame('BedPort', $result[0]['entries'][0]['portName']);
        self::assertSame('BED_DEFAULT', $result[0]['entries'][0]['Item']['stdItem']['ClassName']);
        self::assertSame('BED_DEFAULT', $result[0]['Item']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName']);
    }

    private function loadXmlDocument(string $xml): TestRootDocument
    {
        $path = $this->writeFile('fixtures/loadout.xml', $xml);
        $document = new TestRootDocument;
        $document->load($path);

        return $document;
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

    /**
     * @param  array<int, array{name: string, type: string, subType: string}>  $ports
     * @param  array<int, array{portName: string, className: string}>  $defaultLoadout
     */
    private function writeItemFixture(
        string $className,
        string $uuid,
        string $type,
        string $subType,
        string $name,
        float $mass,
        array $ports = [],
        array $defaultLoadout = []
    ): string {
        $portXml = array_map(
            static fn (array $port): string => sprintf(
                '<SItemPortDef Name="%s" DisplayName="%s" MaxSize="1" MinSize="1"><Types><Type Type="%s" SubTypes="%s" /></Types></SItemPortDef>',
                $port['name'],
                $port['name'],
                $port['type'],
                $port['subType']
            ),
            $ports
        );

        $loadoutXml = array_map(
            static fn (array $entry): string => sprintf(
                '<SItemPortLoadoutEntryParams itemPortName="%s" entityClassName="%s" />',
                $entry['portName'],
                $entry['className']
            ),
            $defaultLoadout
        );

        return $this->writeFile(
            'records/entity/'.strtolower($className).'.xml',
            sprintf(
                <<<'XML'
                <EntityClassDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="libs/foundry/records/entityclassdefinition/%3$s.xml">
                    <Components>
                        <SAttachableComponentParams>
                            <AttachDef Type="%4$s" SubType="%5$s" Size="1" Grade="A" Manufacturer="00000000-0000-0000-0000-000000000000">
                                <Localization>
                                    <English Name="%6$s" Description="" />
                                </Localization>
                            </AttachDef>
                        </SAttachableComponentParams>
                        <SEntityPhysicsControllerParams>
                            <PhysType>
                                <SEntityRigidPhysicsControllerParams Mass="%7$s" />
                            </PhysType>
                        </SEntityPhysicsControllerParams>
                        <SItemPortContainerComponentParams>
                            <Ports>%8$s</Ports>
                        </SItemPortContainerComponentParams>
                        <SEntityComponentDefaultLoadoutParams>
                            <loadout>
                                <SItemPortLoadoutManualParams>
                                    <entries>%9$s</entries>
                                </SItemPortLoadoutManualParams>
                            </loadout>
                        </SEntityComponentDefaultLoadoutParams>
                    </Components>
                </EntityClassDefinition.%1$s>
                XML,
                $className,
                $uuid,
                strtolower($className),
                $type,
                $subType,
                $name,
                $mass,
                implode('', $portXml),
                implode('', $loadoutXml)
            )
        );
    }
}
