<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadItems;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadItemsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_split_indexes_and_scunpacked_item_files(): void
    {
        $command = new TestLoadItemsCommand([
            [
                'className' => 'SHIP_GUN',
                'formatted' => [
                    'classification' => 'Ship.Weapon.LaserCannon',
                    'stdItem' => ['ClassName' => 'SHIP_GUN', 'Name' => 'Ship Gun'],
                ],
                'rawEntity' => [
                    'ClassName' => 'SHIP_GUN',
                    '__ref' => 'ship-gun-uuid',
                    '__type' => 'WeaponGun',
                ],
                'defaultJson' => json_encode(['fallback' => 'ship-gun'], JSON_THROW_ON_ERROR),
            ],
            [
                'className' => 'FPS_RIFLE',
                'formatted' => [
                    'classification' => 'FPS.Weapon.AssaultRifle',
                    'stdItem' => ['ClassName' => 'FPS_RIFLE', 'Name' => 'FPS Rifle'],
                ],
                'rawEntity' => [
                    'ClassName' => 'FPS_RIFLE',
                    '__ref' => 'fps-rifle-uuid',
                    '__type' => 'WeaponPersonal',
                ],
                'defaultJson' => json_encode(['fallback' => 'fps-rifle'], JSON_THROW_ON_ERROR),
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--scUnpackedFormat' => true,
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
     * @param  array<int, array{className: string, formatted: array, rawEntity: array, defaultJson: string}>  $records
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
