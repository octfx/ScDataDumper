<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Exception;
use JsonException;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:items',
    description: 'Load and dump SC Items',
    hidden: false
)]
class LoadItems extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading items');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $service = ServiceFactory::getItemService();

        $io->progressStart($service->count());

        $outDir = sprintf('%s%sitems', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        $start = microtime(true);

        $avoids = [
            'undefined',
            'airtrafficcontroller',
            'button',
            'char_body',
            'char_head',
            'char_hair_color',
            'char_head_eyebrow',
            'char_head_eyelash',
            'char_head_eyes',
            'char_head_hair',
            'char_lens',
            'char_skin_color',
            'cloth',
            'debris',
            'removablechip',
            'noitem_player',
            'noitem_vehicle',
        ];

        $index = [];

        $iter = $service->iterator();

        foreach ($iter as $item) {
            $attach = $item->getAttachDef();

            $type = $item->getAttachType();

            $io->progressAdvance();

            if ($attach === null || $type === null || in_array(strtolower($type), $avoids)) {
                continue;
            }

            $fileName = strtolower($item->getClassName());
            $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            $stdItem = (new Item($item))->toArray();

            $index[] = $stdItem;

            if (! $overwrite && file_exists($filePath)) {
                continue;
            }

            $ref = fopen($filePath, 'wb');
            try {
                if ($input->getOption('scUnpackedFormat')) {
                    $json = json_encode([
                        'Raw' => [
                            'Entity' => [
                                ...$item->toArray(),
                                'ClassName' => $item->getClassName(),
                                '__ref' => $item->getUuid(),
                                '__type' => $item->getAttachType(),
                            ],
                        ],
                        'Item' => $stdItem,
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } else {
                    $json = $item->toJson();
                }

                fwrite($ref, $json);
            } catch (Exception $e) {
                $io->error('Could not write file '.$fileName);
            } finally {
                fclose($ref);
            }
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved item files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        $filePath = sprintf('%s%sitems.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        $index = collect($index);

        $fpsItems = $index->filter(static fn ($item) => ! empty($item['classification']) && str_starts_with($item['classification'], 'FPS.'))->values()->toArray();
        $shipItems = $index->filter(static fn ($item) => ! empty($item['classification']) && str_starts_with($item['classification'], 'Ship.'))->values()->toArray();

        $filePath = sprintf('%s%sfps-items.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($fpsItems, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        $filePath = sprintf('%s%sship-items.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($shipItems, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:items Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED);
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED);
        $this->addOption('overwrite');
        $this->addOption('scUnpackedFormat');
    }
}
