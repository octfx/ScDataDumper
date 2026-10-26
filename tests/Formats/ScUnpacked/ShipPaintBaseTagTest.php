<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * Base paint tag derivation from SubGeometry tags. CIG ships occasional
 * miscased tags (Constellation Mk V: "Paint_Constellation_Mk5_..." next to
 * "Paint_Constellation_mk5_Black_Red_Red"); a case-sensitive common prefix
 * collapses that hull's base to the Mk IV tag "Paint_Constellation".
 */
final class ShipPaintBaseTagTest extends ScDataTestCase
{
    #[DataProvider('provideTagSets')]
    public function test_base_tag_derivation(array $paintTags, ?string $expected): void
    {
        $ship = (new ReflectionClass(Ship::class))->newInstanceWithoutConstructor();

        $baseTag = $this->invokeMethod($ship, 'extractBasePaintTag', $paintTags);

        self::assertSame($expected, $baseTag);
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: ?string}>
     */
    public static function provideTagSets(): iterable
    {
        $mk5Tags = [
            'Paint_Constellation_Mk5_Black_Gray_Purple',
            'Paint_Constellation_Mk5_White_Orange_Gray',
            'Paint_Constellation_Mk5_Blue_Black_Yellow',
            'Paint_Constellation_Mk5_Blue_Orange_Blue',
            'Paint_Constellation_Mk5_Red_Gray_Yellow',
            'Paint_Constellation_mk5_Black_Red_Red',
            'Paint_Constellation_Mk5_Black_Black_Orange',
        ];

        yield 'mk5 hull with one miscased variant' => [$mk5Tags, 'Paint_Constellation_Mk5'];

        // Tag order must not influence the derived casing.
        yield 'mk5 hull with the miscased variant first' => [
            ['Paint_Constellation_mk5_Black_Red_Red', ...array_slice($mk5Tags, 0, 6)],
            'Paint_Constellation_Mk5',
        ];

        yield 'consistent tags keep existing behavior' => [
            [
                'Paint_Skylark_Black_Gray_Purple',
                'Paint_Skylark_Blue_Orange_Blue',
                'Paint_Skylark_Red_Gray_Yellow',
            ],
            'Paint_Skylark',
        ];

        yield 'variants sharing only the family prefix' => [
            [
                'Paint_Constellation_Invictus_Blue_Gold',
                'Paint_Constellation_Heron_Black',
                'Paint_Constellation_Luna2951_Red',
            ],
            'Paint_Constellation',
        ];

        yield 'single tag strips the last variant segment' => [
            ['Paint_Skylark_Blue_Orange_Blue'],
            'Paint_Skylark_Blue_Orange',
        ];

        yield 'no common prefix' => [
            ['Paint_Hornet_F7A', 'Xaint_Hornet_F7C'],
            null,
        ];
    }
}
