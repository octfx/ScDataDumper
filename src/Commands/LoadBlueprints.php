<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Formats\ScUnpacked\Blueprint;
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
    name: 'load:blueprints',
    description: 'Load and dump SC Blueprints',
    hidden: false
)]
class LoadBlueprints extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading blueprints');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $service = ServiceFactory::getBlueprintService();

        $io->progressStart($service->count());

        $outDir = sprintf('%s%sblueprints', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        $start = microtime(true);
        $testFileCount = 0;
        $exportedCount = 0;

        foreach ($service->iterator() as $blueprintDocument) {
            $className = strtolower($blueprintDocument->getClassName());

            //            if (str_contains($className, 'test')) {
            //                $io->note(sprintf('Skipping test blueprint: %s', $className));
            //                $testFileCount++;
            //                $io->progressAdvance();
            //
            //                continue;
            //            }

            dd($blueprintDocument->toArray());
            $format = new Blueprint($blueprintDocument);
            $data = $format->toArray();
            if (empty($data)) {
                $io->progressAdvance();

                continue;
            }

            $blueprintData = [
                'Raw' => $blueprintDocument->toArray(),
                'Blueprint' => $data,
            ];

            dd($blueprintData);

            $fileName = $className;
            $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            if (! $overwrite && file_exists($filePath)) {
                $io->progressAdvance();

                continue;
            }

            try {
                $jsonContent = json_encode($blueprintData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

                if (! $this->writeJsonFile($filePath, $jsonContent, $io)) {
                    $io->warning(sprintf('Skipping blueprint %s due to write failure', $fileName));
                    $io->progressAdvance();

                    continue;
                }

                $exportedCount++;
            } catch (JsonException $e) {
                $io->warning(sprintf('Failed to encode JSON for blueprint %s: %s', $fileName, $e->getMessage()));
                $io->progressAdvance();

                continue;
            }

            $io->progressAdvance();
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved blueprint files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        if ($testFileCount > 0) {
            $io->note(sprintf('Skipped %d test blueprint files', $testFileCount));
        }

        $io->text(sprintf('Exported %d blueprints', $exportedCount));

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
        $this->setHelp('php cli.php load:blueprints Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing blueprint JSON files'
        );
    }
}
