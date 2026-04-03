<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\DataDumper;

use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use XMLReader;

final readonly class Game2ExtractorService
{
    private array $types;

    private string $gameDataPath;

    /**
     * @param  string  $scDataDir  Path to unforged SC Data, i.e. folder containing `Data` and `Engine`
     */
    public function __construct(
        private string $scDataDir,
        private SymfonyStyle $io
    ) {
        // Dot is important; otherwise TagFoo.Bar would match Tag.
        $this->types = [
            'Tag.',
            'StarMapObjectType.',
            'ConsumableSubtype.',
            'ResourceType.',
        ];

        $this->gameDataPath = sprintf(
            '%s%sData%sGame2.xml',
            $scDataDir,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
        );
    }

    public function extract(): void
    {
        if (! file_exists($this->gameDataPath)) {
            $this->io->error("Game2.xml not found at $this->gameDataPath");

            return;
        }

        $reader = XMLReader::open($this->gameDataPath, null, LIBXML_NONET | LIBXML_COMPACT);
        if (! $reader) {
            throw new RuntimeException(sprintf('Failed to open %s', $this->gameDataPath));
        }

        $stats = [];
        $failedWrite = false;

        try {
            while ($reader->read()) {
                if (
                    $reader->nodeType !== XMLReader::ELEMENT
                    || ! array_any($this->types, fn (string $type) => str_starts_with($reader->name, $type))
                ) {
                    continue;
                }

                $uuid = $reader->getAttribute('__ref');
                $path = $reader->getAttribute('__path');
                $xml = $reader->readOuterXml();

                if (empty($path) || empty($uuid) || $xml === '') {
                    $this->io->error("Invalid data for $reader->name");

                    continue;
                }

                $outPath = $this->makeOutputDir($path, $uuid);
                if ($outPath === null) {
                    $this->io->error(sprintf('Invalid output path "%s" for %s', $path, $reader->name));

                    continue;
                }

                $success = file_put_contents($outPath, $xml);
                if ($success === false) {
                    $this->io->error(sprintf('Failed to write %s', $outPath));
                    $failedWrite = true;

                    continue;
                }

                $type = explode('.', $reader->name)[0];
                $stats[$type] ??= 0;
                $stats[$type]++;
            }
        } finally {
            $reader->close();
        }

        if ($failedWrite) {
            $this->io->error('One or more files failed to write during extraction');

            return;
        }

        $this->io->success('Game2.xml extracted successfully');
        $this->io->table(
            ['Type', 'Count'],
            array_map(
                static fn (string $type, int $count): array => [$type, $count],
                array_keys($stats),
                $stats
            )
        );
    }

    private function makeOutputDir(string $path, string $uuid): ?string
    {
        $parts = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn (string $segment): bool => $segment !== '' && ! in_array($segment, ['.', '..'], true)
        ));

        if ($parts === []) {
            return null;
        }

        $lastPart = array_last($parts);
        if ($lastPart !== null && str_ends_with($lastPart, '.xml')) {
            array_pop($parts);
        }

        $parts[] = "$uuid.xml";

        $path = implode(DIRECTORY_SEPARATOR, [
            $this->scDataDir,
            'Game2',
            ...$parts,
        ]);

        $outDir = dirname($path);
        if (is_dir($outDir)) {
            return $path;
        }

        if (! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        return $path;
    }
}
