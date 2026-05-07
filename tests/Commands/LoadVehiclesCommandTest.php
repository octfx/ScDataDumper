<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadVehicles;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadVehiclesCommandTest extends ScDataTestCase
{
    public function test_execute_writes_ship_index_and_raw_vehicle_dump(): void
    {
        $command = new TestLoadVehiclesCommand([
            [
                'className' => 'TEST_SHIP',
                'formatted' => [
                    'ClassName' => 'TEST_SHIP',
                    'Name' => 'Test Ship',
                ],
                'entity' => [
                    'ClassName' => 'TEST_SHIP',
                    '__ref' => 'ship-uuid',
                ],
                'vehicle' => [
                    'Parts' => [['Name' => 'seat_mount']],
                ],
                'loadout' => [
                    ['portName' => 'seat_mount'],
                ],
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--with-raw' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertCount(1, $this->readJsonFile('ships.json'));

        $shipFile = $this->readJsonFile('ships/test_ship.json');
        self::assertSame('TEST_SHIP', $shipFile['ClassName']);

        $rawFile = $this->readJsonFile('ships/test_ship-raw.json');
        self::assertSame('TEST_SHIP', $rawFile['Raw']['Entity']['ClassName']);
        self::assertSame('seat_mount', $rawFile['Loadout'][0]['portName']);
    }
}

final class TestLoadVehiclesCommand extends LoadVehicles
{
    /**
     * @param  array<int, array{className: string, formatted: array, entity: array, vehicle: ?array, loadout: array}>  $records
     */
    public function __construct(private readonly array $records)
    {
        parent::__construct();
        $this->setName('load:vehicles');
    }

    protected function prepareServices(InputInterface $input, OutputInterface $output): void {}

    protected function getVehicleExportCount(): int
    {
        return count($this->records);
    }

    protected function iterateVehicleExports(?string $nameFilter): iterable
    {
        foreach ($this->records as $record) {
            if ($nameFilter !== null && ! str_contains(strtolower($record['className']), $nameFilter)) {
                continue;
            }

            yield $record;
        }
    }
}
