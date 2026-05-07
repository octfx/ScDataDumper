<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadItems;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadItemsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_split_indexes_and_scunpacked_item_files_by_default(): void
    {
        $command = new TestLoadItemsCommand([
            [
                'className' => 'SHIP_GUN',
                'formatted' => [
                    'classification' => 'Ship.Weapon.LaserCannon',
                    'stdItem' => ['ClassName' => 'SHIP_GUN', 'Name' => 'Ship Gun'],
                ],
                'item' => new MockItem(
                    'SHIP_GUN',
                    'ship-gun-uuid',
                    'WeaponGun',
                    ['ClassName' => 'SHIP_GUN'],
                    json_encode(['fallback' => 'ship-gun'], JSON_THROW_ON_ERROR),
                ),
            ],
            [
                'className' => 'FPS_RIFLE',
                'formatted' => [
                    'classification' => 'FPS.Weapon.AssaultRifle',
                    'stdItem' => ['ClassName' => 'FPS_RIFLE', 'Name' => 'FPS Rifle'],
                ],
                'item' => new MockItem(
                    'FPS_RIFLE',
                    'fps-rifle-uuid',
                    'WeaponPersonal',
                    ['ClassName' => 'FPS_RIFLE'],
                    json_encode(['fallback' => 'fps-rifle'], JSON_THROW_ON_ERROR),
                ),
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);
        self::assertCount(2, $this->readJsonFile('items.json'));
        self::assertCount(1, $this->readJsonFile('ship-items.json'));
        self::assertCount(1, $this->readJsonFile('fps-items.json'));

        $shipItemFile = $this->readJsonFile('items/ship_gun.json');
        self::assertSame('SHIP_GUN', $shipItemFile['Raw']['Entity']['ClassName']);
        self::assertSame('SHIP_GUN', $shipItemFile['Item']['stdItem']['ClassName']);

        $fpsItemFile = $this->readJsonFile('items/fps_rifle.json');
        self::assertSame('FPS_RIFLE', $fpsItemFile['Raw']['Entity']['ClassName']);
        self::assertSame('FPS_RIFLE', $fpsItemFile['Item']['stdItem']['ClassName']);
    }

    public function test_execute_accepts_scunpacked_flag_as_a_noop(): void
    {
        $records = [
            [
                'className' => 'SHIP_GUN',
                'formatted' => [
                    'classification' => 'Ship.Weapon.LaserCannon',
                    'stdItem' => ['ClassName' => 'SHIP_GUN', 'Name' => 'Ship Gun'],
                ],
                'item' => new MockItem(
                    'SHIP_GUN',
                    'ship-gun-uuid',
                    'WeaponGun',
                    ['ClassName' => 'SHIP_GUN'],
                    json_encode(['fallback' => 'ship-gun'], JSON_THROW_ON_ERROR),
                ),
            ],
        ];

        $defaultOutputDir = $this->tempDir.DIRECTORY_SEPARATOR.'default';
        mkdir($defaultOutputDir, 0777, true);
        $defaultTester = new CommandTester(new TestLoadItemsCommand($records));
        $defaultTester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $defaultOutputDir,
        ]);
        $defaultContents = file_get_contents($defaultOutputDir.DIRECTORY_SEPARATOR.'items/ship_gun.json');
        self::assertNotFalse($defaultContents);

        $flaggedOutputDir = $this->tempDir.DIRECTORY_SEPARATOR.'flagged';
        mkdir($flaggedOutputDir, 0777, true);
        $flaggedTester = new CommandTester(new TestLoadItemsCommand($records));
        $flaggedTester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $flaggedOutputDir,
            '--scUnpackedFormat' => true,
        ]);
        $flaggedContents = file_get_contents($flaggedOutputDir.DIRECTORY_SEPARATOR.'items/ship_gun.json');
        self::assertNotFalse($flaggedContents);

        self::assertSame($defaultContents, $flaggedContents);
    }

    public function test_execute_writes_hydrated_raw_entity_for_real_documents(): void
    {
        $manufacturerPath = $this->writeFile(
            'records/scitemmanufacturer/fallback.xml',
            <<<'XML'
            <SCItemManufacturer.FALLBACK Code="FALL" __type="SCItemManufacturer" __ref="11111111-1111-1111-1111-111111111111" __path="libs/foundry/records/scitemmanufacturer/fallback.xml">
                <Localization Name="@manufacturer_name" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY">
                    <displayFeatures />
                </Localization>
            </SCItemManufacturer.FALLBACK>
            XML
        );

        $this->writeCacheFiles(
            uuidToClassMap: ['11111111-1111-1111-1111-111111111111' => 'FALLBACK'],
            classToUuidMap: ['FALLBACK' => '11111111-1111-1111-1111-111111111111'],
            uuidToPathMap: ['11111111-1111-1111-1111-111111111111' => $manufacturerPath]
        );

        $this->initializeMinimalItemServices([
            'LOC_EMPTY' => '',
            'manufacturer_name' => 'Fallback Industries',
            'item_name' => 'Localized Test Item',
            'item_description' => 'Hydrated utility item.',
        ]);

        $entityPath = $this->writeFile(
            'records/entities/scitem/test_item.xml',
            <<<'XML'
            <EntityClassDefinition.TEST_ITEM __type="EntityClassDefinition" __ref="22222222-2222-2222-2222-222222222222" __path="libs/foundry/records/entities/scitem/test_item.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="SeatDashboard" SubType="UNDEFINED" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@item_name" ShortName="@LOC_EMPTY" Description="@item_description" />
                        </AttachDef>
                    </SAttachableComponentParams>
                </Components>
            </EntityClassDefinition.TEST_ITEM>
            XML
        );

        $item = new EntityClassDefinition;
        $item->load($entityPath);

        $command = new TestLoadItemsCommand([
            [
                'className' => 'TEST_ITEM',
                'formatted' => [
                    'classification' => 'Ship.Component.Dashboard',
                    'stdItem' => ['ClassName' => 'TEST_ITEM', 'Name' => 'Localized Test Item'],
                ],
                'item' => $item,
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $itemFile = $this->readJsonFile('items/test_item.json');
        self::assertSame(
            'Localized Test Item',
            $itemFile['Raw']['Entity']['Components']['SAttachableComponentParams']['AttachDef']['Localization']['English']['Name']
        );
        self::assertSame(
            'Fallback Industries',
            $itemFile['Raw']['Entity']['Components']['SAttachableComponentParams']['AttachDef']['Manufacturer']['Localization']['English']['Name']
        );
    }

    public function test_execute_throws_runtime_exception_when_index_file_cannot_be_opened(): void
    {
        self::assertTrue(mkdir($this->tempDir.DIRECTORY_SEPARATOR.'items.json', 0777, true) || is_dir($this->tempDir.DIRECTORY_SEPARATOR.'items.json'));

        $tester = new CommandTester(new TestLoadItemsCommand([
            [
                'className' => 'SHIP_GUN',
                'formatted' => [
                    'classification' => 'Ship.Weapon.LaserCannon',
                    'stdItem' => ['ClassName' => 'SHIP_GUN', 'Name' => 'Ship Gun'],
                ],
                'item' => new MockItem(
                    'SHIP_GUN',
                    'ship-gun-uuid',
                    'WeaponGun',
                    ['ClassName' => 'SHIP_GUN'],
                    json_encode(['fallback' => 'ship-gun'], JSON_THROW_ON_ERROR),
                ),
            ],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to open index output files for writing');

        $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);
    }

    // -- Type filter tests (merged from LoadItemsTest) --

    public function test_type_filter_extends_defaults_instead_of_replacing_them(): void
    {
        $command = new LoadItems;
        $excluded = $this->invokeBuildTypeFilterAvoidList($command, 'Ship.Weapon.Gun, noitem_player');

        self::assertContains('undefined', $excluded);
        self::assertContains('noitem_player', $excluded);
        self::assertContains('ship.weapon.gun', $excluded);
        self::assertSame(1, $this->countOccurrences('noitem_player', $excluded));
    }

    public function test_type_filter_matching_is_case_insensitive_and_exact(): void
    {
        $command = new LoadItems;
        $excluded = $this->invokeBuildTypeFilterAvoidList($command, '  SeAt.Component  ');

        self::assertTrue($this->invokeIsTypeExcluded($command, 'seat.component', $excluded));
        self::assertTrue($this->invokeIsTypeExcluded($command, 'SEAT.COMPONENT', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded($command, 'seat', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded($command, 'seat.component.extra', $excluded));
    }

    public function test_type_filter_ignores_empty_tokens_and_deduplicates(): void
    {
        $command = new LoadItems;
        $excluded = $this->invokeBuildTypeFilterAvoidList($command, ' , , BUTTON, button , custom , custom ,, ');

        self::assertSame(1, $this->countOccurrences('button', $excluded));
        self::assertSame(1, $this->countOccurrences('custom', $excluded));
        self::assertSame(0, $this->countOccurrences('', $excluded));
    }

    public function test_wildcard_tokens_are_treated_as_literal_values(): void
    {
        $command = new LoadItems;
        $excluded = $this->invokeBuildTypeFilterAvoidList($command, 'ship.*');

        self::assertTrue($this->invokeIsTypeExcluded($command, 'ship.*', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded($command, 'ship.weapon.gun', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded($command, 'ship', $excluded));
    }

    /**
     * @return array<int, string>
     */
    private function invokeBuildTypeFilterAvoidList(LoadItems $command, mixed $typeFilter): array
    {
        $result = $this->invokeMethod($command, 'buildTypeFilterAvoidList', $typeFilter);
        self::assertIsArray($result);

        return $result;
    }

    /**
     * @param  array<int, string>  $excludedTypes
     */
    private function invokeIsTypeExcluded(LoadItems $command, string $type, array $excludedTypes): bool
    {
        $result = $this->invokeMethod($command, 'isTypeExcluded', $type, $excludedTypes);
        self::assertIsBool($result);

        return $result;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function countOccurrences(string $needle, array $values): int
    {
        return count(array_keys($values, $needle, true));
    }
}

final class TestLoadItemsCommand extends LoadItems
{
    /**
     * @param  array<int, array{className: string, formatted: array, item: MockItem}>  $records
     */
    public function __construct(private readonly array $records)
    {
        parent::__construct();
        $this->setName('load:items');
    }

    protected function prepareServices(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void {}

    protected function getItemExportCount(): int
    {
        return count($this->records);
    }

    protected function iterateItemExports(?string $nameFilter, mixed $typeFilter): iterable
    {
        foreach ($this->records as $record) {
            if ($nameFilter !== null && ! str_contains(strtolower($record['className']), $nameFilter)) {
                continue;
            }

            yield $record;
        }
    }
}

final class MockItem
{
    public function __construct(
        private readonly string $className,
        private readonly string $uuid,
        private readonly string $attachType,
        private readonly array $toArrayResult,
        private readonly string $toJsonResult,
    ) {}

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getAttachType(): string
    {
        return $this->attachType;
    }

    public function toArray(): array
    {
        return $this->toArrayResult;
    }

    public function toJson(): string
    {
        return $this->toJsonResult;
    }
}
