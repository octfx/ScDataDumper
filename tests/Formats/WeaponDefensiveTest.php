<?php

namespace Tests\Formats;

use Octfx\ScDataDumper\Formats\ScUnpacked\WeaponDefensive;
use Octfx\ScDataDumper\Services\ServiceFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WeaponDefensiveTest extends TestCase
{
    private static bool $servicesInitialized = false;

    private function initServices(): void
    {
        if (self::$servicesInitialized) {
            return;
        }

        $factory = new ServiceFactory('import');
        $factory->initialize();

        self::$servicesInitialized = true;
    }

    #[Test]
    public function it_extracts_countermeasure_signatures_and_capacity(): void
    {
        $this->initServices();

        $item = ServiceFactory::getItemService()->getByReference('6e052f56-88e1-4e80-adb1-1c679cc3f8d9'); // CRUS_Spirit_CML_Chaff
        $format = new WeaponDefensive($item);

        $result = $format->toArray();

        $this->assertNotNull($result);
        $this->assertSame('Chaff', $result['Type']);

        $this->assertSame(80000.0, $result['Signatures']['Infrared']['Start']);
        $this->assertSame(150000.0, $result['Signatures']['Electromagnetic']['Start']);
        $this->assertSame(100000.0, $result['Signatures']['CrossSection']['Start']);
        $this->assertSame(0.0, $result['Signatures']['Decibel']['Start']);

        $this->assertSame(10.0, $result['InitialCapacity']);
        $this->assertSame(10.0, $result['Capacity']);
        $this->assertSame(144.0, $result['Projectile']['Range']);
        $this->assertSame(1.0, $result['Projectile']['VolumeSpawnDelay']);
        $this->assertSame(12.0, $result['Projectile']['VolumeLifetime']);
    }

    #[Test]
    public function it_returns_null_for_non_weapon_defensive_items(): void
    {
        $format = new WeaponDefensive(null);

        $this->assertNull($format->toArray());
    }
}
