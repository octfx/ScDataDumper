<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Services\Resource\CommodityTradeLocationResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:commodity-trade-locations',
    description: 'Link commodities to trade locations via tag hierarchy matching',
    hidden: false
)]
class LoadCommodityTradeLocations extends AbstractDataCommand
{
    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading commodity trade location links');

        $outputDir = rtrim((string) $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $this->ensureDirectory($outputDir);

        $resourcesDir = $outputDir.DIRECTORY_SEPARATOR.'resources';
        $this->ensureDirectory($resourcesDir);

        $outPath = $resourcesDir.DIRECTORY_SEPARATOR.'commodity_trade_locations.json';
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! $overwrite && file_exists($outPath)) {
            $io->success(sprintf('Skipped existing commodity trade locations at %s', $outPath));

            return Command::SUCCESS;
        }

        $this->prepareServices($input, $output);

        $start = microtime(true);

        $resolver = $this->withLazyReferenceHydration(
            [ServiceFactory::getFoundryLookupService()],
            static fn (): CommodityTradeLocationResolver => new CommodityTradeLocationResolver
        );

        $results = $resolver->resolveAll();

        usort($results, static fn (array $a, array $b): int => [$a['CommodityName'], $a['CommodityKey']]
            <=> [$b['CommodityName'], $b['CommodityKey']]);

        $encoded = json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $bytesWritten = file_put_contents($outPath, $encoded);

        if ($bytesWritten === false) {
            throw new RuntimeException(sprintf('Failed to write commodity trade locations file: %s', $outPath));
        }

        $duration = microtime(true) - $start;
        $io->success(sprintf('Saved commodity trade locations (%s commodities | %s s | %s)',
            number_format(count($results)),
            round($duration),
            $outPath
        ));

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:commodity-trade-locations Path/To/ScDataDir Path/To/JsonOutDir [--overwrite]');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing commodity trade locations file'
        );
    }
}
