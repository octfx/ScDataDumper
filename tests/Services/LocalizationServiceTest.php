<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\LocalizationService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class LocalizationServiceTest extends ScDataTestCase
{
    public function test_get_translation_normalizes_escaped_newlines_and_trims_result(): void
    {
        $this->writeCacheFiles();

        $this->writeFile(
            'Data/Localization/english/global.ini',
            <<<'INI'
            CSV_NAME=Argo CSV-SM\n
            CSV_DESC=Manufacturer: Argo Astronautics\nFocus: Cargo\n\nSupply model.\n
            INI
        );

        $service = new LocalizationService($this->tempDir);

        self::assertSame('Argo CSV-SM', $service->getTranslation('@CSV_NAME'));
        self::assertSame(
            "Manufacturer: Argo Astronautics\nFocus: Cargo\n\nSupply model.",
            $service->getTranslation('@CSV_DESC')
        );
        self::assertSame('Argo CSV-SM', $service->getAllTranslations()['english']['CSV_NAME']);
    }

    public function test_translate_value_returns_null_for_null_input(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue(null));
    }

    public function test_translate_value_returns_null_for_empty_string(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue(''));
    }

    public function test_translate_value_returns_string_as_is_without_at_prefix(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertSame('Hello World', $service->translateValue('Hello World'));
    }

    public function test_translate_value_resolves_at_prefixed_key(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "MY_KEY=Translated Value\n");

        $service = new LocalizationService($this->tempDir);

        self::assertSame('Translated Value', $service->translateValue('@MY_KEY'));
    }

    public function test_translate_value_returns_null_for_loc_empty_when_exclude_placeholder(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue('@LOC_EMPTY', excludePlaceholder: true));
    }

    public function test_translate_value_returns_null_for_blank_space_when_exclude_placeholder(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "blank_space=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue('@blank_space', excludePlaceholder: true));
    }

    public function test_translate_value_returns_null_for_loc_placeholder_when_exclude_placeholder(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_PLACEHOLDER=something\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue('@LOC_PLACEHOLDER', excludePlaceholder: true));
    }

    public function test_translate_value_returns_null_for_untranslated_key(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertNull($service->translateValue('@NONEXISTENT_KEY_12345'));
    }

    public function test_get_translation_returns_key_as_is_when_not_found(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertSame('@NONEXISTENT_KEY', $service->getTranslation('@NONEXISTENT_KEY'));
    }

    public function test_get_locales_returns_english(): void
    {
        $this->writeCacheFiles();
        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        $service = new LocalizationService($this->tempDir);

        self::assertContains('english', $service->getLocales());
    }
}
