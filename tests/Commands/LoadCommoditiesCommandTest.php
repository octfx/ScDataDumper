<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadCommodities;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadCommoditiesCommandTest extends ScDataTestCase
{
    private const RAW_ORE_UUID = '11111111-1111-1111-1111-111111111111';

    private const REFINED_ORE_UUID = '22222222-2222-2222-2222-222222222222';

    private const PLACEHOLDER_ORE_UUID = '33333333-3333-3333-3333-333333333333';

    private const MINEABLE_ELEMENT_UUID = '44444444-4444-4444-4444-444444444444';

    public function test_execute_writes_localized_commodities_index(): void
    {
        $this->writeCacheFiles();
        $this->writeMineableElementCache([
            self::MINEABLE_ELEMENT_UUID => sprintf(
                <<<'XML'
                <MineableElement.RawOreElement resourceType="%s" elementInstability="50" elementResistance="-0.7" __type="MineableElement" __ref="%s" __path="libs/foundry/records/mining/mineableelements/raw_ore_element.xml" />
                XML,
                self::RAW_ORE_UUID,
                self::MINEABLE_ELEMENT_UUID,
            ),
        ]);
        $this->writeResourceTypeCache([
            self::RAW_ORE_UUID => sprintf(
                <<<'XML'
                <ResourceType.RawOre displayName="@items_commodities_rawore" description="@items_commodities_rawore_desc" refinedVersion="%s" validateDefaultCargoBox="1" __type="ResourceType" __ref="%s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <defaultCargoContainers>
                    <SResourceTypeDefaultCargoContainers oneSCU="crate_one" fourSCU="crate_four" />
                  </defaultCargoContainers>
                  <densityType>
                    <ResourceTypeDensity>
                      <densityUnit>
                        <GramsPerCubicCentimeter gramsPerCubicCentimeter="2.5" />
                      </densityUnit>
                    </ResourceTypeDensity>
                  </densityType>
                  <properties>
                    <ResourceTypeVolatility name="Volatile" volatility="0.8" healthDecayPerSecond="1.5" />
                  </properties>
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

        $tester = new CommandTester(new TestLoadCommoditiesCommand);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $resourceTypes = $this->readJsonFile('resources/commodities.json');

        $rawOre = $this->findResourceType($resourceTypes, 'RawOre');
        self::assertSame('Raw Ore', $rawOre['Name']);
        self::assertSame('Freshly mined ore', $rawOre['Description']);
        self::assertSame(self::REFINED_ORE_UUID, $rawOre['RefinedVersionUUID']);
        self::assertSame('Refined Ore', $rawOre['RefinedVersionName']);
        self::assertTrue($rawOre['ValidateDefaultCargoBox']);
        self::assertTrue($rawOre['HasDefaultCargoContainers']);
        self::assertEquals([
            ['UUID' => 'crate_one', 'Name' => 'oneSCU', 'Size' => 1],
            ['UUID' => 'crate_four', 'Name' => 'fourSCU', 'Size' => 4],
        ], $rawOre['CargoContainers']);
        self::assertSame(2.5, $rawOre['DensityGPerCc']);
        self::assertSame(0.8, $rawOre['Volatility']);
        self::assertSame(1.5, $rawOre['VolatilityHealthDecayPerSecond']);
        self::assertEquals(50.0, $rawOre['Instability']);
        self::assertEquals(-0.7, $rawOre['Resistance']);

        $refinedOre = $this->findResourceType($resourceTypes, 'RefinedOre');
        self::assertSame('Refined Ore', $refinedOre['Name']);
        self::assertNull($refinedOre['Description']);
        self::assertNull($refinedOre['RefinedVersionUUID']);
        self::assertNull($refinedOre['RefinedVersionName']);
        self::assertFalse($refinedOre['ValidateDefaultCargoBox']);
        self::assertFalse($refinedOre['HasDefaultCargoContainers']);
        self::assertSame([], $refinedOre['CargoContainers']);
        self::assertNull($refinedOre['DensityGPerCc']);
        self::assertNull($refinedOre['Volatility']);
        self::assertNull($refinedOre['VolatilityHealthDecayPerSecond']);
        self::assertNull($refinedOre['Instability']);
        self::assertNull($refinedOre['Resistance']);

        $placeholderOre = $this->findResourceType($resourceTypes, 'PlaceholderOre');
        self::assertSame('PlaceholderOre', $placeholderOre['Name']);
        self::assertNull($placeholderOre['Description']);
        self::assertNull($placeholderOre['DensityGPerCc']);
        self::assertNull($placeholderOre['Instability']);
        self::assertNull($placeholderOre['Resistance']);
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

        $tester = new CommandTester(new TestLoadCommoditiesCommand);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $resourceTypes = $this->readJsonFile('resources/commodities.json');

        $rawOre = $this->findResourceType($resourceTypes, 'RawOre');
        self::assertSame('common', $rawOre['Tier']);

        $refinedOre = $this->findResourceType($resourceTypes, 'RefinedOre');
        self::assertSame('rare', $refinedOre['Tier']);

        $placeholderOre = $this->findResourceType($resourceTypes, 'PlaceholderOre');
        self::assertNull($placeholderOre['Tier']);
    }

    public function test_make_cache_arguments_does_not_forward_export_overwrite(): void
    {
        $command = new InspectableLoadCommoditiesCommand;
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
     * @param  array<int, array<string, mixed>>  $resourceTypes
     * @return array<string, mixed>
     */
    private function findResourceType(array $resourceTypes, string $key): array
    {
        foreach ($resourceTypes as $resourceType) {
            if (($resourceType['Key'] ?? null) === $key) {
                return $resourceType;
            }
        }

        self::fail(sprintf('Missing resource type with key %s', $key));
    }

    public function test_resource_type_export_includes_commodity_group_path(): void
    {
        $processedGoodsUuid = 'a0000000-0000-0000-0000-000000000001';
        $viceUuid = 'a0000000-0000-0000-0000-000000000002';
        $widowUuid = 'a0000000-0000-0000-0000-000000000003';
        $metalUuid = 'a0000000-0000-0000-0000-000000000004';

        $this->writeCacheFiles();

        $this->writeResourceTypeCache([
            $widowUuid => sprintf(
                <<<'XML'
                <ResourceType.Widow displayName="@items_widow" description="@LOC_EMPTY" validateDefaultCargoBox="0" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
                XML,
                $widowUuid,
            ),
            $metalUuid => sprintf(
                <<<'XML'
                <ResourceType.Iron displayName="@items_iron" description="@LOC_EMPTY" validateDefaultCargoBox="0" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml" />
                XML,
                $metalUuid,
            ),
        ]);

        $this->writeResourceTypeGroupCache([
            $processedGoodsUuid => sprintf(
                <<<'XML'
                <ResourceTypeGroup.ProcessedGoods displayName="@items_type_processed" description="@LOC_EMPTY" __type="ResourceTypeGroup" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <groups>
                    <Reference value="%2$s" />
                  </groups>
                  <resources>
                  </resources>
                </ResourceTypeGroup.ProcessedGoods>
                XML,
                $processedGoodsUuid,
                $viceUuid,
            ),
            $viceUuid => sprintf(
                <<<'XML'
                <ResourceTypeGroup.Vice displayName="@items_type_vice" description="@LOC_EMPTY" __type="ResourceTypeGroup" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
                  <resources>
                    <Reference value="%2$s" />
                  </resources>
                </ResourceTypeGroup.Vice>
                XML,
                $viceUuid,
                $widowUuid,
            ),
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            <<<'INI'
            LOC_EMPTY=
            items_widow=Widow
            items_iron=Iron
            INI
        );

        $tester = new CommandTester(new TestLoadCommoditiesCommand);
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(0, $exitCode);

        $resourceTypes = $this->readJsonFile('resources/commodities.json');

        $widow = $this->findResourceType($resourceTypes, 'Widow');
        self::assertSame(['ProcessedGoods', 'Vice'], $widow['CommodityGroups']);

        $iron = $this->findResourceType($resourceTypes, 'Iron');
        self::assertSame([], $iron['CommodityGroups']);
    }
}

final class TestLoadCommoditiesCommand extends LoadCommodities
{
    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        (new ServiceFactory($input->getArgument('scDataPath')))->initialize();
    }
}

final class InspectableLoadCommoditiesCommand extends LoadCommodities
{
    /**
     * @return array{path: string}
     */
    public function exposeMakeCacheArguments(InputInterface $input): array
    {
        return $this->makeCacheArguments($input);
    }
}
