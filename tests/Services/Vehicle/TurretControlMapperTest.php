<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;
use Octfx\ScDataDumper\DocumentTypes\VehicleDefinition;
use Octfx\ScDataDumper\Services\Vehicle\TurretControlMapper;
use PHPUnit\Framework\TestCase;

final class TurretControlMapperTest extends TestCase
{
    /**
     * Ballista pattern: pilot seat directly controls a turret via WeaponController priority.
     * The pilot seat has a WeaponController PriorityGroup with the turret's tag at exclusive_control.
     */
    public function test_direct_pilot_turret_control(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_driver">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="remote_turret">
                      <Priority value="exclusive_control"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_turret">
          <ItemPort>
            <Types><Type type="Turret"/></Types>
            <ControllerDef controllableTags="remote_turret"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_turret', $result);
    }

    /**
     * Carrack pattern: bridge right seat controls turret via shared controllableTag.
     * Both the seat and the turret have controllableTags="passengerRightSeat".
     * The seat has a WeaponController PriorityGroup for tag "passengerRightSeat".
     */
    public function test_carrack_shared_tag_pattern(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_bridge_r">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="passengerRightSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="passengerRightSeat">
                      <Priority value="exclusive_control"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_turret_remote_turret">
          <ItemPort>
            <Types><Type type="Turret"/></Types>
            <ControllerDef controllableTags="passengerRightSeat"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_turret_remote_turret', $result);
    }

    /**
     * Hornet Mk II pattern: weapon controller bridges pilot seat tag to turret tag.
     * Pilot seat has WeaponController priority for "pilotSeat".
     * Weapon controller has controllableTags="pilotSeat" and maps "remote_turret" tag.
     * Turret has controllableTags="remote_turret".
     */
    public function test_weapon_controller_bridge(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="pilotSeat">
                      <Priority value="100"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_controller_weapon">
          <ItemPort>
            <Types><Type type="WeaponController"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UsableDef>
                <PriorityGroups>
                  <PriorityGroup itemType="Turret" defaultPriority="100">
                    <tags tag="remote_turret">
                      <Priority value="50"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UsableDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_remote_turret">
          <ItemPort>
            <Types><Type type="Turret"/></Types>
            <ControllerDef controllableTags="remote_turret"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_remote_turret', $result);
    }

    /**
     * Dedicated turret seat should NOT make the turret bridge-controllable.
     */
    public function test_dedicated_turret_seat_not_bridge(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_manned_turret_top">
          <Parts>
            <Part name="hardpoint_turret_top_seat">
              <ItemPort>
                <Types><Type type="Seat"/></Types>
                <ControllerDef controllableTags="turretSeat01">
                  <UserDef>
                    <PriorityGroups>
                      <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                        <tags tag="manned_turret_top">
                          <Priority value="1"/>
                        </tags>
                      </PriorityGroup>
                    </PriorityGroups>
                  </UserDef>
                </ControllerDef>
              </ItemPort>
            </Part>
          </Parts>
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="manned_turret_top"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertEmpty($result);
    }

    /**
     * A2 copilot pattern: copilot seat controls bottom turret.
     */
    public function test_copilot_controls_turret(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="bottom_turret">
                      <Priority value="0"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_seat_copilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="copilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="bottom_turret">
                      <Priority value="exclusive_control"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_bottom_turret">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="bottom_turret"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_bottom_turret', $result);
    }

    public function test_null_vehicle_returns_empty(): void
    {
        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets(null, null);

        self::assertSame([], $result);
    }

    public function test_no_controllable_parts_returns_empty(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertSame([], $result);
    }

    /**
     * Reclaimer pattern: turret console seats on bridge control turrets.
     */
    public function test_turret_console_controls_turret(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat"/>
          </ItemPort>
        </Part>
        <Part name="hardpoint_turret_console_01">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="consoleSeat01">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="front_turret">
                      <Priority value="exclusive_control"/>
                    </tags>
                    <tags tag="rear_turret">
                      <Priority value="exclusive_control"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_front_turret">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="front_turret"/>
          </ItemPort>
        </Part>
        <Part name="hardpoint_rear_turret">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="rear_turret"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_front_turret', $result);
        self::assertContains('hardpoint_rear_turret', $result);
    }

    /**
     * Mixed: some turrets are bridge-controllable, others are not.
     */
    public function test_mixed_bridge_and_dedicated_turrets(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="top_turret">
                      <Priority value="exclusive_control"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_remote_turret_top">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="top_turret"/>
          </ItemPort>
        </Part>
        <Part name="hardpoint_manned_turret_tail">
          <Parts>
            <Part name="hardpoint_turret_tail_seat">
              <ItemPort>
                <Types><Type type="Seat"/></Types>
                <ControllerDef controllableTags="tailSeat">
                  <UserDef>
                    <PriorityGroups>
                      <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                        <tags tag="tail_turret">
                          <Priority value="exclusive_control"/>
                        </tags>
                      </PriorityGroup>
                    </PriorityGroups>
                  </UserDef>
                </ControllerDef>
              </ItemPort>
            </Part>
          </Parts>
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="tail_turret"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertContains('hardpoint_remote_turret_top', $result);
        self::assertNotContains('hardpoint_manned_turret_tail', $result);
    }

    /**
     * Turret with no_control priority (value=0) should NOT be bridge-controllable.
     */
    public function test_zero_priority_excludes_turret(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="turret_tag">
                      <Priority value="0"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_remote_turret">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="turret_tag"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, null);

        self::assertEmpty($result);
    }

    /**
     * Weapon controller in entity bridges pilot seat to turret tags (Paladin pattern).
     */
    public function test_entity_weapon_controller_bridge(): void
    {
        $vehicle = $this->createVehicle(<<<'XML'
<Vehicle.Test>
  <Parts>
    <Part name="Body">
      <Parts>
        <Part name="hardpoint_seat_pilot">
          <ItemPort>
            <Types><Type type="Seat"/></Types>
            <ControllerDef controllableTags="pilotSeat">
              <UserDef>
                <PriorityGroups>
                  <PriorityGroup itemType="WeaponController" defaultPriority="no_control">
                    <tags tag="weapon_controller_pilot">
                      <Priority value="100"/>
                    </tags>
                  </PriorityGroup>
                </PriorityGroups>
              </UserDef>
            </ControllerDef>
          </ItemPort>
        </Part>
        <Part name="hardpoint_remote_turret_left">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="Remote_Turret_Left"/>
          </ItemPort>
        </Part>
        <Part name="hardpoint_remote_turret_right">
          <ItemPort>
            <Types><Type type="TurretBase"/></Types>
            <ControllerDef controllableTags="Remote_Turret_Right"/>
          </ItemPort>
        </Part>
      </Parts>
    </Part>
  </Parts>
</Vehicle.Test>
XML);

        $entity = $this->createEntity(<<<'XML'
<EntityClassDefinition.Test __type="EntityClassDefinition">
  <Components>
    <SItemPortContainerComponentParams>
      <Ports>
        <Port Name="hardpoint_controller_weapon_pilot" __type="SItemPortDef">
          <Types><Type type="WeaponController"/></Types>
          <SItemPortDefParams>
            <ControllerDef controllableTags="weapon_controller_pilot"/>
            <SCItemControllableGroupParams>
              <priorityGroups>
                <SCItemPriorityGroupParam tag="Remote_Turret_Left"/>
                <SCItemPriorityGroupParam tag="Remote_Turret_Right"/>
              </priorityGroups>
            </SCItemControllableGroupParams>
          </SItemPortDefParams>
        </Port>
      </Ports>
    </SItemPortContainerComponentParams>
  </Components>
</EntityClassDefinition.Test>
XML);

        $mapper = new TurretControlMapper;
        $result = $mapper->getBridgeControllableTurrets($vehicle, $entity);

        self::assertContains('hardpoint_remote_turret_left', $result);
        self::assertContains('hardpoint_remote_turret_right', $result);
    }

    /**
     * WeaponDpsAggregator splits turret DPS based on turret control map.
     */
    public function test_weapon_dps_aggregator_uses_turret_control_map(): void
    {
        $parts = [
            [
                'Name' => 'hardpoint_remote_turret_top',
                'Port' => [
                    'PortName' => 'hardpoint_remote_turret_top',
                    'Category' => 'Remote turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('Turret.GunTurret'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    [
                                        'PortName' => 'hardpoint_class_2',
                                        'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $context = new \Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: ['hardpoint_remote_turret_top'],
        );

        $aggregator = new \Octfx\ScDataDumper\Services\Vehicle\WeaponDpsAggregator(
            new \Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker
        );
        $result = $aggregator->calculate($context);
        $weaponry = $result['Weaponry'];

        self::assertSame(420.0, $weaponry['PilotDps']);
        self::assertSame(504.0, $weaponry['PilotAlpha']);
        self::assertSame(300.0, $weaponry['PilotSustainedDps']);
        self::assertArrayNotHasKey('TurretDps', $weaponry);
        self::assertTrue($weaponry['Turrets'][0]['IsPilotSlaveable']);

        // Individual weapons should carry UUID and inherit IsPilotSlaveable
        $weapons = $weaponry['Turrets'][0]['Weapons'];
        self::assertCount(1, $weapons);
        self::assertSame('Laser_S3_UUID', $weapons[0]['UUID']);
        self::assertTrue($weapons[0]['IsPilotSlaveable']);
    }

    /**
     * WeaponDpsAggregator leaves non-bridge turret DPS in TurretDps.
     */
    public function test_weapon_dps_aggregator_non_bridge_turret_stays_separate(): void
    {
        $parts = [
            [
                'Name' => 'hardpoint_remote_turret_top',
                'Port' => [
                    'PortName' => 'hardpoint_remote_turret_top',
                    'Category' => 'Remote turrets',
                    'InstalledItem' => array_merge(
                        $this->makeItem('Turret.GunTurret'),
                        [
                            'stdItem' => [
                                'Ports' => [
                                    [
                                        'PortName' => 'hardpoint_class_2',
                                        'InstalledItem' => $this->makeWeapon('WeaponGun.Gun', 'Laser_S3', 420.0, 504.0, 300.0),
                                    ],
                                ],
                            ],
                        ]
                    ),
                ],
            ],
        ];

        $context = new \Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext(
            standardisedParts: $parts,
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
            turretControlMap: [], // empty = no bridge-controllable turrets
        );

        $aggregator = new \Octfx\ScDataDumper\Services\Vehicle\WeaponDpsAggregator(
            new \Octfx\ScDataDumper\Services\Vehicle\StandardisedPartWalker
        );
        $result = $aggregator->calculate($context);
        $weaponry = $result['Weaponry'];

        self::assertArrayNotHasKey('PilotDps', $weaponry);
        self::assertSame(420.0, $weaponry['TurretDps']);
        self::assertFalse($weaponry['Turrets'][0]['IsPilotSlaveable']);

        // Individual weapons should carry UUID and inherit IsPilotSlaveable=false
        $weapons = $weaponry['Turrets'][0]['Weapons'];
        self::assertCount(1, $weapons);
        self::assertSame('Laser_S3_UUID', $weapons[0]['UUID']);
        self::assertFalse($weapons[0]['IsPilotSlaveable']);
    }

    private function createVehicle(string $xml): Vehicle
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'vehicle_test_');
        file_put_contents($tempFile, $xml);

        $vehicle = new Vehicle;
        $vehicle->load($tempFile);

        unlink($tempFile);

        return $vehicle;
    }

    private function createEntity(string $xml): VehicleDefinition
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'entity_test_');
        file_put_contents($tempFile, $xml);

        $entity = new VehicleDefinition;
        $entity->load($tempFile);

        unlink($tempFile);

        return $entity;
    }

    private function makeItem(string $type, array $stdItem = []): array
    {
        return [
            'type' => $type,
            'stdItem' => $stdItem,
        ];
    }

    private function makeWeapon(string $type, string $className, float $dps, float $alpha, ?float $sustained): array
    {
        $damage = [
            'DpsTotal' => $dps,
            'AlphaTotal' => $alpha,
        ];
        if ($sustained !== null) {
            $damage['Sustained'] = $sustained;
        }

        return $this->makeItem($type, [
            'UUID' => $className . '_UUID',
            'ClassName' => $className,
            'Weapon' => [
                'Damage' => $damage,
            ],
        ]);
    }
}
