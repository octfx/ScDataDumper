<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Crafting;

use Octfx\ScDataDumper\DocumentTypes\Crafting\CraftingGameplayPropertyDef;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class CraftingGameplayPropertyDefTest extends ScDataTestCase
{
    public function test_get_unit_format_returns_real_format(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        self::assertSame('Percent', $def->getUnitFormat());
    }

    public function test_get_unit_format_returns_null_for_loc_empty(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Health_MaxHealth propertyName="Health" unitFormat="@LOC_EMPTY" __type="CraftingGameplayPropertyDef" __ref="93a49e68-01cf-4d7e-b995-8224466a9982" __path="libs/foundry/records/crafting/craftedproperties/gpp_health_maxhealth.xml" />
        XML);

        self::assertNull($def->getUnitFormat());
    }

    public function test_get_unit_format_returns_null_when_absent(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Health_MaxHealth propertyName="Health" __type="CraftingGameplayPropertyDef" __ref="93a49e68-01cf-4d7e-b995-8224466a9982" __path="libs/foundry/records/crafting/craftedproperties/gpp_health_maxhealth.xml" />
        XML);

        self::assertNull($def->getUnitFormat());
    }

    public function test_get_display_scale_returns_float(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Quantum_Speed propertyName="Quantum Speed" unitFormat="@StatUnit_MmPerSec" __type="CraftingGameplayPropertyDef" __ref="7ab22dc9-6687-41ab-aebd-4dd5486be458" __path="libs/foundry/records/crafting/craftedproperties/gpp_quantum_speed.xml">
          <displayTransformation>
            <CraftingDisplayTransformation_Scale scale="1E-06" />
          </displayTransformation>
        </CraftingGameplayPropertyDef.GPP_Quantum_Speed>
        XML);

        self::assertSame(1.0E-6, $def->getDisplayScale());
    }

    public function test_get_display_scale_returns_large_value(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Quantum_FuelRequirement propertyName="Fuel" unitFormat="@StatUnits_PerKm" __type="CraftingGameplayPropertyDef" __ref="d53a3c58-3334-4cba-9197-c4e9d4f6c3ad" __path="libs/foundry/records/crafting/craftedproperties/gpp_quantum_fuelrequirement.xml">
          <displayTransformation>
            <CraftingDisplayTransformation_Scale scale="1000" />
          </displayTransformation>
        </CraftingGameplayPropertyDef.GPP_Quantum_FuelRequirement>
        XML);

        self::assertSame(1000.0, $def->getDisplayScale());
    }

    public function test_get_display_scale_returns_null_when_absent(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        self::assertNull($def->getDisplayScale());
    }

    public function test_get_name_overrides_returns_structured_data(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="@StatName_GPP_Weapon_Damage" unitFormat="@StatUnits_Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml">
          <nameOverrides>
            <CraftingPropertyNameOverride propertyName="@StatName_GPP_Weapon_Damage_Override_Laser">
              <condition>
                <CraftingPropertyNameOverrideCondition_ItemType>
                  <matchItemTypes>
                    <Enum value="WeaponMining" />
                  </matchItemTypes>
                </CraftingPropertyNameOverrideCondition_ItemType>
              </condition>
            </CraftingPropertyNameOverride>
          </nameOverrides>
        </CraftingGameplayPropertyDef.GPP_Weapon_Damage>
        XML);

        $overrides = $def->getNameOverrides();

        self::assertNotNull($overrides);
        self::assertCount(1, $overrides);
        self::assertSame('@StatName_GPP_Weapon_Damage_Override_Laser', $overrides[0]['property_name']);
        self::assertSame(['WeaponMining'], $overrides[0]['match_item_types']);
    }

    public function test_get_name_overrides_returns_null_when_absent(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_FireRate propertyName="Weapon Fire Rate" unitFormat="RPM" __type="CraftingGameplayPropertyDef" __ref="551b651c-8a34-438f-9d19-93fdffe56246" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_firerate.xml" />
        XML);

        self::assertNull($def->getNameOverrides());
    }

    public function test_get_property_name_returns_localized_name(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices([
            'StatName_GPP_Weapon_Damage,P' => 'Impact Force',
        ]);

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="@StatName_GPP_Weapon_Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        self::assertSame('Impact Force', $def->getPropertyName());
    }

    public function test_get_property_name_returns_null_when_absent(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        self::assertNull($def->getPropertyName());
    }

    public function test_get_property_name_returns_plain_text_as_is(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices();

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        // Plain text (no @ prefix) returned as-is
        self::assertSame('Weapon Damage', $def->getPropertyName());
    }

    public function test_get_resolved_property_name_returns_base_name_without_match(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices([
            'StatName_GPP_Weapon_Damage,P' => 'Impact Force',
        ]);

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="@StatName_GPP_Weapon_Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml">
          <nameOverrides>
            <CraftingPropertyNameOverride propertyName="@StatName_GPP_Weapon_Damage_Override_Laser">
              <condition>
                <CraftingPropertyNameOverrideCondition_ItemType>
                  <matchItemTypes>
                    <Enum value="WeaponMining" />
                  </matchItemTypes>
                </CraftingPropertyNameOverrideCondition_ItemType>
              </condition>
            </CraftingPropertyNameOverride>
          </nameOverrides>
        </CraftingGameplayPropertyDef.GPP_Weapon_Damage>
        XML);

        // No item type match -> base name
        self::assertSame('Impact Force', $def->getResolvedPropertyName('Weapon'));
    }

    public function test_get_resolved_property_name_applies_override_on_match(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices([
            'StatName_GPP_Weapon_Damage,P' => 'Impact Force',
            'StatName_GPP_Weapon_Damage_Override_Laser,P' => 'Laser Power',
        ]);

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="@StatName_GPP_Weapon_Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml">
          <nameOverrides>
            <CraftingPropertyNameOverride propertyName="@StatName_GPP_Weapon_Damage_Override_Laser">
              <condition>
                <CraftingPropertyNameOverrideCondition_ItemType>
                  <matchItemTypes>
                    <Enum value="WeaponMining" />
                  </matchItemTypes>
                </CraftingPropertyNameOverrideCondition_ItemType>
              </condition>
            </CraftingPropertyNameOverride>
          </nameOverrides>
        </CraftingGameplayPropertyDef.GPP_Weapon_Damage>
        XML);

        // WeaponMining matches override
        self::assertSame('Laser Power', $def->getResolvedPropertyName('WeaponMining'));
    }

    public function test_get_resolved_property_name_returns_base_when_no_overrides(): void
    {
        $this->writeCacheFiles();
        $this->initializeMinimalItemServices([
            'StatName_GPP_Health_MaxHealth,P' => 'Integrity',
        ]);

        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Health_MaxHealth propertyName="@StatName_GPP_Health_MaxHealth" unitFormat="@LOC_EMPTY" __type="CraftingGameplayPropertyDef" __ref="93a49e68-01cf-4d7e-b995-8224466a9982" __path="libs/foundry/records/crafting/craftedproperties/gpp_health_maxhealth.xml" />
        XML);

        // No overrides, no item type -> base name
        self::assertSame('Integrity', $def->getResolvedPropertyName(null));
    }

    public function test_get_normalized_property_key_strips_gpp_prefix(): void
    {
        $def = $this->createDef(<<<'XML'
        <CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />
        XML);

        self::assertSame('weapon_damage', $def->getNormalizedPropertyKey());
    }

    private function createDef(string $xml): CraftingGameplayPropertyDef
    {
        $doc = new CraftingGameplayPropertyDef;
        $doc->loadXML($xml);

        return $doc;
    }
}
