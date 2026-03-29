<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Services\Vehicle;

use Closure;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataCalculator;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataContext;
use Octfx\ScDataDumper\Services\Vehicle\VehicleDataOrchestrator;
use PHPUnit\Framework\TestCase;

final class VehicleDataOrchestratorTest extends TestCase
{
    public function test_calculators_execute_in_priority_order(): void
    {
        $executionOrder = [];

        $highPriority = new TestVehicleDataCalculator(
            priority: 30,
            resultCallback: static function (VehicleDataContext $context) use (&$executionOrder): array {
                $executionOrder[] = 'high';

                return ['high' => $context->isSpaceship];
            }
        );

        $lowPriority = new TestVehicleDataCalculator(
            priority: 10,
            resultCallback: static function (VehicleDataContext $context) use (&$executionOrder): array {
                $executionOrder[] = 'low';

                return ['low' => $context->isSpaceship];
            }
        );

        $midPriority = new TestVehicleDataCalculator(
            priority: 20,
            resultCallback: static function (VehicleDataContext $context) use (&$executionOrder): array {
                $executionOrder[] = 'mid';

                return ['mid' => $context->isSpaceship];
            }
        );

        $orchestrator = new VehicleDataOrchestrator([
            $highPriority,
            $lowPriority,
            $midPriority,
        ]);

        $result = $orchestrator->calculate($this->makeContext());

        self::assertSame(['low', 'mid', 'high'], $executionOrder);
        self::assertSame(
            [
                'low' => true,
                'mid' => true,
                'high' => true,
            ],
            $result
        );
    }

    public function test_skips_calculators_when_can_calculate_returns_false(): void
    {
        $executionOrder = [];

        $skipped = new TestVehicleDataCalculator(
            priority: 10,
            canCalculateResult: false,
            resultCallback: static function () use (&$executionOrder): array {
                $executionOrder[] = 'skipped';

                return ['skipped' => true];
            }
        );

        $executed = new TestVehicleDataCalculator(
            priority: 20,
            resultCallback: static function () use (&$executionOrder): array {
                $executionOrder[] = 'executed';

                return ['executed' => true];
            }
        );

        $orchestrator = new VehicleDataOrchestrator([
            $executed,
            $skipped,
        ]);

        $result = $orchestrator->calculate($this->makeContext());

        self::assertSame(['executed'], $executionOrder);
        self::assertSame(1, $skipped->canCalculateCalls);
        self::assertSame(0, $skipped->calculateCalls);
        self::assertSame(['executed' => true], $result);
    }

    public function test_passes_merged_intermediate_results_to_later_calculators(): void
    {
        $secondCalculatorIntermediate = null;
        $thirdCalculatorIntermediate = null;

        $first = new TestVehicleDataCalculator(
            priority: 10,
            result: [
                'alpha' => 'A',
                'shared' => 'first',
            ]
        );

        $second = new TestVehicleDataCalculator(
            priority: 20,
            resultCallback: static function (VehicleDataContext $context) use (&$secondCalculatorIntermediate): array {
                $secondCalculatorIntermediate = $context->intermediateResults;

                return [
                    'beta' => $context->intermediateResults['alpha'] ?? null,
                    'shared' => 'second',
                ];
            }
        );

        $third = new TestVehicleDataCalculator(
            priority: 30,
            resultCallback: static function (VehicleDataContext $context) use (&$thirdCalculatorIntermediate): array {
                $thirdCalculatorIntermediate = $context->intermediateResults;

                return [
                    'gamma' => $context->intermediateResults['shared'] ?? null,
                ];
            }
        );

        $orchestrator = new VehicleDataOrchestrator([
            $third,
            $first,
            $second,
        ]);

        $result = $orchestrator->calculate($this->makeContext());

        self::assertSame(
            [
                'alpha' => 'A',
                'shared' => 'first',
            ],
            $secondCalculatorIntermediate
        );

        self::assertSame(
            [
                'alpha' => 'A',
                'shared' => 'second',
                'beta' => 'A',
            ],
            $thirdCalculatorIntermediate
        );

        self::assertSame(
            [
                'alpha' => 'A',
                'shared' => 'second',
                'beta' => 'A',
                'gamma' => 'second',
            ],
            $result
        );
    }

    private function makeContext(): VehicleDataContext
    {
        return new VehicleDataContext(
            standardisedParts: [],
            portSummary: [],
            ifcsLoadoutEntry: null,
            mass: 0.0,
            loadoutMass: 0.0,
            isVehicle: false,
            isGravlev: false,
            isSpaceship: true,
        );
    }
}

final class TestVehicleDataCalculator implements VehicleDataCalculator
{
    public int $canCalculateCalls = 0;

    public int $calculateCalls = 0;

    public function __construct(
        private int $priority,
        private bool $canCalculateResult = true,
        private array $result = [],
        private ?Closure $canCalculateCallback = null,
        private ?Closure $resultCallback = null,
    ) {}

    public function canCalculate(VehicleDataContext $context): bool
    {
        $this->canCalculateCalls++;

        if ($this->canCalculateCallback !== null) {
            return ($this->canCalculateCallback)($context);
        }

        return $this->canCalculateResult;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $this->calculateCalls++;

        if ($this->resultCallback !== null) {
            return ($this->resultCallback)($context);
        }

        return $this->result;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
