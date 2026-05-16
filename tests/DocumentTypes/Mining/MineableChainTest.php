<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class MineableChainTest extends ScDataTestCase
{
    private const HARVESTABLE_UUID = '10000000-0000-0000-0000-000000000001';

    private const ENTITY_UUID = '10000000-0000-0000-0000-000000000002';

    private const GLOBAL_PARAMS_UUID = '10000000-0000-0000-0000-000000000003';

    private const COMPOSITION_UUID = '10000000-0000-0000-0000-000000000004';

    private const ELEMENT_A_UUID = '10000000-0000-0000-0000-000000000005';

    private const ELEMENT_B_UUID = '10000000-0000-0000-0000-000000000006';

    private const RESOURCE_A_UUID = '10000000-0000-0000-0000-000000000007';

    private const RESOURCE_B_UUID = '10000000-0000-0000-0000-000000000008';

    protected function setUp(): void
    {
        parent::setUp();

        $harvestablePath = $this->writeFile(
            'Game2/libs/foundry/records/harvestable/harvestablepresets/sample_mineable.xml',
            sprintf(
                '<HarvestablePreset.SampleMineable entityClass="%2$s" respawnInSlotTime="3600" __type="HarvestablePreset" __ref="%1$s" __path="libs/foundry/records/harvestable/harvestablepresets/sample_mineable.xml"><harvestBehaviour /></HarvestablePreset.SampleMineable>',
                self::HARVESTABLE_UUID,
                self::ENTITY_UUID,
            )
        );

        $entityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_mineable_entity.xml',
            sprintf(
                '<EntityClassDefinition.SampleMineableEntity __type="EntityClassDefinition" __ref="%1$s" __path="libs/foundry/records/entities/mineable/sample_mineable_entity.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" Size="1" Grade="1" /></SAttachableComponentParams><MineableParams globalParams="%2$s" composition="%3$s" filledFactor="1" glowCurvePower="1" glowLerpSpeed="0.25" /></Components></EntityClassDefinition.SampleMineableEntity>',
                self::ENTITY_UUID,
                self::GLOBAL_PARAMS_UUID,
                self::COMPOSITION_UUID,
            )
        );

        $globalParamsPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/miningglobalparams/sample_global_params.xml',
            sprintf(
                '<MiningGlobalParams.SampleGlobalParams __type="MiningGlobalParams" __ref="%1$s" __path="libs/foundry/records/mining/miningglobalparams/sample_global_params.xml" />',
                self::GLOBAL_PARAMS_UUID,
            )
        );

        $compositionPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/rockcompositionpresets/sample_composition.xml',
            sprintf(
                '<MineableComposition.SampleComposition depositName="@sample_deposit" minimumDistinctElements="2" __type="MineableComposition" __ref="%1$s" __path="libs/foundry/records/mining/rockcompositionpresets/sample_composition.xml"><compositionArray><MineableCompositionPart mineableElement="%2$s" minPercentage="30" maxPercentage="70" probability="1" curveExponent="1" qualityScale="1" /><MineableCompositionPart mineableElement="%3$s" minPercentage="20" maxPercentage="50" probability="0.4" curveExponent="1" qualityScale="1" /></compositionArray></MineableComposition.SampleComposition>',
                self::COMPOSITION_UUID,
                self::ELEMENT_A_UUID,
                self::ELEMENT_B_UUID,
            )
        );

        $elementAPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/mineableelements/element_a.xml',
            sprintf(
                '<MineableElement.ElementA resourceType="%2$s" elementInstability="50" elementResistance="-0.7" __type="MineableElement" __ref="%1$s" __path="libs/foundry/records/mining/mineableelements/element_a.xml" />',
                self::ELEMENT_A_UUID,
                self::RESOURCE_A_UUID,
            )
        );

        $elementBPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/mineableelements/element_b.xml',
            sprintf(
                '<MineableElement.ElementB resourceType="%2$s" elementInstability="400" elementResistance="0.5" __type="MineableElement" __ref="%1$s" __path="libs/foundry/records/mining/mineableelements/element_b.xml" />',
                self::ELEMENT_B_UUID,
                self::RESOURCE_B_UUID,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleMineableEntity' => $entityPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::HARVESTABLE_UUID) => 'SampleMineable',
                strtolower(self::ENTITY_UUID) => 'SampleMineableEntity',
                strtolower(self::GLOBAL_PARAMS_UUID) => 'SampleGlobalParams',
                strtolower(self::COMPOSITION_UUID) => 'SampleComposition',
                strtolower(self::ELEMENT_A_UUID) => 'ElementA',
                strtolower(self::ELEMENT_B_UUID) => 'ElementB',
            ],
            classToUuidMap: [
                'SampleMineable' => strtolower(self::HARVESTABLE_UUID),
                'SampleMineableEntity' => strtolower(self::ENTITY_UUID),
                'SampleGlobalParams' => strtolower(self::GLOBAL_PARAMS_UUID),
                'SampleComposition' => strtolower(self::COMPOSITION_UUID),
                'ElementA' => strtolower(self::ELEMENT_A_UUID),
                'ElementB' => strtolower(self::ELEMENT_B_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::HARVESTABLE_UUID) => $harvestablePath,
                strtolower(self::ENTITY_UUID) => $entityPath,
                strtolower(self::GLOBAL_PARAMS_UUID) => $globalParamsPath,
                strtolower(self::COMPOSITION_UUID) => $compositionPath,
                strtolower(self::ELEMENT_A_UUID) => $elementAPath,
                strtolower(self::ELEMENT_B_UUID) => $elementBPath,
            ],
        );

        $this->writeResourceTypeCache([
            self::RESOURCE_A_UUID => sprintf(
                '<ResourceType.Aluminium displayName="@resource_aluminium" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/aluminium.xml" />',
                self::RESOURCE_A_UUID,
            ),
            self::RESOURCE_B_UUID => sprintf(
                '<ResourceType.Bexalite displayName="@resource_bexalite" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/bexalite.xml" />',
                self::RESOURCE_B_UUID,
            ),
        ]);

        $this->writeFile(
            'Data/Localization/english/global.ini',
            "resource_aluminium=Aluminium\nresource_bexalite=Bexalite\nsample_deposit=Sample Deposit\n"
        );

        new ServiceFactory($this->tempDir)->initialize();
    }

    public function test_resolves_the_mineable_chain_when_reference_hydration_is_disabled(): void
    {
        $document = (new HarvestablePreset)
            ->setReferenceHydrationEnabled(false);
        $document->load($this->tempDir.'/Game2/libs/foundry/records/harvestable/harvestablepresets/sample_mineable.xml');

        self::assertSame(self::ENTITY_UUID, $document->getEntityClass()?->getUuid());
        self::assertSame(self::GLOBAL_PARAMS_UUID, $document->getEntityClass()?->getMineableParams()?->getGlobalParams()?->getUuid());
        self::assertSame(
            self::RESOURCE_A_UUID,
            $document->getEntityClass()?->getMineableParams()?->getComposition()?->getParts()[0]->getMineableElement()?->getResourceType()?->getUuid()
        );
        self::assertArrayNotHasKey('EntityClass', $document->toArray());
    }
}
