<?php

namespace Tests\Helper;

use Octfx\ScDataDumper\Helper\ItemDescriptionParser;
use Octfx\ScDataDumper\Helper\ItemDescriptionParserConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ItemDescriptionParserTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset config for each test
        ItemDescriptionParser::setConfig(new ItemDescriptionParserConfig);
    }

    #[Test]
    public function it_parses_standard_format()
    {
        $input = "Manufacturer: MISC\nSize: 3\n\nThis is the description.";
        $result = ItemDescriptionParser::parse($input, ['Manufacturer', 'Size']);

        $this->assertEquals('Musashi Industrial & Starflight Concern', $result['data']['Manufacturer']);
        $this->assertEquals('3', $result['data']['Size']);
        $this->assertEquals('This is the description.', $result['description']);
    }

    #[Test]
    public function it_handles_unicode_characters()
    {
        $input = "Size: 3\n\nDescription with émoji 🚀 and accénted chars";
        $result = ItemDescriptionParser::parse($input, ['Size']);

        $this->assertEquals('3', $result['data']['Size']);
        $this->assertStringContainsString('🚀', $result['description']);
        $this->assertStringContainsString('émoji', $result['description']);
    }

    #[Test]
    public function it_handles_smart_quotes()
    {
        // Using Unicode escape sequences for smart quotes
        $input = "Type: Weapon\n\nThis is \u{201C}quoted\u{201D} text with \u{2018}smart\u{2019} quotes";
        $result = ItemDescriptionParser::parse($input, ['Type']);

        $this->assertEquals('Weapon', $result['data']['Type']);
        // Smart quotes normalized to regular quotes
        $this->assertStringContainsString('"quoted"', $result['description']);
        $this->assertStringContainsString("'smart'", $result['description']);
    }

    #[Test]
    public function it_handles_various_newline_formats()
    {
        $tests = [
            "Size: 3\n\nDescription",
            "Size: 3\r\n\r\nDescription",
            "Size: 3\r\rDescription",
            'Size: 3\\n\\nDescription',
        ];

        foreach ($tests as $input) {
            $result = ItemDescriptionParser::parse($input, ['Size']);
            $this->assertEquals('3', $result['data']['Size']);
            $this->assertNotEmpty($result['description']);
        }
    }

    #[Test]
    public function it_detects_placeholders()
    {
        $inputs = [
            '<= PLACEHOLDER =>',
            'Some text [PH] placeholder',
            '{PH}',
            'PLACEHOLDER content',
        ];

        foreach ($inputs as $input) {
            $result = ItemDescriptionParser::parse($input);
            $this->assertEmpty($result['description']);
            $this->assertNull($result['data']);
        }
    }

    #[Test]
    public function it_handles_malformed_colon_spacing()
    {
        $input = "Size:3\nType:  Weapon\n\nDescription";
        $result = ItemDescriptionParser::parse($input, ['Size', 'Type']);

        $this->assertEquals('3', $result['data']['Size']);
        $this->assertEquals('Weapon', $result['data']['Type']);
    }

    #[Test]
    public function it_sanitizes_values_with_trailing_punctuation()
    {
        $input = "Size: 3,\nType: Weapon.\n\nDescription";
        $result = ItemDescriptionParser::parse($input, ['Size', 'Type']);

        $this->assertEquals('3', $result['data']['Size']);
        $this->assertEquals('Weapon', $result['data']['Type']);
    }

    #[Test]
    public function it_handles_custom_keyword_mapping()
    {
        $input = "Temp. Rating: High\n\nDescription";
        $result = ItemDescriptionParser::parse($input, [
            'Temp. Rating' => 'temp_rating',
        ]);

        $this->assertEquals('High', $result['data']['temp_rating']);
    }

    #[Test]
    public function it_returns_consistent_structure_when_no_data()
    {
        $input = 'Just a plain description with no keywords';
        $result = ItemDescriptionParser::parse($input, ['Size']);

        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']);
        $this->assertEquals($input, $result['description']);
    }

    #[Test]
    public function it_handles_case_insensitive_manufacturer_fixes()
    {
        $inputs = ['MISC', 'misc', 'MiSc', 'Misc'];

        foreach ($inputs as $input) {
            $result = ItemDescriptionParser::parse("Manufacturer: $input\n\nDesc", ['Manufacturer']);
            $this->assertEquals('Musashi Industrial & Starflight Concern', $result['data']['Manufacturer']);
        }
    }

    #[Test]
    public function it_allows_custom_manufacturer_fixes()
    {
        $config = new ItemDescriptionParserConfig;
        $config->addManufacturerFix('TEST', 'Test Corporation');
        ItemDescriptionParser::setConfig($config);

        $result = ItemDescriptionParser::parse("Manufacturer: TEST\n\nDesc", ['Manufacturer']);
        $this->assertEquals('Test Corporation', $result['data']['Manufacturer']);
    }

    #[Test]
    public function it_handles_special_characters_in_values()
    {
        $input = "Type: Energy/Ballistic\nSize: 3-5\nTemp: -20°C to +150°C\n\nDesc";
        $result = ItemDescriptionParser::parse($input, ['Type', 'Size', 'Temp']);

        $this->assertEquals('Energy/Ballistic', $result['data']['Type']);
        $this->assertEquals('3-5', $result['data']['Size']);
        $this->assertStringContainsString('°C', $result['data']['Temp']);
    }

    #[Test]
    public function it_filters_keywords_for_performance()
    {
        // Test with STANDARD_KEYWORDS (79 keywords) - should filter
        $input = "Size: 3\n\nDescription";
        $result = ItemDescriptionParser::parse($input); // Uses STANDARD_KEYWORDS

        $this->assertEquals('3', $result['data']['Size']);
        $this->assertEquals('Description', $result['description']);
    }

    #[Test]
    public function it_handles_empty_values_correctly()
    {
        $input = "Size:\nType: Weapon\n\nDescription";
        $result = ItemDescriptionParser::parse($input, ['Size', 'Type']);

        // Empty values should not be included
        $this->assertArrayNotHasKey('Size', $result['data'] ?? []);
        $this->assertEquals('Weapon', $result['data']['Type']);
    }

    #[Test]
    public function it_handles_multiline_keyword_section()
    {
        $input = "Manufacturer: MISC\nSize: 3\nType: Weapon\nGrade: A\n\nThis is a multi-line\ndescription text\nwith several lines.";
        $result = ItemDescriptionParser::parse($input, ['Manufacturer', 'Size', 'Type', 'Grade']);

        $this->assertEquals('Musashi Industrial & Starflight Concern', $result['data']['Manufacturer']);
        $this->assertEquals('3', $result['data']['Size']);
        $this->assertEquals('Weapon', $result['data']['Type']);
        $this->assertEquals('A', $result['data']['Grade']);
        $this->assertStringContainsString('multi-line', $result['description']);
    }

    #[Test]
    public function it_handles_values_with_colons()
    {
        $input = "Range: 10:30 meters\n\nDescription";
        $result = ItemDescriptionParser::parse($input, ['Range']);

        // Should capture until newline, including the colon in the value
        $this->assertEquals('10:30 meters', $result['data']['Range']);
    }
}
