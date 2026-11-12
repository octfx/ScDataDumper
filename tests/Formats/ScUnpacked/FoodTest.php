<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\Food;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class FoodTest extends ScDataTestCase
{
    private const COAGULANT_UUID = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

    private const STEROIDS_UUID = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();

        // Mirrors crlf_consumable_ea_medicateall_01: two BloodDrugLevel contents
        $this->writeCacheFiles();
        $this->writeFoundryRecord(
            self::COAGULANT_UUID,
            'records/entities/scitem/consumables/subtypes',
            sprintf(
                '<ConsumableSubtype.Coagulant __type="ConsumableSubtype" __ref="%1$s" __path="libs/foundry/records/entities/scitem/consumables/subtypes/coagulant.xml" typeName="Medical" consumableName="Coagulant"><effectsPerMicroSCU><ConsumableEffectModifyActorStatus statType="BloodDrugLevel" statPointChange="0.05" /></effectsPerMicroSCU></ConsumableSubtype.Coagulant>',
                self::COAGULANT_UUID,
            )
        );
        $this->writeFoundryRecord(
            self::STEROIDS_UUID,
            'records/entities/scitem/consumables/subtypes',
            sprintf(
                '<ConsumableSubtype.Steroids __type="ConsumableSubtype" __ref="%1$s" __path="libs/foundry/records/entities/scitem/consumables/subtypes/steroids.xml" typeName="Medical" consumableName="Steroids"><effectsPerMicroSCU><ConsumableEffectModifyActorStatus statType="BloodDrugLevel" statPointChange="0.1" /></effectsPerMicroSCU></ConsumableSubtype.Steroids>',
                self::STEROIDS_UUID,
            )
        );

        $this->initializeMinimalItemServices();

        $foundryService = new FoundryLookupService($this->tempDir);
        $foundryService->initialize();
        $this->addServiceToFactory('FoundryLookupService', $foundryService);
    }

    public function test_nutrition_sums_multiple_contents_modifying_the_same_stat(): void
    {
        $food = $this->makeFood(
            contents: sprintf(
                '<ConsumableContent consumableSubtype="%1$s" ratio="1" /><ConsumableContent consumableSubtype="%2$s" ratio="1" />',
                self::COAGULANT_UUID,
                self::STEROIDS_UUID,
            ),
        );

        $result = $food->toArray();

        self::assertNotNull($result);
        self::assertNotNull($result['Nutrition']['BloodDrugLevel']);
        // 0.05 * 1 + 0.1 * 1 = 0.15 per µSCU, 0.15 * 30 = 4.5 total
        self::assertEquals(4.5, $result['Nutrition']['BloodDrugLevel']['Total']);
        self::assertEquals(0.15, $result['Nutrition']['BloodDrugLevel']['PerMicroSCU']);
    }

    public function test_nutrition_accumulates_ratio_weighted_contents(): void
    {
        // Mirrors medicateall style ratios: 0.05@1, 0.1@1, 0.1@0.1 => 0.16 per µSCU
        $food = $this->makeFood(
            contents: sprintf(
                '<ConsumableContent consumableSubtype="%1$s" ratio="1" /><ConsumableContent consumableSubtype="%2$s" ratio="1" /><ConsumableContent consumableSubtype="%2$s" ratio="0.1" />',
                self::COAGULANT_UUID,
                self::STEROIDS_UUID,
            ),
        );

        $result = $food->toArray();

        self::assertNotNull($result);
        self::assertEquals(4.8, $result['Nutrition']['BloodDrugLevel']['Total']);
        self::assertEquals(0.16, $result['Nutrition']['BloodDrugLevel']['PerMicroSCU']);
    }

    private function makeFood(string $contents): Food
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <EntityClassDefinition.TEST_FOOD __type="EntityClassDefinition" __ref="cccccccc-cccc-cccc-cccc-cccccccccccc" __path="libs/foundry/records/entities/scitem/consumables/test_food.xml">
                <Components>
                    <SAttachableComponentParams>
                        <AttachDef Type="Chemical" SubType="Food" Size="1" Grade="1" Manufacturer="11111111-1111-1111-1111-111111111111">
                            <Localization Name="@LOC_EMPTY" ShortName="@LOC_EMPTY" Description="@LOC_EMPTY" />
                        </AttachDef>
                    </SAttachableComponentParams>
                    <SCItemConsumableParams containerTypeTag="a1b2c3">
                        <consumableVolume>
                            <SMicroCargoUnit microSCU="30" />
                        </consumableVolume>
                        <defaultContents>{$contents}</defaultContents>
                    </SCItemConsumableParams>
                </Components>
            </EntityClassDefinition.TEST_FOOD>
        XML;

        $tmpFile = sys_get_temp_dir().'/food_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, trim($xml));

        try {
            $doc = new EntityClassDefinition;
            $doc->load($tmpFile);

            return new Food($doc);
        } finally {
            @unlink($tmpFile);
        }
    }
}
