<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\DataDumper;

use ZipArchive;

final class SocpakReader
{
    /** @var array<string, list<string>> */
    private array $dirCache = [];

    /** @var array<string, string|null> keyed by socpakPath => editorXml content */
    private array $editorXmlCache = [];

    /** @var array<string, string|null> keyed by socpakPath => first non-editor/non-metadata XML content */
    private array $xmlCache = [];

    public function __construct(
        private readonly ?string $scDataPath,
    ) {}

    /**
     * Resolve a relative path (from a game entity reference) to an absolute filesystem path.
     *
     * Walks the SC `Data/` directory tree using case-insensitive matching.
     */
    public function resolveSocpakPath(string $relativePath): ?string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $normalized = ltrim($normalized, '/');
        $normalized = strtolower($normalized);

        $dataDir = $this->scDataPath.DIRECTORY_SEPARATOR.'Data';

        $parts = explode('/', $normalized);
        $currentDir = $dataDir;

        foreach ($parts as $i => $part) {
            $isLast = $i === count($parts) - 1;

            if ($isLast) {
                $resolved = $this->findFileInDir($part, $currentDir);

                return $resolved !== null
                    ? $currentDir.DIRECTORY_SEPARATOR.$resolved
                    : null;
            }

            $resolved = $this->findDirInDir($part, $currentDir);

            if ($resolved === null) {
                return null;
            }

            $currentDir .= DIRECTORY_SEPARATOR.$resolved;
        }

        return null;
    }

    /**
     * Extract the `_editor.xml` entry from a socpak zip.
     *
     * Results are cached per socpak path.
     */
    public function extractEditorXml(string $socpakPath): ?string
    {
        if (array_key_exists($socpakPath, $this->editorXmlCache)) {
            return $this->editorXmlCache[$socpakPath];
        }

        $zip = new ZipArchive;

        if ($zip->open($socpakPath) !== true) {
            return $this->editorXmlCache[$socpakPath] = null;
        }

        $editorXml = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = str_replace('\\', '/', $stat['name']);

            if (str_ends_with($entryName, '_editor.xml')) {
                $editorXml = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        return $this->editorXmlCache[$socpakPath] = ($editorXml !== false) ? $editorXml : null;
    }

    /**
     * Extract the main object-container XML from a socpak zip.
     *
     * The container XML always matches the socpak basename (e.g. `stanton3b.socpak` ->
     * `stanton3b.xml`).  Matching by name avoids false positives from nested or
     * auxiliary XML entries in the zip.
     *
     * Falls back to the first non-editor, non-metadata XML if the basename match fails
     * (backwards-compatibility with older or atypical socpak layouts).
     *
     * Results are cached per socpak path.
     */
    public function extractXml(string $socpakPath): ?string
    {
        if (array_key_exists($socpakPath, $this->xmlCache)) {
            return $this->xmlCache[$socpakPath];
        }

        $zip = new ZipArchive;

        if ($zip->open($socpakPath) !== true) {
            return $this->xmlCache[$socpakPath] = null;
        }

        $target = pathinfo($socpakPath, PATHINFO_FILENAME).'.xml';
        $xml = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);

            if ($name === $target) {
                $xml = $zip->getFromIndex($i);
                break;
            }
        }

        // Fallback: first non-editor, non-metadata XML (covers older/non-standard layouts)
        if ($xml === null || $xml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', $stat['name']);

                if (str_ends_with($name, '.xml')
                    && ! str_contains($name, '_editor')
                    && ! str_contains($name, 'metadata')) {
                    $xml = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        return $this->xmlCache[$socpakPath] = ($xml !== false) ? $xml : null;
    }

    /**
     * Case-insensitive file lookup inside a directory.
     */
    public function findFileInDir(string $lowerName, string $dir): ?string
    {
        $entries = $this->scanDir($dir);
        $lower = strtolower($lowerName);

        return array_find($entries, fn ($entry) => strtolower($entry) === $lower && ! is_dir($dir.DIRECTORY_SEPARATOR.$entry));
    }

    /**
     * Case-insensitive directory lookup inside a directory.
     */
    public function findDirInDir(string $lowerName, string $dir): ?string
    {
        $entries = $this->scanDir($dir);
        $lower = strtolower($lowerName);

        return array_find($entries, fn ($entry) => strtolower($entry) === $lower && is_dir($dir.DIRECTORY_SEPARATOR.$entry));
    }

    /**
     * Check whether a file exists in a directory (case-insensitive).
     */
    public function fileExistsInDir(string $filename, string $dir): bool
    {
        return $this->findFileInDir($filename, $dir) !== null;
    }

    /**
     * @return list<string>
     */
    public function scanDir(string $dir): array
    {
        if (! isset($this->dirCache[$dir])) {
            $entries = @scandir($dir);
            $this->dirCache[$dir] = $entries !== false
                ? array_values(array_diff($entries, ['.', '..']))
                : [];
        }

        return $this->dirCache[$dir];
    }
}
