# Test Directory

This directory contains PHPUnit tests for the ScDataDumper project.

## Running Tests

### Locally
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/ExampleTest.php

# Run with verbose output
./vendor/bin/phpunit --verbose

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
```

### In Docker
The Docker container is configured for production use and does not include dev dependencies.
Tests should be run locally. To run tests, ensure dev dependencies are installed:

```bash
composer install --dev
./vendor/bin/phpunit
```

## Directory Structure

- `Unit/` - Unit tests for individual components
- `Feature/` - Feature/integration tests
- `Formats/` - Tests for format output classes
- `Helper/` - Tests for helper utilities
- `Vehicle/` - Tests for vehicle-specific functionality

## Writing Tests

Follow the pattern in `tests/Unit/ExampleTest.php` for new tests. Use PHPUnit's
`#[Test]` attribute for test methods and extend `PHPUnit\Framework\TestCase`.

Example:
```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        $this->assertTrue(true);
    }
}
```

## TDD Workflow

1. Write a failing test
2. Write the minimal code to make the test pass
3. Refactor if needed
4. Repeat
