<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\TagDatabaseService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class TagDatabaseServiceTest extends ScDataTestCase
{
    private const ARMOURY_UUID = '6caa4979-dfea-4566-9e9e-506be5da4c91';

    private const CARGO_UUID = '9f3b87f2-e91a-49dc-a061-e5b700739177';

    public function test_initialize_loads_tags_from_existing_json_cache(): void
    {
        $this->writeCacheFiles();
        file_put_contents(
            $this->getCachePath(),
            json_encode([
                strtolower(self::ARMOURY_UUID) => [
                    'name' => 'ArmouryItem',
                    'legacyGUID' => '4294967295',
                    'children' => [],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $service = new TagDatabaseService($this->tempDir);
        $service->initialize();

        self::assertSame('ArmouryItem', $service->getTagName(self::ARMOURY_UUID));
        self::assertSame([
            strtolower(self::ARMOURY_UUID) => [
                'name' => 'ArmouryItem',
                'legacyGUID' => '4294967295',
                'children' => [],
            ],
        ], $service->getTagMap());
    }

    public function test_initialize_loads_tags_from_extracted_tag_files(): void
    {
        $this->writeCacheFiles();
        $this->writeExtractedTagFiles([
            ['name' => 'ArmouryItem', 'uuid' => self::ARMOURY_UUID, 'legacyGUID' => '4294967295'],
            ['name' => 'Cargo', 'uuid' => self::CARGO_UUID, 'legacyGUID' => '7'],
        ]);

        $service = new TagDatabaseService($this->tempDir);
        $service->initialize();

        self::assertSame('ArmouryItem', $service->getTagName(self::ARMOURY_UUID));
        self::assertSame('ArmouryItem', $service->getTagName(strtoupper(self::ARMOURY_UUID)));
        self::assertSame([
            strtolower(self::ARMOURY_UUID) => [
                'name' => 'ArmouryItem',
                'legacyGUID' => '4294967295',
                'children' => [],
            ],
            strtolower(self::CARGO_UUID) => [
                'name' => 'Cargo',
                'legacyGUID' => '7',
                'children' => [],
            ],
        ], $service->getTagMap());
        self::assertSame([
            strtolower(self::ARMOURY_UUID) => 'ArmouryItem',
            strtolower(self::CARGO_UUID) => 'Cargo',
        ], $service->getTagNameMap());
    }

    public function test_initialize_ignores_malformed_tag_documents(): void
    {
        $this->writeCacheFiles();
        $validPath = $this->writeFile(
            'Game2/libs/foundry/records/tagdatabase/valid.xml',
            sprintf('<Tag.%1$s tagName="ValidTag" legacyGUID="21" __type="Tag" __ref="%1$s" />', self::ARMOURY_UUID)
        );
        $missingNamePath = $this->writeFile(
            'Game2/libs/foundry/records/tagdatabase/missing-name.xml',
            sprintf('<Tag.%s __type="Tag" __ref="%s" />', self::CARGO_UUID, self::CARGO_UUID)
        );
        $this->writeCacheFiles(classToPathMap: [
            'Tag' => [
                self::ARMOURY_UUID => $validPath,
                self::CARGO_UUID => $missingNamePath,
            ],
        ]);

        $service = new TagDatabaseService($this->tempDir);
        $service->initialize();

        self::assertSame([
            strtolower(self::ARMOURY_UUID) => [
                'name' => 'ValidTag',
                'legacyGUID' => '21',
                'children' => [],
            ],
        ], $service->getTagMap());
    }

    public function test_initialize_returns_empty_map_when_tag_section_is_missing(): void
    {
        $this->writeCacheFiles();

        $service = new TagDatabaseService($this->tempDir);
        $service->initialize();

        self::assertSame([], $service->getTagMap());
        self::assertFileExists($this->getCachePath());
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
