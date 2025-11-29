<?php

namespace Octfx\ScDataDumper\Helper;

class ItemDescriptionParser
{
    /**
     * Standard keywords extracted from SC item descriptions
     */
    public const STANDARD_KEYWORDS = [
        'Action Figure',
        'All Charge Rates',
        'Area of Effect',
        'Armor',
        'Attachment Point',
        'Attachments',
        'Badge',
        'Box',
        'Capacity',
        'Carrying Capacity',
        'Catastrophic Charge Rate',
        'Class',
        'Cluster Modifier',
        'Collection Point Radius',
        'Collection Throughput',
        'Core Compatibility',
        'Damage Reduction',
        'Damage Type',
        'Duration',
        'Effect',
        'Effective Range',
        'Effects',
        'Extraction Laser Power',
        'Extraction Rate',
        'Extraction Throughput',
        'Grade',
        'HEI',
        'Inert Materials',
        'Instability',
        'Item Type',
        'Laser Instability',
        'Magazine Size',
        'Magnification',
        'Manufacturer',
        'Maximum Range',
        'Mining Laser Power',
        'Missiles',
        'Model',
        'Module Slots',
        'Module',
        'NDR',
        'Optimal Charge Rate',
        'Optimal Charge Window Rate',
        'Optimal Charge Window Size',
        'Optimal Charge Window',
        'Optimal Range',
        'Overcharge Rate',
        'Plant',
        'Plaque',
        'Poster',
        'Power Transfer',
        'Radiation Protection',
        'Radiation Scrub Rate',
        'Rate Of Fire',
        'Resistance',
        'Schematic',
        'Shatter Damage',
        'Size',
        'Spaceglobe',
        'Temp. Rating',
        'Throttle Responsiveness Delay',
        'Throttle Speed',
        'Tracking Signal',
        'Trophy',
        'Type',
        'Uses',
    ];

    /**
     * Common manufacturer name corrections
     * Maps abbreviated or incorrect names to their canonical forms
     */
    private const MANUFACTURER_FIXES = [
        'Lighting Power Ltd.' => 'Lightning Power Ltd.',
        'MISC' => 'Musashi Industrial & Starflight Concern',
        'Nav-E7' => 'Nav-E7 Gadgets',
        'RSI' => 'Roberts Space Industries',
        'YORM' => 'Yorm',
    ];

    /**
     * Parse structured data from item description text
     * Automatically applies manufacturer name corrections
     *
     * Extracts "Keyword: Value" patterns from descriptions formatted as:
     * "Manufacturer: AEGIS\nSize: 3\n\nActual item description text..."
     *
     * @param  string  $description  Raw description text from item data
     * @param  array|null  $keywords  Keywords to extract (null = use STANDARD_KEYWORDS)
     *                                Can be:
     *                                - Array of strings: ['Size', 'Grade'] => outputs same keys
     *                                - Associative array: ['Temp. Rating' => 'temp_rating'] => maps to custom keys
     * @return array {
     *
     * @type array|null $data Extracted key-value pairs (null if no data found)
     * @type string $description Cleaned description with data section removed
     *              }
     */
    public static function parse(string $description, ?array $keywords = null): array
    {
        if ($keywords === null) {
            $keywords = self::STANDARD_KEYWORDS;
        }

        $description = self::normalizeText($description);

        // Extract lines with "keyword: value" pattern
        $parts = explode("\n", $description);
        if (count($parts) === 1) {
            $parts = explode('\n', $parts[0]);
        }

        $withColon = collect($parts)->filter(function (string $part) {
            return preg_match('/\w:[\s| ]/u', $part) === 1;
        })->implode("\n");

        // Handle both array formats: ['Size'] or ['Size' => 'size']
        if (array_is_list($keywords)) {
            $keywords = array_combine($keywords, $keywords);
        }

        $match = preg_match_all(
            '/('.implode('|', array_keys($keywords)).'):(?:\s| )?([µ\w_& (),.\\-°\/%%+-]*)(?:\n|\\\n|$)/m',
            $withColon,
            $matches
        );

        if ($match === false || $match === 0) {
            // Handle placeholder descriptions
            if ($description === '<= PLACEHOLDER =>' || str_contains($description, '[PH]')) {
                return [];
            }

            return [
                'description' => $description,
            ];
        }

        $out = [];
        for ($i = 0, $iMax = count($matches[1]); $i < $iMax; $i++) {
            if (isset($keywords[$matches[1][$i]])) {
                $value = trim($matches[2][$i]);
                $out[$keywords[$matches[1][$i]]] = $value;
            }
        }

        // Apply manufacturer name corrections automatically
        if (! empty($out['manufacturer'])) {
            $out['manufacturer'] = self::MANUFACTURER_FIXES[$out['manufacturer']] ?? $out['manufacturer'];
        }

        return [
            'description' => self::getCleanText($description),
            'data' => $out,
        ];
    }

    /**
     * Extract clean description text, removing data section
     *
     * @param  string  $description  Raw description text
     * @return string Description with "Keyword: Value" section removed
     */
    private static function getCleanText(string $description): string
    {
        $description = self::normalizeText($description);

        $exploded = explode("\n\n", $description);
        if (count($exploded) === 1) {
            $exploded = explode('\n\n', $exploded[0]);
        }

        // Remove sections containing "keyword: value" patterns
        $exploded = array_filter($exploded, static function (string $part) {
            return preg_match('/(：|\w:[\s| ])/u', $part) !== 1;
        });

        return trim(implode("\n\n", $exploded));
    }

    /**
     * Normalize description text
     * - Converts escaped newlines to actual newlines
     * - Normalizes various quote and space characters
     */
    private static function normalizeText(string $description): string
    {
        $description = str_replace('\\n \\n', '\\n\\n', $description);
        $description = trim(str_replace('\n', "\n", $description));

        return str_replace(
            ["\u{2018}", "\u{2019}", '`', "\u{00B4}", "\u{00A0}"],
            ['\'', '\'', '\'', '\'', ' '],
            $description
        );
    }
}
