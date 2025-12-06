<?php

namespace Octfx\ScDataDumper\Helper;

class ItemDescriptionParser
{
    /**
     * Configuration instance for the parser
     */
    private static ?ItemDescriptionParserConfig $config = null;

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
        'Battery Size',
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
        'Focus',
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
     * Set custom configuration for the parser
     */
    public static function setConfig(ItemDescriptionParserConfig $config): void
    {
        self::$config = $config;
    }

    /**
     * Get parser configuration (lazy initialization)
     */
    private static function getConfig(): ItemDescriptionParserConfig
    {
        return self::$config ??= new ItemDescriptionParserConfig;
    }

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
            return preg_match('/\w:/u', $part) === 1;
        })->implode("\n");

        // Handle both array formats: ['Size'] or ['Size' => 'size']
        if (array_is_list($keywords)) {
            $keywords = array_combine($keywords, $keywords);
        }

        $keywords = self::filterRelevantKeywords($withColon, $keywords);

        // Early return if no keywords remain
        if (empty($keywords)) {
            return [
                'description' => $description,
                'data' => null,
            ];
        }

        $match = preg_match_all(
            '/('.implode('|', array_keys($keywords)).'):[ \t]?(.+?)(?:\n|$)/mu',
            $withColon,
            $matches
        );

        if ($match === false || $match === 0) {
            if (self::getConfig()->isPlaceholder($description)) {
                return [
                    'description' => '',
                    'data' => null,
                ];
            }

            return [
                'description' => $description,
                'data' => null,
            ];
        }

        $out = [];
        for ($i = 0, $iMax = count($matches[1]); $i < $iMax; $i++) {
            if (isset($keywords[$matches[1][$i]])) {
                $value = self::sanitizeValue($matches[2][$i]);
                if ($value !== '') {
                    $out[$keywords[$matches[1][$i]]] = $value;
                }
            }
        }

        // Apply manufacturer name corrections automatically
        // Check for both 'manufacturer' and 'Manufacturer' keys
        foreach (['manufacturer', 'Manufacturer'] as $key) {
            if (! empty($out[$key])) {
                $fixed = self::getConfig()->getManufacturerFix($out[$key]);
                if ($fixed !== null) {
                    $out[$key] = $fixed;
                }
            }
        }

        return [
            'description' => self::getCleanText($description),
            'data' => $out ?: null,
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
            return preg_match('/(：|\w:)/u', $part) !== 1;
        });

        return trim(implode("\n\n", $exploded));
    }

    /**
     * Pre-filter keywords to only those likely present in description
     * Reduces regex complexity for descriptions with few keywords
     *
     * @param  string  $description  Description text to search
     * @param  array  $keywords  All available keywords
     * @return array Filtered keywords that appear in description
     */
    private static function filterRelevantKeywords(string $description, array $keywords): array
    {
        if (count($keywords) < 10) {
            return $keywords;
        }

        $relevant = array_filter($keywords, static function ($key) use ($description) {
            return stripos($description, $key) !== false;
        }, ARRAY_FILTER_USE_KEY);

        return $relevant ?: $keywords;
    }

    /**
     * Sanitize extracted value
     * Handles trailing punctuation, excess whitespace, invalid chars
     *
     * @param  string  $value  Raw value from regex extraction
     * @return string Sanitized value
     */
    private static function sanitizeValue(string $value): string
    {
        $value = trim($value);

        $value = rtrim($value, '.,;');

        $value = preg_replace('/\s+/', ' ', $value);

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return trim($value);
    }

    /**
     * Normalize description text
     * - Converts all newline formats to \n
     * - Normalizes Unicode quotes, dashes, and spaces
     * - Collapses excessive whitespace
     */
    private static function normalizeText(string $description): string
    {
        $config = self::getConfig();

        foreach ($config->getNewlineFormats() as $format) {
            $description = str_replace($format, "\n", $description);
        }

        // Normalize double-escaped newlines with spaces (\\n \\n -> \n\n)
        $description = preg_replace('/\\\n\s+\\\n/', "\n\n", $description);

        $description = str_replace(
            array_keys($config->getUnicodeReplacements()),
            array_values($config->getUnicodeReplacements()),
            $description
        );

        $description = preg_replace('/[ \t]+/', ' ', $description);

        return trim($description);
    }
}
