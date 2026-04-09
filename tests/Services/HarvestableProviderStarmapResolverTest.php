<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\Services\Resource\HarvestableProviderStarmapResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class HarvestableProviderStarmapResolverTest extends ScDataTestCase
{
    private const STANTON1_TAG_UUID = '10000000-0000-0000-0000-000000000001';

    private const STANTON1_STARMAP_UUID = '10000000-0000-0000-0000-000000000002';

    private const STANTON1_PROVIDER_UUID = '10000000-0000-0000-0000-000000000003';

    private const GLACIEM_STARMAP_UUID = '10000000-0000-0000-0000-000000000004';

    private const GLACIEM_PROVIDER_UUID = '10000000-0000-0000-0000-000000000005';

    private const AKIRO_STARMAP_UUID = '10000000-0000-0000-0000-000000000006';

    private const AKIRO_PROVIDER_UUID = '10000000-0000-0000-0000-000000000007';

    private const CLUSTER_PROVIDER_UUID = '10000000-0000-0000-0000-000000000008';

    private const STANTON2C_STARMAP_UUID = '10000000-0000-0000-0000-000000000009';

    private const STANTON2C_PROVIDER_UUID = '10000000-0000-0000-0000-000000000010';

    private const STANTON1_L1_STARMAP_UUID = '10000000-0000-0000-0000-000000000020';

    private const STANTON1_L4_STARMAP_UUID = '10000000-0000-0000-0000-000000000021';

    public function test_resolves_body_provider_to_location_hierarchy_tag(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Stanton1',
            uuid: self::STANTON1_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton1.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('Stanton1', $result['starmapKey']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('Stanton1', $result['locationName']);
        self::assertSame('unknown', $result['locationType']);
        self::assertSame(self::STANTON1_STARMAP_UUID, $result['starmapObjectUuid']);
        self::assertSame(self::STANTON1_TAG_UUID, $result['starmapLocationHierarchyTagUuid']);
        self::assertSame('Stanton1', $result['starmapLocationHierarchyTagName']);
        self::assertSame('tag', $result['matchStrategy']);
        self::assertCount(1, $result['locations']);
        self::assertSame('Stanton1', $result['locations'][0]['className']);
        self::assertSame(self::STANTON1_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
    }

    public function test_resolves_asteroid_provider_to_starmap_object_uuid(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Nyx_GlaciemRing',
            uuid: self::GLACIEM_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/nyx/asteroidfield/hpp_nyx_glaciemring.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('GlaciemRing', $result['starmapKey']);
        self::assertSame('Nyx', $result['systemKey']);
        self::assertSame('Glaciem Ring', $result['locationName']);
        self::assertSame('belt', $result['locationType']);
        self::assertSame(self::GLACIEM_STARMAP_UUID, $result['starmapObjectUuid']);
        self::assertNull($result['starmapLocationHierarchyTagUuid']);
        self::assertNull($result['starmapLocationHierarchyTagName']);
        self::assertCount(1, $result['locations']);
        self::assertSame(self::GLACIEM_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
    }

    public function test_resolves_provider_by_display_name_when_class_name_differs(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Pyro_AkiroCluster',
            uuid: self::AKIRO_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/pyro/asteroidfield/hpp_pyro_akirocluster.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('AkiroCluster', $result['starmapKey']);
        self::assertSame('Pyro', $result['systemKey']);
        self::assertSame('Akiro Cluster', $result['locationName']);
        self::assertSame(self::AKIRO_STARMAP_UUID, $result['starmapObjectUuid']);
        self::assertSame('display_name', $result['matchStrategy']);
        self::assertCount(1, $result['locations']);
        self::assertSame(self::AKIRO_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
    }

    public function test_resolves_cluster_provider_metadata_without_starmap_match(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'AsteroidCluster_Low_Yield',
            uuid: self::CLUSTER_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidfield/asteroidcluster_low_yield.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('AsteroidClusterLowYield', $result['starmapKey']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('Asteroid Cluster (Low Yield)', $result['locationName']);
        self::assertSame('cluster', $result['locationType']);
        self::assertNull($result['starmapObjectUuid']);
        self::assertSame('none', $result['matchStrategy']);
        self::assertSame([], $result['locations']);
    }

    public function test_resolves_belt_provider_via_asteroid_ring_index(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Stanton2c_Belt',
            uuid: self::STANTON2C_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidfield/hpp_stanton2c_belt.xml',
            stanton2cFixture: true,
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('Stanton2cBelt', $result['starmapKey']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('Yela Asteroid Belt', $result['locationName']);
        self::assertSame('belt', $result['locationType']);
        self::assertSame(self::STANTON2C_STARMAP_UUID, $result['starmapObjectUuid']);
        self::assertSame('asteroid_ring', $result['matchStrategy']);
        self::assertCount(1, $result['locations']);
        self::assertSame(self::STANTON2C_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
    }

    public function test_infers_lagrange_type_from_class_name(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Lagrange_A',
            uuid: '10000000-0000-0000-0000-000000000011',
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidfield/hpp_lagrange_a.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('LagrangeA', $result['starmapKey']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('lagrange', $result['locationType']);
        self::assertSame('none', $result['matchStrategy']);
    }

    public function test_resolves_socpak_mapped_hpp_to_multiple_starmap_locations(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Lagrange_A',
            uuid: '10000000-0000-0000-0000-000000000011',
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidfield/hpp_lagrange_a.xml',
            socpakMapping: [
                '10000000-0000-0000-0000-000000000011' => ['Stanton1_L1', 'Stanton1_L4'],
            ],
            extraStarmapFixtures: [
                [
                    'className' => 'Stanton1_L1',
                    'uuid' => self::STANTON1_L1_STARMAP_UUID,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1_l1.xml',
                ],
                [
                    'className' => 'Stanton1_L4',
                    'uuid' => self::STANTON1_L4_STARMAP_UUID,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1_l4.xml',
                ],
            ],
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('socpak', $result['matchStrategy']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertCount(2, $result['locations']);

        self::assertSame('Stanton1_L1', $result['locations'][0]['className']);
        self::assertSame(self::STANTON1_L1_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
        self::assertSame('Stanton1_L4', $result['locations'][1]['className']);
        self::assertSame(self::STANTON1_L4_STARMAP_UUID, $result['locations'][1]['starmapObjectUuid']);
    }

    public function test_resolves_cluster_provider_via_socpak_mapping(): void
    {
        $lowYieldUuid = 'd69da463-0000-0000-0000-000000000001';
        $stanton1L1SocpakUuid = '10000000-0000-0000-0000-000000000020';
        $stanton1L2SocpakUuid = '10000000-0000-0000-0000-000000000030';

        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'AsteroidCluster_Low_Yield',
            uuid: $lowYieldUuid,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidfield/asteroidcluster_low_yield.xml',
            socpakMapping: [
                $lowYieldUuid => ['Stanton1_L1', 'Stanton1_L2'],
            ],
            extraStarmapFixtures: [
                [
                    'className' => 'Stanton1_L1',
                    'uuid' => $stanton1L1SocpakUuid,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1_l1.xml',
                ],
                [
                    'className' => 'Stanton1_L2',
                    'uuid' => $stanton1L2SocpakUuid,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1_l2.xml',
                ],
            ],
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('socpak', $result['matchStrategy']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('Asteroid Cluster (Low Yield)', $result['locationName']);
        self::assertSame('cluster', $result['locationType']);
        self::assertCount(2, $result['locations']);
        self::assertSame('Stanton1_L1', $result['locations'][0]['className']);
        self::assertSame('Stanton1_L2', $result['locations'][1]['className']);
    }

    public function test_resolves_ship_graveyard_via_socpak_mapping(): void
    {
        $graveyardUuid = 'adbddd5e-0000-0000-0000-000000000001';
        $aberdeenSocpakUuid = '10000000-0000-0000-0000-000000000040';
        $magdaSocpakUuid = '10000000-0000-0000-0000-000000000050';

        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_ShipGraveyard_001',
            uuid: $graveyardUuid,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_shipgraveyard_001.xml',
            socpakMapping: [
                $graveyardUuid => ['Stanton1b', 'Stanton1c'],
            ],
            extraStarmapFixtures: [
                [
                    'className' => 'Stanton1b',
                    'uuid' => $aberdeenSocpakUuid,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1b/starmapobject.stanton1b.xml',
                ],
                [
                    'className' => 'Stanton1c',
                    'uuid' => $magdaSocpakUuid,
                    'path' => 'libs/foundry/records/starmap/pu/system/stanton/stanton1c/starmapobject.stanton1c.xml',
                ],
            ],
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('socpak', $result['matchStrategy']);
        self::assertSame('Stanton', $result['systemKey']);
        self::assertSame('Ship Graveyard', $result['locationName']);
        self::assertCount(2, $result['locations']);
        self::assertSame('Stanton1b', $result['locations'][0]['className']);
        self::assertSame('Stanton1c', $result['locations'][1]['className']);
    }

    public function test_falls_back_to_tag_match_without_socpak_mapping(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Stanton1',
            uuid: self::STANTON1_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton1.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $result = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('tag', $result['matchStrategy']);
        self::assertCount(1, $result['locations']);
        self::assertSame(self::STANTON1_STARMAP_UUID, $result['locations'][0]['starmapObjectUuid']);
    }

    /**
     * @param  list<array{className: string, uuid: string, path: string}>  $extraStarmapFixtures
     * @param  array<string, list<string>>|null  $socpakMapping
     */
    private function bootstrapResolverFixturesAndLoadProvider(
        string $className,
        string $uuid,
        string $path,
        bool $stanton2cFixture = false,
        ?array $socpakMapping = null,
        array $extraStarmapFixtures = [],
    ): HarvestableProviderPreset {
        $stantonPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/system/stanton/stanton1.xml',
            sprintf(
                '<StarMapObject.Stanton1 name="@LOC_UNINITIALIZED" description="" locationHierarchyTag="%1$s" __type="StarMapObject" __ref="%2$s" __path="libs/foundry/records/starmap/pu/system/stanton/stanton1.xml" />',
                self::STANTON1_TAG_UUID,
                self::STANTON1_STARMAP_UUID,
            )
        );

        $glaciemPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/glaciemring.xml',
            sprintf(
                '<StarMapObject.GlaciemRing name="@LOC_UNINITIALIZED" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/glaciemring.xml" />',
                self::GLACIEM_STARMAP_UUID,
            )
        );

        $akiroPath = $this->writeFile(
            'Game2/libs/foundry/records/starmap/pu/pyro_asteroidbelta.xml',
            sprintf(
                '<StarMapObject.Pyro_AkiroCluster name="Akiro Cluster" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/pyro_asteroidbelta.xml" />',
                self::AKIRO_STARMAP_UUID,
            )
        );

        $stanton2cPath = null;
        if ($stanton2cFixture) {
            $stanton2cPath = $this->writeFile(
                'Game2/libs/foundry/records/starmap/pu/system/stanton/stanton2c/starmapobject.stanton2c.xml',
                sprintf(
                    '<StarMapObject.Stanton2c name="@LOC_UNINITIALIZED" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/system/stanton/stanton2c/starmapobject.stanton2c.xml"><asteroidRings><StarMapAsteroidRing densityScale="0.2" sizeScale="8" innerRadius="482000" outerRadius="782000" depth="2000" /></asteroidRings></StarMapObject.Stanton2c>',
                    self::STANTON2C_STARMAP_UUID,
                )
            );
        }

        $providerPath = $this->writeFile(
            'Game2/'.$path,
            sprintf(
                '<HarvestableProviderPreset.%1$s __type="HarvestableProviderPreset" __ref="%2$s" __path="%3$s"><harvestableGroups /></HarvestableProviderPreset.%1$s>',
                $className,
                $uuid,
                $path,
            )
        );

        $classToPathMap = [
            'HarvestableProviderPreset' => [
                $className => $providerPath,
            ],
            'StarMapObject' => [
                'Pyro_AkiroCluster' => $akiroPath,
                'Stanton1' => $stantonPath,
                'GlaciemRing' => $glaciemPath,
            ],
        ];

        $uuidToClassMap = [
            strtolower(self::AKIRO_STARMAP_UUID) => 'Pyro_AkiroCluster',
            strtolower(self::STANTON1_STARMAP_UUID) => 'Stanton1',
            strtolower(self::GLACIEM_STARMAP_UUID) => 'GlaciemRing',
            strtolower($uuid) => $className,
        ];

        $classToUuidMap = [
            'Pyro_AkiroCluster' => strtolower(self::AKIRO_STARMAP_UUID),
            'Stanton1' => strtolower(self::STANTON1_STARMAP_UUID),
            'GlaciemRing' => strtolower(self::GLACIEM_STARMAP_UUID),
            $className => strtolower($uuid),
        ];

        $uuidToPathMap = [
            strtolower(self::AKIRO_STARMAP_UUID) => $akiroPath,
            strtolower(self::STANTON1_STARMAP_UUID) => $stantonPath,
            strtolower(self::GLACIEM_STARMAP_UUID) => $glaciemPath,
            strtolower($uuid) => $providerPath,
        ];

        if ($stanton2cPath !== null) {
            $classToPathMap['StarMapObject']['Stanton2c'] = $stanton2cPath;
            $uuidToClassMap[strtolower(self::STANTON2C_STARMAP_UUID)] = 'Stanton2c';
            $classToUuidMap['Stanton2c'] = strtolower(self::STANTON2C_STARMAP_UUID);
            $uuidToPathMap[strtolower(self::STANTON2C_STARMAP_UUID)] = $stanton2cPath;
        }

        foreach ($extraStarmapFixtures as $fixture) {
            $fixturePath = $this->writeFile(
                'Game2/'.$fixture['path'],
                sprintf(
                    '<StarMapObject.%1$s name="@LOC_UNINITIALIZED" description="" __type="StarMapObject" __ref="%2$s" __path="%3$s" />',
                    $fixture['className'],
                    $fixture['uuid'],
                    $fixture['path'],
                )
            );
            $classToPathMap['StarMapObject'][$fixture['className']] = $fixturePath;
            $uuidToClassMap[strtolower($fixture['uuid'])] = $fixture['className'];
            $classToUuidMap[$fixture['className']] = strtolower($fixture['uuid']);
            $uuidToPathMap[strtolower($fixture['uuid'])] = $fixturePath;
        }

        $this->writeCacheFiles(
            classToPathMap: $classToPathMap,
            uuidToClassMap: $uuidToClassMap,
            classToUuidMap: $classToUuidMap,
            uuidToPathMap: $uuidToPathMap,
        );

        $this->writeExtractedTagFiles([
            ['uuid' => self::STANTON1_TAG_UUID, 'name' => 'Stanton1'],
        ]);

        if ($socpakMapping !== null) {
            file_put_contents(
                $this->tempDir.'/socpak_mappings.json',
                json_encode($socpakMapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        }

        (new ServiceFactory($this->tempDir))->initialize();

        $provider = ServiceFactory::getFoundryLookupService()
            ->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class)
            ->current();

        self::assertInstanceOf(HarvestableProviderPreset::class, $provider);

        return $provider;
    }
}
