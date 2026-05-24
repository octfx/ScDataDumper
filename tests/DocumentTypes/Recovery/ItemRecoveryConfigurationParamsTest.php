<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Recovery;

use Octfx\ScDataDumper\DocumentTypes\Recovery\ItemRecoveryConfigurationParams;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ItemRecoveryConfigurationParamsTest extends ScDataTestCase
{
    private ItemRecoveryConfigurationParams $document;

    protected function setUp(): void
    {
        parent::setUp();

        $path = $this->writeFile(
            'Game2/libs/foundry/records/entitlementpolicies/globalitemrecovery.xml',
            <<<'XML'
            <ItemRecoveryConfigurationParams.GlobalItemRecovery __type="ItemRecoveryConfigurationParams" __ref="c4ac0f5a-c072-4666-b3a0-5aef0cf3ff65" __path="libs/foundry/records/entitlementpolicies/globalitemrecovery.xml" __team="Unknown">
              <Insurable>
                <ItemRecoveryCondition_ItemType type="WeaponGun" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="Shield" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="QuantumDrive" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="Cooler" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="MissileLauncher" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="BombLauncher" subType="BombRack" />
              </Insurable>
              <ReplenishOnRearm>
                <ItemRecoveryCondition_ItemType type="Missile" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="Bomb" subType="UNDEFINED" />
              </ReplenishOnRearm>
              <ReplenishOnClaimAndRepair>
                <ItemRecoveryCondition_ItemType type="UNDEFINED" subType="Fuse" />
              </ReplenishOnClaimAndRepair>
              <NeverRepair>
                <ItemRecoveryCondition_ItemType type="NOITEM_Vehicle" subType="UNDEFINED" />
              </NeverRepair>
              <NeverLTP>
                <ItemRecoveryCondition_ItemType type="Missile" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="Bomb" subType="UNDEFINED" />
              </NeverLTP>
              <ForceLTP>
                <ItemRecoveryCondition_ItemType type="Container" subType="Personal" />
                <ItemRecoveryCondition_ItemType type="Container" subType="Box" />
                <ItemRecoveryCondition_ItemType type="Char_Armor_Helmet" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="WeaponAttachment" subType="Barrel" />
              </ForceLTP>
              <DontEquipWhenBricked>
                <ItemRecoveryCondition_ItemType type="Char_Armor_Helmet" subType="UNDEFINED" />
                <ItemRecoveryCondition_ItemType type="Char_Clothing_Torso_0" subType="UNDEFINED" />
              </DontEquipWhenBricked>
              <economyParams globalBrickTimer="10" baseReclaimTime="0" scalingPriceFloor="0" aUECperSecond="1E-06" defaultLoadoutCooldownMultiplier="200" claimCostMultiplier="0.003">
                <cooldownOverrides>
                  <ItemRecoveryOverrideGroupDef multiplier="1">
                    <classes>
                      <Reference value="aaaaaaaa-1111-1111-1111-111111111111" />
                    </classes>
                  </ItemRecoveryOverrideGroupDef>
                  <ItemRecoveryOverrideGroupDef multiplier="0.6">
                    <classes>
                      <Reference value="bbbbbbbb-2222-2222-2222-222222222222" />
                      <Reference value="cccccccc-3333-3333-3333-333333333333" />
                    </classes>
                  </ItemRecoveryOverrideGroupDef>
                </cooldownOverrides>
                <costOverrides>
                  <ItemRecoveryOverrideGroupDef multiplier="0.8">
                    <classes>
                      <Reference value="dddddddd-4444-4444-4444-444444444444" />
                    </classes>
                  </ItemRecoveryOverrideGroupDef>
                </costOverrides>
              </economyParams>
            </ItemRecoveryConfigurationParams.GlobalItemRecovery>
            XML
        );

        $this->document = new ItemRecoveryConfigurationParams;
        $this->document->load($path);
    }

    // --- Insurable ---

    public function test_insurable_type_with_undefined_subtype(): void
    {
        self::assertTrue($this->document->isInsurable('WeaponGun'));
    }

    public function test_insurable_type_with_specific_subtype(): void
    {
        self::assertTrue($this->document->isInsurable('WeaponGun', 'Gun'));
    }

    public function test_insurable_type_with_explicit_subtype(): void
    {
        self::assertTrue($this->document->isInsurable('BombLauncher', 'BombRack'));
    }

    public function test_insurable_type_with_wrong_subtype(): void
    {
        self::assertFalse($this->document->isInsurable('BombLauncher', 'SomeOtherSubType'));
    }

    public function test_non_insurable_type(): void
    {
        self::assertFalse($this->document->isInsurable('Missile'));
    }

    public function test_non_insurable_unknown_type(): void
    {
        self::assertFalse($this->document->isInsurable('NonExistentType'));
    }

    // --- ReplenishOnRearm ---

    public function test_replenish_on_rearm_missile(): void
    {
        self::assertTrue($this->document->isReplenishOnRearm('Missile'));
    }

    public function test_replenish_on_rearm_bomb(): void
    {
        self::assertTrue($this->document->isReplenishOnRearm('Bomb'));
    }

    public function test_weapon_gun_not_replenish_on_rearm(): void
    {
        self::assertFalse($this->document->isReplenishOnRearm('WeaponGun'));
    }

    // --- ReplenishOnClaimAndRepair ---

    public function test_replenish_on_claim_andrepair_by_subtype(): void
    {
        self::assertTrue($this->document->isReplenishOnClaimAndRepair('UNDEFINED', 'Fuse'));
    }

    public function test_replenish_on_claim_andrepair_type_without_subtype_no_match(): void
    {
        self::assertFalse($this->document->isReplenishOnClaimAndRepair('UNDEFINED'));
    }

    // --- NeverLTP ---

    public function test_never_ltp_missile(): void
    {
        self::assertTrue($this->document->isNeverLTP('Missile'));
    }

    public function test_weapon_gun_not_never_ltp(): void
    {
        self::assertFalse($this->document->isNeverLTP('WeaponGun'));
    }

    // --- ForceLTP ---

    public function test_force_ltp_with_specific_subtype(): void
    {
        self::assertTrue($this->document->isForceLTP('Container', 'Personal'));
    }

    public function test_force_ltp_with_different_subtype(): void
    {
        self::assertFalse($this->document->isForceLTP('Container', 'Cargo'));
    }

    public function test_force_ltp_with_undefined_subtype_no_match(): void
    {
        // Container has ForceLTP entries for Personal and Box, not UNDEFINED
        self::assertFalse($this->document->isForceLTP('Container'));
    }

    public function test_force_ltp_armor_helmet(): void
    {
        self::assertTrue($this->document->isForceLTP('Char_Armor_Helmet'));
    }

    public function test_force_ltp_weapon_attachment_barrel(): void
    {
        self::assertTrue($this->document->isForceLTP('WeaponAttachment', 'Barrel'));
    }

    // --- DontEquipWhenBricked ---

    public function test_dont_equip_when_bricked_armor_helmet(): void
    {
        self::assertTrue($this->document->isDontEquipWhenBricked('Char_Armor_Helmet'));
    }

    public function test_dont_equip_when_bricked_clothing_torso(): void
    {
        self::assertTrue($this->document->isDontEquipWhenBricked('Char_Clothing_Torso_0'));
    }

    public function test_weapon_gun_not_dont_equip_when_bricked(): void
    {
        self::assertFalse($this->document->isDontEquipWhenBricked('WeaponGun'));
    }

    // --- Economy Params ---

    public function test_economy_params_parsed_correctly(): void
    {
        $params = $this->document->getEconomyParams();

        self::assertSame(10, $params['globalBrickTimer']);
        self::assertEquals(1E-6, $params['aUECperSecond']);
        self::assertSame(0.003, $params['claimCostMultiplier']);
        self::assertSame(200.0, $params['defaultLoadoutCooldownMultiplier']);
    }

    public function test_global_brick_timer(): void
    {
        self::assertSame(10, $this->document->getGlobalBrickTimer());
    }

    // --- Cooldown Multiplier ---

    public function test_cooldown_multiplier_for_known_uuid(): void
    {
        self::assertSame(0.6, $this->document->getCooldownMultiplierForClass('bbbbbbbb-2222-2222-2222-222222222222'));
    }

    public function test_cooldown_multiplier_for_1x_uuid(): void
    {
        self::assertSame(1.0, $this->document->getCooldownMultiplierForClass('aaaaaaaa-1111-1111-1111-111111111111'));
    }

    public function test_cooldown_multiplier_for_unknown_uuid(): void
    {
        self::assertNull($this->document->getCooldownMultiplierForClass('00000000-0000-0000-0000-000000000000'));
    }

    // --- Cost Multiplier ---

    public function test_cost_multiplier_for_known_uuid(): void
    {
        self::assertSame(0.8, $this->document->getCostMultiplierForClass('dddddddd-4444-4444-4444-444444444444'));
    }

    public function test_cost_multiplier_for_unknown_uuid(): void
    {
        self::assertNull($this->document->getCostMultiplierForClass('00000000-0000-0000-0000-000000000000'));
    }

    // --- Override group counts ---

    public function test_cooldown_override_group_count(): void
    {
        self::assertCount(2, $this->document->getCooldownOverrides());
    }

    public function test_cost_override_group_count(): void
    {
        self::assertCount(1, $this->document->getCostOverrides());
    }

    // --- Category entries ---

    public function test_get_category_entries_insurable(): void
    {
        $entries = $this->document->getCategoryEntries('Insurable');

        self::assertCount(6, $entries);
        self::assertSame('WeaponGun', $entries[0]['type']);
        self::assertSame('UNDEFINED', $entries[0]['subType']);
    }

    public function test_get_category_entries_unknown_category(): void
    {
        $entries = $this->document->getCategoryEntries('NonExistent');

        self::assertSame([], $entries);
    }

    // --- Generic matchesCategory ---

    public function test_matches_category_with_undefined_subtype_matches_any(): void
    {
        // WeaponGun is Insurable with UNDEFINED subType
        self::assertTrue($this->document->matchesCategory('Insurable', 'WeaponGun', 'Gun'));
        self::assertTrue($this->document->matchesCategory('Insurable', 'WeaponGun', 'Ballistic'));
        self::assertTrue($this->document->matchesCategory('Insurable', 'WeaponGun'));
    }

    public function test_matches_category_with_specific_subtype_only_matches_exact(): void
    {
        // BombLauncher/BombRack is Insurable
        self::assertTrue($this->document->matchesCategory('Insurable', 'BombLauncher', 'BombRack'));
        self::assertFalse($this->document->matchesCategory('Insurable', 'BombLauncher', 'Other'));
        self::assertFalse($this->document->matchesCategory('Insurable', 'BombLauncher'));
    }
}
