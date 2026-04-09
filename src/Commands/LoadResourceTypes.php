<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\Resource\QualityTierResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
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
final class LoadResourceTypes extends AbstractDataCommand
{
    use NormalizesValues;

    /**
     * @throws ExceptionInterface|JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading resource types');

        $outputDir = $input->getArgument('jsonOutPath');
        $this->ensureDirectory((string) $outputDir);

        $indexFilePath = sprintf('%s%sresource-types.json', $outputDir, DIRECTORY_SEPARATOR);
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! $overwrite && file_exists($indexFilePath)) {
            $io->success(sprintf('Skipped existing resource type index at %s', $indexFilePath));

            return Command::SUCCESS;
        }

        $this->prepareServices($input, $output);

        $service = ServiceFactory::getFoundryLookupService();
        $io->progressStart($service->countDocumentType('ResourceType'));
        $start = microtime(true);

        $resourceTypes = [];
        foreach ($service->getDocumentType('ResourceType', ResourceType::class) as $resourceType) {
            $resourceTypes[] = $this->buildResourceTypeExportEntry($resourceType);
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
     *     cargo_containers: list<array{uuid: string, name: string, size: float|int}>,
     *     quality_distribution_uuid: ?string,
     *     quality_location_override_uuid: ?string,
     *     tier: ?string
     * }
     */
    protected function buildResourceTypeExportEntry(ResourceType $resourceType): array
    {
        $resourceTypeData = $resourceType->toArray();
        $refinedVersionUuid = $this->normalizeString($resourceTypeData['refinedVersion'] ?? null);
        $cargoContainers = $this->readCargoContainers(
            $resourceTypeData['defaultCargoContainers']['SResourceTypeDefaultCargoContainers'] ?? null
        );

        $tier = null;
        $qualityDistribution = $resourceType->getQualityDistribution();
        if ($qualityDistribution !== null) {
            $resolver = new QualityTierResolver;
            $category = $resolver->extractCategoryFromPath($qualityDistribution->getPath());
            $extracted = $resolver->extractTierFromClassName($qualityDistribution->getClassName(), $category);
            $tier = $extracted !== 'default' ? $extracted : null;
        }

        return [
            'uuid' => $resourceType->getUuid(),
            'key' => $resourceType->getClassName(),
            'name' => $this->resolveLocalizedString($resourceTypeData['displayName'] ?? null) ?? $resourceType->getClassName(),
            'description' => $this->resolveLocalizedString($resourceTypeData['description'] ?? null),
            'refined_version_uuid' => $refinedVersionUuid,
            'refined_version_name' => $this->resolveRefinedVersionName($refinedVersionUuid),
            'validate_default_cargo_box' => $this->normalizeBool($resourceTypeData['validateDefaultCargoBox'] ?? null),
            'has_default_cargo_containers' => $cargoContainers !== [],
            'cargo_containers' => $cargoContainers,
            'quality_distribution_uuid' => $this->extractQualityDistributionUuid($resourceTypeData),
            'quality_location_override_uuid' => $this->extractQualityLocationOverrideUuid($resourceTypeData),
            'tier' => $tier,
        ];
    }

    /**
     * @return array{path: string}
     */
    protected function makeCacheArguments(InputInterface $input): array
    {
        return [
            'path' => $this->getScDataPath($input),
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
     * @return list<array{uuid: string, name: string, size: float|int}>
     */
    private function readCargoContainers(mixed $containers): array
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

        $cargoContainers = [];

        foreach ($boxSizeMap as $attribute => $size) {
            if (array_key_exists($attribute, $containers)) {
                $uuid = $this->normalizeString($containers[$attribute]);
                if ($uuid !== null) {
                    $cargoContainers[] = [
                        'uuid' => $uuid,
                        'name' => $attribute,
                        'size' => $size,
                    ];
                }
            }
        }

        return $cargoContainers;
    }

    private function extractQualityDistributionUuid(array $resourceTypeData): ?string
    {
        $properties = $resourceTypeData['properties'] ?? null;
        if (! is_array($properties)) {
            return null;
        }

        $craftingData = $properties['ResourceTypeCraftingData'] ?? null;
        if (! is_array($craftingData)) {
            return null;
        }

        $qualityDistribution = $craftingData['qualityDistribution'] ?? null;
        if (! is_array($qualityDistribution)) {
            return null;
        }

        $recordRef = $qualityDistribution['CraftingQualityDistribution_RecordRef'] ?? null;
        if (! is_array($recordRef)) {
            return null;
        }

        return $this->normalizeString($recordRef['qualityDistributionRecord'] ?? null);
    }

    private function extractQualityLocationOverrideUuid(array $resourceTypeData): ?string
    {
        $properties = $resourceTypeData['properties'] ?? null;
        if (! is_array($properties)) {
            return null;
        }

        $craftingData = $properties['ResourceTypeCraftingData'] ?? null;
        if (! is_array($craftingData)) {
            return null;
        }

        $locationOverride = $craftingData['qualityLocationOverride'] ?? null;
        if (! is_array($locationOverride)) {
            return null;
        }

        $recordRef = $locationOverride['CraftingQualityLocationOverride_RecordRef'] ?? null;
        if (! is_array($recordRef)) {
            return null;
        }

        return $this->normalizeString($recordRef['locationOverrideRecord'] ?? null);
    }

    private function resolveRefinedVersionName(?string $uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $refinedVersion = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($uuid);

        if ($refinedVersion === null) {
            return null;
        }

        $refinedVersionData = $refinedVersion->toArray();

        return $this->resolveLocalizedString($refinedVersionData['displayName'] ?? null) ?? $refinedVersion->getClassName();
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
