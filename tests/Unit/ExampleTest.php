<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Example test to verify PHPUnit setup is working correctly.
 * This test can serve as a template for future TDD development.
 */
class ExampleTest extends TestCase
{
    #[Test]
    public function it_confirms_test_setup_is_working(): void
    {
        // This simple assertion verifies the test framework is functional
        $this->assertTrue(true);
    }

    #[Test]
    public function it_confirms_basic_assertion_methods(): void
    {
        // Demonstrate common assertion methods
        $this->assertEquals(42, 42);
        $this->assertNotEmpty('hello world');
        $this->assertIsArray([1, 2, 3]);
        $this->assertStringContainsString('test', 'unit test');
    }
}
