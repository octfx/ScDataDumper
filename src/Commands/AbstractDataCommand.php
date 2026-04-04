<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Octfx\ScDataDumper\Commands\Concerns\GeneratesCache;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

abstract class AbstractDataCommand extends Command
{
    use GeneratesCache;

    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        $result = $this->generateCache($this->getScDataPath($input), $output);

        if ($result !== Command::SUCCESS) {
            throw new RuntimeException('Failed to generate cache files');
        }

        $factory = new ServiceFactory($this->getScDataPath($input), $this->getJsonOutPath($input));
        $factory->initialize();
    }

    protected function getScDataPath(InputInterface $input): string
    {
        return (string) $input->getArgument('scDataPath');
    }

    protected function getJsonOutPath(InputInterface $input): string
    {
        return (string) $input->getArgument('jsonOutPath');
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
    }

    protected function normalizeFilter(mixed $filter): ?string
    {
        if (! is_string($filter) || $filter === '') {
            return null;
        }

        return strtolower($filter);
    }

    protected function writeJsonFile(string $filePath, string $content, SymfonyStyle $io): bool
    {
        try {
            $bytesWritten = file_put_contents($filePath, $content);

            if ($bytesWritten === false) {
                $io->error(sprintf('Failed to write file: %s', $filePath));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $io->error(sprintf('Error writing %s: %s', $filePath, $e->getMessage()));

            return false;
        }
    }
}
