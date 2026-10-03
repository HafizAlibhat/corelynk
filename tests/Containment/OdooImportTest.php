<?php

namespace Tests\Containment;

use App\Controllers\OdooController;
use Tests\Support\ContainmentTestCase;

/**
 * A test double for OdooController.
 *
 * OdooController::postJson() is protected, so it can be overridden to return canned
 * Odoo JSON-RPC responses. That makes it possible to drive the REAL importCustomers()
 * code path -- the one that used to delete every customer -- with no network access
 * and with full control over the failure mode.
 */
final class FakeOdooController extends OdooController
{
    /** One of: login_fail, fetch_fail, empty_payload, success */
    public string $scenario = 'login_fail';

    /** @var array<int, array<string, mixed>> partners returned on the first page */
    public array $partners = [];

    protected function postJson(string $url, array $payload)
    {
        $service = $payload['params']['service'] ?? '';

        if ($service === 'common') {
            // Odoo unreachable / bad credentials.
            if ($this->scenario === 'login_fail') {
                return ['error' => ['message' => 'connection refused']];
            }

            return ['result' => 1];
        }

        // res.partner search_read
        if ($this->scenario === 'fetch_fail') {
            return ['error' => ['message' => 'internal server error']];
        }

        if ($this->scenario === 'empty_payload') {
            return ['result' => []];
        }

        $opts   = $payload['params']['args'][6] ?? [];
        $offset = (int) ($opts['offset'] ?? 0);

        // Everything on the first page; later pages are empty so the loop terminates.
        return ['result' => $offset === 0 ? $this->partners : []];
    }
}

/**
 * TEST E -- an unreachable or invalid Odoo response cannot change customer or
 *           address counts.
 * TEST F -- a successful sync updates an already-linked customer IN PLACE and
 *           preserves its local primary key.
 *
 * P0-2: importCustomers() previously ran, before contacting Odoo at all and outside
 * any transaction:
 *
 *     DELETE FROM customer_addresses;
 *     DELETE FROM customers;
 *
 * with the failure swallowed into log_message(). If Odoo was unreachable the method
 * destroyed all 412 customers and then returned 'login_failed'. There is no soft
 * delete on customers, no foreign key referencing them, and no audit trail.
 *
 * Test F matters independently of the delete: quotations, sales orders and invoices
 * reference the LOCAL numeric customers.id, and no document snapshots the customer
 * name. A delete-and-reinsert would reassign primary keys and silently repoint every
 * historical document at the wrong party -- so even a SUCCESSFUL wholesale reimport
 * was unsafe.
 */
final class OdooImportTest extends ContainmentTestCase
{
    /**
     * importCustomers() runs its own transBegin/transCommit. An outer test transaction
     * would nest inside it, so the controller's commit would never land and every
     * success-path assertion would read pre-sync state. Clean up by id watermark instead.
     */
    protected bool $useTransaction = false;

    /** Children first: customer_addresses references customers. */
    protected array $cleanupFrom = ['customer_addresses' => 0, 'customers' => 0];

    private function makeController(string $scenario, array $partners = []): FakeOdooController
    {
        $controller = new FakeOdooController();

        $request  = \Config\Services::request();
        $response = \Config\Services::response();
        $logger   = \Config\Services::logger();

        // importCustomers() refuses anything other than POST.
        if (method_exists($request, 'withMethod')) {
            $request = $request->withMethod('POST');
        }

        $controller->initController($request, $response, $logger);

        $controller->scenario = $scenario;
        $controller->partners = $partners;

        return $controller;
    }

    private function counts(): array
    {
        return [
            'customers' => (int) $this->db->table('customers')->countAllResults(),
            'addresses' => (int) $this->db->table('customer_addresses')->countAllResults(),
        ];
    }

    private function seedCustomer(int $odooId, string $name, string $code): int
    {
        $this->db->table('customers')->insert([
            'customer_code' => $code,
            'name'          => $name,
            'odoo_id'       => $odooId,
            'email'         => strtolower($code) . '@example.test',
            'status'        => 'active',
        ]);

        return (int) $this->db->insertID();
    }

    private function decode($response): array
    {
        $body = is_string($response) ? $response : (string) $response->getBody();

        return json_decode($body, true) ?: [];
    }

    // ---------------------------------------------------------------- TEST E

    public function testUnreachableOdooCannotChangeCustomerOrAddressCounts(): void
    {
        $this->seedCustomer(5001, 'Existing Odoo Customer', 'TEST-C-001');
        $this->seedCustomer(5002, 'Another Odoo Customer', 'TEST-C-002');
        $before = $this->counts();

        $result = $this->decode($this->makeController('login_fail')->importCustomers());

        self::assertFalse($result['ok'] ?? true, 'A failed Odoo login reported success.');
        self::assertSame('login_failed', $result['error'] ?? '');
        self::assertFalse($result['changed'] ?? true, 'The endpoint did not report that nothing changed.');

        self::assertSame(
            $before,
            $this->counts(),
            'P0-2 REGRESSION: a failed Odoo login changed local customer data. '
            . 'This is the defect that could destroy all 412 customers with no recovery path.'
        );
    }

    public function testMidPaginationFetchFailureCannotChangeCounts(): void
    {
        $this->seedCustomer(5003, 'Existing Odoo Customer', 'TEST-C-003');
        $before = $this->counts();

        $result = $this->decode($this->makeController('fetch_fail')->importCustomers());

        self::assertFalse($result['ok'] ?? true);
        self::assertSame('fetch_failed', $result['error'] ?? '');
        self::assertSame(
            $before,
            $this->counts(),
            'A malformed partner page changed local data. It used to break out of the loop '
            . 'and still report ok, producing a silent partial sync.'
        );
    }

    /**
     * An empty payload must be treated as a failed fetch. Acting on it would deactivate
     * every Odoo-sourced customer at once.
     */
    public function testEmptyPayloadIsRejectedAndChangesNothing(): void
    {
        $this->seedCustomer(5004, 'Existing Odoo Customer', 'TEST-C-004');
        $before = $this->counts();

        $result = $this->decode($this->makeController('empty_payload')->importCustomers());

        self::assertFalse($result['ok'] ?? true);
        self::assertSame('empty_payload', $result['error'] ?? '');
        self::assertSame($before, $this->counts(), 'An empty Odoo payload changed local data.');
    }

    /**
     * The endpoint must never delete, in any scenario.
     */
    public function testImportContainsNoDeleteStatement(): void
    {
        $src = $this->sourceOf('app/Controllers/OdooController.php');

        $start = strpos($src, 'public function importCustomers()');
        self::assertNotSame(false, $start, 'importCustomers not found.');
        $body = substr($src, $start, 12000);

        // Strip comments so the historical explanation does not trip the assertion.
        $body = preg_replace('~^\s*//.*$~m', '', $body);

        self::assertStringNotContainsString(
            'DELETE FROM customers',
            (string) $body,
            'P0-2 REGRESSION: the wholesale customer delete is back.'
        );
        self::assertStringNotContainsString(
            'DELETE FROM customer_addresses',
            (string) $body,
            'P0-2 REGRESSION: the wholesale address delete is back.'
        );
    }

    // ---------------------------------------------------------------- TEST F

    public function testSuccessfulSyncPreservesTheLocalPrimaryKey(): void
    {
        $localId = $this->seedCustomer(7001, 'Original Name', 'TEST-C-KEEP');
        $before  = $this->counts();

        $result = $this->decode($this->makeController('success', [
            [
                'id'     => 7001,
                'name'   => 'Renamed In Odoo',
                'email'  => 'renamed@example.test',
                'phone'  => '111',
                'street' => '1 Test Street',
                'city'   => 'Testville',
            ],
        ])->importCustomers());

        self::assertTrue($result['ok'] ?? false, 'A valid sync reported failure: ' . json_encode($result));
        self::assertSame(0, $result['deleted'] ?? -1, 'The sync reported deletions.');

        $row = $this->db->table('customers')->where('odoo_id', 7001)->get()->getRowArray();

        self::assertNotNull($row, 'The linked customer disappeared during sync.');
        self::assertSame(
            $localId,
            (int) $row['id'],
            'The local primary key changed during sync. Every quotation, sales order and '
            . 'invoice references customers.id and no document snapshots the customer name, '
            . 'so this would silently repoint history at the wrong party.'
        );
        self::assertSame('Renamed In Odoo', $row['name'], 'The in-place update did not apply.');

        // Updated, not duplicated.
        self::assertSame(
            $before['customers'],
            $this->counts()['customers'],
            'The sync created a duplicate customer instead of updating in place.'
        );
    }

    /**
     * Locally-created customers (no Odoo link) must be left completely alone -- not
     * deleted, and not deactivated by the missing-partner sweep.
     */
    public function testLocallyCreatedCustomersAreNeverTouched(): void
    {
        $this->db->table('customers')->insert([
            'customer_code' => 'TEST-C-LOCAL',
            'name'          => 'Locally Created Customer',
            'odoo_id'       => null,
            'status'        => 'active',
        ]);
        $localOnlyId = (int) $this->db->insertID();

        $this->decode($this->makeController('success', [
            ['id' => 7002, 'name' => 'Some Odoo Partner'],
        ])->importCustomers());

        $row = $this->db->table('customers')->where('id', $localOnlyId)->get()->getRowArray();

        self::assertNotNull($row, 'A locally-created customer was deleted by the Odoo sync.');
        self::assertSame(
            'active',
            $row['status'],
            'A locally-created customer was deactivated by the Odoo sync. Only Odoo-sourced '
            . 'customers may be deactivated when absent from the payload.'
        );
    }

    /**
     * An Odoo-sourced customer missing from the payload is deactivated, never deleted.
     */
    public function testMissingOdooCustomerIsDeactivatedNotDeleted(): void
    {
        $goneId = $this->seedCustomer(7003, 'No Longer In Odoo', 'TEST-C-GONE');

        $this->decode($this->makeController('success', [
            ['id' => 7004, 'name' => 'Still In Odoo'],
        ])->importCustomers());

        $row = $this->db->table('customers')->where('id', $goneId)->get()->getRowArray();

        self::assertNotNull($row, 'A customer absent from Odoo was physically deleted.');
        self::assertSame('inactive', $row['status'], 'The absent customer was not deactivated.');
    }
}
