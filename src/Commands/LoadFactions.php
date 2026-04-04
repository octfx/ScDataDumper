<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Faction\Faction;
use Octfx\ScDataDumper\Formats\ScUnpacked\Faction as ScUnpackedFaction;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:factions',
    description: 'Load and dump SC Factions',
    hidden: false
)]
class LoadFactions extends AbstractDataCommand
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading factions');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $service = ServiceFactory::getFoundryLookupService();

        $io->progressStart($service->countDocumentType('Faction'));

        $outDir = sprintf('%s%sfactions', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        $this->ensureDirectory($outDir);

        $start = microtime(true);

        $this->withLazyReferenceHydration([$service], function () use ($service, $overwrite, $io, $outDir): void {
            foreach ($service->getDocumentType('Faction', Faction::class) as $faction) {
                $fileName = strtolower($faction->getClassName());
                $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

                if (! $overwrite && file_exists($filePath)) {
                    $io->progressAdvance();

                    continue;
                }

                try {
                    $json = json_encode((new ScUnpackedFaction($faction))->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    if (! $this->writeJsonFile($filePath, $json, $io)) {
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
        });

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved factions files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        return Command::SUCCESS;
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
            'Deprecated: SC Unpacked format is now the default and this option has no effect'
        );
    }
}
