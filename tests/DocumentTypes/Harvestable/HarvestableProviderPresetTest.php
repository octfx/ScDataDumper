<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Harvestable;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElement;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElementGroup;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class HarvestableProviderPresetTest extends ScDataTestCase
{
    private const PROVIDER_PRESET_UUID = '00000000-0000-0000-0000-000000000001';

    private const HARVESTABLE_UUID = '00000000-0000-0000-0000-000000000002';

    private const ENTITY_UUID = '00000000-0000-0000-0000-000000000003';

    private const CLUSTER_UUID = '00000000-0000-0000-0000-000000000004';

    private const SETUP_UUID = '00000000-0000-0000-0000-000000000005';

    private const TAG_UUID = '00000000-0000-0000-0000-000000000006';

    protected function setUp(): void
    {
        parent::setUp();

        $providerPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/providerpresets/system/test/sample.xml',
            <<<'XML'
            <HarvestableProviderPreset.Sample __type="HarvestableProviderPreset" __ref="00000000-0000-0000-0000-000000000001" __path="libs/foundry/records/harvestable/providerpresets/system/test/sample.xml">
              <harvestableGroups>
                <HarvestableElementGroup groupName="Mineables" groupProbability="1">
                  <harvestables>
                    <HarvestableElement harvestable="00000000-0000-0000-0000-000000000002" relativeProbability="2" clustering="00000000-0000-0000-0000-000000000004">
                      <geometries>
                        <HarvestableGeometry tag="00000000-0000-0000-0000-000000000006" />
                      </geometries>
                    </HarvestableElement>
                    <HarvestableElement harvestableEntityClass="00000000-0000-0000-0000-000000000003" harvestableSetup="00000000-0000-0000-0000-000000000005" relativeProbability="1" />
                  </harvestables>
                </HarvestableElementGroup>
                <HarvestableElementGroup groupName="Salvage" groupProbability="0.5">
                  <harvestables>
                    <HarvestableElement harvestableEntityClass="00000000-0000-0000-0000-000000000003" relativeProbability="4" />
                  </harvestables>
                </HarvestableElementGroup>
              </harvestableGroups>
            </HarvestableProviderPreset.Sample>
            XML
        );

        $harvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_harvestable.xml',
            <<<'XML'
            <HarvestablePreset.SampleHarvestable __type="HarvestablePreset" __ref="00000000-0000-0000-0000-000000000002" __path="libs/foundry/records/harvestable/harvestablepresets/sample_harvestable.xml" displayName="@sample_harvestable">
              <harvestBehaviour />
            </HarvestablePreset.SampleHarvestable>
            XML
        );

        $entityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/scitem/test/sample_entity.xml',
            <<<'XML'
            <EntityClassDefinition.SampleEntity __type="EntityClassDefinition" __ref="00000000-0000-0000-0000-000000000003" __path="libs/foundry/records/entities/scitem/test/sample_entity.xml" Category="Default" Icon="default.bmp" />
            XML
        );

        $clusterPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/clusteringpresets/sample_cluster.xml',
            <<<'XML'
            <HarvestableClusterPreset.SampleCluster __type="HarvestableClusterPreset" __ref="00000000-0000-0000-0000-000000000004" __path="libs/foundry/records/harvestable/clusteringpresets/sample_cluster.xml" probabilityOfClustering="100">
              <clusterParamsArray />
            </HarvestableClusterPreset.SampleCluster>
            XML
        );

        $setupPath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablesetups/sample_setup.xml',
            <<<'XML'
            <HarvestableSetup.SampleSetup __type="HarvestableSetup" __ref="00000000-0000-0000-0000-000000000005" __path="libs/foundry/records/harvestable/harvestablesetups/sample_setup.xml" canRotate="1" />
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleEntity' => $entityPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::PROVIDER_PRESET_UUID) => 'Sample',
                strtolower(self::HARVESTABLE_UUID) => 'SampleHarvestable',
                strtolower(self::ENTITY_UUID) => 'SampleEntity',
                strtolower(self::CLUSTER_UUID) => 'SampleCluster',
                strtolower(self::SETUP_UUID) => 'SampleSetup',
                strtolower(self::TAG_UUID) => self::TAG_UUID,
            ],
            classToUuidMap: [
                'Sample' => strtolower(self::PROVIDER_PRESET_UUID),
                'SampleHarvestable' => strtolower(self::HARVESTABLE_UUID),
                'SampleEntity' => strtolower(self::ENTITY_UUID),
                'SampleCluster' => strtolower(self::CLUSTER_UUID),
                'SampleSetup' => strtolower(self::SETUP_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::PROVIDER_PRESET_UUID) => $providerPath,
                strtolower(self::HARVESTABLE_UUID) => $harvestablePath,
                strtolower(self::ENTITY_UUID) => $entityPath,
                strtolower(self::CLUSTER_UUID) => $clusterPath,
                strtolower(self::SETUP_UUID) => $setupPath,
            ],
        );

        $this->writeFile('Data/Localization/english/global.ini', "sample_harvestable=Sample Harvestable\n");
        (new ServiceFactory($this->tempDir))->initialize();
    }

    public function test_hydrates_supported_harvestable_references_without_touching_geometry_tags(): void
    {
        $document = new HarvestableProviderPreset;
        $document->load($this->tempDir.'/Game2/libs/foundry/records/harvestable/providerpresets/system/test/sample.xml');

        $data = $document->toArray();
        $groups = $data['harvestableGroups'];

        self::assertIsArray($groups);
        self::assertCount(2, $groups);

        $elements = $groups[0]['harvestables'];

        self::assertIsArray($elements);
        self::assertCount(2, $elements);

        self::assertSame(self::HARVESTABLE_UUID, $elements[0]['harvestable']);
        self::assertSame(self::HARVESTABLE_UUID, $elements[0]['Harvestable']['__ref']);
        self::assertSame('Sample Harvestable', $elements[0]['Harvestable']['displayName']);
        self::assertSame(self::CLUSTER_UUID, $elements[0]['Clustering']['__ref']);
        self::assertSame(self::TAG_UUID, $elements[0]['geometries']['HarvestableGeometry']['tag']);

        self::assertSame(self::ENTITY_UUID, $elements[1]['harvestableEntityClass']);
        self::assertSame(self::ENTITY_UUID, $elements[1]['HarvestableEntity']['__ref']);
        self::assertSame(self::SETUP_UUID, $elements[1]['HarvestableSetup']['__ref']);
    }

    public function test_harvestable_element_accessors_expose_raw_references_and_hydrated_documents(): void
    {
        $document = new HarvestableProviderPreset;
        $document->load($this->tempDir.'/Game2/libs/foundry/records/harvestable/providerpresets/system/test/sample.xml');

        $groups = $document->getHarvestableGroups();

        self::assertCount(2, $groups);
        self::assertContainsOnlyInstancesOf(HarvestableElementGroup::class, $groups);

        $elements = $document->getHarvestableElements();

        self::assertCount(3, $elements);
        self::assertContainsOnlyInstancesOf(HarvestableElement::class, $elements);

        $firstElement = $elements[0];
        $secondElement = $elements[1];

        self::assertSame(self::HARVESTABLE_UUID, $firstElement->getHarvestableReference());
        self::assertNull($firstElement->getHarvestableEntityClassReference());
        self::assertSame(self::CLUSTER_UUID, $firstElement->getClusteringReference());
        self::assertNull($firstElement->getHarvestableSetupReference());
        self::assertSame(self::HARVESTABLE_UUID, $firstElement->getHarvestable()?->getUuid());
        self::assertSame('@sample_harvestable', $firstElement->getHarvestable()?->get('@displayName'));
        self::assertNull($firstElement->getHarvestableEntity());
        self::assertSame(self::CLUSTER_UUID, $firstElement->getClustering()?->getUuid());
        self::assertNull($firstElement->getHarvestableSetup());

        self::assertNull($secondElement->getHarvestableReference());
        self::assertSame(self::ENTITY_UUID, $secondElement->getHarvestableEntityClassReference());
        self::assertNull($secondElement->getClusteringReference());
        self::assertSame(self::SETUP_UUID, $secondElement->getHarvestableSetupReference());
        self::assertNull($secondElement->getHarvestable());
        self::assertSame(self::ENTITY_UUID, $secondElement->getHarvestableEntity()?->getUuid());
        self::assertNull($secondElement->getClustering());
        self::assertSame(self::SETUP_UUID, $secondElement->getHarvestableSetup()?->getUuid());
    }

    public function test_harvestable_group_accessors_expose_name_probability_and_child_probabilities(): void
    {
        $document = new HarvestableProviderPreset;
        $document->load($this->tempDir.'/Game2/libs/foundry/records/harvestable/providerpresets/system/test/sample.xml');

        $group = $document->getHarvestableGroups()[0];
        $elements = $group->getHarvestableElements();
        $otherGroupElement = $document->getHarvestableGroups()[1]->getHarvestableElements()[0];

        self::assertSame('Mineables', $group->getName());
        self::assertSame(1.0, $group->getProbability());
        self::assertCount(2, $elements);
        self::assertSame(2.0, $elements[0]->getRelativeProbability());
        self::assertSame(1.0, $elements[1]->getRelativeProbability());
        self::assertSame(3.0, $group->getTotalRelativeProbability());
        self::assertEqualsWithDelta(2 / 3, $group->getHarvestableElementProbability($elements[0]), 0.00001);
        self::assertEqualsWithDelta(1 / 3, $group->getHarvestableElementProbability($elements[1]), 0.00001);

        $probabilities = $group->getHarvestableElementProbabilities();

        self::assertCount(2, $probabilities);
        self::assertEqualsWithDelta(2 / 3, $probabilities[0], 0.00001);
        self::assertEqualsWithDelta(1 / 3, $probabilities[1], 0.00001);
        self::assertNull($group->getHarvestableElementProbability($otherGroupElement));
    }
}
