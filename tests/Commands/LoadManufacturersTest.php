<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadManufacturers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LoadManufacturersTest extends TestCase
{
    private LoadManufacturers $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new LoadManufacturers;
    }

    public function test_description_is_included_when_localized_text_exists(): void
    {
        $result = $this->invokeBuildManufacturerExportEntry([
            'Code' => 'ACAS',
            'Localization' => [
                'Name' => 'Ace Astrogation',
                'Description' => 'Precision star charts and pilot tools.',
            ],
            '__ref' => 'cfc0122d-5275-415a-a656-3dcb3346feb5',
        ]);

        self::assertSame('ACAS', $result['Code']);
        self::assertSame('Ace Astrogation', $result['Name']);
        self::assertSame('cfc0122d-5275-415a-a656-3dcb3346feb5', $result['Reference']);
        self::assertSame('Precision star charts and pilot tools.', $result['Description']);
    }

    #[DataProvider('emptyOrMissingDescriptionProvider')]
    public function test_description_is_omitted_when_empty_or_missing(array $manufacturerArray): void
    {
        $result = $this->invokeBuildManufacturerExportEntry($manufacturerArray);

        self::assertArrayNotHasKey('Description', $result);
        self::assertArrayHasKey('Code', $result);
        self::assertArrayHasKey('Name', $result);
        self::assertArrayHasKey('Reference', $result);
    }

    public function test_raw_localization_token_like_description_is_not_exported(): void
    {
        $result = $this->invokeBuildManufacturerExportEntry([
            'Code' => 'ACAS',
            'Localization' => [
                'Name' => 'Ace Astrogation',
                'Description' => '@manufacturer_DescACAS',
            ],
            '__ref' => 'cfc0122d-5275-415a-a656-3dcb3346feb5',
        ]);

        self::assertArrayNotHasKey('Description', $result);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function emptyOrMissingDescriptionProvider(): array
    {
        return [
            'empty description string' => [[
                'Code' => 'ACAS',
                'Localization' => [
                    'Name' => 'Ace Astrogation',
                    'Description' => '   ',
                ],
                '__ref' => 'cfc0122d-5275-415a-a656-3dcb3346feb5',
            ]],
            'missing description key' => [[
                'Code' => 'ACAS',
                'Localization' => [
                    'Name' => 'Ace Astrogation',
                ],
                '__ref' => 'cfc0122d-5275-415a-a656-3dcb3346feb5',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $manufacturerArray
     * @return array<string, mixed>
     */
    private function invokeBuildManufacturerExportEntry(array $manufacturerArray): array
    {
        $method = new ReflectionMethod(LoadManufacturers::class, 'buildManufacturerExportEntry');

        $result = $method->invoke($this->command, $manufacturerArray);
        self::assertIsArray($result);

        return $result;
    }
}
