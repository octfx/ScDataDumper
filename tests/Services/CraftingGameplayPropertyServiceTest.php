<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\CraftingGameplayPropertyDef;
use Octfx\ScDataDumper\Services\CraftingGameplayPropertyService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class CraftingGameplayPropertyServiceTest extends ScDataTestCase
{
    private const WEAPON_DAMAGE_UUID = 'cfc129ce-488a-46f2-92f7-9272cd0cfdfb';

    private const WEAPON_FIRERATE_UUID = '551b651c-8a34-438f-9d19-93fdffe56246';

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeCacheFiles();
        $this->writeCraftingGameplayPropertyCache([
            self::WEAPON_DAMAGE_UUID => '<CraftingGameplayPropertyDef.GPP_Weapon_Damage propertyName="Weapon Damage" unitFormat="Percent" __type="CraftingGameplayPropertyDef" __ref="cfc129ce-488a-46f2-92f7-9272cd0cfdfb" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_damage.xml" />',
            self::WEAPON_FIRERATE_UUID => '<CraftingGameplayPropertyDef.GPP_Weapon_FireRate propertyName="Weapon Fire Rate" unitFormat="RPM" __type="CraftingGameplayPropertyDef" __ref="551b651c-8a34-438f-9d19-93fdffe56246" __path="libs/foundry/records/crafting/craftedproperties/gpp_weapon_firerate.xml" />',
        ]);
    }

    public function test_get_by_reference_returns_gameplay_property_document_from_cache(): void
    {
        $service = new CraftingGameplayPropertyService($this->tempDir);
        $service->initialize();

        $property = $service->getByReference(self::WEAPON_DAMAGE_UUID);

        self::assertInstanceOf(CraftingGameplayPropertyDef::class, $property);
        self::assertSame(self::WEAPON_DAMAGE_UUID, $property?->getUuid());
        self::assertSame('GPP_Weapon_Damage', $property?->getClassName());
        self::assertSame('weapon_damage', $property?->getPropertyKey());
        self::assertFileExists($this->getCachePath());
    }

    public function test_initialize_fails_when_cache_is_missing(): void
    {
        unlink($this->getCachePath());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing crafting gameplay property cache');

        $service = new CraftingGameplayPropertyService($this->tempDir);
        $service->initialize();
    }

    private function getCachePath(): string
    {
        return sprintf(
            '%s%scrafting-gameplay-property-cache-%s.json',
            $this->tempDir,
            DIRECTORY_SEPARATOR,
            PHP_OS_FAMILY
        );
    }
}
