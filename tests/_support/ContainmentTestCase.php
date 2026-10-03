<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base class for the Batch 2A containment regression tests.
 *
 * Isolation guarantees, in order of strength:
 *
 *  1. Every connection is opened against the 'tests' database group, which points
 *     at corelynk_test -- a structural clone of corelynk_db holding no business data.
 *  2. assertIsolated() re-checks the live connection name on every setUp and aborts
 *     the whole run if it is ever anything other than corelynk_test. A test can
 *     therefore never write into corelynk_db even if the config is later changed.
 *  3. Each test runs inside a transaction that is unconditionally rolled back in
 *     tearDown, so the test database itself is left clean too.
 *
 * The 'tests' group deliberately keeps strictOn = false, matching production.
 * That is what allows these tests to detect SILENT truncation -- the failure mode
 * that produced every blank status in this system -- rather than relying on MySQL
 * to raise an error it does not raise in the configuration that actually runs.
 */
abstract class ContainmentTestCase extends TestCase
{
    protected const TEST_DB = 'corelynk_test';

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    /**
     * Wrap each test in a transaction that is rolled back in tearDown.
     *
     * Tests that drive application code which manages its OWN transaction must set this
     * to false: CI4 counts transaction depth, so an outer transaction here swallows the
     * inner transCommit() and the code under test can never observe its own committed
     * state. Such tests clean up by id in tearDown instead (see $cleanupFrom).
     */
    protected bool $useTransaction = true;

    /** table => id watermark taken in setUp, for tests that cannot use a transaction. */
    protected array $cleanupFrom = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect('tests');
        $this->assertIsolated();

        if ($this->useTransaction) {
            $this->db->transBegin();

            return;
        }

        foreach (array_keys($this->cleanupFrom) as $table) {
            $this->cleanupFrom[$table] = (int) ($this->db->query(
                "SELECT COALESCE(MAX(id), 0) AS m FROM {$table}"
            )->getRowArray()['m'] ?? 0);
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            if ($this->useTransaction) {
                // Always roll back. No containment test is allowed to persist a row.
                $this->db->transRollback();
            } else {
                // Everything created during the test has an id above the watermark.
                foreach ($this->cleanupFrom as $table => $watermark) {
                    $this->db->query("DELETE FROM {$table} WHERE id > ?", [$watermark]);
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Hard stop if we are not pointed at the isolated database.
     */
    protected function assertIsolated(): void
    {
        $name = $this->db->getDatabase();
        if ($name !== self::TEST_DB) {
            throw new \RuntimeException(
                "REFUSING TO RUN: tests are connected to '{$name}', expected '" . self::TEST_DB . "'. "
                . 'Aborting so that no test data can reach the business database.'
            );
        }
    }

    /**
     * Write a value to a column and read it straight back.
     *
     * This is the core primitive of the whole suite. With strictOn = false an
     * out-of-domain write returns success and stores '', so the only way to detect
     * it is to compare what came back with what went in.
     *
     * @return string the value as actually stored
     */
    protected function roundTrip(string $table, string $column, string $value, array $extra = []): string
    {
        // Document numbers carry UNIQUE indexes, so a fixed fixture value collides as soon
        // as the same domain is round-tripped more than once. Make every TEST- prefixed
        // fixture value unique per call; the column under test is never touched.
        static $seq = 0;
        $seq++;
        foreach ($extra as $k => $v) {
            if (is_string($v) && strpos($v, 'TEST-') === 0) {
                $extra[$k] = $v . '-' . $seq;
            }
        }

        $row = array_merge($extra, [$column => $value]);
        $this->db->table($table)->insert($row);
        $id = (int) $this->db->insertID();

        if ($id <= 0) {
            self::fail("Insert into {$table} did not return an id.");
        }

        $stored = $this->db->table($table)->select($column)->where('id', $id)->get()->getRowArray();

        return (string) ($stored[$column] ?? '__MISSING__');
    }

    /**
     * The ENUM members a column actually accepts, read from the live test schema.
     *
     * @return string[]
     */
    protected function enumMembers(string $table, string $column): array
    {
        $row = $this->db->query(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TEST_DB, $table, $column]
        )->getRowArray();

        if (! $row || stripos((string) $row['t'], 'enum(') !== 0) {
            return [];
        }

        $inner = substr((string) $row['t'], 5, -1);
        $out   = [];
        foreach (explode(',', $inner) as $part) {
            $out[] = trim($part, "' ");
        }

        return $out;
    }

    /** Read the source of an application file, for structural assertions. */
    protected function sourceOf(string $relativePath): string
    {
        $full = ROOTPATH . $relativePath;
        if (! is_file($full)) {
            self::fail("Source file not found: {$relativePath}");
        }

        return (string) file_get_contents($full);
    }
}
