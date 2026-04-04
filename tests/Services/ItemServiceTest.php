<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

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
}
