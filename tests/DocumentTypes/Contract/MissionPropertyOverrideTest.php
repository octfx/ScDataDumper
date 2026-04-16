<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Contract;

use DOMDocument;
use Octfx\ScDataDumper\DocumentTypes\Contract\ContractGeneratorRecord;
use Octfx\ScDataDumper\DocumentTypes\Contract\MissionPropertyOverride;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\AINameValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\BooleanValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\CombinedDataSetEntriesValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\EntitySpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\FloatValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\HaulingOrdersValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\IntegerValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\LocationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\MissionItemValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\NPCSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\OrganizationValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\RewardValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\ShipSpawnDescriptionsValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\StringHashValue;
use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\TagsValue;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class MissionPropertyOverrideTest extends ScDataTestCase
{
    private string $generatorPath;

    private const GENERATOR_UUID = '40000000-0000-0000-0000-000000000001';

    private const OVERRIDE_BOOLEAN = 0;

    private const OVERRIDE_INTEGER = 1;

    private const OVERRIDE_FLOAT = 2;

    private const OVERRIDE_STRING_HASH = 3;

    private const OVERRIDE_AI_NAME = 4;

    private const OVERRIDE_TAGS = 5;

    private const OVERRIDE_LOCATION = 6;

    private const OVERRIDE_LOCATIONS = 7;

    private const OVERRIDE_ORGANIZATION = 8;

    private const OVERRIDE_MISSION_ITEM = 9;

    private const OVERRIDE_REWARD = 10;

    private const OVERRIDE_SHIP_SPAWN = 11;

    private const OVERRIDE_NPC_SPAWN = 12;

    private const OVERRIDE_ENTITY_SPAWN = 13;

    private const OVERRIDE_HAULING = 14;

    private const OVERRIDE_EMPTY = 15;

    protected function setUp(): void
    {
        parent::setUp();

        $xml = <<<'XML'
<ContractGenerator.TestProps __type="ContractGenerator" __ref="%1$s" __path="libs/foundry/records/contracts/contractgenerator/test_props.xml">
  <generators>
    <ContractGeneratorHandler_Career debugName="Props_Career">
      <defaultAvailability notifyOnAvailable="0" onceOnly="0" availableInPrison="0" canReacceptAfterAbandoning="0" canReacceptAfterFailing="0" hasPersonalCooldown="0" hideInMobiGlas="0">
        <prerequisites />
      </defaultAvailability>
      <contractParams>
        <propertyOverrides>
          <MissionProperty missionVariableName="HandlerOrg" extendedTextToken="Contractor">
            <value>
              <MissionPropertyValue_Organization>
                <matchConditions>
                  <DataSetMatchCondition_SpecificOrganizationsDef>
                    <organizations>
                      <Reference value="aa000000-0000-0000-0000-000000000001" />
                    </organizations>
                  </DataSetMatchCondition_SpecificOrganizationsDef>
                </matchConditions>
              </MissionPropertyValue_Organization>
            </value>
          </MissionProperty>
        </propertyOverrides>
      </contractParams>
      <contracts>
        <Contract id="props-001" debugName="AllTypes" template="bb000000-0000-0000-0000-000000000001">
          <paramOverrides>
            <stringParamOverrides>
              <ContractStringParam param="Title" value="@test_title" />
            </stringParamOverrides>
            <boolParamOverrides>
              <ContractBoolParam param="Illegal" value="0" />
            </boolParamOverrides>
            <propertyOverrides>
              <MissionProperty missionVariableName="MyBoolean" extendedTextToken="BoolToken">
                <value>
                  <MissionPropertyValue_Boolean value="1" />
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyInteger" extendedTextToken="IntToken">
                <value>
                  <MissionPropertyValue_Integer>
                    <options>
                      <MissionPropertyValueOption_Integer textId="@LOC_UNINITIALIZED" weighting="1" DEBUG_forceChooseThisOption="0" value="5" variation="2" />
                    </options>
                  </MissionPropertyValue_Integer>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyFloat" extendedTextToken="FloatToken">
                <value>
                  <MissionPropertyValue_Float>
                    <options>
                      <MissionPropertyValueOption_Float textId="@LOC_UNINITIALIZED" weighting="1" DEBUG_forceChooseThisOption="0" value="90.5" variation="0" />
                    </options>
                  </MissionPropertyValue_Float>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyStringHash" extendedTextToken="HashToken">
                <value>
                  <MissionPropertyValue_StringHash>
                    <options>
                      <MissionPropertyValueOption_StringHash textId="@delivery_missionitem" weighting="1" DEBUG_forceChooseThisOption="0" value="Close" />
                    </options>
                  </MissionPropertyValue_StringHash>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyAIName" extendedTextToken="AINameToken">
                <value>
                  <MissionPropertyValue_AIName randomName="1" randomLastName="0" randomNickName="1" characterGivenName="@LOC_UNINITIALIZED" characterGivenLastName="@LOC_UNINITIALIZED" characterGivenNickName="@LOC_UNINITIALIZED" characterNameData="cc000000-0000-0000-0000-000000000001" chanceOfNickName="0.05" />
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MissionAcceptTags">
                <value>
                  <MissionPropertyValue_Tags>
                    <tags>
                      <tags>
                        <Reference value="dd000000-0000-0000-0000-000000000001" />
                        <Reference value="dd000000-0000-0000-0000-000000000002" />
                      </tags>
                    </tags>
                    <negativeTags>
                      <tags>
                        <Reference value="dd000000-0000-0000-0000-000000000003" />
                      </tags>
                    </negativeTags>
                  </MissionPropertyValue_Tags>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="PickupLocation" extendedTextToken="Location">
                <value>
                  <MissionPropertyValue_Location logErrorOnSearchFail="1">
                    <matchConditions>
                      <DataSetMatchCondition_TagSearch tagType="General">
                        <tagSearch>
                          <TagSearchTerm>
                            <positiveTags>
                              <Reference value="ee000000-0000-0000-0000-000000000001" />
                              <Reference value="ee000000-0000-0000-0000-000000000002" />
                            </positiveTags>
                            <negativeTags>
                              <Reference value="ee000000-0000-0000-0000-000000000003" />
                            </negativeTags>
                          </TagSearchTerm>
                        </tagSearch>
                      </DataSetMatchCondition_TagSearch>
                    </matchConditions>
                    <resourceTags>
                      <Reference value="ee000000-0000-0000-0000-000000000004" />
                    </resourceTags>
                  </MissionPropertyValue_Location>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="DropOffLocations" extendedTextToken="Destination">
                <value>
                  <MissionPropertyValue_Locations logErrorOnSearchFail="1" minLocationsToFind="2" maxLocationsToFind="3" failIfMinAmountNotFound="0">
                    <matchConditions>
                      <DataSetMatchCondition_TagSearch tagType="General">
                        <tagSearch>
                          <TagSearchTerm>
                            <positiveTags>
                              <Reference value="ff000000-0000-0000-0000-000000000001" />
                            </positiveTags>
                          </TagSearchTerm>
                          <TagSearchTerm>
                            <positiveTags>
                              <Reference value="ff000000-0000-0000-0000-000000000002" />
                            </positiveTags>
                          </TagSearchTerm>
                        </tagSearch>
                      </DataSetMatchCondition_TagSearch>
                    </matchConditions>
                  </MissionPropertyValue_Locations>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyOrg">
                <value>
                  <MissionPropertyValue_Organization>
                    <matchConditions>
                      <DataSetMatchCondition_SpecificOrganizationsDef>
                        <organizations>
                          <Reference value="aa000000-0000-0000-0000-000000000002" />
                          <Reference value="aa000000-0000-0000-0000-000000000003" />
                        </organizations>
                      </DataSetMatchCondition_SpecificOrganizationsDef>
                    </matchConditions>
                  </MissionPropertyValue_Organization>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyMissionItem" extendedTextToken="MissionItem">
                <value>
                  <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="2">
                    <matchConditions>
                      <DataSetMatchCondition_TagSearch tagType="General">
                        <tagSearch>
                          <TagSearchTerm>
                            <positiveTags>
                              <Reference value="11000000-0000-0000-0000-000000000001" />
                            </positiveTags>
                            <negativeTags>
                              <Reference value="11000000-0000-0000-0000-000000000002" />
                            </negativeTags>
                          </TagSearchTerm>
                        </tagSearch>
                      </DataSetMatchCondition_TagSearch>
                    </matchConditions>
                  </MissionPropertyValue_MissionItem>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyReward">
                <value>
                  <MissionPropertyValue_Reward>
                    <rewardDef reward="5000" max="10000" plusBonuses="1" currencyType="aUEC" />
                  </MissionPropertyValue_Reward>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="EnemyShips">
                <value>
                  <MissionPropertyValue_ShipSpawnDescriptions allowedForMissionRestrictedDeliveries="0">
                    <spawnDescriptions>
                      <SpawnDescription_ShipGroup Name="Wave1">
                        <ships>
                          <SpawnDescription_ShipOptions>
                            <options>
                              <SpawnDescription_Ship concurrentAmount="2" includeLocationAISpawnTags="0" weight="1" initialDamageSettings="dd000000-0000-0000-0000-000000000099">
                                <tags>
                                  <tags>
                                    <Reference value="ss000000-0000-0000-0000-000000000001" />
                                    <Reference value="ss000000-0000-0000-0000-000000000002" />
                                  </tags>
                                </tags>
                                <markupTags>
                                  <tags>
                                    <Reference value="ss000000-0000-0000-0000-000000000003" />
                                  </tags>
                                </markupTags>
                              </SpawnDescription_Ship>
                            </options>
                          </SpawnDescription_ShipOptions>
                          <SpawnDescription_ShipOptions>
                            <options>
                              <SpawnDescription_Ship concurrentAmount="1" includeLocationAISpawnTags="0" weight="1">
                                <tags>
                                  <tags>
                                    <Reference value="ss000000-0000-0000-0000-000000000004" />
                                  </tags>
                                </tags>
                              </SpawnDescription_Ship>
                            </options>
                          </SpawnDescription_ShipOptions>
                        </ships>
                      </SpawnDescription_ShipGroup>
                      <SpawnDescription_ShipGroup Name="Wave2">
                        <ships>
                          <SpawnDescription_ShipOptions>
                            <options>
                              <SpawnDescription_Ship concurrentAmount="3" includeLocationAISpawnTags="1" weight="2">
                                <tags>
                                  <tags>
                                    <Reference value="ss000000-0000-0000-0000-000000000005" />
                                  </tags>
                                </tags>
                              </SpawnDescription_Ship>
                            </options>
                          </SpawnDescription_ShipOptions>
                        </ships>
                      </SpawnDescription_ShipGroup>
                    </spawnDescriptions>
                  </MissionPropertyValue_ShipSpawnDescriptions>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyNPCs">
                <value>
                  <MissionPropertyValue_NPCSpawnDescriptions>
                    <spawnDescriptions>
                      <SpawnDescription_NPC_Group Name="Guard Squad">
                        <options>
                          <SpawnDescription_NPCOption priority="1" includeLocationAISpawnTags="0" weight="1">
                            <autoSpawnSettings name="Guard" initialActivity="Sentry" excludeShipCrew="1" excludeSpawnGender="Female" minGroupSize="2" maxGroupSize="4" maxConcurrentSpawns="2" maxSpawns="4" minSpawnDelay="0" maxSpawnDelay="5" missionAlliedMarker="0" isCritical="0">
                              <positiveCharacterTags>
                                <tags>
                                  <Reference value="nn000000-0000-0000-0000-000000000001" />
                                </tags>
                              </positiveCharacterTags>
                              <entityTags>
                                <tags>
                                  <Reference value="nn000000-0000-0000-0000-000000000002" />
                                </tags>
                              </entityTags>
                            </autoSpawnSettings>
                          </SpawnDescription_NPCOption>
                        </options>
                      </SpawnDescription_NPC_Group>
                    </spawnDescriptions>
                  </MissionPropertyValue_NPCSpawnDescriptions>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyEntities">
                <value>
                  <MissionPropertyValue_EntitySpawnDescriptions>
                    <spawnDescriptions>
                      <SpawnDescription_EntityGroup Name="Loot">
                        <entities>
                          <SpawnDescription_EntityOptions>
                            <options>
                              <SpawnDescription_Entity amount="3" weight="1">
                                <tags>
                                  <tags>
                                    <Reference value="en000000-0000-0000-0000-000000000001" />
                                  </tags>
                                </tags>
                                <negativeTags>
                                  <tags>
                                    <Reference value="en000000-0000-0000-0000-000000000002" />
                                  </tags>
                                </negativeTags>
                                <markupTags>
                                  <tags>
                                    <Reference value="en000000-0000-0000-0000-000000000003" />
                                  </tags>
                                </markupTags>
                              </SpawnDescription_Entity>
                            </options>
                          </SpawnDescription_EntityOptions>
                        </entities>
                      </SpawnDescription_EntityGroup>
                    </spawnDescriptions>
                  </MissionPropertyValue_EntitySpawnDescriptions>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyHauling">
                <value>
                  <MissionPropertyValue_HaulingOrders>
                    <haulingOrderContent>
                      <HaulingOrderContent_EntityClass entityClass="hh000000-0000-0000-0000-000000000001" minAmount="1" maxAmount="2" />
                      <HaulingOrderContent_Resource resource="hh000000-0000-0000-0000-000000000002" maxContainerSize="-1" minSCU="10" maxSCU="20" />
                    </haulingOrderContent>
                  </MissionPropertyValue_HaulingOrders>
                </value>
              </MissionProperty>
              <MissionProperty missionVariableName="MyEmptyProperty" extendedTextToken="Empty" />
            </propertyOverrides>
          </paramOverrides>
          <generationParams>
            <ContractGenerationParams_Legacy maxInstances="1" maxInstancesPerPlayer="1" respawnTime="0" respawnTimeVariation="0" />
          </generationParams>
          <contractResults contractBuyInAmount="0" timeToComplete="-1" />
        </Contract>
      </contracts>
    </ContractGeneratorHandler_Career>
  </generators>
</ContractGenerator.TestProps>
XML;

        $this->generatorPath = $this->writeFile(
            'Data/Libs/Foundry/Records/contracts/contractgenerator/test_props.xml',
            sprintf($xml, self::GENERATOR_UUID)
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'ContractGenerator' => [
                    'TestProps' => $this->generatorPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::GENERATOR_UUID) => 'TestProps',
            ],
            classToUuidMap: [
                'TestProps' => strtolower(self::GENERATOR_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::GENERATOR_UUID) => $this->generatorPath,
            ],
        );
    }

    private function loadGenerator(): ContractGeneratorRecord
    {
        $doc = new ContractGeneratorRecord;
        $doc->load($this->generatorPath);

        return $doc;
    }

    private function getOverride(int $index): MissionPropertyOverride
    {
        return $this->loadGenerator()->getHandlers()[0]->getContracts()[0]->getPropertyOverrides()[$index];
    }

    public function test_contract_entry_has_property_overrides(): void
    {
        $doc = $this->loadGenerator();
        $contract = $doc->getHandlers()[0]->getContracts()[0];

        $overrides = $contract->getPropertyOverrides();
        self::assertCount(16, $overrides);
    }

    public function test_empty_property_override(): void
    {
        $doc = $this->loadGenerator();
        $contract = $doc->getHandlers()[0]->getContracts()[0];

        $empty = $contract->getPropertyOverrides()[self::OVERRIDE_EMPTY];
        self::assertSame('MyEmptyProperty', $empty->getMissionVariableName());
        self::assertSame('Empty', $empty->getExtendedTextToken());
        self::assertNull($empty->getValueTypeName());
        self::assertNull($empty->getValue());
    }

    public function test_boolean_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_BOOLEAN);

        self::assertSame('MyBoolean', $prop->getMissionVariableName());
        self::assertSame('BoolToken', $prop->getExtendedTextToken());
        self::assertSame('MissionPropertyValue_Boolean', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(BooleanValue::class, $value);
        self::assertTrue($value->getValue());
    }

    public function test_integer_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_INTEGER);

        self::assertSame('MissionPropertyValue_Integer', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(IntegerValue::class, $value);
        $options = $value->getOptions();
        self::assertCount(1, $options);
        self::assertSame('@LOC_UNINITIALIZED', $options[0]['textId']);
        self::assertSame(1, $options[0]['weighting']);
        self::assertSame(5, $options[0]['value']);
        self::assertSame(2.0, $options[0]['variation']);
    }

    public function test_float_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_FLOAT);

        self::assertSame('MissionPropertyValue_Float', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(FloatValue::class, $value);
        $options = $value->getOptions();
        self::assertCount(1, $options);
        self::assertSame(90.5, $options[0]['value']);
        self::assertSame(0.0, $options[0]['variation']);
    }

    public function test_string_hash_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_STRING_HASH);

        self::assertSame('MissionPropertyValue_StringHash', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(StringHashValue::class, $value);
        $options = $value->getOptions();
        self::assertCount(1, $options);
        self::assertSame('@delivery_missionitem', $options[0]['textId']);
        self::assertSame('Close', $options[0]['value']);
    }

    public function test_ai_name_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_AI_NAME);

        self::assertSame('MissionPropertyValue_AIName', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(AINameValue::class, $value);
        self::assertTrue($value->isRandomName());
        self::assertFalse($value->isRandomLastName());
        self::assertTrue($value->isRandomNickName());
        self::assertSame('@LOC_UNINITIALIZED', $value->getCharacterGivenName());
        self::assertSame('cc000000-0000-0000-0000-000000000001', $value->getCharacterNameDataReference());
        self::assertSame(0.05, $value->getChanceOfNickName());
    }

    public function test_tags_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_TAGS);

        self::assertSame('MissionPropertyValue_Tags', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(TagsValue::class, $value);
        self::assertSame([
            'dd000000-0000-0000-0000-000000000001',
            'dd000000-0000-0000-0000-000000000002',
        ], $value->getTags());
        self::assertSame([
            'dd000000-0000-0000-0000-000000000003',
        ], $value->getNegativeTags());
    }

    public function test_location_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_LOCATION);

        self::assertSame('MissionPropertyValue_Location', $prop->getValueTypeName());
        self::assertSame('Location', $prop->getExtendedTextToken());

        $value = $prop->getValue();
        self::assertInstanceOf(LocationValue::class, $value);
        self::assertTrue($value->getLogErrorOnSearchFail());
        self::assertSame('General', $value->getMatchConditionTagType());

        $terms = $value->getTagSearchTerms();
        self::assertCount(1, $terms);
        self::assertSame([
            'ee000000-0000-0000-0000-000000000001',
            'ee000000-0000-0000-0000-000000000002',
        ], $terms[0]['positiveTags']);
        self::assertSame([
            'ee000000-0000-0000-0000-000000000003',
        ], $terms[0]['negativeTags']);

        $resourceTags = $value->getResourceTags();
        self::assertSame(['ee000000-0000-0000-0000-000000000004'], $resourceTags);
    }

    public function test_locations_value_with_multiple_search_terms(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_LOCATIONS);

        self::assertSame('MissionPropertyValue_Locations', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(LocationsValue::class, $value);
        self::assertTrue($value->getLogErrorOnSearchFail());
        self::assertSame(2, $value->getMinLocationsToFind());
        self::assertSame(3, $value->getMaxLocationsToFind());
        self::assertFalse($value->getFailIfMinAmountNotFound());

        $terms = $value->getTagSearchTerms();
        self::assertCount(2, $terms);
        self::assertSame(['ff000000-0000-0000-0000-000000000001'], $terms[0]['positiveTags']);
        self::assertSame(['ff000000-0000-0000-0000-000000000002'], $terms[1]['positiveTags']);
    }

    public function test_organization_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_ORGANIZATION);

        self::assertSame('MissionPropertyValue_Organization', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(OrganizationValue::class, $value);
        self::assertSame([
            'aa000000-0000-0000-0000-000000000002',
            'aa000000-0000-0000-0000-000000000003',
        ], $value->getOrganizations());
    }

    public function test_mission_item_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_MISSION_ITEM);

        self::assertSame('MissionPropertyValue_MissionItem', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(MissionItemValue::class, $value);
        self::assertSame(1, $value->getMinItemsToFind());
        self::assertSame(2, $value->getMaxItemsToFind());
        self::assertSame('General', $value->getMatchConditionTagType());
        self::assertSame([], $value->getSpecificItems());

        $terms = $value->getTagSearchTerms();
        self::assertCount(1, $terms);
        self::assertSame(['11000000-0000-0000-0000-000000000001'], $terms[0]['positiveTags']);
        self::assertSame(['11000000-0000-0000-0000-000000000002'], $terms[0]['negativeTags']);
    }

    public function test_reward_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_REWARD);

        self::assertSame('MissionPropertyValue_Reward', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(RewardValue::class, $value);
        self::assertSame(5000, $value->getReward());
        self::assertSame(10000, $value->getMax());
        self::assertTrue($value->isPlusBonuses());
        self::assertSame('aUEC', $value->getCurrencyType());
    }

    public function test_ship_spawn_descriptions_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_SHIP_SPAWN);

        self::assertSame('MissionPropertyValue_ShipSpawnDescriptions', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(ShipSpawnDescriptionsValue::class, $value);
        self::assertFalse($value->isAllowedForMissionRestrictedDeliveries());

        $groups = $value->getShipGroups();
        self::assertCount(2, $groups);

        $wave1 = $groups[0];
        self::assertSame('Wave1', $wave1['name']);
        self::assertCount(2, $wave1['shipOptions']);

        $ship1 = $wave1['shipOptions'][0]['ships'][0];
        self::assertSame(2, $ship1['concurrentAmount']);
        self::assertFalse($ship1['includeLocationAISpawnTags']);
        self::assertSame(1, $ship1['weight']);
        self::assertSame('dd000000-0000-0000-0000-000000000099', $ship1['initialDamageSettings']);
        self::assertSame([
            'ss000000-0000-0000-0000-000000000001',
            'ss000000-0000-0000-0000-000000000002',
        ], $ship1['tags']);
        self::assertSame(['ss000000-0000-0000-0000-000000000003'], $ship1['markupTags']);

        $ship2 = $wave1['shipOptions'][1]['ships'][0];
        self::assertSame(1, $ship2['concurrentAmount']);
        self::assertSame(['ss000000-0000-0000-0000-000000000004'], $ship2['tags']);
        self::assertSame([], $ship2['markupTags']);

        $wave2 = $groups[1];
        self::assertSame('Wave2', $wave2['name']);
        self::assertCount(1, $wave2['shipOptions']);
        $wave2Ship = $wave2['shipOptions'][0]['ships'][0];
        self::assertSame(3, $wave2Ship['concurrentAmount']);
        self::assertTrue($wave2Ship['includeLocationAISpawnTags']);
        self::assertSame(2, $wave2Ship['weight']);
    }

    public function test_npc_spawn_descriptions_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_NPC_SPAWN);

        self::assertSame('MissionPropertyValue_NPCSpawnDescriptions', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(NPCSpawnDescriptionsValue::class, $value);

        $groups = $value->getNPCGroups();
        self::assertCount(1, $groups);

        $group = $groups[0];
        self::assertSame('Guard Squad', $group['name']);
        self::assertCount(1, $group['options']);

        $option = $group['options'][0];
        self::assertSame(1, $option['priority']);
        self::assertFalse($option['includeLocationAISpawnTags']);
        self::assertSame(1, $option['weight']);

        $settings = $option['autoSpawnSettings'];
        self::assertSame('Guard', $settings['name']);
        self::assertSame('Sentry', $settings['initialActivity']);
        self::assertTrue($settings['excludeShipCrew']);
        self::assertSame('Female', $settings['excludeSpawnGender']);
        self::assertSame(2, $settings['minGroupSize']);
        self::assertSame(4, $settings['maxGroupSize']);
        self::assertSame(2, $settings['maxConcurrentSpawns']);
        self::assertSame(4, $settings['maxSpawns']);
        self::assertSame(0, $settings['minSpawnDelay']);
        self::assertSame(5, $settings['maxSpawnDelay']);
        self::assertFalse($settings['missionAlliedMarker']);
        self::assertFalse($settings['isCritical']);
        self::assertSame(['nn000000-0000-0000-0000-000000000001'], $settings['positiveCharacterTags']);
        self::assertSame([], $settings['closetPositiveTags']);
        self::assertSame([], $settings['defendAreaPositiveTags']);
        self::assertSame(['nn000000-0000-0000-0000-000000000002'], $settings['entityTags']);
    }

    public function test_entity_spawn_descriptions_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_ENTITY_SPAWN);

        self::assertSame('MissionPropertyValue_EntitySpawnDescriptions', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(EntitySpawnDescriptionsValue::class, $value);

        $groups = $value->getEntityGroups();
        self::assertCount(1, $groups);

        $group = $groups[0];
        self::assertSame('Loot', $group['name']);
        self::assertCount(1, $group['entities']);

        $entity = $group['entities'][0];
        self::assertSame(3, $entity['amount']);
        self::assertSame(1, $entity['weight']);
        self::assertSame(['en000000-0000-0000-0000-000000000001'], $entity['tags']);
        self::assertSame(['en000000-0000-0000-0000-000000000002'], $entity['negativeTags']);
        self::assertSame(['en000000-0000-0000-0000-000000000003'], $entity['markupTags']);
    }

    public function test_hauling_orders_value(): void
    {
        $prop = $this->getOverride(self::OVERRIDE_HAULING);

        self::assertSame('MissionPropertyValue_HaulingOrders', $prop->getValueTypeName());

        $value = $prop->getValue();
        self::assertInstanceOf(HaulingOrdersValue::class, $value);

        $orders = $value->getOrders();
        self::assertCount(2, $orders);

        self::assertSame('EntityClass', $orders[0]['type']);
        self::assertSame('hh000000-0000-0000-0000-000000000001', $orders[0]['entityClass']);
        self::assertSame(1, $orders[0]['minAmount']);
        self::assertSame(2, $orders[0]['maxAmount']);

        self::assertSame('Resource', $orders[1]['type']);
        self::assertSame('hh000000-0000-0000-0000-000000000002', $orders[1]['resource']);
        self::assertSame(-1, $orders[1]['maxContainerSize']);
        self::assertSame(10, $orders[1]['minSCU']);
        self::assertSame(20, $orders[1]['maxSCU']);
    }

    public function test_handler_contract_param_property_overrides(): void
    {
        $doc = $this->loadGenerator();
        $handler = $doc->getHandlers()[0];

        $overrides = $handler->getContractParamPropertyOverrides();
        self::assertCount(1, $overrides);

        $org = $overrides[0];
        self::assertSame('HandlerOrg', $org->getMissionVariableName());
        self::assertSame('Contractor', $org->getExtendedTextToken());
        self::assertSame('MissionPropertyValue_Organization', $org->getValueTypeName());

        $value = $org->getValue();
        self::assertInstanceOf(OrganizationValue::class, $value);
        self::assertSame(['aa000000-0000-0000-0000-000000000001'], $value->getOrganizations());
    }

    public function test_tags_value_without_negative_tags(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="SimpleTags">
  <value>
    <MissionPropertyValue_Tags>
      <tags>
        <tags>
          <Reference value="tt000000-0000-0000-0000-000000000001" />
        </tags>
      </tags>
    </MissionPropertyValue_Tags>
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        self::assertNotNull($prop);

        $value = $prop->getValue();
        self::assertInstanceOf(TagsValue::class, $value);
        self::assertSame(['tt000000-0000-0000-0000-000000000001'], $value->getTags());
        self::assertSame([], $value->getNegativeTags());
    }

    public function test_mission_item_value_with_specific_items(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="MyItem">
  <value>
    <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1">
      <matchConditions>
        <DataSetMatchCondition_SpecificItemsDef>
          <items>
            <Reference value="ii000000-0000-0000-0000-000000000001" />
            <Reference value="ii000000-0000-0000-0000-000000000002" />
          </items>
        </DataSetMatchCondition_SpecificItemsDef>
      </matchConditions>
    </MissionPropertyValue_MissionItem>
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        $value = $prop->getValue();
        self::assertInstanceOf(MissionItemValue::class, $value);
        self::assertSame([
            'ii000000-0000-0000-0000-000000000001',
            'ii000000-0000-0000-0000-000000000002',
        ], $value->getSpecificItems());
        self::assertSame([], $value->getTagSearchTerms());
    }

    public function test_hauling_orders_mission_item_type(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="MyHauling">
  <value>
    <MissionPropertyValue_HaulingOrders>
      <haulingOrderContent>
        <HaulingOrderContent_MissionItem minAmount="1" maxAmount="0">
          <item value="MissionProperty[0016]" />
        </HaulingOrderContent_MissionItem>
      </haulingOrderContent>
    </MissionPropertyValue_HaulingOrders>
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        $value = $prop->getValue();
        self::assertInstanceOf(HaulingOrdersValue::class, $value);

        $orders = $value->getOrders();
        self::assertCount(1, $orders);
        self::assertSame('MissionItem', $orders[0]['type']);
        self::assertSame('MissionProperty[0016]', $orders[0]['missionItem']);
        self::assertSame(1, $orders[0]['minAmount']);
    }

    public function test_hauling_orders_or_type(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="MyHaulingOr">
  <value>
    <MissionPropertyValue_HaulingOrders>
      <haulingOrderContent>
        <HaulingOrderContent_Or>
          <options>
            <HaulingOrder_OrOption_And>
              <orders>
                <HaulingOrderContent_Resource resource="hh000000-0000-0000-0000-000000000010" maxContainerSize="-1" minSCU="10" maxSCU="10" />
              </orders>
            </HaulingOrder_OrOption_And>
            <HaulingOrder_OrOption_And>
              <orders>
                <HaulingOrderContent_EntityClass entityClass="hh000000-0000-0000-0000-000000000020" minAmount="1" maxAmount="1" />
              </orders>
            </HaulingOrder_OrOption_And>
          </options>
        </HaulingOrderContent_Or>
      </haulingOrderContent>
    </MissionPropertyValue_HaulingOrders>
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        $value = $prop->getValue();
        self::assertInstanceOf(HaulingOrdersValue::class, $value);

        $orders = $value->getOrders();
        self::assertCount(1, $orders);
        self::assertSame('Or', $orders[0]['type']);
        self::assertCount(2, $orders[0]['orOptions']);

        $firstAnd = $orders[0]['orOptions'][0];
        self::assertCount(1, $firstAnd);
        self::assertSame('Resource', $firstAnd[0]['type']);
        self::assertSame('hh000000-0000-0000-0000-000000000010', $firstAnd[0]['resource']);
        self::assertSame(10, $firstAnd[0]['minSCU']);

        $secondAnd = $orders[0]['orOptions'][1];
        self::assertSame('EntityClass', $secondAnd[0]['type']);
        self::assertSame('hh000000-0000-0000-0000-000000000020', $secondAnd[0]['entityClass']);
    }

    public function test_combined_data_set_entries_value(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="MyCombined">
  <value>
    <MissionPropertyValue_CombinedDataSetEntries>
      <dataSetEntryProperties>
        <MissionProperty extendedTextToken="InnerItem">
          <value>
            <MissionPropertyValue_MissionItem minItemsToFind="1" maxItemsToFind="1">
              <matchConditions>
                <DataSetMatchCondition_TagSearch tagType="General">
                  <tagSearch>
                    <TagSearchTerm>
                      <positiveTags>
                        <Reference value="cc000000-0000-0000-0000-000000000001" />
                      </positiveTags>
                    </TagSearchTerm>
                  </tagSearch>
                </DataSetMatchCondition_TagSearch>
              </matchConditions>
            </MissionPropertyValue_MissionItem>
          </value>
        </MissionProperty>
        <MissionProperty extendedTextToken="InnerBool">
          <value>
            <MissionPropertyValue_Boolean value="0" />
          </value>
        </MissionProperty>
      </dataSetEntryProperties>
    </MissionPropertyValue_CombinedDataSetEntries>
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        $value = $prop->getValue();
        self::assertInstanceOf(CombinedDataSetEntriesValue::class, $value);

        $properties = $value->getProperties();
        self::assertCount(2, $properties);

        self::assertSame('InnerItem', $properties[0]->getExtendedTextToken());
        self::assertInstanceOf(MissionItemValue::class, $properties[0]->getValue());

        self::assertSame('InnerBool', $properties[1]->getExtendedTextToken());
        self::assertInstanceOf(BooleanValue::class, $properties[1]->getValue());
        self::assertFalse($properties[1]->getValue()->getValue());
    }

    public function test_unknown_value_type_returns_null(): void
    {
        $dom = new DOMDocument;
        $dom->loadXML(<<<'XML'
<MissionProperty missionVariableName="Unknown">
  <value>
    <MissionPropertyValue_UnknownType someAttr="test" />
  </value>
</MissionProperty>
XML);
        $prop = MissionPropertyOverride::fromNode($dom->documentElement);
        self::assertSame('MissionPropertyValue_UnknownType', $prop->getValueTypeName());
        self::assertNull($prop->getValue());
    }
}
