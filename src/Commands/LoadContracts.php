<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractEntry;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractHandler;
use Octfx\ScDataDumper\Formats\ScUnpacked\Contract as ContractFormat;
use Octfx\ScDataDumper\Services\ContractGeneratorService;
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
    name: 'load:contracts',
    description: 'Load and dump SC Contract Generator data',
    hidden: false
)]
class LoadContracts extends AbstractDataCommand
{
    private const TYPE_CONTRACTS = 'contracts';

    private const TYPE_INTRO = 'intro';

    private const TYPE_LEGACY = 'legacy';

    private const TYPE_PVP = 'pvp';

    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading contracts');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $service = ServiceFactory::getContractGeneratorService();

        $io->text('Building mission chain index...');
        ['index' => $chainIndex, 'count' => $total] = $this->buildChainIndex($service);

        $outDir = sprintf('%s%scontracts', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $this->ensureDirectory($outDir);

        $io->progressStart($total);

        $start = microtime(true);

        $this->withLazyReferenceHydration([$service], function () use ($service, $chainIndex, $overwrite, $io, $outDir): void {
            foreach ($service->iterator() as $record) {
                $this->processRecord($record, $chainIndex, $overwrite, $io, $outDir);
            }
        });

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf(
            'Saved contract files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{index: array{awarded: array<string, list<array{uuid: string, title: ?string, debug_name: ?string}>>, required: array<string, list<array{uuid: string, title: ?string, debug_name: ?string}>>}, count: int}
     */
    private function buildChainIndex(ContractGeneratorService $service): array
    {
        $localization = ServiceFactory::getLocalizationService();
        $awarded = [];
        $required = [];
        $count = 0;

        foreach ($service->iterator() as $record) {
            foreach ($record->getHandlers() as $handler) {
                $entries = $this->allEntries($handler);
                $count += count($entries);

                foreach ($entries as $entry) {
                    $rawTitle = $entry->getTitle();
                    $translatedTitle = $rawTitle !== null
                        ? ($localization->translateValue($rawTitle) ?? $rawTitle)
                        : null;

                    $summary = [
                        'uuid' => $entry->getId() ?? '',
                        'title' => $translatedTitle,
                        'debug_name' => $entry->getDebugName(),
                    ];

                    $results = $entry->getResults();
                    if ($results !== null) {
                        foreach ($results->getCompletionTags() as $tag) {
                            $awarded[$tag][] = $summary;
                        }
                    }

                    foreach ($entry->getCompletedContractTagPrerequisites() as $prereq) {
                        foreach ($prereq['requiredTags'] as $tag) {
                            $required[$tag][] = $summary;
                        }
                    }
                }
            }
        }

        return ['index' => ['awarded' => $awarded, 'required' => $required], 'count' => $count];
    }

    private function processRecord(
        ContractGeneratorRecord $record,
        array $chainIndex,
        bool $overwrite,
        SymfonyStyle $io,
        string $outDir,
    ): void {
        foreach ($record->getHandlers() as $handler) {
            $entrySets = [
                self::TYPE_CONTRACTS => $handler->getContracts(),
                self::TYPE_INTRO => $handler->getIntroContracts(),
                self::TYPE_LEGACY => $handler->getLegacyContracts(),
                self::TYPE_PVP => $handler->getPVPBountyContracts(),
            ];

            foreach ($entrySets as $type => $entries) {
                foreach ($entries as $entry) {
                    $this->writeEntry($entry, $handler, $record, $type, $chainIndex, $overwrite, $io, $outDir);
                    $io->progressAdvance();
                }
            }
        }
    }

    private function writeEntry(
        ContractEntry $entry,
        ContractHandler $handler,
        ContractGeneratorRecord $record,
        string $type,
        array $chainIndex,
        bool $overwrite,
        SymfonyStyle $io,
        string $outDir,
    ): void {
        $debugName = $entry->getDebugName();
        $id = $debugName !== null
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $debugName)
            : $entry->getId() ?? 'unknown';
        $fileName = strtolower((string) $id);
        $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

        if (! $overwrite && file_exists($filePath)) {
            return;
        }

        try {
            $format = new ContractFormat($entry, $handler, $record, $chainIndex);
            $data = $format->toArray();
            $data['entry_type'] = $type;

            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (! $this->writeJsonFile($filePath, $json, $io)) {
                $io->warning(sprintf('Skipping contract %s due to write failure', $fileName));
            }
        } catch (JsonException $e) {
            $io->warning(sprintf('Failed to encode JSON for contract %s: %s', $fileName, $e->getMessage()));
        }
    }

    /**
     * @return list<ContractEntry>
     */
    private function allEntries(ContractHandler $handler): array
    {
        return array_merge(
            $handler->getContracts(),
            $handler->getIntroContracts(),
            $handler->getLegacyContracts(),
            $handler->getPVPBountyContracts(),
        );
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:contracts Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing contract JSON files'
        );
    }
}
