<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadBlueprints;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadBlueprintsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_formatted_index_and_raw_blueprint_files_by_default(): void
    {
        $command = new TestLoadBlueprintsCommand([
            [
                'className' => 'BP_CRAFT_TEST_AMMO',
                'formatted' => [
                    'uuid' => 'blueprint-uuid',
                    'key' => 'BP_CRAFT_TEST_AMMO',
                    'availability' => [
                        'default' => false,
                        'reward_pools' => [],
                    ],
                ],
                'rawBlueprint' => [
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ],
                'defaultJson' => json_encode([
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $index = $this->readJsonFile('blueprints.json');
        self::assertCount(1, $index);
        self::assertSame('BP_CRAFT_TEST_AMMO', $index[0]['key']);

        $blueprintFile = $this->readJsonFile('blueprints/bp_craft_test_ammo.json');
        self::assertArrayHasKey('blueprint', $blueprintFile);
        self::assertArrayNotHasKey('Blueprint', $blueprintFile);
    }

    public function test_execute_writes_scunpacked_payload_when_requested(): void
    {
        $command = new TestLoadBlueprintsCommand([
            [
                'className' => 'BP_CRAFT_TEST_AMMO',
                'formatted' => [
                    'uuid' => 'blueprint-uuid',
                    'key' => 'BP_CRAFT_TEST_AMMO',
                    'availability' => [
                        'default' => true,
                        'reward_pools' => [
                            [
                                'uuid' => 'reward-uuid',
                                'key' => 'REWARD_POOL',
                            ],
                        ],
                    ],
                ],
                'rawBlueprint' => [
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ],
                'defaultJson' => json_encode([
                    'blueprint' => [
                        'CraftingBlueprint' => [
                            'category' => 'ammo-category',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--scUnpackedFormat' => true,
        ]);

        self::assertSame(0, $exitCode);

        $blueprintFile = $this->readJsonFile('blueprints/bp_craft_test_ammo.json');
        self::assertSame('ammo-category', $blueprintFile['Raw']['Blueprint']['blueprint']['CraftingBlueprint']['category']);
        self::assertSame('BP_CRAFT_TEST_AMMO', $blueprintFile['Blueprint']['key']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}

final class TestLoadBlueprintsCommand extends LoadBlueprints
{
    /**
     * @param  array<int, array{className: string, formatted: array, rawBlueprint: array, defaultJson: string}>  $records
     */
    public function __construct(private readonly array $records)
    {
        parent::__construct();
        $this->setName('load:blueprints');
    }

    protected function prepareServices(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void
    {
    }

    protected function getBlueprintExportCount(): int
    {
        return count($this->records);
    }

    protected function iterateBlueprintExports(?string $nameFilter): iterable
    {
        foreach ($this->records as $record) {
            if ($nameFilter !== null && ! str_contains(strtolower($record['className']), $nameFilter)) {
                continue;
            }

            yield $record;
        }
    }
}
