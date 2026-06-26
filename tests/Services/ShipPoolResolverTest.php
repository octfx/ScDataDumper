<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services;

use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\Services\ShipPoolResolver;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

/**
 * Covers ShipPoolResolver: tag-based ship resolution ("Strategy A" in
 * issues/combat-ship-pool-validation.md).
 */
final class ShipPoolResolverTest extends ScDataTestCase
{
    // Selection tags present on entities.
    private const CRIMINAL = 'a45f6533-982c-45dc-97ea-0166a3560617';

    private const VERY_EASY = '1c361447-b3d2-4f59-90dd-3f8b6f95c0aa';

    private const EASY = '3069fb30-e0e6-4fd1-a25b-13152bf970f3';

    private const NINETALES = 'ecfb92fe-5d2d-4fef-aa8f-2f09fcf42b6f';

    // Behavior tags on NO entity -> must be dropped by the universe intersect.
    private const TARGET = '6a7d46fa-68cc-4c1e-9e4d-73af17c3f3ea';

    private const HUMAN_PILOT_40 = '5be30324-0f0c-4032-b645-21f10cc59d10';

    public function test_resolves_ships_by_selection_tags_with_negative_exclusion(): void
    {
        $resolver = $this->bootstrapResolver();

        // Criminal + VeryEasy plus two behavior tags (on no entity); negative excludes Ninetails.
        $ships = $resolver->resolve(
            [self::CRIMINAL, self::VERY_EASY, self::TARGET, self::HUMAN_PILOT_40],
            [self::NINETALES],
        );

        // Alpha crim + Alpha civ both match; same className -> one linkable entry.
        self::assertSame(
            [['className' => 'AEGS_ALPHA', 'name' => 'Aegis Alpha']],
            $ships,
        );
    }

    public function test_returns_empty_when_no_selection_tag_survives(): void
    {
        $resolver = $this->bootstrapResolver();

        // HumanPilot40 lives on no entity -> empty selection -> no ships (not every ship).
        self::assertSame([], $resolver->resolve([self::HUMAN_PILOT_40], []));
    }

    public function test_multiple_entities_match_without_negative_tag(): void
    {
        $resolver = $this->bootstrapResolver();

        // Alpha (x2) and Gamma; classNames dedupe to two distinct hulls, sorted by name.
        $ships = $resolver->resolve([self::CRIMINAL, self::VERY_EASY], []);

        self::assertSame(
            [
                ['className' => 'AEGS_ALPHA', 'name' => 'Aegis Alpha'],
                ['className' => 'ANVL_GAMMA', 'name' => 'Anvil Gamma'],
            ],
            $ships,
        );
    }

    public function test_emits_null_classname_when_ship_entity_has_no_hull_reference(): void
    {
        $resolver = $this->bootstrapResolver();

        // Delta has no shipEntityClassName (alien/NPC-only hull); still resolvable by tag,
        // displayed by name, but not linkable -> className null.
        $ships = $resolver->resolve([self::VERY_EASY], [self::CRIMINAL]);

        self::assertSame(
            [['className' => null, 'name' => 'RSI Delta']],
            $ships,
        );
    }

    private function bootstrapResolver(): ShipPoolResolver
    {
        $this->writeFixtures();

        (new ServiceFactory($this->tempDir))->initialize();

        $resolver = new ShipPoolResolver($this->tempDir);
        $resolver->initialize();

        return $resolver;
    }

    private function writeFixtures(): void
    {
        $this->writeFile(
            'Data/Localization/english/global.ini',
            implode(PHP_EOL, [
                'vehicle_NameSHIP_ALPHA=Aegis Alpha',
                'vehicle_NameSHIP_BETA=Drake Beta',
                'vehicle_NameSHIP_GAMMA=Anvil Gamma',
                'vehicle_NameSHIP_DELTA=RSI Delta',
            ])
        );

        $entities = [
            'SHIP_ALPHA_PU_AI_CRIM' => $this->writeShipEntity('ship_alpha_pu_ai_crim', [self::CRIMINAL, self::VERY_EASY], 'SHIP_ALPHA', 'AEGS_ALPHA'),
            'SHIP_ALPHA_PU_AI_CIV' => $this->writeShipEntity('ship_alpha_pu_ai_civ', [self::CRIMINAL, self::VERY_EASY], 'SHIP_ALPHA', 'AEGS_ALPHA'),
            'SHIP_BETA_PU_AI_CRIM' => $this->writeShipEntity('ship_beta_pu_ai_crim', [self::CRIMINAL, self::EASY], 'SHIP_BETA', 'DRAK_BETA'),
            'SHIP_GAMMA_PU_AI_CRIM' => $this->writeShipEntity('ship_gamma_pu_ai_crim', [self::CRIMINAL, self::VERY_EASY, self::NINETALES], 'SHIP_GAMMA', 'ANVL_GAMMA'),
            'SHIP_DELTA_PU_AI_UEE' => $this->writeShipEntity('ship_delta_pu_ai_uee', [self::VERY_EASY], 'SHIP_DELTA', null),
        ];

        $this->writeCacheFiles(classToPathMap: ['EntityClassDefinition' => $entities]);
    }

    /**
     * @param  list<string>  $tagUuids  top-level (selection) tags only
     * @param  string        $nameKey   localization key suffix for vehicleName
     * @param  ?string       $className base-hull ClassName (link key), or null for hulls with no player equivalent
     */
    private function writeShipEntity(string $slug, array $tagUuids, string $nameKey, ?string $className): string
    {
        $references = implode('', array_map(
            static fn (string $uuid): string => sprintf('<Reference value="%s" />', $uuid),
            $tagUuids,
        ));

        // shipInsuranceParams carries the base-hull className (the link key); nested <tags>
        // under EAEntityDataParams must NOT be indexed, only the top-level block.
        $insurance = $className === null
            ? ''
            : sprintf('<SEntityInsuranceProperties><shipInsuranceParams shipEntityClassName="%s" /></SEntityInsuranceProperties>', $className);

        return $this->writeFile(
            sprintf('Data/Libs/Foundry/Records/entities/spaceships/%s.xml', $slug),
            sprintf(
                '<EntityClassDefinition.%1$s __type="EntityClassDefinition" __ref="%2$s" __path="x">'
                .'<tags>%3$s</tags>'
                .'<StaticEntityClassData>'
                .'%5$s'
                .'<EAEntityDataParams inclusionMode="DoNotInclude"><inclusionParams><tags><tags>'
                .'<Reference value="11111111-0000-0000-0000-0000000000aa" />'
                .'</tags></tags></inclusionParams></EAEntityDataParams>'
                .'</StaticEntityClassData>'
                .'<Components>'
                .'<VehicleComponentParams vehicleName="@vehicle_Name%4$s" />'
                .'</Components>'
                .'</EntityClassDefinition.%1$s>',
                strtoupper($slug),
                sprintf('%s-0000-0000-0000-%012d', substr(md5($slug), 0, 8), crc32($slug) & 0xffffff),
                $references,
                $nameKey,
                $insurance,
            )
        );
    }
}
