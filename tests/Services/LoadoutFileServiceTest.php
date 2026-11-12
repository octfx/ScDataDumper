<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\LoadoutFileService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class LoadoutFileServiceTest extends ScDataTestCase
{
    public function test_load_accepts_bare_loadout_root_and_returns_entries(): void
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout.xml',
            <<<'XML'
            <Loadout>
                <Items>
                    <item portName="BedPort" itemName="BED_DEFAULT" />
                </Items>
            </Loadout>
            XML
        );

        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        $loadout = $service->getByLoadoutPath('Scripts/Loadouts/test_loadout.xml');

        self::assertNotNull($loadout);
        self::assertCount(1, $loadout->getEntries());
        self::assertSame('BedPort', $loadout->getEntries()[0]->getPortName());
        self::assertSame('BED_DEFAULT', $loadout->getEntries()[0]->getEntityClassName());
    }

    public function test_load_accepts_empty_loadout_root_and_returns_no_entries(): void
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout_empty.xml',
            '<Loadout />'
        );

        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        $loadout = $service->getByLoadoutPath('Scripts/Loadouts/test_loadout_empty.xml');

        self::assertNotNull($loadout);
        self::assertSame([], $loadout->getEntries());
    }

    public function test_load_parses_capital_item_entries_with_nested_items(): void
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout_capital_item.xml',
            <<<'XML'
            <Loadout>
              <Items>
                <Item portName="Body_ItemPort" itemName="m_body_01" tag="Char_Body">
                  <Items>
                    <Item portName="Armor_Undersuit" itemName="cds_undersuit_01_01_01" tag="Char_Armor_Undersuit(Male)" />
                  </Items>
                </Item>
              </Items>
            </Loadout>
            XML
        );

        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        $loadout = $service->getByLoadoutPath('Scripts/Loadouts/test_loadout_capital_item.xml');

        self::assertNotNull($loadout);
        $entries = $loadout->getEntries();
        self::assertCount(1, $entries);
        self::assertSame('Body_ItemPort', $entries[0]->getPortName());
        self::assertSame('m_body_01', $entries[0]->getEntityClassName());

        $nested = $entries[0]->getNestedEntries();
        self::assertCount(1, $nested);
        self::assertSame('Armor_Undersuit', $nested[0]->getPortName());
        self::assertSame('cds_undersuit_01_01_01', $nested[0]->getEntityClassName());
    }

    public function test_get_by_loadout_path_resolves_case_insensitive_references(): void
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout_case.xml',
            '<Loadout><Items><Item portName="BedPort" itemName="BED_DEFAULT" /></Items></Loadout>'
        );

        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        // Entities reference the same file with lowercase "scripts/loadouts/...".
        self::assertNotNull($service->getByLoadoutPath('scripts/loadouts/test_loadout_case.xml'));
        self::assertNotNull($service->getByLoadoutPath('SCRIPTS/LOADOUTS/TEST_LOADOUT_CASE.XML'));
        self::assertNull($service->getByLoadoutPath('scripts/loadouts/does_not_exist.xml'));
    }

    public function test_load_accepts_nested_loadout_under_character_customization_root(): void
    {
        $this->writeCacheFiles();
        $this->writeFile(
            'Data/Scripts/Loadouts/test_loadout_character_customization.xml',
            <<<'XML'
            <CharacterCustomization modelTag="Female">
                <Loadout>
                    <Items>
                        <item portName="Head_ItemPort" itemName="PU_Protos_Head" />
                    </Items>
                </Loadout>
            </CharacterCustomization>
            XML
        );

        $service = new LoadoutFileService($this->tempDir);
        $service->initialize();

        $loadout = $service->getByLoadoutPath('Scripts/Loadouts/test_loadout_character_customization.xml');

        self::assertNotNull($loadout);
        self::assertCount(1, $loadout->getEntries());
        self::assertSame('Head_ItemPort', $loadout->getEntries()[0]->getPortName());
        self::assertSame('PU_Protos_Head', $loadout->getEntries()[0]->getEntityClassName());
    }
}
