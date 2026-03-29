<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\ResourceTypeService;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:resource-types',
    description: 'Load and dump SC resource types',
    hidden: false
)]
class LoadResourceTypes extends Command
{
    /**
     * @throws ExceptionInterface|JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading resource types');

        $outputDir = $input->getArgument('jsonOutPath');
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outputDir));
        }

        $indexFilePath = sprintf('%s%sresource-types.json', $outputDir, DIRECTORY_SEPARATOR);
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! $overwrite && file_exists($indexFilePath)) {
            $io->success(sprintf('Skipped existing resource type index at %s', $indexFilePath));

            return Command::SUCCESS;
        }

        $this->prepareServices($input, $output);

        $service = ServiceFactory::getResourceTypeService();
        $io->progressStart($service->count());
        $start = microtime(true);

        $resourceTypes = [];
        foreach ($service->iterator() as $resourceType) {
            $resourceTypes[] = $this->buildResourceTypeExportEntry($resourceType, $service);
            $io->progressAdvance();
        }

        usort(
            $resourceTypes,
            static fn (array $left, array $right): int => [$left['name'], $left['key'], $left['uuid']]
                <=> [$right['name'], $right['key'], $right['uuid']]
        );

        $bytesWritten = file_put_contents(
            $indexFilePath,
            json_encode($resourceTypes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        if ($bytesWritten === false) {
            throw new RuntimeException(sprintf('Failed to write resource type index file: %s', $indexFilePath));
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved resource type index (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$indexFilePath
        ));

        return Command::SUCCESS;
    }

    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        $cacheCommand = new GenerateCache;
        $cacheInput = new ArrayInput($this->makeCacheArguments($input));
        $cacheInput->setInteractive(false);
        $cacheCommand->run($cacheInput, $output);

        $factory = new ServiceFactory($input->getArgument('scDataPath'));
        $factory->initialize();
    }

    /**
     * @return array{
     *     uuid: string,
     *     key: string,
     *     name: string,
     *     description: ?string,
     *     refined_version_uuid: ?string,
     *     refined_version_name: ?string,
     *     validate_default_cargo_box: bool,
     *     has_default_cargo_containers: bool,
     *     box_sizes_scu: list<float|int>
     * }
     */
    protected function buildResourceTypeExportEntry(ResourceType $resourceType, ResourceTypeService $service): array
    {
        $resourceTypeData = $resourceType->toArray();
        $refinedVersionUuid = $this->normalizeString($resourceTypeData['refinedVersion'] ?? null);
        $boxSizes = $this->readCargoBoxSizes(
            $resourceTypeData['defaultCargoContainers']['SResourceTypeDefaultCargoContainers'] ?? null
        );

        return [
            'uuid' => $resourceType->getUuid(),
            'key' => $resourceType->getClassName(),
            'name' => $this->resolveLocalizedString($resourceTypeData['displayName'] ?? null) ?? $resourceType->getClassName(),
            'description' => $this->resolveLocalizedString($resourceTypeData['description'] ?? null),
            'refined_version_uuid' => $refinedVersionUuid,
            'refined_version_name' => $this->resolveRefinedVersionName($refinedVersionUuid, $service),
            'validate_default_cargo_box' => $this->normalizeBool($resourceTypeData['validateDefaultCargoBox'] ?? null),
            'has_default_cargo_containers' => $boxSizes !== [],
            'box_sizes_scu' => $boxSizes,
        ];
    }

    /**
     * @return array{path: string}
     */
    protected function makeCacheArguments(InputInterface $input): array
    {
        return [
            'path' => $input->getArgument('scDataPath'),
        ];
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:resource-types Path/To/ScDataDir Path/To/JsonOutDir [--overwrite]');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing resource type index file'
        );
    }

    /**
     * @return list<float|int>
     */
    private function readCargoBoxSizes(mixed $containers): array
    {
        if (! is_array($containers)) {
            return [];
        }

        $boxSizeMap = [
            'one_eighthSCU' => 0.125,
            'oneSCU' => 1,
            'twoSCU' => 2,
            'fourSCU' => 4,
            'eightSCU' => 8,
            'sixteenSCU' => 16,
            'twentyFourSCU' => 24,
            'thirtyTwoSCU' => 32,
        ];

        $boxSizes = [];

        foreach ($boxSizeMap as $attribute => $size) {
            if (array_key_exists($attribute, $containers) && $containers[$attribute] !== null && $containers[$attribute] !== '') {
                $boxSizes[] = $size;
            }
        }

        return $boxSizes;
    }

    private function resolveRefinedVersionName(?string $uuid, ResourceTypeService $service): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $refinedVersion = $service->getByReference($uuid);

        if ($refinedVersion === null) {
            return null;
        }

        $refinedVersionData = $refinedVersion->toArray();

        return $this->resolveLocalizedString($refinedVersionData['displayName'] ?? null) ?? $refinedVersion->getClassName();
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return false;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    private function resolveLocalizedString(mixed $value): ?string
    {
        $normalizedValue = $this->normalizeString($value);

        if ($normalizedValue === null) {
            return null;
        }

        if (! str_starts_with($normalizedValue, '@')) {
            return $normalizedValue;
        }

        try {
            $translatedValue = $this->normalizeString(
                ServiceFactory::getLocalizationService()->getTranslation($normalizedValue)
            );
        } catch (RuntimeException) {
            return null;
        }

        if ($translatedValue === null || $translatedValue === $normalizedValue) {
            return null;
        }

        return $translatedValue;
    }
}
