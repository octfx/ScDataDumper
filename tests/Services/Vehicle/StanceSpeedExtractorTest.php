<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\Vehicle\StanceSpeedExtractor;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class StanceSpeedExtractorTest extends ScDataTestCase
{
    private const STANCE_CONFIG_UUID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    private const SPEED_INFO_UUID = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeCacheFiles();

        $this->writeFoundryRecord(
            self::STANCE_CONFIG_UUID,
            'records/actorstanceconfig',
            sprintf(
                '<ActorStanceConfig.Test __type="ActorStanceConfig" __ref="%1$s" __path="libs/foundry/records/actorstanceconfig/test.xml"><stanceSpeeds><Reference value="%2$s" /></stanceSpeeds></ActorStanceConfig.Test>',
                self::STANCE_CONFIG_UUID,
                self::SPEED_INFO_UUID,
            )
        );

        $this->writeFoundryRecord(
            self::SPEED_INFO_UUID,
            'records/actorspeeds',
            sprintf(
                '<ActorStanceSpeedsInfo.Test __type="ActorStanceSpeedsInfo" __ref="%1$s" __path="libs/foundry/records/actorspeeds/test.xml"><speeds defaultSpeed="1.99" sprintSpeed="2.88" defaultLinearAcceleration="1.5" defaultRotationSpeed="60.0" /></ActorStanceSpeedsInfo.Test>',
                self::SPEED_INFO_UUID,
            )
        );

        (new ServiceFactory($this->tempDir))->initialize();
    }

    public function test_actor_with_stance_speed_chain(): void
    {
        $entity = $this->createEntity(self::STANCE_CONFIG_UUID);

        $result = (new StanceSpeedExtractor)->extract($entity);

        self::assertNotNull($result);
        self::assertSame(1.99, $result['WalkSpeedMs']);
        self::assertSame(2.88, $result['SprintSpeedMs']);
        self::assertSame(1.5, $result['Acceleration']);
        self::assertSame(60.0, $result['RotationSpeed']);
    }

    public function test_kph_conversion(): void
    {
        $entity = $this->createEntity(self::STANCE_CONFIG_UUID);

        $result = (new StanceSpeedExtractor)->extract($entity);

        self::assertNotNull($result);
        self::assertEqualsWithDelta(1.99 * 3.6, $result['WalkSpeedKph'], 0.001);
        self::assertEqualsWithDelta(2.88 * 3.6, $result['SprintSpeedKph'], 0.001);
    }

    public function test_non_actor_entity_returns_null(): void
    {
        $entity = $this->createEntityWithXml(<<<'XML'
<EntityClassDefinition.Test __type="EntityClassDefinition">
  <Components>
    <SPhysicsComponentParams />
  </Components>
</EntityClassDefinition.Test>
XML);

        $result = (new StanceSpeedExtractor)->extract($entity);

        self::assertNull($result);
    }

    public function test_actor_without_stances_data_record_returns_null(): void
    {
        $entity = $this->createEntityWithXml(<<<'XML'
<EntityClassDefinition.Test __type="EntityClassDefinition">
  <Components>
    <SActorComponentParams />
  </Components>
</EntityClassDefinition.Test>
XML);

        $result = (new StanceSpeedExtractor)->extract($entity);

        self::assertNull($result);
    }

    public function test_broken_reference_chain_returns_null(): void
    {
        // Entity references a valid stance config, but the speed UUID inside is unresolvable
        $brokenConfigUuid = '99999999-8888-7777-6666-555555555555';
        $this->writeFoundryRecord(
            $brokenConfigUuid,
            'records/actorstanceconfig',
            sprintf(
                '<ActorStanceConfig.Broken __type="ActorStanceConfig" __ref="%1$s" __path="libs/foundry/records/actorstanceconfig/broken.xml"><stanceSpeeds><Reference value="deadbeef-dead-beef-dead-beefdeadbeef" /></stanceSpeeds></ActorStanceConfig.Broken>',
                $brokenConfigUuid,
            )
        );

        $entity = $this->createEntity($brokenConfigUuid);

        $result = (new StanceSpeedExtractor)->extract($entity);

        self::assertNull($result);
    }

    private function createEntity(string $stancesDataRecordUuid): VehicleDefinition
    {
        return $this->createEntityWithXml(sprintf(
            '<EntityClassDefinition.Test __type="EntityClassDefinition"><Components><SActorComponentParams stancesDataRecord="%s" /></Components></EntityClassDefinition.Test>',
            $stancesDataRecordUuid
        ));
    }

    private function createEntityWithXml(string $xml): VehicleDefinition
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'stance_test_');
        file_put_contents($tempFile, $xml);

        $entity = new VehicleDefinition;
        $entity->load($tempFile);

        unlink($tempFile);

        return $entity;
    }
}
