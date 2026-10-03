<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Runs the Batch 2A containment regression tests.
 *
 * Run: php spark test:containment
 * Exits non-zero on any failure, so it can gate a deploy.
 *
 * WHY THIS COMMAND EXISTS
 * -----------------------
 * This project declares phpunit/phpunit ^10.5 in composer.json require-dev and ships a
 * phpunit.xml.dist pointing at ./tests, but vendor/phpunit/phpunit is empty -- the dev
 * dependencies were never installed -- and there is no composer binary available here,
 * so `phpunit` genuinely cannot be executed on this machine.
 *
 * Rather than ship untested containment fixes and defer the tests indefinitely, the
 * tests are written as ordinary PHPUnit test classes and this command drives them via
 * the small compatibility shim in tests/_support/PhpUnitShim.php. When the dev
 * dependencies are eventually installed, the shim defines nothing, the same test files
 * run unmodified under `vendor/bin/phpunit`, and this command can be deleted.
 *
 * SAFETY
 * ------
 * The command repoints the default database group at 'tests' (corelynk_test) BEFORE any
 * connection is opened, then refuses to run at all unless the live connection really is
 * corelynk_test. Application code called by the tests -- notably OdooController, which
 * calls Config\Database::connect() internally -- therefore cannot reach corelynk_db.
 */
class RunContainmentTests extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:containment';
    protected $description = 'Run the Batch 2A containment regression tests against the isolated test database.';

    private const REQUIRED_DB = 'corelynk_test';

    private int $passed = 0;
    private int $failed = 0;
    private int $errors = 0;

    /** @var array<int, string> */
    private array $failureDetail = [];

    public function run(array $params)
    {
        // ---- Isolation, established before anything connects. ----
        $dbConfig = config('Database');
        $dbConfig->defaultGroup = 'tests';

        $probe = \Config\Database::connect();
        $actual = $probe->getDatabase();

        if ($actual !== self::REQUIRED_DB) {
            CLI::error('REFUSING TO RUN.');
            CLI::write("The default connection resolved to '{$actual}', expected '" . self::REQUIRED_DB . "'.");
            CLI::write('Aborting so that no test row can be written into the business database.');

            return EXIT_ERROR;
        }

        CLI::write('Isolated test database: ' . CLI::color($actual, 'green'));
        CLI::newLine();

        require_once ROOTPATH . 'tests/_support/PhpUnitShim.php';
        require_once ROOTPATH . 'tests/_support/ContainmentTestCase.php';

        $usingRealPhpUnit = class_exists(\PHPUnit\Runner\Version::class);
        if (! $usingRealPhpUnit) {
            CLI::write('PHPUnit is not installed; running the same test classes via the shim.', 'yellow');
            CLI::newLine();
        }

        $files = glob(ROOTPATH . 'tests/Containment/*Test.php') ?: [];
        sort($files);

        if ($files === []) {
            CLI::error('No test files found in tests/Containment.');

            return EXIT_ERROR;
        }

        foreach ($files as $file) {
            require_once $file;
        }

        foreach ($this->testClasses($files) as $class) {
            $this->runClass($class);
        }

        return $this->report();
    }

    /**
     * Map the loaded files to their declared test classes.
     *
     * @param array<int, string> $files
     *
     * @return array<int, string>
     */
    private function testClasses(array $files): array
    {
        $classes = [];

        foreach ($files as $file) {
            $base  = basename($file, '.php');
            $class = 'Tests\\Containment\\' . $base;

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function runClass(string $class): void
    {
        CLI::write(CLI::color(str_replace('Tests\\Containment\\', '', $class), 'cyan'));

        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'test') !== 0) {
                continue;
            }

            $this->runMethod($class, $method->getName());
        }

        CLI::newLine();
    }

    private function runMethod(string $class, string $method): void
    {
        $label = $this->humanise($method);
        $test  = null;

        try {
            $test = new $class();
            $test->runSetUp();
            $test->{$method}();

            CLI::write('  ' . CLI::color('PASS', 'green') . '  ' . $label);
            $this->passed++;
        } catch (AssertionFailedError $e) {
            CLI::write('  ' . CLI::color('FAIL', 'red') . '  ' . $label);
            CLI::write('        ' . $e->getMessage(), 'red');
            $this->failed++;
            $this->failureDetail[] = $class . '::' . $method . ' -- ' . $e->getMessage();
        } catch (\Throwable $e) {
            CLI::write('  ' . CLI::color('ERROR', 'light_red') . ' ' . $label);
            CLI::write('        ' . get_class($e) . ': ' . $e->getMessage(), 'red');
            $this->errors++;
            $this->failureDetail[] = $class . '::' . $method . ' -- ' . get_class($e) . ': ' . $e->getMessage();
        } finally {
            if ($test !== null) {
                try {
                    $test->runTearDown();
                } catch (\Throwable $e) {
                    CLI::write('        teardown failed: ' . $e->getMessage(), 'yellow');
                }
            }
        }
    }

    private function humanise(string $method): string
    {
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', substr($method, 4));

        return strtolower((string) $words);
    }

    private function report(): int
    {
        $total = $this->passed + $this->failed + $this->errors;

        CLI::write(str_repeat('-', 60));
        CLI::write(sprintf(
            '%d test(s): %s passed, %s failed, %s errored.',
            $total,
            CLI::color((string) $this->passed, 'green'),
            CLI::color((string) $this->failed, $this->failed ? 'red' : 'green'),
            CLI::color((string) $this->errors, $this->errors ? 'red' : 'green')
        ));

        if ($this->failed === 0 && $this->errors === 0) {
            CLI::write('Containment verified.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::newLine();
        CLI::error('Containment NOT verified:');
        foreach ($this->failureDetail as $detail) {
            CLI::write('  - ' . $detail, 'red');
        }

        return EXIT_ERROR;
    }
}
