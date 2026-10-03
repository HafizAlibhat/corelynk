<?php

/**
 * Minimal PHPUnit compatibility shim.
 *
 * This project declares phpunit/phpunit ^10.5 in composer.json require-dev and
 * ships a phpunit.xml.dist pointing at ./tests -- but vendor/phpunit/phpunit is
 * empty (the dev dependencies were never installed) and there is no composer
 * binary on this machine, so `phpunit` cannot be executed here.
 *
 * Rather than postpone the regression tests until someone runs `composer install`,
 * the tests in tests/Containment are written as ordinary PHPUnit test classes and
 * this file supplies just enough of \PHPUnit\Framework\TestCase for them to run
 * today via `php spark test:containment`.
 *
 * The shim defines nothing if real PHPUnit is already loaded, so once the dev
 * dependencies are installed the same test files run unmodified under phpunit
 * with no changes and no duplicated assertions.
 *
 * Only the assertions actually used by the containment tests are implemented.
 */

namespace PHPUnit\Framework;

if (! class_exists(AssertionFailedError::class, false)) {
    class AssertionFailedError extends \Exception
    {
    }
}

if (! class_exists(TestCase::class, false)) {
    abstract class TestCase
    {
        /** @var int Number of assertions executed, for reporting. */
        public static $assertionCount = 0;

        protected function setUp(): void
        {
        }

        protected function tearDown(): void
        {
        }

        /** Public entry points so a non-PHPUnit runner can drive the lifecycle. */
        public function runSetUp(): void
        {
            $this->setUp();
        }

        public function runTearDown(): void
        {
            $this->tearDown();
        }

        private static function describe($v): string
        {
            if ($v === null) {
                return 'NULL';
            }
            if ($v === true) {
                return 'true';
            }
            if ($v === false) {
                return 'false';
            }
            if (is_scalar($v)) {
                return "'" . $v . "' (" . gettype($v) . ')';
            }

            return gettype($v);
        }

        public static function fail(string $message = ''): void
        {
            throw new AssertionFailedError($message);
        }

        public static function assertTrue($condition, string $message = ''): void
        {
            self::$assertionCount++;
            if ($condition !== true) {
                self::fail($message ?: 'Failed asserting that ' . self::describe($condition) . ' is true.');
            }
        }

        public static function assertFalse($condition, string $message = ''): void
        {
            self::$assertionCount++;
            if ($condition !== false) {
                self::fail($message ?: 'Failed asserting that ' . self::describe($condition) . ' is false.');
            }
        }

        public static function assertSame($expected, $actual, string $message = ''): void
        {
            self::$assertionCount++;
            if ($expected !== $actual) {
                self::fail(($message ?: 'Failed asserting two values are identical.')
                    . ' Expected ' . self::describe($expected) . ', got ' . self::describe($actual) . '.');
            }
        }

        public static function assertNotSame($expected, $actual, string $message = ''): void
        {
            self::$assertionCount++;
            if ($expected === $actual) {
                self::fail(($message ?: 'Failed asserting two values are not identical.')
                    . ' Both were ' . self::describe($actual) . '.');
            }
        }

        public static function assertEquals($expected, $actual, string $message = ''): void
        {
            self::$assertionCount++;
            if ($expected != $actual) {
                self::fail(($message ?: 'Failed asserting two values are equal.')
                    . ' Expected ' . self::describe($expected) . ', got ' . self::describe($actual) . '.');
            }
        }

        public static function assertNull($actual, string $message = ''): void
        {
            self::$assertionCount++;
            if ($actual !== null) {
                self::fail($message ?: 'Failed asserting that ' . self::describe($actual) . ' is null.');
            }
        }

        public static function assertNotNull($actual, string $message = ''): void
        {
            self::$assertionCount++;
            if ($actual === null) {
                self::fail($message ?: 'Failed asserting that value is not null.');
            }
        }

        public static function assertCount(int $expected, $haystack, string $message = ''): void
        {
            self::$assertionCount++;
            $actual = is_countable($haystack) ? count($haystack) : -1;
            if ($actual !== $expected) {
                self::fail(($message ?: 'Failed asserting count.')
                    . ' Expected ' . $expected . ', got ' . $actual . '.');
            }
        }

        public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
        {
            self::$assertionCount++;
            if (strpos($haystack, $needle) === false) {
                self::fail(($message ?: 'Failed asserting that string contains needle.')
                    . " Needle: '{$needle}'.");
            }
        }

        public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
        {
            self::$assertionCount++;
            if (strpos($haystack, $needle) !== false) {
                self::fail(($message ?: 'Failed asserting that string does not contain needle.')
                    . " Needle: '{$needle}'.");
            }
        }

        public static function assertGreaterThan($expected, $actual, string $message = ''): void
        {
            self::$assertionCount++;
            if (! ($actual > $expected)) {
                self::fail(($message ?: 'Failed asserting greater than.')
                    . ' Expected > ' . self::describe($expected) . ', got ' . self::describe($actual) . '.');
            }
        }
    }
}
