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
