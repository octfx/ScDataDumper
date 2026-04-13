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

    /**
     * @return array<string, string>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
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
