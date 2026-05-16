<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingBlueprintRecord;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class CraftingBlueprintRecordTest extends ScDataTestCase
{
    private const OUTPUT_ITEM_UUID = '8177489f-ed83-44ac-afd4-2b32a80fa0a6';

    private const INPUT_ITEM_UUID = '4a9a0bce-0f8b-4688-8ddf-dbd57e59af01';

    private const RESOURCE_UUID = '61189578-ed7a-4491-9774-37ae2f82b8b0';

    private const GAMEPLAY_PROPERTY_UUID = 'cfc129ce-488a-46f2-92f7-9272cd0cfdfb';

    protected function setUp(): void
    {
        parent::setUp();

        $outputItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/output_ammo.xml',
            <<<'XML'
            <EntityClassDefinition.OUTPUT_AMMO __type="EntityClassDefinition" __ref="8177489f-ed83-44ac-afd4-2b32a80fa0a6" __path="libs/foundry/records/entities/items/output_ammo.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="WeaponAmmo" SubType="Magazine" Size="1" />
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.OUTPUT_AMMO>
            XML
        );

        $inputItemPath = $this->writeFile(
            'Data/Libs/Foundry/Records/entities/items/input_material.xml',
            <<<'XML'
            <EntityClassDefinition.INPUT_MATERIAL __type="EntityClassDefinition" __ref="4a9a0bce-0f8b-4688-8ddf-dbd57e59af01" __path="libs/foundry/records/entities/items/input_material.xml">
              <Components>
                <SAttachableComponentParams>
                  <AttachDef Type="CraftingMaterial" SubType="Catalyst" Size="1" />
                </SAttachableComponentParams>
              </Components>
            </EntityClassDefinition.INPUT_MATERIAL>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'OUTPUT_AMMO' => $outputItemPath,
                    'INPUT_MATERIAL' => $inputItemPath,
                ],
            ],
            uuidToClassMap: [
                self::OUTPUT_ITEM_UUID => 'OUTPUT_AMMO',
                self::INPUT_ITEM_UUID => 'INPUT_MATERIAL',
            ],
            classToUuidMap: [
                'OUTPUT_AMMO' => self::OUTPUT_ITEM_UUID,
                'INPUT_MATERIAL' => self::INPUT_ITEM_UUID,
            ],
            uuidToPathMap: [
                self::OUTPUT_ITEM_UUID => $outputItemPath,
                self::INPUT_ITEM_UUID => $inputItemPath,
            ],
        );
        $this->writeResourceTypeCache([
            self::RESOURCE_UUID => <<<'XML'
            <ResourceType.Hephaestanite displayName="Hephaestanite" __type="ResourceType" __ref="61189578-ed7a-4491-9774-37ae2f82b8b0" __path="libs/foundry/records/resourcetypedatabase/resourcetypedatabase.xml">
              <densityType>
                <ResourceTypeDensity>
                  <densityUnit>
                    <GramsPerCubicCentimeter gramsPerCubicCentimeter="1" />
                  </densityUnit>
                </ResourceTypeDensity>
              </densityType>
            </ResourceType.Hephaestanite>
            XML,
        ]);
        $this->writeCraftingGameplayPropertyCache([
            self::GAMEPLAY_PROPERTY_UUID => '<CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />',
        ]);

        $this->initializeBlueprintDefinitionServices();
    }

    public function test_resolves_output_entity_when_reference_hydration_is_disabled(): void
    {
        $path = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/test/bp_craft_phase_two_lazy.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_PHASE_TWO_LAZY __type="CraftingBlueprintRecord" __ref="11111111-2222-3333-4444-555555555555" __path="libs/foundry/records/crafting/blueprints/crafting/test/bp_craft_phase_two_lazy.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="Phase Two Blueprint">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="8177489f-ed83-44ac-afd4-2b32a80fa0a6" />
                  </processSpecificData>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_PHASE_TWO_LAZY>
            XML
        );

        $document = (new CraftingBlueprintRecord)
            ->setReferenceHydrationEnabled(false);
        $document->load($path);

        self::assertSame(self::OUTPUT_ITEM_UUID, $document->getOutputEntity()?->getUuid());
        self::assertSame('WeaponAmmo', $document->getOutputEntity()?->getAttachType());
    }
}
