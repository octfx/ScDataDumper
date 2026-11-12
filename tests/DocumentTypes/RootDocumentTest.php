<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\DocumentTypes;

use Octfx\ScDataDumper\Tests\Fixtures\TestRootDocument;
use PHPUnit\Framework\TestCase;

final class RootDocumentTest extends TestCase
{
    public function test_interleaved_same_named_children_are_all_kept_in_document_order(): void
    {
        $tasks = $this->loadFixtureCarryableTasks();

        // 7 XML children: 3 MoveToAnimate, 2 BespokeTake, 1 BespokePlace, 1 SetInteractionState.
        self::assertCount(7, $tasks);

        // Repeated names aggregate into the numeric list, unique names stay keyed.
        self::assertSame(
            ['fragmentId' => 'Take', 'bForceExactPositioning' => 0.0],
            $tasks[0]
        );
        self::assertSame(['fragmentId' => 'Take'], $tasks[1]);
        self::assertSame(
            ['fragmentId' => 'Place', 'bForceExactPositioning' => 0.0],
            $tasks[2]
        );
        self::assertSame(
            ['fragmentId' => 'Take', 'fragTag' => 'first_select', 'bForceExactPositioning' => 0.0],
            $tasks[3]
        );
        self::assertSame(
            ['fragmentId' => 'Take', 'fragTag' => 'first_select'],
            $tasks[4]
        );
        self::assertSame(
            ['fragmentId' => 'Place'],
            $tasks['SSequencerBespokePlaceCarryableTaskParams']
        );
        self::assertSame(
            ['state' => ['value' => 'SInteractionState[928B]']],
            $tasks['SSequencerSetInteractionStateCarryableTaskParams']
        );
    }

    public function test_adjacent_same_named_children_still_aggregate_into_a_list(): void
    {
        $component = $this->loadFixtureComponent();

        self::assertSame(
            [
                ['fragmentId' => 'Take'],
                ['fragmentId' => 'Place'],
            ],
            $component['adjacentTasks']
        );
    }

    public function test_single_occurrence_child_stays_keyed(): void
    {
        $component = $this->loadFixtureComponent();

        self::assertSame(
            ['fragmentId' => 'Place'],
            $component['singleTask']['SSequencerBespokePlaceCarryableTaskParams']
        );
    }

    private function loadFixtureCarryableTasks(): array
    {
        return $this->loadFixtureComponent()['carryableTasks'];
    }

    private function loadFixtureComponent(): array
    {
        $doc = new TestRootDocument;
        $doc->load(__DIR__.'/../Fixtures/xml/volt_carryable_tasks.xml');

        return $doc->toArray()['Components']['SEntityComponentCarryableParams'];
    }
}
