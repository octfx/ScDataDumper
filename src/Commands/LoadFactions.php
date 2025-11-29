<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'load:factions',
    description: 'Load and dump SC Factions',
    hidden: false
)]
class LoadFactions extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading factions');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $service = ServiceFactory::getFactionService();

        $io->progressStart($service->count());

        $outDir = sprintf('%s%sfactions', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        $start = microtime(true);

        foreach ($service->iterator() as $faction) {
            $fileName = strtolower($faction->getClassName());
            $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            if (! $overwrite && file_exists($filePath)) {
                $io->progressAdvance();

                continue;
            }

            try {
                if (! $this->writeJsonFile($filePath, $faction->toJson(), $io)) {
                    $io->warning(sprintf('Skipping faction %s due to write failure', $fileName));
                    $io->progressAdvance();

                    continue;
                }
            } catch (JsonException $e) {
                $io->warning(sprintf('Failed to encode JSON for faction %s: %s', $fileName, $e->getMessage()));
                $io->progressAdvance();

                continue;
            }

            $io->progressAdvance();
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved factions files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        return Command::SUCCESS;
    }

    /**
     * Safely write JSON content to file
     */
    private function writeJsonFile(string $filePath, string $content, SymfonyStyle $io): bool
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

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:factions Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing faction JSON files'
        );
        $this->addOption(
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Export factions in SC Unpacked format (currently has no effect)'
        );
    }
}
