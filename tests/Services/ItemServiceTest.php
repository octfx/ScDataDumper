<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use RuntimeException;

final class ItemServiceTest extends ScDataTestCase
{
    public function test_get_paths_by_sub_type_returns_only_matching_entity_paths(): void
    {
        $mineablePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_mineable.xml',
            '<EntityClassDefinition.SampleMineable __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/mineable/sample_mineable.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" /></SAttachableComponentParams></Components></EntityClassDefinition.SampleMineable>'
        );

        $weaponPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/weapon/sample_weapon.xml',
            '<EntityClassDefinition.SampleWeapon __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000002" __path="libs/foundry/records/entities/weapon/sample_weapon.xml"><Components><SAttachableComponentParams><AttachDef Type="WeaponPersonal" SubType="Small" /></SAttachableComponentParams></Components></EntityClassDefinition.SampleWeapon>'
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleMineable' => $mineablePath,
                    'SampleWeapon' => $weaponPath,
                ],
            ],
            uuidToClassMap: [
                '10000000-0000-0000-0000-000000000001' => 'SampleMineable',
                '10000000-0000-0000-0000-000000000002' => 'SampleWeapon',
            ],
            classToUuidMap: [
                'SampleMineable' => '10000000-0000-0000-0000-000000000001',
                'SampleWeapon' => '10000000-0000-0000-0000-000000000002',
            ],
            uuidToPathMap: [
                '10000000-0000-0000-0000-000000000001' => $mineablePath,
                '10000000-0000-0000-0000-000000000002' => $weaponPath,
            ],
        );

        $this->writeFile(
            sprintf('entityMetadataMap-%s.json', PHP_OS_FAMILY),
            json_encode([
                'SampleMineable' => [
                    'uuid' => '10000000-0000-0000-0000-000000000001',
                    'path' => $mineablePath,
                    'type' => 'Misc',
                    'sub_type' => 'Mineable',
                ],
                'SampleWeapon' => [
                    'uuid' => '10000000-0000-0000-0000-000000000002',
                    'path' => $weaponPath,
                    'type' => 'WeaponPersonal',
                    'sub_type' => 'Small',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertSame([$mineablePath], $service->getPathsBySubType('Mineable'));
    }

    public function test_get_paths_by_sub_type_throws_when_entity_metadata_cache_is_missing(): void
    {
        $this->writeCacheFiles();
        unlink(sprintf('%s%sentityMetadataMap-%s.json', $this->tempDir, DIRECTORY_SEPARATOR, PHP_OS_FAMILY));

        $service = new ItemService($this->tempDir);
        $service->initialize();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entityMetadataMap');

        $service->getPathsBySubType('Mineable');
    }

    public function test_get_uuid_by_class_name_returns_uuid_when_found(): void
    {
        $mineablePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_mineable.xml',
            '<EntityClassDefinition.SampleMineable __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/mineable/sample_mineable.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" /></SAttachableComponentParams></Components></EntityClassDefinition.SampleMineable>'
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleMineable' => $mineablePath,
                ],
            ],
            uuidToClassMap: [
                '10000000-0000-0000-0000-000000000001' => 'SampleMineable',
            ],
            classToUuidMap: [
                'SampleMineable' => '10000000-0000-0000-0000-000000000001',
            ],
            uuidToPathMap: [
                '10000000-0000-0000-0000-000000000001' => $mineablePath,
            ],
        );

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertSame('10000000-0000-0000-0000-000000000001', $service->getUuidByClassName('SampleMineable'));
    }

    public function test_get_uuid_by_class_name_returns_null_for_null(): void
    {
        $this->writeCacheFiles();

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertNull($service->getUuidByClassName(null));
    }

    public function test_get_uuid_by_class_name_returns_null_for_unknown_class(): void
    {
        $this->writeCacheFiles();

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertNull($service->getUuidByClassName('NonExistentClass'));
    }

    public function test_count_returns_number_of_items(): void
    {
        $mineablePath = $this->writeFile(
            'Game2/libs/foundry/records/entities/mineable/sample_mineable.xml',
            '<EntityClassDefinition.SampleMineable __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/mineable/sample_mineable.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="Mineable" /></SAttachableComponentParams></Components></EntityClassDefinition.SampleMineable>'
        );
        $weaponPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/weapon/sample_weapon.xml',
            '<EntityClassDefinition.SampleWeapon __type="EntityClassDefinition" __ref="10000000-0000-0000-0000-000000000002" __path="libs/foundry/records/entities/weapon/sample_weapon.xml"><Components><SAttachableComponentParams><AttachDef Type="WeaponPersonal" SubType="Small" /></SAttachableComponentParams></Components></EntityClassDefinition.SampleWeapon>'
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'SampleMineable' => $mineablePath,
                    'SampleWeapon' => $weaponPath,
                ],
            ],
            uuidToClassMap: [
                '10000000-0000-0000-0000-000000000001' => 'SampleMineable',
                '10000000-0000-0000-0000-000000000002' => 'SampleWeapon',
            ],
            classToUuidMap: [
                'SampleMineable' => '10000000-0000-0000-0000-000000000001',
                'SampleWeapon' => '10000000-0000-0000-0000-000000000002',
            ],
            uuidToPathMap: [
                '10000000-0000-0000-0000-000000000001' => $mineablePath,
                '10000000-0000-0000-0000-000000000002' => $weaponPath,
            ],
        );

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertSame(2, $service->count());
    }

    public function test_get_by_class_name_returns_entity_when_found(): void
    {
        $entityPath = $this->writeFile(
            'Game2/libs/foundry/records/entities/test/test_entity.xml',
            '<EntityClassDefinition.TestEntity __type="EntityClassDefinition" __ref="30000000-0000-0000-0000-000000000001" __path="libs/foundry/records/entities/test/test_entity.xml"><Components><SAttachableComponentParams><AttachDef Type="Misc" SubType="UNDEFINED" /></SAttachableComponentParams></Components></EntityClassDefinition.TestEntity>'
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'EntityClassDefinition' => [
                    'TestEntity' => $entityPath,
                ],
            ],
            uuidToClassMap: [
                '30000000-0000-0000-0000-000000000001' => 'TestEntity',
            ],
            classToUuidMap: [
                'TestEntity' => '30000000-0000-0000-0000-000000000001',
            ],
            uuidToPathMap: [
                '30000000-0000-0000-0000-000000000001' => $entityPath,
            ],
        );

        $service = new ItemService($this->tempDir);
        $service->initialize();

        $result = $service->getByClassName('TestEntity');
        self::assertInstanceOf(EntityClassDefinition::class, $result);
    }

    public function test_get_by_class_name_returns_null_for_unknown(): void
    {
        $this->writeCacheFiles();

        $service = new ItemService($this->tempDir);
        $service->initialize();

        self::assertNull($service->getByClassName('NonExistent'));
    }
}
