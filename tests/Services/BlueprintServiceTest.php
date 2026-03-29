<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\CraftingBlueprintRecord;
use Octfx\ScDataDumper\Services\BlueprintService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class BlueprintServiceTest extends ScDataTestCase
{
    private const BLUEPRINT_UUID = 'd1da140e-b7ee-46ba-b76a-f5dd33c0348c';

    private const BLUEPRINT_CLASS = 'BP_CRAFT_lbco_sniper_energy_01_mag';

    private const RESOURCE_UUID = '61189578-ed7a-4491-9774-37ae2f82b8b0';

    private const IGNORED_BLUEPRINT_UUID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    private const DEFAULT_BLUEPRINT_UUID = 'ffffffff-1111-2222-3333-444444444444';

    private const REWARD_POOL_UUID = 'e9947de1-6160-4d62-a319-2f4693140c88';

    private const REWARD_POOL_KEY = 'BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB';

    protected function setUp(): void
    {
        parent::setUp();

        $blueprintPath = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/crafting/fpsgear/ammo/electron/bp_craft_lbco_sniper_energy_01_mag.xml',
            <<<'XML'
            <CraftingBlueprintRecord.BP_CRAFT_lbco_sniper_energy_01_mag __type="CraftingBlueprintRecord" __ref="d1da140e-b7ee-46ba-b76a-f5dd33c0348c" __path="libs/foundry/records/crafting/blueprints/crafting/fpsgear/ammo/electron/bp_craft_lbco_sniper_energy_01_mag.xml">
              <blueprint>
                <CraftingBlueprint category="f9ccf95d-ad0e-4c33-97e0-e56c847a7e37" blueprintName="@LOC_PLACEHOLDER">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="8177489f-ed83-44ac-afd4-2b32a80fa0a6" />
                  </processSpecificData>
                  <tiers>
                    <CraftingBlueprintTier>
                      <recipe>
                        <CraftingRecipe>
                          <costs>
                            <CraftingRecipeCosts>
                              <craftTime>
                                <TimeValue_Partitioned days="0" hours="0" minutes="0" seconds="10" />
                              </craftTime>
                              <mandatoryCost>
                                <CraftingCost_Select count="2">
                                  <options>
                                    <CraftingCost_Select count="1">
                                      <nameInfo debugName="MAGAZINE" displayName="@crafting_ui_slotname_magazine" />
                                      <options>
                                        <CraftingCost_Resource resource="61189578-ed7a-4491-9774-37ae2f82b8b0" minQuality="0">
                                          <quantity>
                                            <SStandardCargoUnit standardCargoUnits="0.03" />
                                          </quantity>
                                        </CraftingCost_Resource>
                                      </options>
                                    </CraftingCost_Select>
                                  </options>
                                </CraftingCost_Select>
                              </mandatoryCost>
                            </CraftingRecipeCosts>
                          </costs>
                        </CraftingRecipe>
                      </recipe>
                    </CraftingBlueprintTier>
                  </tiers>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.BP_CRAFT_lbco_sniper_energy_01_mag>
            XML
        );

        $ignoredBlueprintPath = $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprints/dismantle/ignored_blueprint.xml',
            <<<'XML'
            <CraftingBlueprintRecord.IGNORED_BLUEPRINT __type="CraftingBlueprintRecord" __ref="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee" __path="libs/foundry/records/crafting/blueprints/dismantle/ignored_blueprint.xml">
              <blueprint>
                <CraftingBlueprint category="ignored-category" blueprintName="@LOC_PLACEHOLDER">
                  <processSpecificData>
                    <CraftingProcess_Creation entityClass="ignored-output-uuid" />
                  </processSpecificData>
                </CraftingBlueprint>
              </blueprint>
            </CraftingBlueprintRecord.IGNORED_BLUEPRINT>
            XML
        );

        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/globalparams/craftingglobalparams.xml',
            <<<'XML'
            <CraftingGlobalParams.CraftingGlobalParams refiningQualityUnitMultiplier="2" defaultCompositionQuality="500" __type="CraftingGlobalParams" __ref="f99cff9b-c0b5-4d03-83f7-c7209d92b51d" __path="libs/foundry/records/crafting/globalparams/craftingglobalparams.xml">
              <defaultBlueprintSelection>
                <DefaultBlueprintSelection_Whitelist>
                  <blueprintRecords>
                    <Reference value="ffffffff-1111-2222-3333-444444444444" />
                  </blueprintRecords>
                </DefaultBlueprintSelection_Whitelist>
              </defaultBlueprintSelection>
            </CraftingGlobalParams.CraftingGlobalParams>
            XML
        );

        $this->writeFile(
            'Data/Libs/Foundry/Records/crafting/blueprintrewards/blueprintmissionpools/bp_missionreward_headhunters_mercenaryfps_eliminateall_regionab.xml',
            <<<'XML'
            <BlueprintPoolRecord.BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB __type="BlueprintPoolRecord" __ref="e9947de1-6160-4d62-a319-2f4693140c88" __path="libs/foundry/records/crafting/blueprintrewards/blueprintmissionpools/bp_missionreward_headhunters_mercenaryfps_eliminateall_regionab.xml">
              <blueprintRewards>
                <BlueprintReward weight="1" blueprintRecord="d1da140e-b7ee-46ba-b76a-f5dd33c0348c" />
              </blueprintRewards>
            </BlueprintPoolRecord.BP_MISSIONREWARD_HeadHunters_MercenaryFPS_EliminateALL_RegionAB>
            XML
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'CraftingBlueprintRecord' => [
                    self::BLUEPRINT_CLASS => $blueprintPath,
                    'IGNORED_BLUEPRINT' => $ignoredBlueprintPath,
                ],
            ],
            uuidToClassMap: [
                self::BLUEPRINT_UUID => self::BLUEPRINT_CLASS,
                self::IGNORED_BLUEPRINT_UUID => 'IGNORED_BLUEPRINT',
            ],
            classToUuidMap: [
                self::BLUEPRINT_CLASS => self::BLUEPRINT_UUID,
                'IGNORED_BLUEPRINT' => self::IGNORED_BLUEPRINT_UUID,
            ],
            uuidToPathMap: [
                self::BLUEPRINT_UUID => $blueprintPath,
                self::IGNORED_BLUEPRINT_UUID => $ignoredBlueprintPath,
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

        $this->initializeBlueprintDefinitionServices();
    }

    public function test_initialize_discovers_only_modern_creation_blueprints(): void
    {
        $service = new BlueprintService($this->tempDir);
        $service->initialize();

        $blueprints = iterator_to_array($service->iterator());

        self::assertSame(1, $service->count());
        self::assertCount(1, $blueprints);
        self::assertSame([self::BLUEPRINT_UUID], array_map(
            static fn (CraftingBlueprintRecord $blueprint): string => $blueprint->getUuid(),
            $blueprints
        ));
        self::assertNull($service->getByReference(self::IGNORED_BLUEPRINT_UUID));
    }

    public function test_get_by_reference_returns_crafting_blueprint_record_for_known_uuid(): void
    {
        $service = new BlueprintService($this->tempDir);
        $service->initialize();

        $blueprint = $service->getByReference(self::BLUEPRINT_UUID);

        self::assertInstanceOf(CraftingBlueprintRecord::class, $blueprint);
        self::assertSame('f9ccf95d-ad0e-4c33-97e0-e56c847a7e37', $blueprint?->getCategoryUuid());
        self::assertSame('8177489f-ed83-44ac-afd4-2b32a80fa0a6', $blueprint?->getOutputEntityUuid());
        self::assertSame('CraftingBlueprintTier', $blueprint?->getCraftTier()?->nodeName);
        self::assertSame(
            '@crafting_ui_slotname_magazine',
            $blueprint?->get('blueprint/CraftingBlueprint/tiers/CraftingBlueprintTier/recipe/CraftingRecipe/costs/CraftingRecipeCosts/mandatoryCost/CraftingCost_Select/options/CraftingCost_Select/nameInfo@displayName')
        );
    }

    public function test_availability_helpers_use_default_whitelist_and_reward_pools(): void
    {
        $service = new BlueprintService($this->tempDir);
        $service->initialize();

        self::assertFalse($service->isDefaultBlueprint(self::BLUEPRINT_UUID));
        self::assertTrue($service->isDefaultBlueprint(self::DEFAULT_BLUEPRINT_UUID));
        self::assertSame([
            [
                'uuid' => self::REWARD_POOL_UUID,
                'key' => self::REWARD_POOL_KEY,
            ],
        ], $service->getRewardPoolsForBlueprint(self::BLUEPRINT_UUID));
    }
}
