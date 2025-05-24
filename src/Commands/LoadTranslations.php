<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Services\LocalizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:translations',
    description: 'Dumps all translations to a file called labels.json',
    hidden: false
)]
class LoadTranslations extends Command
{
    /**
     * @throws JsonException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading translations');

        $translations = (new LocalizationService($input->getArgument('scDataPath')))->getAllTranslations()['english'];

        $translations = array_map(static fn ($translation) => mb_convert_encoding($translation, 'UTF-8', 'UTF-8'), $translations);

        $io->info('Writing translations to labels.json...');

        $filePath = sprintf('%s%slabels.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($translations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:translations Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED);
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED);
    }
}
