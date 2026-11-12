<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use DOMDocument;
use Octfx\ScDataDumper\Formats\ScUnpacked\BulletPierceability;
use PHPUnit\Framework\TestCase;

/**
 * pierceabilityParams carries its values as XML attributes, mirroring
 * e.g. import/.../ammoparams/vehicle/ammoparams.apar_ballisticgatling_s4_ammo.xml.
 */
final class BulletPierceabilityTest extends TestCase
{
    private const string XML = <<<'XML'
    <BulletProjectileParams>
        <pierceabilityParams damageFalloffLevel1="0" damageFalloffLevel2="0" damageFalloffLevel3="0" maxPenetrationThickness="0.5" />
    </BulletProjectileParams>
    XML;

    public function test_reads_pierceability_params_from_attributes(): void
    {
        $doc = new DOMDocument;
        $doc->loadXML(self::XML);

        $result = (new BulletPierceability($doc->documentElement))->toArray();

        self::assertSame([
            'DamageFalloffLevel1' => 0.0,
            'DamageFalloffLevel2' => 0.0,
            'DamageFalloffLevel3' => 0.0,
            'MaxPenetrationThickness' => 0.5,
        ], $result);
    }

    public function test_returns_null_without_pierceability_params(): void
    {
        $doc = new DOMDocument;
        $doc->loadXML('<BulletProjectileParams />');

        self::assertNull((new BulletPierceability($doc->documentElement))->toArray());
    }
}
