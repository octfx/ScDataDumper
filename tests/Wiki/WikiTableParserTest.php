<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Wiki;

use Octfx\ScDataDumper\Wiki\WikiTableParser;
use PHPUnit\Framework\TestCase;

final class WikiTableParserTest extends TestCase
{
    public function test_parses_published_items_layout_with_note_and_scratch_row(): void
    {
        // Mirrors the real Star_Citizen_Wiki:SCWAPI/items page: a {{Note|...}}
        // block, a header, two data rows, and a trailing blank scratch row.
        $wikitext = <<<'WIKI'
{{Note|This table is the authoritative source of truth.

'''How it works:'''
* Empty cell means "no override", not clear.
}}
{| class="wikitable scwapi-items"
!UUID
!Event
!Manufacturer
!Name
!Label
|-
|0025926d-1659-44ad-9780-a9538a19a638
|
|XNAA
|
|Nox Slipstream Livery
|-
|01684bc9-391d-431e-9c48-b5c0270778ff
|Concierge
|RSI
|
|Scorpius Tiburon Livery
|-
|
|
|
|
|
|}
WIKI;

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertCount(3, $rows);
        self::assertSame(
            ['UUID' => '0025926d-1659-44ad-9780-a9538a19a638', 'Event' => '', 'Manufacturer' => 'XNAA', 'Name' => '', 'Label' => 'Nox Slipstream Livery'],
            $rows[0],
        );
        self::assertSame('Concierge', $rows[1]['Event']);
        // Scratch row passes through untouched; the command decides to skip it.
        self::assertSame('', $rows[2]['UUID']);
    }

    public function test_empty_cell_is_empty_string_not_omitted(): void
    {
        $wikitext = "{| class=\"wikitable\"\n!UUID\n!Event\n|-\n|abc\n|\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame(['UUID' => 'abc', 'Event' => ''], $rows[0]);
    }

    public function test_handles_inline_double_pipe_cells(): void
    {
        $wikitext = "{| class=\"wikitable\"\n!UUID\n!Manufacturer\n|-\n|abc||ORIG\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame(['UUID' => 'abc', 'Manufacturer' => 'ORIG'], $rows[0]);
    }

    public function test_returns_empty_when_no_table_present(): void
    {
        self::assertSame([], WikiTableParser::parseTable('Just some prose, no table here.'));
    }

    public function test_ignores_row_separator_attributes(): void
    {
        $wikitext = "{| class=\"wikitable\"\n!UUID\n|-\n|one\n|- style=\"background:#eee\"\n|two\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame('one', $rows[0]['UUID']);
        self::assertSame('two', $rows[1]['UUID']);
    }

    public function test_short_cells_are_padded_to_header_count(): void
    {
        // Fewer cells than headers should not raise; missing cells become ''.
        $wikitext = "{| class=\"wikitable\"\n!UUID\n!Event\n!Manufacturer\n|-\n|abc\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame(['UUID' => 'abc', 'Event' => '', 'Manufacturer' => ''], $rows[0]);
    }

    public function test_extra_cells_beyond_headers_are_dropped(): void
    {
        // mapRow iterates headers, not cells, so surplus cells are ignored.
        $wikitext = "{| class=\"wikitable\"\n!UUID\n|-\n|abc||extra||ignored\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame(['UUID' => 'abc'], $rows[0]);
    }

    public function test_handles_inline_double_bang_headers(): void
    {
        $wikitext = "{| class=\"wikitable\"\n!UUID !! Event\n|-\n|abc||ORIG\n|}";

        $rows = WikiTableParser::parseTable($wikitext);

        self::assertSame(['UUID' => 'abc', 'Event' => 'ORIG'], $rows[0]);
    }
}
