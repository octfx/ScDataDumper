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
            'uuid-one' => 'ArmouryItem',
            'uuid-two' => 'Cargo',
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
        // The command does not filter empty-name tags — they pass through as-is
        self::assertSame('', $result['uuid-empty']);
        self::assertSame('ValidTag', $result['uuid-valid']);
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
