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
}
