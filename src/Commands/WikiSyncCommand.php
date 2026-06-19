<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Illuminate\Support\Str;
use JsonException;
use Octfx\ScDataDumper\Wiki\WikiTableParser;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Syncs SCWAPI override tables (items, vehicles) and the manufacturer master
 * list from starcitizen.tools into import/. A missing page degrades to empty
 * overrides -- consumers already treat a missing override file as additive.
 */
#[AsCommand(
    name: 'wiki:sync',
    description: 'Download SCWAPI override pages + manufacturer data into import/',
)]
class WikiSyncCommand extends Command
{
    private const string API = 'https://starcitizen.tools/api.php';

    private const string MANUFACTURERS_PAGE = 'Module:Manufacturers/data.json';

    /** @var array<string, string> page name => local output file */
    private const array TABLE_PAGES = [
        'items' => 'Star_Citizen_Wiki:SCWAPI/items',
        'vehicles' => 'Star_Citizen_Wiki:SCWAPI/vehicles',
    ];

    private const string USER_AGENT = 'ScDataDumper/wiki-sync (+https://star-citizen.wiki)';

    protected function configure(): void
    {
        $this->setHelp('php cli.php wiki:sync');
        $this->addOption(
            'import-dir',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory to write synced JSON into',
            dirname(__DIR__, 2).'/import',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing wiki override data');

        $importDir = (string) $input->getOption('import-dir');
        if (! is_dir($importDir) && ! mkdir($importDir, 0777, true) && ! is_dir($importDir)) {
            $io->error(sprintf('Could not create import directory: %s', $importDir));

            return Command::FAILURE;
        }

        // Manufacturers first: its codes validate the table rows below.
        $manufacturers = $this->syncManufacturers($importDir, $io);
        if ($manufacturers === null) {
            return Command::FAILURE;
        }
        $validCodes = array_keys($manufacturers);

        foreach (self::TABLE_PAGES as $name => $page) {
            $this->syncTable($name, $page, $importDir, $validCodes, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $validCodes
     */
    private function syncTable(string $name, string $page, string $importDir, array $validCodes, SymfonyStyle $io): void
    {
        $wikitext = $this->fetchPageContent($page);
        if ($wikitext === null) {
            $io->warning(sprintf('Page not found, writing empty overrides: %s', $page));
            $this->writeJson($importDir.'/wiki_'.$name.'.json', []);

            return;
        }

        $rows = WikiTableParser::parseTable($wikitext);
        $records = [];
        $duplicates = 0;
        $badCodes = 0;

        foreach ($rows as $row) {
            $uuid = trim($row['UUID'] ?? '');

            if ($uuid === '' || ! Str::isUuid($uuid)) {
                continue;
            }

            if (isset($records[$uuid])) {
                $duplicates++;
                $io->warning(sprintf('Duplicate UUID in %s, last wins: %s', $name, $uuid));
            }

            $facts = [];
            foreach (['Event', 'Manufacturer', 'Name'] as $column) {
                $value = trim($row[$column] ?? '');
                if ($value !== '') {
                    $facts[strtolower($column)] = $value;
                }
            }
            if (isset($facts['manufacturer']) && ! in_array($facts['manufacturer'], $validCodes, true)) {
                $badCodes++;
            }

            if ($facts !== []) {
                $records[$uuid] = $facts;
            }
        }

        if ($badCodes > 0) {
            $io->warning(sprintf('%s: %d row(s) reference unknown manufacturer codes -- check against Module:Manufacturers/data.json', $name, $badCodes));
        }

        $this->writeJson($importDir.'/wiki_'.$name.'.json', $records);
        $io->text(sprintf('  %s: %d record(s)%s', $name, count($records), $duplicates ? ", {$duplicates} duplicate(s)" : ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function syncManufacturers(string $importDir, SymfonyStyle $io): ?array
    {
        $raw = $this->fetchPageContent(self::MANUFACTURERS_PAGE);
        if ($raw === null) {
            $io->error('Could not fetch manufacturer master list');

            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $io->error(sprintf('Manufacturer page is not valid JSON: %s', $e->getMessage()));

            return null;
        }

        if (! is_array($data)) {
            $io->error('Manufacturer page did not decode to an object');

            return null;
        }

        $this->writeJson($importDir.'/wiki_manufacturers.json', $data);
        $io->text(sprintf('  manufacturers: %d code(s)', count($data)));

        return $data;
    }

    /**
     * Fetches the raw wikitext (main slot) of a page. Returns null when the
     * page does not exist.
     */
    private function fetchPageContent(string $page): ?string
    {
        $url = sprintf(
            '%s?action=query&prop=revisions&titles=%s&rvprop=content&rvslots=main&format=json&formatversion=2',
            self::API,
            rawurlencode($page),
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: '.self::USER_AGENT."\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $pageData = $payload['query']['pages'][0] ?? null;
        if ($pageData === null || ($pageData['missing'] ?? false) || ($pageData['invalid'] ?? false)) {
            return null;
        }

        return $pageData['revisions'][0]['slots']['main']['content'] ?? null;
    }

    /**
     * Atomic write: readers silently degrade on a truncated file, so stage to
     * a temp file and rename into place.
     *
     * @param  array<string, mixed>|list<mixed>  $data
     *
     * @throws RuntimeException when the file cannot be staged or committed
     */
    private function writeJson(string $path, array $data): void
    {
        ksort($data);

        $tmp = $path.'.tmp';
        $bytesWritten = file_put_contents(
            $tmp,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        if ($bytesWritten === false) {
            throw new RuntimeException(sprintf('Failed to write wiki sync file: %s', $path));
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Failed to commit wiki sync file: %s', $path));
        }
    }
}
