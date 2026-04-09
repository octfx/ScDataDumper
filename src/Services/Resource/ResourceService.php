<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use JsonException;
use RuntimeException;

final class ResourceService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $index = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $rows = [];

    public function __construct(
        private readonly string $basePath,
        private readonly ?string $scDataPath = null,
        private readonly string $relativeIndexPath = 'resources/resources.json'
    ) {
        $this->loadIndex();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByReference(?string $uuid): ?array
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return $this->index[$uuid] ?? null;
    }

    public function has(?string $uuid): bool
    {
        return $this->getByReference($uuid) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    private function loadIndex(): void
    {
        $indexPath = $this->getIndexPath();

        if (! file_exists($indexPath)) {
            throw new RuntimeException(sprintf(
                'Resource index is missing at %s. Generate it with: php cli.php load:resources %s %s --overwrite',
                $indexPath,
                $this->scDataPath ?? '<scDataPath>',
                $this->basePath
            ));
        }

        try {
            $data = json_decode(file_get_contents($indexPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                'Resource index at %s contains invalid JSON.',
                $indexPath
            ), previous: $exception);
        }

        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException(sprintf(
                'Resource index at %s must be a JSON array of rows.',
                $indexPath
            ));
        }

        $index = [];

        foreach ($data as $row) {
            if (! is_array($row)) {
                throw new RuntimeException(sprintf(
                    'Resource index at %s contains a non-object row.',
                    $indexPath
                ));
            }

            $uuid = $row['uuid'] ?? null;

            if ($uuid === null) {
                throw new RuntimeException(sprintf(
                    'Resource index at %s contains a row without a valid uuid.',
                    $indexPath
                ));
            }

            if ($uuid === null) {
                throw new RuntimeException(sprintf(
                    'Resource index at %s contains a row without a valid uuid.',
                    $indexPath
                ));
            }

            $index[$uuid] = $row;
        }

        $this->rows = $data;
        $this->index = $index;
    }

    private function getIndexPath(): string
    {
        return sprintf(
            '%s%s%s',
            rtrim($this->basePath, DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            str_replace('/', DIRECTORY_SEPARATOR, $this->relativeIndexPath)
        );
    }
}
