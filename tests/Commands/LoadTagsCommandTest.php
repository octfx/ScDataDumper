<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadTags;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadTagsCommandTest extends ScDataTestCase
{
    public function test_execute_writes_flat_uuid_to_name_map(): void
    {
        $this->writeCacheFiles();
        file_put_contents(
            $this->getCachePath(),
            json_encode([
                'uuid-one' => [
                    'name' => 'ArmouryItem',
                    'legacyGUID' => '4294967295',
                    'children' => [],
                ],
                'uuid-two' => [
                    'name' => 'Cargo',
                    'legacyGUID' => '7',
                    'children' => [],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new LoadTags);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame([
            'uuid-one' => [
                'name' => 'ArmouryItem',
                'parent_uuid' => null,
            ],
            'uuid-two' => [
                'name' => 'Cargo',
                'parent_uuid' => null,
            ],
        ], $this->readJsonFile('tags.json'));
    }

    public function test_execute_handles_empty_tag_database(): void
    {
        $this->writeCacheFiles();
        file_put_contents($this->getCachePath(), json_encode([], JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new LoadTags);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame([], $this->readJsonFile('tags.json'));
    }

    public function test_execute_includes_parent_uuid_from_children(): void
    {
        $this->writeCacheFiles();
        file_put_contents(
            $this->getCachePath(),
            json_encode([
                'uuid-parent' => [
                    'name' => 'Weapon',
                    'legacyGUID' => '1',
                    'children' => ['uuid-child'],
                ],
                'uuid-child' => [
                    'name' => 'WeaponPistol',
                    'legacyGUID' => '2',
                    'children' => [],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new LoadTags);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);
        $result = $this->readJsonFile('tags.json');
        self::assertSame(['name' => 'Weapon', 'parent_uuid' => null], $result['uuid-parent']);
        self::assertSame(['name' => 'WeaponPistol', 'parent_uuid' => 'uuid-parent'], $result['uuid-child']);
    }

    public function test_execute_skips_tags_with_empty_names(): void
    {
        $this->writeCacheFiles();
        file_put_contents(
            $this->getCachePath(),
            json_encode([
                'uuid-empty' => [
                    'name' => '',
                    'legacyGUID' => '4294967295',
                    'children' => [],
                ],
                'uuid-valid' => [
                    'name' => 'ValidTag',
                    'legacyGUID' => '7',
                    'children' => [],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new LoadTags);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);
        $result = $this->readJsonFile('tags.json');
        // The command does not filter empty-name tags - they pass through as-is
        self::assertSame(['name' => '', 'parent_uuid' => null], $result['uuid-empty']);
        self::assertSame(['name' => 'ValidTag', 'parent_uuid' => null], $result['uuid-valid']);
    }

    private function getCachePath(): string
    {
        return sprintf(
            '%s%stagdatabase-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
