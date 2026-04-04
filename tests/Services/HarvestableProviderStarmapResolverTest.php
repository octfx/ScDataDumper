<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\Services\HarvestableProviderStarmapResolver;
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

    public function test_resolves_body_provider_to_location_hierarchy_tag(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Stanton1',
            uuid: self::STANTON1_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/hpp_stanton1.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $resolved = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('Stanton1', $resolved['starmapKey']);
        self::assertSame('Stanton', $resolved['systemKey']);
        self::assertSame('Hurston', $resolved['locationName']);
        self::assertSame('planet', $resolved['locationType']);
        self::assertSame(self::STANTON1_STARMAP_UUID, $resolved['starmapObjectUuid']);
        self::assertSame(self::STANTON1_TAG_UUID, $resolved['starmapLocationHierarchyTagUuid']);
        self::assertSame('Stanton1', $resolved['starmapLocationHierarchyTagName']);
    }

    public function test_resolves_asteroid_provider_to_starmap_object_uuid(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Nyx_GlaciemRing',
            uuid: self::GLACIEM_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/nyx/asteroidfield/hpp_nyx_glaciemring.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $resolved = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('GlaciemRing', $resolved['starmapKey']);
        self::assertSame('Nyx', $resolved['systemKey']);
        self::assertSame('Glaciem Ring', $resolved['locationName']);
        self::assertSame('belt', $resolved['locationType']);
        self::assertSame(self::GLACIEM_STARMAP_UUID, $resolved['starmapObjectUuid']);
        self::assertNull($resolved['starmapLocationHierarchyTagUuid']);
        self::assertNull($resolved['starmapLocationHierarchyTagName']);
    }

    public function test_resolves_provider_by_display_name_when_class_name_differs(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'HPP_Pyro_AkiroCluster',
            uuid: self::AKIRO_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/pyro/asteroidfield/hpp_pyro_akirocluster.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $resolved = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('AkiroCluster', $resolved['starmapKey']);
        self::assertSame('Pyro', $resolved['systemKey']);
        self::assertSame('Akiro Cluster', $resolved['locationName']);
        self::assertSame(self::AKIRO_STARMAP_UUID, $resolved['starmapObjectUuid']);
        self::assertSame('display_name', $resolved['matchStrategy']);
    }

    public function test_resolves_cluster_provider_metadata_without_starmap_match(): void
    {
        $provider = $this->bootstrapResolverFixturesAndLoadProvider(
            className: 'AsteroidCluster_Low_Yield',
            uuid: self::CLUSTER_PROVIDER_UUID,
            path: 'libs/foundry/records/harvestable/providerpresets/system/stanton/asteroidcluster_low_yield.xml',
        );

        $resolver = new HarvestableProviderStarmapResolver;
        $resolved = $resolver->resolveHarvestableProvider($provider);

        self::assertSame('AsteroidClusterLowYield', $resolved['starmapKey']);
        self::assertSame('Stanton', $resolved['systemKey']);
        self::assertSame('Asteroid Cluster (Low Yield)', $resolved['locationName']);
        self::assertSame('cluster', $resolved['locationType']);
        self::assertNull($resolved['starmapObjectUuid']);
        self::assertSame('none', $resolved['matchStrategy']);
    }

    private function bootstrapResolverFixturesAndLoadProvider(string $className, string $uuid, string $path): HarvestableProviderPreset
    {
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
                '<StarMapObject.Pyro_asteroidbeltA name="Akiro Cluster" description="" __type="StarMapObject" __ref="%1$s" __path="libs/foundry/records/starmap/pu/pyro_asteroidbelta.xml" />',
                self::AKIRO_STARMAP_UUID,
            )
        );

        $providerPath = $this->writeFile(
            'Game2/'.$path,
            sprintf(
                '<HarvestableProviderPreset.%1$s __type="HarvestableProviderPreset" __ref="%2$s" __path="%3$s"><harvestableGroups /></HarvestableProviderPreset.%1$s>',
                $className,
                $uuid,
                $path,
            )
        );

        $this->writeCacheFiles(
            classToPathMap: [
                'HarvestableProviderPreset' => [
                    $className => $providerPath,
                ],
                'StarMapObject' => [
                    'Pyro_asteroidbeltA' => $akiroPath,
                    'Stanton1' => $stantonPath,
                    'GlaciemRing' => $glaciemPath,
                ],
            ],
            uuidToClassMap: [
                strtolower(self::AKIRO_STARMAP_UUID) => 'Pyro_asteroidbeltA',
                strtolower(self::STANTON1_STARMAP_UUID) => 'Stanton1',
                strtolower(self::GLACIEM_STARMAP_UUID) => 'GlaciemRing',
                strtolower($uuid) => $className,
            ],
            classToUuidMap: [
                'Pyro_asteroidbeltA' => strtolower(self::AKIRO_STARMAP_UUID),
                'Stanton1' => strtolower(self::STANTON1_STARMAP_UUID),
                'GlaciemRing' => strtolower(self::GLACIEM_STARMAP_UUID),
                $className => strtolower($uuid),
            ],
            uuidToPathMap: [
                strtolower(self::AKIRO_STARMAP_UUID) => $akiroPath,
                strtolower(self::STANTON1_STARMAP_UUID) => $stantonPath,
                strtolower(self::GLACIEM_STARMAP_UUID) => $glaciemPath,
                strtolower($uuid) => $providerPath,
            ],
        );

        $this->writeExtractedTagFiles([
            ['uuid' => self::STANTON1_TAG_UUID, 'name' => 'Stanton1'],
        ]);

        (new ServiceFactory($this->tempDir))->initialize();

        $provider = ServiceFactory::getFoundryLookupService()
            ->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class)
            ->current();

        self::assertInstanceOf(HarvestableProviderPreset::class, $provider);

        return $provider;
    }
}
