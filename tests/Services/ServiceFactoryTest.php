<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use JsonException;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class ServiceFactoryTest extends ScDataTestCase
{
    private const ASTATINE_UUID = '11111111-1111-1111-1111-111111111111';

    private const BEXALITE_UUID = '22222222-2222-2222-2222-222222222222';

    private string $dumpA;

    private string $dumpB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpA = $this->tempDir.DIRECTORY_SEPARATOR.'dump-a';
        $this->dumpB = $this->tempDir.DIRECTORY_SEPARATOR.'dump-b';

        $this->buildDump($this->dumpA, 'Astatine', self::ASTATINE_UUID);
        $this->buildDump($this->dumpB, 'Bexalite', self::BEXALITE_UUID);
    }

    /**
     * BaseService only loads its static maps when they are empty, so swapping
     * the data dir must clear them or every lookup keeps reading the old dump.
     *
     * @throws JsonException
     */
    public function test_reinitializing_with_a_different_sc_data_path_reloads_shared_static_maps(): void
    {
        (new ServiceFactory($this->dumpA))->initialize();

        self::assertSame(['Astatine'], $this->resourceTypeClassNames());
        self::assertSame(
            'Astatine',
            ServiceFactory::getFoundryLookupService()->getResourceTypeByReference(self::ASTATINE_UUID)?->getClassName()
        );

        (new ServiceFactory($this->dumpB))->initialize();

        self::assertSame(['Bexalite'], $this->resourceTypeClassNames());
        self::assertSame(
            'Bexalite',
            ServiceFactory::getFoundryLookupService()->getResourceTypeByReference(self::BEXALITE_UUID)?->getClassName()
        );
    }

    /**
     * @throws JsonException
     */
    public function test_reinitializing_with_the_same_sc_data_path_keeps_loaded_maps(): void
    {
        (new ServiceFactory($this->dumpA))->initialize();
        self::assertSame(['Astatine'], $this->resourceTypeClassNames());

        (new ServiceFactory($this->dumpA))->initialize();
        self::assertSame(['Astatine'], $this->resourceTypeClassNames());
    }

    /**
     * @return list<string>
     */
    private function resourceTypeClassNames(): array
    {
        $names = [];
        foreach (ServiceFactory::getFoundryLookupService()->getResourceTypes() as $resourceType) {
            $names[] = $resourceType->getClassName();
        }

        return $names;
    }

    /**
     * Build a minimal dump: one ResourceType record plus the five cache maps
     * BaseService requires, using the same shapes as writeResourceTypeCache.
     *
     * @throws JsonException
     */
    private function buildDump(string $dir, string $className, string $uuid): void
    {
        $xmlPath = $this->writeFile(
            sprintf('%s/Game2/libs/foundry/records/resourcetypedatabase/%s.xml', basename($dir), strtolower($className)),
            sprintf(
                '<ResourceType.%1$s displayName="@items_commodities_%1$s" __type="ResourceType" __ref="%2$s" __path="libs/foundry/records/resourcetypedatabase/%3$s.xml" />',
                $className,
                $uuid,
                strtolower($className)
            )
        );

        $maps = [
            'classToTypeMap' => ['ResourceType' => 'ResourceType'],
            'classToPathMap' => ['ResourceType' => [strtolower($className) => $xmlPath]],
            'uuidToClassMap' => [$uuid => $className],
            'uuidToPathMap' => [$uuid => $xmlPath],
            'classToUuidMap' => [$className => $uuid],
        ];

        foreach ($maps as $name => $payload) {
            file_put_contents(
                sprintf('%s%s%s-%s.json', $dir, DIRECTORY_SEPARATOR, $name, PHP_OS_FAMILY),
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
        }
    }
}
