<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use FilesystemIterator;
use Generator;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Loadout;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class LoadoutFileService extends BaseService
{
    private array $loadoutPaths = [];

    public function count(): int
    {
        return count($this->loadoutPaths);
    }

    /**
     * @throws JsonException
     */
    public function initialize(): void
    {
        $loadoutsDir = $this->scDataDir.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'Loadouts';

        if (! is_dir($loadoutsDir)) {
            throw new RuntimeException(sprintf('Directory %s does not exist or is not readable.', $loadoutsDir));
        }

        $this->loadoutPaths = $this->mapLoadoutFiles($loadoutsDir);
    }

    /**
     * Uses RecursiveDirectoryIterator to find all XML files under the Loadouts directory.
     */
    private function mapLoadoutFiles(string $directory): array
    {
        $files = [];
        $basePathLength = strlen($this->scDataDir.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (strtolower($file->getExtension()) === 'xml') {
                $fullPath = $file->getRealPath();
                if ($fullPath === false) {
                    continue;
                }

                $relativePath = substr($fullPath, $basePathLength);
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $files[$relativePath] = $fullPath;
            }
        }

        return $files;
    }

    public function iterator(): Generator
    {
        foreach ($this->loadoutPaths as $path) {
            yield $this->load($path);
        }
    }

    public function getByLoadoutPath(string $loadoutPath): ?Loadout
    {
        $loadoutPath = str_replace('\\', '/', $loadoutPath);

        if (isset($this->loadoutPaths[$loadoutPath])) {
            return $this->load($this->loadoutPaths[$loadoutPath]);
        }

        return null;
    }

    public function load(string $filePath): Loadout
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException(sprintf('File %s does not exist or is not readable.', $filePath));
        }

        $loadout = new Loadout;
        $loadout->load($filePath);

        return $loadout;
    }
}
