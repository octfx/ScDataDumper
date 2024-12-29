<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use Exception;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CacheService
{
    /**
     * @param  string  $scDataDir  Path to unforged SC Data, i.e. folder Containing `Data` and `Engine` folder
     */
    public function __construct(
        private readonly string $scDataDir,
        private readonly SymfonyStyle $io
    ) {}

    /**
     * @throws JsonException
     */
    public function makeCacheFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scDataDir));

        $classToPathMap = [];
        $classToTypeMap = [];
        $uuidToClassMap = [];
        $uuidToPathMap = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $this->io->progressAdvance();
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $ref = fopen($file->getRealPath(), 'rb');
                if ($ref) {
                    try {
                        $firstLine = fgets($ref);
                    } catch (Exception $e) {
                        continue;
                    }

                    if (str_contains($firstLine, '__ref')) {
                        preg_match('/__ref="([^"]+)"/', $firstLine, $refMatches);
                        preg_match('/^<(\w+)\.(\w+)/', $firstLine, $classMatches);

                        if (isset($classMatches[1], $classMatches[2], $refMatches[1])) {
                            $classToPathMap[$classMatches[1]] ??= [];

                            $classToPathMap[$classMatches[1]][$classMatches[2]] = $file->getRealPath();
                            $uuidToClassMap[$refMatches[1]] = $classMatches[2];
                            $uuidToPathMap[$refMatches[1]] = $file->getRealPath();

                            if (str_starts_with($firstLine, '<EntityClassDefinition')) {
                                while (($line = fgets($ref)) !== false) {
                                    if (str_contains($line, 'AttachDef')) {
                                        preg_match('/Type="([^"]+)" SubType="([^"]+)"/', $line, $typeMatches);

                                        if (isset($typeMatches[1], $typeMatches[2])) {
                                            $classToTypeMap[$classMatches[1].'.'.$classMatches[2]] = $typeMatches[1];
                                        }

                                        break;
                                    }

                                    if (str_contains($line, 'SEntityComponentDefaultLoadoutParams')) {
                                        break;
                                    }
                                }
                            } else {
                                preg_match('/__type="([^"]+)"/', $firstLine, $typeMatches);
                                if (isset($typeMatches[1])) {
                                    $classToTypeMap[$classMatches[1]] = $typeMatches[1];
                                }
                            }
                        }
                    }

                    fclose($ref);
                }
            }
        }

        $files = [
            [
                sprintf('classToPathMap-%s.json', PHP_OS_FAMILY),
                $classToPathMap,
            ],
            [
                sprintf('classToTypeMap-%s.json', PHP_OS_FAMILY),
                $classToTypeMap,
            ],
            [
                sprintf('classToUuidMap-%s.json', PHP_OS_FAMILY),
                array_flip($uuidToClassMap),
            ],
            [
                sprintf('uuidToClassMap-%s.json', PHP_OS_FAMILY),
                $uuidToClassMap,
            ],
            [
                sprintf('uuidToPathMap-%s.json', PHP_OS_FAMILY),
                $uuidToPathMap,
            ],
        ];

        foreach ($files as $file) {
            [$fileName, $array] = $file;

            $ref = fopen(sprintf('%s%s%s', $this->scDataDir, DIRECTORY_SEPARATOR, $fileName), 'wb');
            fwrite($ref, json_encode($array, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            fclose($ref);
        }
    }
}
