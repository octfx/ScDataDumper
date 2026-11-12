<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes;

use Octfx\ScDataDumper\DocumentTypes\Mission\MissionBrokerEntry;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;

final class MissionBrokerEntryTest extends ScDataTestCase
{
    public function test_get_deadline_exports_show_timer_threshold(): void
    {
        $entry = new MissionBrokerEntry;
        $entry->loadXML(
            <<<'XML'
            <MissionBrokerMission>
              <missionDeadline missionCompletionTime="300" missionAutoEnd="1" missionResultAfterTimerEnd="Failed" remainingTimeToShowTimer="15" missionEndReason="@HaulCargo_Fail_TimeOut" />
            </MissionBrokerMission>
            XML
        );

        $deadline = $entry->getDeadline();

        self::assertSame([
            'completion_time' => 300,
            'auto_end' => true,
            'result_after_timer' => 'Failed',
            'show_timer' => 15,
            'end_reason' => '@HaulCargo_Fail_TimeOut',
        ], $deadline);
    }

    public function test_get_deadline_omits_show_timer_when_zero(): void
    {
        $entry = new MissionBrokerEntry;
        $entry->loadXML(
            <<<'XML'
            <MissionBrokerMission>
              <missionDeadline missionCompletionTime="300" missionAutoEnd="1" remainingTimeToShowTimer="0" />
            </MissionBrokerMission>
            XML
        );

        self::assertSame([
            'completion_time' => 300,
            'auto_end' => true,
        ], $entry->getDeadline());
    }
}
