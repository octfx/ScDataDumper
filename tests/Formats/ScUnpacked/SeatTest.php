<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\ScUnpacked\Seat;
use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\TestCase;

final class SeatTest extends TestCase
{
    /**
     * SCItemSeatEjectParams ships as template data on many non-ejecting seats
     * (Prospector, Fortune, 100i, ground vehicles, etc.). The params block alone
     * must NOT be treated as an ejection seat -- only an actual eject interaction counts.
     */
    public function test_eject_params_without_interaction_is_not_an_ejection_seat(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.Seat_Test __type="EntityClassDefinition">
            <Components>
                <SCItemSeatParams seatType="DUAL_STICK" minYaw="-70" maxYaw="70" minPitch="-45" maxPitch="65">
                    <ejection>
                        <SCItemSeatEjectParams maxLinearVelocity="1400" maxLinearAcceleration="20" maxAngularVelocity="1400" maxAngularAcceleration="20" ejectionLoopTime="4">
                            <offsetAngles x="90" y="0" z="0" />
                        </SCItemSeatEjectParams>
                    </ejection>
                </SCItemSeatParams>
            </Components>
        </EntityClassDefinition.Seat_Test>
        XML;

        $result = (new Seat($this->loadXml($xml)))->toArray();

        self::assertIsArray($result);
        self::assertArrayNotHasKey('HasEjection', $result);
        self::assertArrayNotHasKey('Ejection', $result);
    }

    public function test_eject_params_with_interaction_is_an_ejection_seat(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.Seat_Test __type="EntityClassDefinition">
            <Components>
                <SCItemSeatParams seatType="HOTAS_C_L" minYaw="-70" maxYaw="70" minPitch="-45" maxPitch="65">
                    <ejection>
                        <SCItemSeatEjectParams maxLinearVelocity="2000" maxLinearAcceleration="100" maxAngularVelocity="2000" maxAngularAcceleration="100" ejectionLoopTime="1">
                            <ejectionInteraction value="SSharedInteractionParams[7B6A]" />
                            <offsetAngles x="90" y="0" z="0" />
                        </SCItemSeatEjectParams>
                    </ejection>
                </SCItemSeatParams>
            </Components>
        </EntityClassDefinition.Seat_Test>
        XML;

        $result = (new Seat($this->loadXml($xml)))->toArray();

        self::assertIsArray($result);
        self::assertTrue($result['HasEjection']);
        self::assertIsArray($result['Ejection']);
        self::assertSame(2000.0, $result['Ejection']['MaxLinearVelocity']);
    }

    public function test_seat_without_eject_block_has_no_ejection_fields(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.Seat_Test __type="EntityClassDefinition">
            <Components>
                <SCItemSeatParams seatType="HOTAS_C_L" minYaw="-70" maxYaw="70" minPitch="-45" maxPitch="65" />
            </Components>
        </EntityClassDefinition.Seat_Test>
        XML;

        $result = (new Seat($this->loadXml($xml)))->toArray();

        self::assertIsArray($result);
        self::assertArrayNotHasKey('HasEjection', $result);
        self::assertArrayNotHasKey('Ejection', $result);
    }

    public function test_to_array_returns_null_when_no_seat_params(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <EntityClassDefinition.Seat_Test __type="EntityClassDefinition">
            <Components />
        </EntityClassDefinition.Seat_Test>
        XML;

        self::assertNull((new Seat($this->loadXml($xml)))->toArray());
    }

    private function loadXml(string $xml): TestRootDocument
    {
        $tmpFile = sys_get_temp_dir().'/seat_test_'.uniqid().'.xml';
        file_put_contents($tmpFile, $xml);

        try {
            $doc = new TestRootDocument;
            $doc->load($tmpFile);

            return $doc;
        } finally {
            @unlink($tmpFile);
        }
    }
}
