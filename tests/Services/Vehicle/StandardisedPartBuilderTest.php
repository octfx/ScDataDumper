<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Services\ItemClassifierService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\PortClassifierService;
use Octfx\ScDataDumper\Services\Vehicle\StandardisedPartBuilder;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;

final class StandardisedPartBuilderTest extends ScDataTestCase
{
    public function test_build_part_list_skips_flagged_parts_and_injects_virtual_ports(): void
    {
        $this->writeCacheFiles();
        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $builder = new StandardisedPartBuilder(
            $itemService,
            new ItemClassifierService,
            new PortClassifierService,
            new Element($this->makeDocument('fixtures/entity_ports.xml', <<<'XML'
                <EntityPorts>
                    <SItemPortDef Name="utility_socket" DisplayName="Utility Socket" MinSize="1" MaxSize="1" Flags="$uneditable invisible">
                        <Types>
                            <Type Type="Utility" />
                        </Types>
                    </SItemPortDef>
                </EntityPorts>
                XML
            )->documentElement)
        );

        $parts = $builder->buildPartList(
            $this->makeDocument('fixtures/parts.xml', <<<'XML'
                <Vehicle.TEST_IMPL>
                    <Parts>
                        <Part name="weapon_mount" mass="10">
                            <ItemPort display_name="Weapon Mount" minSize="1" maxSize="3">
                                <Types>
                                    <Type type="WeaponGun" />
                                </Types>
                            </ItemPort>
                        </Part>
                        <Part name="hidden_mount" skipPart="1" mass="5">
                            <ItemPort display_name="Hidden Mount" minSize="1" maxSize="1">
                                <Types>
                                    <Type type="WeaponGun" />
                                </Types>
                            </ItemPort>
                        </Part>
                    </Parts>
                </Vehicle.TEST_IMPL>
                XML
            )->get('Parts')?->children() ?? [],
            [[
                'portName' => 'utility_socket',
                'className' => 'UTILITY_BEAM',
                'entries' => [],
                'Item' => [
                    'classification' => 'Ship.Utility.TractorBeam',
                    'stdItem' => [
                        'ClassName' => 'UTILITY_BEAM',
                        'Ports' => [],
                    ],
                ],
            ]]
        );

        self::assertCount(2, $parts);
        self::assertSame(['weapon_mount', 'utility_socket'], array_column($parts, 'Name'));

        $virtualPart = $parts[1];
        self::assertSame('Utility Socket', $virtualPart['DisplayName']);
        self::assertTrue($virtualPart['Port']['Uneditable']);
        self::assertSame('UTILITY_BEAM', $virtualPart['Port']['InstalledItem']['stdItem']['ClassName'] ?? null);
        self::assertSame('Utility hardpoints', $virtualPart['Port']['Category']);
    }

    public function test_build_part_list_installs_nested_entries_into_preformatted_items(): void
    {
        $this->writeCacheFiles();
        $itemService = new ItemService($this->tempDir);
        $itemService->initialize();

        $builder = new StandardisedPartBuilder(
            $itemService,
            new ItemClassifierService,
            new PortClassifierService
        );

        $parts = $builder->buildPartList(
            $this->makeDocument('fixtures/nested_parts.xml', <<<'XML'
                <Vehicle.TEST_IMPL>
                    <Parts>
                        <Part name="turret_mount" mass="20">
                            <ItemPort display_name="Turret Mount" minSize="2" maxSize="2">
                                <Types>
                                    <Type type="Turret" subtypes="Remote" />
                                </Types>
                            </ItemPort>
                        </Part>
                    </Parts>
                </Vehicle.TEST_IMPL>
                XML
            )->get('Parts')?->children() ?? [],
            [[
                'portName' => 'turret_mount',
                'className' => 'TURRET_TEST',
                'entries' => [[
                    'portName' => 'AmmoPort',
                    'className' => 'MAG_TEST',
                    'entries' => [],
                    'Item' => [
                        'classification' => 'Ship.WeaponAttachment.Magazine',
                        'stdItem' => [
                            'ClassName' => 'MAG_TEST',
                            'Ports' => [],
                        ],
                    ],
                ]],
                'Item' => [
                    'classification' => 'Ship.Turret.Remote',
                    'stdItem' => [
                        'ClassName' => 'TURRET_TEST',
                        'Ports' => [[
                            'PortName' => 'AmmoPort',
                            'Types' => ['WeaponAttachment.Magazine'],
                            'Flags' => [],
                            'RequiredTags' => [],
                            'Uneditable' => false,
                        ]],
                    ],
                ],
            ]]
        );

        self::assertCount(1, $parts);
        self::assertSame('TURRET_TEST', $parts[0]['Port']['InstalledItem']['stdItem']['ClassName'] ?? null);
        self::assertSame(
            'MAG_TEST',
            $parts[0]['Port']['InstalledItem']['stdItem']['Ports'][0]['InstalledItem']['stdItem']['ClassName'] ?? null
        );
        self::assertSame(
            'ship.weaponattachment.magazine',
            $parts[0]['Port']['InstalledItem']['stdItem']['Ports'][0]['InstalledItem']['classification'] ?? null
        );
    }

    private function makeDocument(string $relativePath, string $xml): TestRootDocument
    {
        $path = $this->writeFile($relativePath, $xml);

        $document = new TestRootDocument;
        $document->load($path);

        return $document;
    }
}
