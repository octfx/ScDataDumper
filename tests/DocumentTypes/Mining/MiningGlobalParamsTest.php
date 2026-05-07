<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Mining;

use Octfx\ScDataDumper\DocumentTypes\Mining\MiningGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class MiningGlobalParamsTest extends ScDataTestCase
{
    private const GLOBAL_PARAMS_UUID = '20000000-0000-0000-0000-000000000001';

    private const WASTE_RESOURCE_UUID = '20000000-0000-0000-0000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();

        $globalParamsPath = $this->writeFile(
            'Game2/libs/foundry/records/mining/miningglobalparams/sample_global_params.xml',
            sprintf(
                '<MiningGlobalParams.SampleGlobalParams powerCapacityPerMass="5" decayPerMass="0.2" optimalWindowSize="0.5" optimalWindowFactor="0.5" resistanceCurveFactor="0.33" optimalWindowThinnessCurveFactor="0.7" optimalWindowMaxSize="0.7" cSCUPerVolume="3" defaultMass="0.001" wasteResourceType="%2$s" __type="MiningGlobalParams" __ref="%1$s" __path="libs/foundry/records/mining/miningglobalparams/sample_global_params.xml"><mineableInstabilityParams instabilityWavePeriod="3" instabilityWaveVariance="1" instabilityCurveFactor="1" /><mineableExplosionParams dangerPoolFactor="80" defaultVolume="1" /></MiningGlobalParams.SampleGlobalParams>',
                self::GLOBAL_PARAMS_UUID,
                self::WASTE_RESOURCE_UUID,
            )
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                strtolower(self::GLOBAL_PARAMS_UUID) => 'SampleGlobalParams',
            ],
            classToUuidMap: [
                'SampleGlobalParams' => strtolower(self::GLOBAL_PARAMS_UUID),
            ],
            uuidToPathMap: [
                strtolower(self::GLOBAL_PARAMS_UUID) => $globalParamsPath,
            ],
        );

        $this->writeResourceTypeCache([
            self::WASTE_RESOURCE_UUID => sprintf(
                '<ResourceType.Waste displayName="@resource_waste" __type="ResourceType" __ref="%1$s" __path="libs/foundry/records/resourcetypedatabase/waste.xml" />',
                self::WASTE_RESOURCE_UUID,
            ),
        ]);

        $this->writeFile('Data/Localization/english/global.ini', "resource_waste=Waste\n");

        (new ServiceFactory($this->tempDir))->initialize();
    }

    public function test_exposes_minimal_player_facing_accessors_and_hydrates_waste_resource_type(): void
    {
        $document = (new MiningGlobalParams)
            ->setReferenceHydrationEnabled(true);
        $document->load($this->tempDir.'/Game2/libs/foundry/records/mining/miningglobalparams/sample_global_params.xml');

        self::assertSame(5.0, $document->getPowerCapacityPerMass());
        self::assertSame(0.2, $document->getDecayPerMass());
        self::assertSame(0.5, $document->getOptimalWindowSize());
        self::assertSame(0.5, $document->getOptimalWindowFactor());
        self::assertSame(0.7, $document->getOptimalWindowMaxSize());
        self::assertSame(0.33, $document->getResistanceCurveFactor());
        self::assertSame(0.7, $document->getOptimalWindowThinnessCurveFactor());
        self::assertSame(3.0, $document->getCScuPerVolume());
        self::assertSame(0.001, $document->getDefaultMass());
        self::assertSame(self::WASTE_RESOURCE_UUID, $document->getWasteResourceTypeReference());
        self::assertSame(3.0, $document->getInstabilityWavePeriod());
        self::assertSame(1.0, $document->getInstabilityWaveVariance());
        self::assertSame(1.0, $document->getInstabilityCurveFactor());
        self::assertSame(80.0, $document->getDangerPoolFactor());
        self::assertSame(1.0, $document->getDefaultExplosionVolume());
        self::assertInstanceOf(ResourceType::class, $document->getWasteResourceType());
        self::assertSame(self::WASTE_RESOURCE_UUID, $document->getWasteResourceType()?->getUuid());

        $data = $document->toArray();

        self::assertSame(self::WASTE_RESOURCE_UUID, $data['ResourceType']['__ref']);
    }

    public function test_returns_null_accessors_when_attributes_missing(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/mining/miningglobalparams/empty_params.xml',
            '<MiningGlobalParams.EmptyParams __type="MiningGlobalParams" __ref="30000000-0000-0000-0000-000000000001" __path="libs/foundry/records/mining/miningglobalparams/empty_params.xml" />'
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                strtolower('30000000-0000-0000-0000-000000000001') => 'EmptyParams',
            ],
            classToUuidMap: [
                'EmptyParams' => strtolower('30000000-0000-0000-0000-000000000001'),
            ],
            uuidToPathMap: [
                strtolower('30000000-0000-0000-0000-000000000001') => $path,
            ],
        );

        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        (new ServiceFactory($this->tempDir))->initialize();

        $document = new MiningGlobalParams;
        $document->load($path);

        self::assertNull($document->getPowerCapacityPerMass());
        self::assertNull($document->getDecayPerMass());
        self::assertNull($document->getOptimalWindowSize());
        self::assertNull($document->getOptimalWindowFactor());
        self::assertNull($document->getOptimalWindowMaxSize());
        self::assertNull($document->getResistanceCurveFactor());
        self::assertNull($document->getOptimalWindowThinnessCurveFactor());
        self::assertNull($document->getCScuPerVolume());
        self::assertNull($document->getDefaultMass());
        self::assertNull($document->getWasteResourceTypeReference());
        self::assertNull($document->getInstabilityWavePeriod());
        self::assertNull($document->getInstabilityWaveVariance());
        self::assertNull($document->getInstabilityCurveFactor());
        self::assertNull($document->getDangerPoolFactor());
        self::assertNull($document->getDefaultExplosionVolume());
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $document = (new MiningGlobalParams)
            ->setReferenceHydrationEnabled(false);
        $document->load($this->tempDir.'/Game2/libs/foundry/records/mining/miningglobalparams/sample_global_params.xml');

        $data = $document->toArray();

        // Numeric attributes are cast to float, names stored without @ prefix
        self::assertSame(5.0, $data['powerCapacityPerMass']);
        self::assertSame(0.2, $data['decayPerMass']);
        self::assertSame(0.5, $data['optimalWindowSize']);
        self::assertSame(0.5, $data['optimalWindowFactor']);
        self::assertSame(0.33, $data['resistanceCurveFactor']);
        self::assertSame(0.7, $data['optimalWindowThinnessCurveFactor']);
        self::assertSame(0.7, $data['optimalWindowMaxSize']);
        self::assertSame(3.0, $data['cSCUPerVolume']);
        self::assertSame(0.001, $data['defaultMass']);
        self::assertSame(self::WASTE_RESOURCE_UUID, $data['wasteResourceType']);
        self::assertArrayHasKey('mineableInstabilityParams', $data);
        self::assertArrayHasKey('mineableExplosionParams', $data);
    }

    public function test_waste_resource_type_returns_null_when_reference_missing(): void
    {
        $path = $this->writeFile(
            'Game2/libs/foundry/records/mining/miningglobalparams/no_waste.xml',
            '<MiningGlobalParams.NoWaste powerCapacityPerMass="1" __type="MiningGlobalParams" __ref="40000000-0000-0000-0000-000000000001" __path="libs/foundry/records/mining/miningglobalparams/no_waste.xml" />'
        );

        $this->writeCacheFiles(
            uuidToClassMap: [
                strtolower('40000000-0000-0000-0000-000000000001') => 'NoWaste',
            ],
            classToUuidMap: [
                'NoWaste' => strtolower('40000000-0000-0000-0000-000000000001'),
            ],
            uuidToPathMap: [
                strtolower('40000000-0000-0000-0000-000000000001') => $path,
            ],
        );

        $this->writeFile('Data/Localization/english/global.ini', "LOC_EMPTY=\n");

        new ServiceFactory($this->tempDir)->initialize();

        $document = new MiningGlobalParams;
        $document->load($path);

        self::assertNull($document->getWasteResourceTypeReference());
        self::assertNull($document->getWasteResourceType());
    }
}
