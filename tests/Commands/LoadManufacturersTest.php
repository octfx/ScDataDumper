<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadManufacturers;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoadManufacturersTest extends ScDataTestCase
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

    public function test_apply_canonical_override_applies_data_json_code_and_name(): void
    {
        // data.json canonical wins for both code and name.
        $entry = ['Code' => 'FSKI', 'Name' => 'Aegis Dynamics', 'Reference' => 'fski-uuid'];

        $result = $this->invokeMethod($this->command, 'applyCanonicalOverride', $entry, [
            'code' => 'FSKI',
            'name' => 'FireStorm Kinetics',
            'uuid' => 'fski-uuid',
        ]);

        self::assertSame('FSKI', $result['Code']);
        self::assertSame('FireStorm Kinetics', $result['Name']);
    }

    public function test_apply_canonical_override_changes_code_when_data_json_code_differs(): void
    {
        // XML code AEG -> data.json canonical AEGS. The export must emit the
        // canonical code so it joins with items (which also emit AEGS).
        $entry = ['Code' => 'AEG', 'Name' => 'Aegis Dynamics', 'Reference' => 'aeg-uuid'];

        $result = $this->invokeMethod($this->command, 'applyCanonicalOverride', $entry, [
            'code' => 'AEGS',
            'name' => 'Aegis Dynamics',
            'uuid' => 'aeg-uuid',
        ]);

        self::assertSame('AEGS', $result['Code']);
        self::assertSame('Aegis Dynamics', $result['Name']);
        // Reference (uuid) is owned by the XML record, never touched here.
        self::assertSame('aeg-uuid', $result['Reference']);
    }

    public function test_apply_canonical_override_keeps_xml_values_when_no_data_json_match(): void
    {
        // Garbage / uncurated code -> null canonical -> XML code+name preserved.
        $entry = ['Code' => 'CUBY', 'Name' => 'Casaba Outlet', 'Reference' => 'cuby-uuid'];

        $result = $this->invokeMethod($this->command, 'applyCanonicalOverride', $entry, null);

        self::assertSame('CUBY', $result['Code']);
        self::assertSame('Casaba Outlet', $result['Name']);
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
        $result = $this->invokeMethod($this->command, 'buildManufacturerExportEntry', $manufacturerArray);
        self::assertIsArray($result);

        return $result;
    }
}
