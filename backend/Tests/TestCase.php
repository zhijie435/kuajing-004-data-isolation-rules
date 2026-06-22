<?php

namespace App\Tests;

use App\Core\Context\TenantContext;

abstract class TestCase
{
    protected string $currentTest = '';
    protected int $passed = 0;
    protected int $failed = 0;
    protected array $failures = [];

    protected function setUp(): void
    {
        TenantContext::getInstance()->reset();
    }

    protected function tearDown(): void
    {
        TenantContext::getInstance()->reset();
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if ($condition) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected true, got false";
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected false, got true";
        }
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
        } else {
            $this->failed++;
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected {$expectedStr}, got {$actualStr}";
        }
    }

    protected function assertNull($actual, string $message = ''): void
    {
        if ($actual === null) {
            $this->passed++;
        } else {
            $this->failed++;
            $actualStr = var_export($actual, true);
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected null, got {$actualStr}";
        }
    }

    protected function assertNotNull($actual, string $message = ''): void
    {
        if ($actual !== null) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected not null, got null";
        }
    }

    protected function assertNotEmpty($actual, string $message = ''): void
    {
        if (!empty($actual)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected not empty, got empty";
        }
    }

    protected function assertEmpty($actual, string $message = ''): void
    {
        if (empty($actual)) {
            $this->passed++;
        } else {
            $this->failed++;
            $actualStr = var_export($actual, true);
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected empty, got {$actualStr}";
        }
    }

    protected function assertContains($needle, array $haystack, string $message = ''): void
    {
        if (in_array($needle, $haystack, true)) {
            $this->passed++;
        } else {
            $this->failed++;
            $needleStr = var_export($needle, true);
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected {$needleStr} in array";
        }
    }

    protected function assertNotContains($needle, array $haystack, string $message = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            $this->passed++;
        } else {
            $this->failed++;
            $needleStr = var_export($needle, true);
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected {$needleStr} not in array";
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected '{$needle}' not found in '{$haystack}'";
        }
    }

    protected function expectException(string $exceptionClass, callable $fn, string $message = ''): void
    {
        try {
            $fn();
            $this->failed++;
            $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected exception {$exceptionClass} was not thrown";
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                $this->passed++;
            } else {
                $this->failed++;
                $actualClass = get_class($e);
                $this->failures[] = "✗ {$this->currentTest}: {$message} - Expected exception {$exceptionClass}, got {$actualClass}";
            }
        }
    }

    public function run(): array
    {
        $reflection = new \ReflectionClass($this);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'test')) {
                $this->currentTest = $methodName;
                $this->setUp();
                try {
                    $this->$methodName();
                } catch (\Throwable $e) {
                    $this->failed++;
                    $this->failures[] = "✗ {$this->currentTest}: Uncaught exception: " . $e->getMessage();
                }
                $this->tearDown();
            }
        }

        return [
            'class' => get_class($this),
            'passed' => $this->passed,
            'failed' => $this->failed,
            'failures' => $this->failures,
        ];
    }

    protected function generateJwtToken(array $payload): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(strtr(json_encode($payload), '+/', '-_'));
        $signature = base64_encode('test_signature');
        return "{$header}.{$payload}.{$signature}";
    }
}
