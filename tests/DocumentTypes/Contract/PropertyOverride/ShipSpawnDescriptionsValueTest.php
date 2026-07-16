<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes\Contract\PropertyOverride;

use Octfx\ScDataDumper\DocumentTypes\Contract\PropertyOverride\ShipSpawnDescriptionsValue;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers ShipSpawnDescriptionsValue::toArray wiring combat spawns to resolved ship
 * models. Each SpawnDescription_Ship carries positive + negative TAGS; toArray must
 * read both and append the resolved ship names per row via ShipPoolResolver.
 */
final class ShipSpawnDescriptionsValueTest extends ScDataTestCase
{
    private const CRIMINAL = 'a45f6533-982c-45dc-97ea-0166a3560617';

    private const VERY_EASY = '1c361447-b3d2-4f59-90dd-3f8b6f95c0aa';

    private const NINETALES = 'ecfb92fe-5d2d-4fef-aa8f-2f09fcf42b6f';

    public function test_to_array_resolves_ship_models_from_selection_tags(): void
    {
        $this->writeFixtures();
        new ServiceFactory($this->tempDir)->initialize();

        $value = new ShipSpawnDescriptionsValue;
        $value->loadXML($this->spawnXml());

        $rows = $value->toArray('Hostile');

        self::assertCount(1, $rows);
        self::assertSame('Ship', $rows[0]['spawn_kind']);
        self::assertSame('Target', $rows[0]['group_name']);
        // Alpha carries both selection tags and no Ninetails; Beta lacks VeryEasy,
        // Gamma carries Ninetails.
        self::assertSame(
            [['className' => 'AEGS_ALPHA', 'name' => 'Aegis Alpha']],
            $rows[0]['ships'],
        );
    }

    public function test_entity_tags_are_surfaced_in_to_array(): void
    {
        $entityTag = 'f1f1f1f1-0000-0000-0000-0000000000ee';

        $this->writeFixtures();
        new ServiceFactory($this->tempDir)->initialize();

        $value = new ShipSpawnDescriptionsValue;
        $value->loadXML($this->spawnXmlWithEntityTags($entityTag));

        $rows = $value->toArray('Hostile');

        self::assertCount(1, $rows);
        self::assertSame([$entityTag], $rows[0]['entity_tags']);
    }

    private function spawnXml(): string
    {
        return '<MissionPropertyValue_ShipSpawnDescriptions allowedForMissionRestrictedDeliveries="0">'
            .'<spawnDescriptions>'
            .'<SpawnDescription_ShipGroup Name="Target"><ships>'
            .'<SpawnDescription_ShipOptions><options>'
            .'<SpawnDescription_Ship concurrentAmount="1" weight="1">'
            .'<tags><tags>'
            .'<Reference value="'.self::CRIMINAL.'" />'
            .'<Reference value="'.self::VERY_EASY.'" />'
            .'</tags></tags>'
            .'<negativeTags><tags>'
            .'<Reference value="'.self::NINETALES.'" />'
            .'</tags></negativeTags>'
            .'</SpawnDescription_Ship>'
            .'</options></SpawnDescription_ShipOptions>'
            .'</ships></SpawnDescription_ShipGroup>'
            .'</spawnDescriptions>'
            .'</MissionPropertyValue_ShipSpawnDescriptions>';
    }

    private function spawnXmlWithEntityTags(string $entityTag): string
    {
        return '<MissionPropertyValue_ShipSpawnDescriptions allowedForMissionRestrictedDeliveries="0">'
            .'<spawnDescriptions>'
            .'<SpawnDescription_ShipGroup Name="Target"><ships>'
            .'<SpawnDescription_ShipOptions><options>'
            .'<SpawnDescription_Ship concurrentAmount="1" weight="1">'
            .'<tags><tags>'
            .'<Reference value="'.self::CRIMINAL.'" />'
            .'<Reference value="'.self::VERY_EASY.'" />'
            .'</tags></tags>'
            .'<entityTags><tags>'
            .'<Reference value="'.$entityTag.'" />'
            .'</tags></entityTags>'
            .'</SpawnDescription_Ship>'
            .'</options></SpawnDescription_ShipOptions>'
            .'</ships></SpawnDescription_ShipGroup>'
            .'</spawnDescriptions>'
            .'</MissionPropertyValue_ShipSpawnDescriptions>';
    }

    private function writeFixtures(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'vehicle_NameSHIP_ALPHA=Aegis Alpha',
                'vehicle_NameSHIP_BETA=Drake Beta',
                'vehicle_NameSHIP_GAMMA=Anvil Gamma',
            ])
        );

        $entities = [
            'SHIP_ALPHA_PU_AI_CRIM' => $this->writeShipEntity('ship_alpha_pu_ai_crim', [self::CRIMINAL, self::VERY_EASY], 'SHIP_ALPHA', 'AEGS_ALPHA'),
            'SHIP_BETA_PU_AI_CRIM' => $this->writeShipEntity('ship_beta_pu_ai_crim', [self::CRIMINAL], 'SHIP_BETA', 'DRAK_BETA'),
            'SHIP_GAMMA_PU_AI_CRIM' => $this->writeShipEntity('ship_gamma_pu_ai_crim', [self::CRIMINAL, self::VERY_EASY, self::NINETALES], 'SHIP_GAMMA', 'ANVL_GAMMA'),
        ];

        $this->writeCacheFiles(classToPathMap: ['EntityClassDefinition' => $entities]);
    }

    /**
     * @param  list<string>  $tagUuids
     * @param  string  $className  base-hull ClassName emitted as the link key
     */
    private function writeShipEntity(string $slug, array $tagUuids, string $nameKey, string $className): string
    {
        $references = implode('', array_map(
            static fn (string $uuid): string => sprintf('<Reference value="%s" />', $uuid),
            $tagUuids,
        ));

        return $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/entities/spaceships/%s.xml', $slug),
            sprintf(
                '<EntityClassDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="x">'
                .'<tags>%3$s</tags>'
                .'<StaticEntityClassData>'
                .'<SEntityInsuranceProperties><shipInsuranceParams shipEntityClassName="%5$s" /></SEntityInsuranceProperties>'
                .'</StaticEntityClassData>'
                .'<Components>'
                .'<VehicleComponentParams vehicleName="@vehicle_Name%4$s" />'
                .'</Components>'
                .'</EntityClassDefinition.%1$s>',
                strtoupper($slug),
                sprintf('%s-0000-0000-0000-000000000001', substr(md5($slug), 0, 8)),
                $references,
                $nameKey,
                $className,
            )
        );
    }
}
