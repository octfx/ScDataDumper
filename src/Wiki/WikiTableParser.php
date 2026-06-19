<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Wiki;

/**
 * Parses one MediaWiki table ({| ... |}) into rows of assoc arrays. Supports
 * one-cell-per-line and inline (|| / !!) formats. Applies no policy: scratch
 * rows, dup UUIDs, and unknown values pass through for the caller to validate.
 */
final class WikiTableParser
{
    /**
     * @return list<array<string, string>>
     */
    public static function parseTable(string $wikitext): array
    {
        if (! preg_match('/\{\|.*?\|}/s', $wikitext, $m)) {
            return [];
        }

        $headers = [];
        $rows = [];
        $current = null;

        foreach (preg_split('/\r\n|\n|\r/', $m[0]) as $line) {
            if (str_starts_with($line, '{|')) {
                continue;
            }

            if (str_starts_with($line, '|}')) {
                break;
            }

            if (str_starts_with($line, '|-')) {
                if ($current !== null && $headers !== []) {
                    $rows[] = self::mapRow($current, $headers);
                }
                $current = [];

                continue;
            }

            if (str_starts_with($line, '!')) {
                foreach (explode('!!', substr($line, 1)) as $cell) {
                    $headers[] = trim($cell);
                }

                continue;
            }

            if (str_starts_with($line, '|')) {
                foreach (explode('||', substr($line, 1)) as $cell) {
                    $current[] = trim($cell);
                }
            }
        }

        if ($current !== null && $headers !== []) {
            $rows[] = self::mapRow($current, $headers);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private static function mapRow(array $cells, array $headers): array
    {
        $row = [];
        foreach ($headers as $i => $header) {
            $row[$header] = $cells[$i] ?? '';
        }

        return $row;
    }
}
