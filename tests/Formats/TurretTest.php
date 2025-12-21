<?php

namespace Tests\Formats;

use DOMDocument;
use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\ScUnpacked\Turret;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TurretTest extends TestCase
{
    #[Test]
    public function it_extracts_axis_speeds_and_limits(): void
    {
        $xml = <<<'XML'
<EntityClassDefinition.Test>
  <Components>
    <SCItemTurretParams rotationStyle="SingleAxis" recenterIfUnused="0">
      <movementList>
        <SCItemTurretJointMovementParams movementTag="00000000-0000-0000-0000-000000000000" jointName="gimbal_mount" slavedOnly="0" restrictTargetAngles="0">
          <yawAxis>
            <SCItemTurretJointMovementAxisParams speed="50" acceleration_timeToFullSpeed="0.3" jerk_timeToFullAcceleration="0.1" accelerationDecay="5">
              <angleLimits>
                <SCItemTurretStandardAngleLimitParams LowestAngle="-180" HighestAngle="180" />
              </angleLimits>
            </SCItemTurretJointMovementAxisParams>
          </yawAxis>
        </SCItemTurretJointMovementParams>
        <SCItemTurretJointMovementParams movementTag="00000000-0000-0000-0000-000000000000" jointName="weapon_mounts" slavedOnly="0" restrictTargetAngles="0">
          <pitchAxis>
            <SCItemTurretJointMovementAxisParams speed="50" acceleration_timeToFullSpeed="0.3" jerk_timeToFullAcceleration="0.1" accelerationDecay="5">
              <angleLimits>
                <SCItemTurretStandardAngleLimitParams LowestAngle="-90" HighestAngle="0" />
              </angleLimits>
            </SCItemTurretJointMovementAxisParams>
          </pitchAxis>
        </SCItemTurretJointMovementParams>
      </movementList>
    </SCItemTurretParams>
  </Components>
</EntityClassDefinition.Test>
XML;

        $doc = new DOMDocument;
        $doc->loadXML($xml);

        $element = new Element($doc->documentElement);
        $format = new Turret($element);

        $result = $format->toArray();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('MovementList', $result);
        $this->assertCount(2, $result['MovementList']);

        $yaw = $result['MovementList'][0]['YawAxis'];
        $this->assertSame(50.0, $yaw['Speed']);
        $this->assertSame(0.3, $yaw['AccelerationTimeToFullSpeed']);
        $this->assertSame(5.0, $yaw['AccelerationDecay']);
        $this->assertSame(-180.0, $yaw['AngleLimits'][0]['LowestAngle']);
        $this->assertSame(180.0, $yaw['AngleLimits'][0]['HighestAngle']);

        $pitch = $result['MovementList'][1]['PitchAxis'];
        $this->assertSame(50.0, $pitch['Speed']);
        $this->assertSame(0.3, $pitch['AccelerationTimeToFullSpeed']);
        $this->assertSame(5.0, $pitch['AccelerationDecay']);
        $this->assertSame(-90.0, $pitch['AngleLimits'][0]['LowestAngle']);
        $this->assertSame(0.0, $pitch['AngleLimits'][0]['HighestAngle']);
    }

    #[Test]
    public function it_returns_null_when_no_turret_component_present(): void
    {
        $xml = <<<'XML'
<EntityClassDefinition.Test>
  <Components>
  </Components>
</EntityClassDefinition.Test>
XML;

        $doc = new DOMDocument;
        $doc->loadXML($xml);

        $element = new Element($doc->documentElement);
        $format = new Turret($element);

        $this->assertNull($format->toArray());
    }
}
