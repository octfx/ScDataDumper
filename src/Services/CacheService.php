<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services;

use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
use XMLReader;

final readonly class CacheService
{
    /**
     * @param  string  $scDataDir  Path to unforged SC Data, i.e. folder Containing `Data` and `Engine` folder
     */
    public function __construct(
        private string $scDataDir,
        private SymfonyStyle $io
    ) {}

    /**
     * @throws JsonException
     */
    public function makeCacheFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scDataDir));

        $classToPathMap = [];
        $classToTypeMap = [];
        $entityMetadataMap = [];
        $uuidToClassMap = [];
        $uuidToPathMap = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $this->io->progressAdvance();
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $filePath = $file->getRealPath();
                if (! is_string($filePath)) {
                    continue;
                }

                $parsed = $this->parseRootClassInfo($filePath);
                if ($parsed === null) {
                    continue;
                }

                $normalizedFilePath = str_replace('\\', '/', $filePath);

                $classToPathMap[$parsed['docType']] ??= [];
                if (
                    $parsed['isSynthesizedNoDot'] === true &&
                    isset($classToPathMap[$parsed['docType']][$parsed['className']]) &&
                    $classToPathMap[$parsed['docType']][$parsed['className']] !== $normalizedFilePath
                ) {
                    $this->io->warning(sprintf(
                        'Synthetic no-dot class collision for %s.%s, overwriting %s with %s',
                        $parsed['docType'],
                        $parsed['className'],
                        $classToPathMap[$parsed['docType']][$parsed['className']],
                        $normalizedFilePath
                    ));
                }

                $classToPathMap[$parsed['docType']][$parsed['className']] = $normalizedFilePath;
                $uuidToClassMap[$parsed['uuid']] = $parsed['className'];
                $uuidToPathMap[$parsed['uuid']] = $normalizedFilePath;

                if ($parsed['docType'] === 'EntityClassDefinition') {
                    $entityMetadata = $this->extractEntityMetadata($filePath);
                    if ($entityMetadata !== null) {
                        $classToTypeMap[$parsed['docType'].'.'.$parsed['className']] = $entityMetadata['type'];
                        $entityMetadataMap[$parsed['className']] = [
                            'uuid' => $parsed['uuid'],
                            'path' => $normalizedFilePath,
                            'type' => $entityMetadata['type'],
                            'sub_type' => $entityMetadata['sub_type'],
                        ];
                    }
                } elseif ($parsed['type'] !== '') {
                    $classToTypeMap[$parsed['docType']] = $parsed['type'];
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
                sprintf('entityMetadataMap-%s.json', PHP_OS_FAMILY),
                $entityMetadataMap,
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

    /**
     * @return array{docType: string, className: string, uuid: string, type: string, isSynthesizedNoDot: bool}|null
     */
    private function parseRootClassInfo(string $filePath): ?array
    {
        $reader = XMLReader::open($filePath, null, LIBXML_NONET | LIBXML_COMPACT);
        if (! $reader) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                $uuid = (string) ($reader->getAttribute('__ref') ?? '');
                if ($uuid === '') {
                    return null;
                }

                $rootName = $reader->name;
                $rootType = (string) ($reader->getAttribute('__type') ?? '');
                $rootPath = (string) ($reader->getAttribute('__path') ?? '');

                [$docType, $className, $isSynthesizedNoDot] = $this->resolveDocTypeAndClassName($rootName, $rootPath, $filePath);
                if ($docType === '' || $className === '') {
                    return null;
                }

                return [
                    'docType' => $docType,
                    'className' => $className,
                    'uuid' => $uuid,
                    'type' => $rootType,
                    'isSynthesizedNoDot' => $isSynthesizedNoDot,
                ];
            }
        } finally {
            $reader->close();
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function resolveDocTypeAndClassName(string $rootName, string $rootPath, string $filePath): array
    {
        $dotPosition = strpos($rootName, '.');
        if ($dotPosition !== false) {
            return [
                substr($rootName, 0, $dotPosition),
                substr($rootName, $dotPosition + 1),
                false,
            ];
        }

        $className = $this->extractFileStem($rootPath);
        if ($className === '') {
            $className = pathinfo($filePath, PATHINFO_FILENAME);
        }

        return [
            $rootName,
            $className,
            true,
        ];
    }

    private function extractFileStem(string $filePath): string
    {
        if ($filePath === '') {
            return '';
        }

        $normalizedPath = str_replace('\\', '/', $filePath);
        $baseName = basename($normalizedPath);
        if ($baseName === '' || $baseName === '.' || $baseName === '..') {
            return '';
        }

        return pathinfo($baseName, PATHINFO_FILENAME);
    }

    /**
     * @return array{type: string, sub_type: ?string}|null
     */
    private function extractEntityMetadata(string $filePath): ?array
    {
        $reader = XMLReader::open($filePath, null, LIBXML_NONET | LIBXML_COMPACT);
        if (! $reader) {
            return null;
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->name === 'AttachDef') {
                    $type = $reader->getAttribute('Type');
                    $subType = $reader->getAttribute('SubType');
                    if ($type !== null && $type !== '') {
                        return [
                            'type' => $type,
                            'sub_type' => $subType !== null && $subType !== '' ? $subType : null,
                        ];
                    }

                    return null;
                }

                if ($reader->name === 'SEntityComponentDefaultLoadoutParams') {
                    return null;
                }
            }
        } finally {
            $reader->close();
        }

        return null;
    }
}
