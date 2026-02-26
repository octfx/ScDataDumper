<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadItems;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LoadItemsTest extends TestCase
{
    private LoadItems $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new LoadItems;
    }

    public function test_type_filter_extends_defaults_instead_of_replacing_them(): void
    {
        $excluded = $this->invokeBuildTypeFilterAvoidList('Ship.Weapon.Gun, noitem_player');

        self::assertContains('undefined', $excluded);
        self::assertContains('noitem_player', $excluded);
        self::assertContains('ship.weapon.gun', $excluded);
        self::assertSame(1, $this->countOccurrences('noitem_player', $excluded));
    }

    public function test_type_filter_matching_is_case_insensitive_and_exact(): void
    {
        $excluded = $this->invokeBuildTypeFilterAvoidList('  SeAt.Component  ');

        self::assertTrue($this->invokeIsTypeExcluded('seat.component', $excluded));
        self::assertTrue($this->invokeIsTypeExcluded('SEAT.COMPONENT', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded('seat', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded('seat.component.extra', $excluded));
    }

    public function test_type_filter_ignores_empty_tokens_and_deduplicates(): void
    {
        $excluded = $this->invokeBuildTypeFilterAvoidList(' , , BUTTON, button , custom , custom ,, ');

        self::assertSame(1, $this->countOccurrences('button', $excluded));
        self::assertSame(1, $this->countOccurrences('custom', $excluded));
        self::assertSame(0, $this->countOccurrences('', $excluded));
    }

    public function test_wildcard_tokens_are_treated_as_literal_values(): void
    {
        $excluded = $this->invokeBuildTypeFilterAvoidList('ship.*');

        self::assertTrue($this->invokeIsTypeExcluded('ship.*', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded('ship.weapon.gun', $excluded));
        self::assertFalse($this->invokeIsTypeExcluded('ship', $excluded));
    }

    /**
     * @return array<int, string>
     */
    private function invokeBuildTypeFilterAvoidList(mixed $typeFilter): array
    {
        $method = new ReflectionMethod(LoadItems::class, 'buildTypeFilterAvoidList');

        $result = $method->invoke($this->command, $typeFilter);
        self::assertIsArray($result);

        return $result;
    }

    /**
     * @param array<int, string> $excludedTypes
     */
    private function invokeIsTypeExcluded(string $type, array $excludedTypes): bool
    {
        $method = new ReflectionMethod(LoadItems::class, 'isTypeExcluded');

        $result = $method->invoke($this->command, $type, $excludedTypes);
        self::assertIsBool($result);

        return $result;
    }

    /**
     * @param array<int, string> $values
     */
    private function countOccurrences(string $needle, array $values): int
    {
        return count(array_keys($values, $needle, true));
    }
}
