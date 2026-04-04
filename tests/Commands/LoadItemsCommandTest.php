<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadItems;
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

    /**
     * @return array<int, mixed>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
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

    protected function prepareServices(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void
    {
    }

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
