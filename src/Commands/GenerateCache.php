<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Exception;
use Octfx\ScDataDumper\Services\CacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate:cache',
    description: 'Generate required cache files',
    hidden: false
)]
class GenerateCache extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Generating cache files...');

        $service = new CacheService($input->getArgument('path'));

        $start = microtime(true);
        try {
            $service->makeCacheFiles();
        } catch (Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $end = microtime(true);
        $duration = round($end - $start);

        $io->success(sprintf(
            'Cache files generated in %ds (%s)',
            $duration,
            $input->getArgument('path')
        ));

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php generate:cache Path/To/ScDataDir');
        $this->addArgument('path', InputArgument::REQUIRED);
    }
}
