<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;
use SplFileInfo;

final class LocalizationService extends BaseService
{
    private array $translations = [];

    /**
     * @param  string  $scDataDir  Path to unforged SC Data, i.e. folder Containing `Data` and `Engine` folder
     *
     * @throws JsonException
     */
    public function __construct(string $scDataDir)
    {
        parent::__construct($scDataDir);

        $basePath = sprintf(
            '%s%sData%sLocalization',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        $translations = new RegexIterator($iterator, '/\.ini/');

        /** @var $file SplFileInfo */
        foreach ($translations as $file) {
            $key = str_replace([$basePath, 'global.ini'], '', $file->getPathname());
            $key = trim($key, DIRECTORY_SEPARATOR);

            $this->translations[$key] = $this->loadTranslation($file->getRealPath());
        }

        if (! isset($this->translations['english'])) {
            throw new RuntimeException('No translations found');
        }
    }

    public function getLocales(): array
    {
        return array_keys($this->translations);
    }

    public function getTranslation(string $key, string $locale = 'english'): string
    {
        $cleanedKey = $this->cleanKey($key);
        $placeholderKey = sprintf('%s,P', $cleanedKey);

        return $this->translations[$locale][$cleanedKey] ?? $this->translations[$locale][$placeholderKey] ?? $key;
    }

    public function getTranslations(string $key): array
    {
        $out = [];

        foreach ($this->getLocales() as $locale) {
            $translation = $this->getTranslation($key, $locale);
            if ($translation !== $key) {
                $out[$locale] = $translation;
            }
        }

        return $out;
    }

    /**
     * Get all available translations.
     *
     * @return array Array of translations with locale keys
     */
    public function getAllTranslations(): array
    {
        return $this->translations;
    }

    private function cleanKey(string $key): string
    {
        return trim(ltrim($key, '@'));
    }

    private function loadTranslation(string $path): array
    {
        $fp = fopen($path, 'rb');

        $translations = [];

        while (! feof($fp)) {
            $line = fgets($fp);

            if (is_bool($line)) {
                break;
            }

            $line = trim($line);
            $parts = explode('=', $line, 2);

            if (count($parts) === 2) {
                $translations[$parts[0]] = $parts[1];
            }
        }

        fclose($fp);

        return $translations;
    }

    public function initialize(): void {}
}
