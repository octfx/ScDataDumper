<?php

namespace Octfx\ScDataDumper\Helper;

class ItemDescriptionParserConfig
{
    /**
     * Manufacturer name normalizations
     * Key = description text, Value = canonical name
     */
    private array $manufacturerFixes = [
        'Lighting Power Ltd.' => 'Lightning Power Ltd.',
        'MISC' => 'Musashi Industrial & Starflight Concern',
        'Nav-E7' => 'Nav-E7 Gadgets',
        'RSI' => 'Roberts Space Industries',
        'YORM' => 'Yorm',
    ];

    /**
     * Patterns that indicate placeholder descriptions
     */
    private array $placeholderPatterns = [
        '/^<= PLACEHOLDER =>$/i',
        '/\[PH\]/i',
        '/\{PH\}/i',
        '/PLACEHOLDER/i',
    ];

    /**
     * Newline sequences to normalize to \n
     */
    private array $newlineFormats = ["\r\n", "\r", '\n', '\\n'];

    /**
     * Unicode character replacements
     */
    private array $unicodeReplacements = [
        "\u{2018}" => "'",  // Left single quotation mark
        "\u{2019}" => "'",  // Right single quotation mark
        "\u{201C}" => '"',  // Left double quotation mark
        "\u{201D}" => '"',  // Right double quotation mark
        '`' => "'",
        "\u{00B4}" => "'",  // Acute accent
        "\u{00A0}" => ' ',  // Non-breaking space
        "\u{2013}" => '-',  // En dash
        "\u{2014}" => '-',  // Em dash
    ];

    /**
     * Add a manufacturer name normalization
     *
     * @param  string  $from  Manufacturer name as it appears in descriptions
     * @param  string  $to  Canonical manufacturer name
     */
    public function addManufacturerFix(string $from, string $to): self
    {
        $this->manufacturerFixes[$from] = $to;

        return $this;
    }

    /**
     * Get canonical manufacturer name (case-insensitive lookup)
     *
     * @param  string  $name  Manufacturer name from description
     * @return string|null Canonical name, or null if not found
     */
    public function getManufacturerFix(string $name): ?string
    {
        if (isset($this->manufacturerFixes[$name])) {
            return $this->manufacturerFixes[$name];
        }

        foreach ($this->manufacturerFixes as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Add a placeholder detection pattern
     *
     * @param  string  $pattern  Regex pattern to match placeholder descriptions
     */
    public function addPlaceholderPattern(string $pattern): self
    {
        $this->placeholderPatterns[] = $pattern;

        return $this;
    }

    /**
     * Check if description is a placeholder
     *
     * @param  string  $description  Description text to check
     */
    public function isPlaceholder(string $description): bool
    {
        foreach ($this->placeholderPatterns as $pattern) {
            if (preg_match($pattern, $description)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Unicode character replacement map
     */
    public function getUnicodeReplacements(): array
    {
        return $this->unicodeReplacements;
    }

    /**
     * Get newline format variants to normalize
     */
    public function getNewlineFormats(): array
    {
        return $this->newlineFormats;
    }
}
