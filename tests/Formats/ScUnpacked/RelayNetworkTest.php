<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Formats\ScUnpacked\RelayNetwork;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class RelayNetworkTest extends ScDataTestCase
{
    public function test_to_array_returns_null_when_no_links(): void
    {
        $entity = $this->createEntityWithLinks([]);
        $relay = new RelayNetwork($entity, []);

        self::assertNull($relay->toArray());
    }

    public function test_to_array_enriches_connected_hardpoints_with_processed_loadout(): void
    {
        $entity = $this->createEntityWithLinks([
            ['port1' => 'hardpoint_relay_bridge', 'port2' => 'hardpoint_seat_pilot'],
            ['port1' => 'hardpoint_relay_bridge', 'port2' => 'hardpoint_radar'],
            ['port1' => 'hardpoint_relay_bridge', 'port2' => 'hardpoint_empty_port'],
        ]);

        $processedLoadout = [
            [
                'HardpointName' => 'hardpoint_seat_pilot',
                'ClassName' => 'SEAT_Pilot_ANVL',
                'UUID' => 'seat-uuid-001',
                'Name' => 'Pilot Seat',
                'Type' => 'Seat.Pilot',
            ],
            [
                'HardpointName' => 'hardpoint_radar',
                'ClassName' => 'RADR_CHCO_S02_Surveyor',
                'UUID' => 'radar-uuid-002',
                'Name' => 'Surveyor',
                'Type' => 'Radar.MidRangeRadar',
            ],
            [
                'HardpointName' => 'hardpoint_empty_port',
                // No ClassName/UUID/Name/Type — empty port
            ],
        ];

        $relay = new RelayNetwork($entity, [], $processedLoadout);
        $result = $relay->toArray();

        self::assertNotNull($result);
        self::assertCount(1, $result['Relays']);

        $bridgeRelay = $result['Relays'][0];
        self::assertSame('hardpoint_relay_bridge', $bridgeRelay['HardpointName']);
        self::assertCount(3, $bridgeRelay['ConnectedHardpoints']);

        // Enriched seat entry
        $seat = $bridgeRelay['ConnectedHardpoints'][0];
        self::assertSame('hardpoint_seat_pilot', $seat['HardpointName']);
        self::assertSame('Pilot Seat', $seat['ItemName']);
        self::assertSame('SEAT_Pilot_ANVL', $seat['ClassName']);
        self::assertSame('Seat.Pilot', $seat['Type']);
        self::assertSame('seat-uuid-001', $seat['UUID']);

        // Enriched radar entry
        $radar = $bridgeRelay['ConnectedHardpoints'][1];
        self::assertSame('hardpoint_radar', $radar['HardpointName']);
        self::assertSame('Surveyor', $radar['ItemName']);
        self::assertSame('RADR_CHCO_S02_Surveyor', $radar['ClassName']);
        self::assertSame('Radar.MidRangeRadar', $radar['Type']);
        self::assertSame('radar-uuid-002', $radar['UUID']);

        // Empty port — all item fields null
        $empty = $bridgeRelay['ConnectedHardpoints'][2];
        self::assertSame('hardpoint_empty_port', $empty['HardpointName']);
        self::assertNull($empty['ItemName']);
        self::assertNull($empty['ClassName']);
        self::assertNull($empty['Type']);
        self::assertNull($empty['UUID']);
    }

    public function test_to_array_falls_back_to_strings_without_processed_loadout(): void
    {
        $entity = $this->createEntityWithLinks([
            ['port1' => 'hardpoint_relay_bridge', 'port2' => 'hardpoint_seat_pilot'],
            ['port1' => 'hardpoint_relay_bridge', 'port2' => 'hardpoint_radar'],
        ]);

        $relay = new RelayNetwork($entity, []);
        $result = $relay->toArray();

        self::assertNotNull($result);
        $connected = $result['Relays'][0]['ConnectedHardpoints'];

        // Without processed loadout, should be plain strings
        self::assertSame('hardpoint_seat_pilot', $connected[0]);
        self::assertSame('hardpoint_radar', $connected[1]);
    }

    public function test_to_array_resolves_nested_processed_loadout(): void
    {
        $entity = $this->createEntityWithLinks([
            ['port1' => 'hardpoint_relay', 'port2' => 'hardpoint_nested_item'],
        ]);

        $processedLoadout = [
            [
                'HardpointName' => 'hardpoint_turret',
                'ClassName' => 'TURRET_PARENT',
                'UUID' => 'turret-parent-uuid',
                'Name' => 'Turret',
                'Type' => 'TurretBase.MannedTurret',
                'Loadout' => [
                    [
                        'HardpointName' => 'hardpoint_nested_item',
                        'ClassName' => 'WEAP_Nested',
                        'UUID' => 'nested-uuid',
                        'Name' => 'Laser Cannon',
                        'Type' => 'WeaponGun.Gun',
                    ],
                ],
            ],
        ];

        $relay = new RelayNetwork($entity, [], $processedLoadout);
        $result = $relay->toArray();

        self::assertNotNull($result);
        $connected = $result['Relays'][0]['ConnectedHardpoints'][0];

        self::assertSame('hardpoint_nested_item', $connected['HardpointName']);
        self::assertSame('Laser Cannon', $connected['ItemName']);
        self::assertSame('WEAP_Nested', $connected['ClassName']);
        self::assertSame('WeaponGun.Gun', $connected['Type']);
        self::assertSame('nested-uuid', $connected['UUID']);
    }

    public function test_to_array_handles_unresolved_hardpoint(): void
    {
        $entity = $this->createEntityWithLinks([
            ['port1' => 'hardpoint_relay', 'port2' => 'hardpoint_not_in_loadout'],
        ]);

        $processedLoadout = [
            [
                'HardpointName' => 'hardpoint_something_else',
                'ClassName' => 'OTHER',
                'UUID' => 'other-uuid',
                'Name' => 'Other',
                'Type' => 'Other.Type',
            ],
        ];

        $relay = new RelayNetwork($entity, [], $processedLoadout);
        $result = $relay->toArray();

        self::assertNotNull($result);
        $connected = $result['Relays'][0]['ConnectedHardpoints'][0];

        self::assertSame('hardpoint_not_in_loadout', $connected['HardpointName']);
        self::assertNull($connected['ItemName']);
        self::assertNull($connected['ClassName']);
        self::assertNull($connected['Type']);
        self::assertNull($connected['UUID']);
    }

    public function test_to_array_includes_links_and_total_fuses(): void
    {
        $links = [
            ['port1' => 'hardpoint_relay_front', 'port2' => 'hardpoint_seat'],
            ['port1' => 'hardpoint_relay_front', 'port2' => 'hardpoint_radar'],
        ];

        $rawLoadout = [
            [
                'portName' => 'hardpoint_relay_front',
                'className' => 'RELAY_2slot',
                'entries' => [],
                'Item' => [
                    'stdItem' => [
                        'Ports' => [
                            ['PortName' => '$slot_fuse_01'],
                            ['PortName' => '$slot_fuse_02'],
                        ],
                    ],
                ],
            ],
        ];

        $entity = $this->createEntityWithLinks($links);
        $relay = new RelayNetwork($entity, $rawLoadout);
        $result = $relay->toArray();

        self::assertNotNull($result);
        self::assertSame(2, $result['TotalFuses']);
        self::assertCount(2, $result['Links']);
        self::assertSame('hardpoint_relay_front', $result['Links'][0]['From']);
        self::assertSame('hardpoint_seat', $result['Links'][0]['To']);
    }

    public function test_to_array_handles_multiple_relays(): void
    {
        $entity = $this->createEntityWithLinks([
            ['port1' => 'hardpoint_relay_front', 'port2' => 'hardpoint_seat'],
            ['port1' => 'hardpoint_relay_rear', 'port2' => 'hardpoint_engine'],
        ]);

        $processedLoadout = [
            [
                'HardpointName' => 'hardpoint_seat',
                'ClassName' => 'SEAT_TEST',
                'UUID' => 'seat-uuid',
                'Name' => 'Pilot Seat',
                'Type' => 'Seat.Pilot',
            ],
            [
                'HardpointName' => 'hardpoint_engine',
                'ClassName' => 'ENGIN_TEST',
                'UUID' => 'engine-uuid',
                'Name' => 'Main Engine',
                'Type' => 'MainThruster.FixedThruster',
            ],
        ];

        $relay = new RelayNetwork($entity, [], $processedLoadout);
        $result = $relay->toArray();

        self::assertNotNull($result);
        self::assertCount(2, $result['Relays']);

        $frontRelay = collect($result['Relays'])->firstOrFail(fn (array $r) => $r['HardpointName'] === 'hardpoint_relay_front');
        self::assertCount(1, $frontRelay['ConnectedHardpoints']);
        self::assertSame('Pilot Seat', $frontRelay['ConnectedHardpoints'][0]['ItemName']);

        $rearRelay = collect($result['Relays'])->firstOrFail(fn (array $r) => $r['HardpointName'] === 'hardpoint_relay_rear');
        self::assertCount(1, $rearRelay['ConnectedHardpoints']);
        self::assertSame('Main Engine', $rearRelay['ConnectedHardpoints'][0]['ItemName']);
    }

    /**
     * Create a minimal RootDocument entity with InternalHardpointLinks.
     *
     * @param  array<int, array{port1: string, port2: string}>  $links
     */
    private function createEntityWithLinks(array $links): VehicleDefinition
    {
        $linkXml = '';
        foreach ($links as $link) {
            $linkXml .= sprintf(
                '<SInternalHardpointLink port1="%s" port2="%s" />' . "\n",
                $link['port1'],
                $link['port2']
            );
        }

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <VehicleDefinition.TEST_RELAY __type="EntityClassDefinition" __ref="relay-test-uuid" __path="libs/foundry/records/entityclassdefinition/test_relay.xml">
            <Components>
                <SItemPortContainerComponentParams>
                    <InternalHardpointLinks>
                        {$linkXml}
                    </InternalHardpointLinks>
                </SItemPortContainerComponentParams>
            </Components>
        </VehicleDefinition.TEST_RELAY>
        XML;

        $entity = new VehicleDefinition;
        $entity->load($this->writeFile('records/entity/test_relay.xml', $xml));

        return $entity;
    }
}
