<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadResourceTypes;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadResourceTypesCommandTest extends ScDataTestCase
{
    private const RAW_ORE_UUID = '11111111-1111-1111-1111-111111111111';

    private const REFINED_ORE_UUID = '22222222-2222-2222-2222-222222222222';

    private const PLACEHOLDER_ORE_UUID = '33333333-3333-3333-3333-333333333333';

    public function test_execute_writes_localized_resource_type_index(): void
    {
        $this->writeCacheFiles();
        $this->writeResourceTypeCache([
            self::RAW_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.RawOre displayName="@items_commodities_rawore" description="@items_commodities_rawore_desc" refinedVersion="%s" validateDefaultCargoBox="1" __type="ResourceType" __ref="%s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <defaultCargoContainers>
                    <SResourceTypeDefaultCargoContainers oneSCU="crate_one" fourSCU="crate_four" />
                  </defaultCargoContainers>
                </ResourceType.RawOre>
                XML,
                self::REFINED_ORE_UUID,
                self::RAW_ORE_UUID,
            ),
            self::REFINED_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.RefinedOre displayName="@items_commodities_refinedore" description="@LOC_EMPTY" validateDefaultCargoBox="0" __type="ResourceType" __ref="%s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
                XML,
                self::REFINED_ORE_UUID,
            ),
            self::PLACEHOLDER_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.PlaceholderOre displayName="@LOC_PLACEHOLDER" description="@LOC_PLACEHOLDER_DESC" validateDefaultCargoBox="0" __type="ResourceType" __ref="%s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
                XML,
                self::PLACEHOLDER_ORE_UUID,
            ),
        ]);
        $this->writeFile(
            'Data/Localization/english/global.ini',
            <<<'INI'
            LOC_EMPTY=
            items_commodities_rawore=Raw Ore
            items_commodities_rawore_desc=Freshly mined ore
            items_commodities_refinedore=Refined Ore
            INI
        );

        $tester = new CommandTester(new TestLoadResourceTypesCommand);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $resourceTypes = $this->readJsonFile('resource-types.json');

        $rawOre = $this->findResourceType($resourceTypes, 'RawOre');
        self::assertSame('Raw Ore', $rawOre['name']);
        self::assertSame('Freshly mined ore', $rawOre['description']);
        self::assertSame(self::REFINED_ORE_UUID, $rawOre['refined_version_uuid']);
        self::assertSame('Refined Ore', $rawOre['refined_version_name']);
        self::assertTrue($rawOre['validate_default_cargo_box']);
        self::assertTrue($rawOre['has_default_cargo_containers']);
        self::assertEquals([
            ['uuid' => 'crate_one', 'name' => 'oneSCU', 'size' => 1],
            ['uuid' => 'crate_four', 'name' => 'fourSCU', 'size' => 4],
        ], $rawOre['cargo_containers']);

        $refinedOre = $this->findResourceType($resourceTypes, 'RefinedOre');
        self::assertSame('Refined Ore', $refinedOre['name']);
        self::assertNull($refinedOre['description']);
        self::assertNull($refinedOre['refined_version_uuid']);
        self::assertNull($refinedOre['refined_version_name']);
        self::assertFalse($refinedOre['validate_default_cargo_box']);
        self::assertFalse($refinedOre['has_default_cargo_containers']);
        self::assertSame([], $refinedOre['cargo_containers']);

        $placeholderOre = $this->findResourceType($resourceTypes, 'PlaceholderOre');
        self::assertSame('PlaceholderOre', $placeholderOre['name']);
        self::assertNull($placeholderOre['description']);
    }

    public function test_resource_type_export_includes_tier(): void
    {
        $commonQdUuid = 'aaaaaaaa-aaaa-aaaa-aaaa-111111111111';
        $rareQdUuid = 'aaaaaaaa-aaaa-aaaa-aaaa-222222222222';

        $this->writeCacheFiles();

        $this->writeQualityDistributionRecord(
            $commonQdUuid,
            sprintf(
                <<<'XML'
                <CraftingQualityDistributionRecord.Common_QualityDistribution __type="CraftingQualityDistributionRecord" __ref="%1$s" __path="libs/foundry/records/crafting/qualitydistribution/Common_QualityDistribution.xml">
                  <qualityDistribution>
                    <CraftingQualityDistributionNormal min="0" max="100" mean="50" stddev="15" />
                  </qualityDistribution>
                </CraftingQualityDistributionRecord.Common_QualityDistribution>
                XML,
                $commonQdUuid,
            ),
        );
        $this->writeQualityDistributionRecord(
            $rareQdUuid,
            sprintf(
                <<<'XML'
                <CraftingQualityDistributionRecord.Rare_QualityDistribution __type="CraftingQualityDistributionRecord" __ref="%1$s" __path="libs/foundry/records/crafting/qualitydistribution/Rare_QualityDistribution.xml">
                  <qualityDistribution>
                    <CraftingQualityDistributionNormal min="0" max="100" mean="75" stddev="10" />
                  </qualityDistribution>
                </CraftingQualityDistributionRecord.Rare_QualityDistribution>
                XML,
                $rareQdUuid,
            ),
        );

        $this->writeResourceTypeCache([
            self::RAW_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.RawOre displayName="@items_commodities_rawore" validateDefaultCargoBox="0" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <properties>
                    <ResourceTypeCraftingData>
                      <qualityDistribution>
                        <CraftingQualityDistribution_RecordRef qualityDistributionRecord="%2$s" />
                      </qualityDistribution>
                    </ResourceTypeCraftingData>
                  </properties>
                </ResourceType.RawOre>
                XML,
                self::RAW_ORE_UUID,
                $commonQdUuid,
            ),
            self::REFINED_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.RefinedOre displayName="@items_commodities_refinedore" validateDefaultCargoBox="0" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <properties>
                    <ResourceTypeCraftingData>
                      <qualityDistribution>
                        <CraftingQualityDistribution_RecordRef qualityDistributionRecord="%2$s" />
                      </qualityDistribution>
                    </ResourceTypeCraftingData>
                  </properties>
                </ResourceType.RefinedOre>
                XML,
                self::REFINED_ORE_UUID,
                $rareQdUuid,
            ),
            self::PLACEHOLDER_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.PlaceholderOre displayName="@LOC_PLACEHOLDER" validateDefaultCargoBox="0" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
                XML,
                self::PLACEHOLDER_ORE_UUID,
            ),
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            <<<'INI'
            items_commodities_rawore=Raw Ore
            items_commodities_refinedore=Refined Ore
            INI,
        );

        $tester = new CommandTester(new TestLoadResourceTypesCommand);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $resourceTypes = $this->readJsonFile('resource-types.json');

        $rawOre = $this->findResourceType($resourceTypes, 'RawOre');
        self::assertSame('common', $rawOre['tier']);

        $refinedOre = $this->findResourceType($resourceTypes, 'RefinedOre');
        self::assertSame('rare', $refinedOre['tier']);

        $placeholderOre = $this->findResourceType($resourceTypes, 'PlaceholderOre');
        self::assertNull($placeholderOre['tier']);
    }

    public function test_make_cache_arguments_does_not_forward_export_overwrite(): void
    {
        $command = new InspectableLoadResourceTypesCommand;
        $input = new ArrayInput([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
        ], $command->getDefinition());

        self::assertSame(['path' => $this->tempDir], $command->exposeMakeCacheArguments($input));
    }

    private function writeQualityDistributionRecord(string $uuid, string $xml): void
    {
        $normalizedUuid = strtolower($uuid);
        $path = $this->writeFile(
            sprintf('Game2/libs/foundry/records/crafting/qualitydistribution/%s.xml', $normalizedUuid),
            $xml,
        );

        $cachePath = sprintf('%s%suuidToPathMap-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, PHP_OS_FAMILY);
        $current = file_exists($cachePath)
            ? json_decode((string) file_get_contents($cachePath), true, 512, JSON_THROW_ON_ERROR)
            : [];

        file_put_contents(
            $cachePath,
            json_encode(array_replace($current, [$normalizedUuid => $path]), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJsonFile(string $relativePath): array
    {
        $contents = file_get_contents($this->tempDir.DIRECTORY_SEPARATOR.$relativePath);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, array<string, mixed>>  $resourceTypes
     * @return array<string, mixed>
     */
    private function findResourceType(array $resourceTypes, string $key): array
    {
        foreach ($resourceTypes as $resourceType) {
            if (($resourceType['key'] ?? null) === $key) {
                return $resourceType;
            }
        }

        self::fail(sprintf('Missing resource type with key %s', $key));
    }
}

final class TestLoadResourceTypesCommand extends LoadResourceTypes
{
    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        (new ServiceFactory($input->getArgument('scDataPath')))->initialize();
    }
}

final class InspectableLoadResourceTypesCommand extends LoadResourceTypes
{
    /**
     * @return array{path: string}
     */
    public function exposeMakeCacheArguments(InputInterface $input): array
    {
        return $this->makeCacheArguments($input);
    }
}
