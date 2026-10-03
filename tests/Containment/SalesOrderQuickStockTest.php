<?php

namespace Tests\Containment;

use App\Services\SalesOrderQuickStockService;
use Config\Database;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesOrderQuickStockTest extends TestCase
{
    public function testIdempotencyMigrationRunsUpAndDownOnDisposableDatabase(): void
    {
        $db = Database::connect('tests');
        self::assertSame('corelynk_test', $db->getDatabase(), 'Refusing to migrate a non-test database.');
        if ($db->tableExists('stock_adjustment_requests')) {
            self::markTestSkipped('The test database already contains stock_adjustment_requests.');
        }

        require_once APPPATH . 'Database/Migrations/2026-09-30-000001_CreateStockAdjustmentRequests.php';
        require_once APPPATH . 'Database/Migrations/2026-09-30-000002_CreateStockAdjustmentRequestBatches.php';
        $requestMigration = new \App\Database\Migrations\CreateStockAdjustmentRequests(Database::forge('tests'));
        $batchMigration = new \App\Database\Migrations\CreateStockAdjustmentRequestBatches(Database::forge('tests'));
        try {
            $requestMigration->up();
            $batchMigration->up();
            self::assertTrue($db->tableExists('stock_adjustment_requests'));
            self::assertTrue($db->tableExists('stock_adjustment_request_batches'));
            $indexes = $db->getIndexData('stock_adjustment_requests');
            self::assertArrayHasKey('uq_stock_adjustment_request_key', $indexes);
            self::assertSame('UNIQUE', $indexes['uq_stock_adjustment_request_key']->type ?? null);
            $batchIndexes = $db->getIndexData('stock_adjustment_request_batches');
            self::assertArrayHasKey('uq_stock_adjustment_request_batch', $batchIndexes);
            self::assertSame('UNIQUE', $batchIndexes['uq_stock_adjustment_request_batch']->type ?? null);
        } finally {
            $batchMigration->down();
            $requestMigration->down();
        }
        self::assertFalse($db->tableExists('stock_adjustment_requests'));
        self::assertFalse($db->tableExists('stock_adjustment_request_batches'));
    }

    public function testFinalizedOrderIsRejectedBeforeAnyDatabaseWrite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('finalized sales order');

        (new SalesOrderQuickStockService())->addMissingStock(
            ['id' => 10, 'status' => 'delivered'],
            [
                'request_key' => str_repeat('a', 32),
                'warehouse_id' => 1,
                'location_id' => 1,
                'lines' => [[
                    'sales_order_line_id' => 1,
                    'qty' => 1,
                    'unit_cost' => 10,
                    'vendor_id' => 1,
                ]],
            ],
            1
        );
    }

    public function testDuplicateSalesOrderLineIsRejectedBeforeAnyDatabaseWrite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid and unique');

        $line = [
            'sales_order_line_id' => 9,
            'qty' => 1,
            'unit_cost' => 10,
            'vendor_id' => 1,
        ];
        (new SalesOrderQuickStockService())->addMissingStock(
            ['id' => 10, 'status' => 'confirmed'],
            [
                'request_key' => str_repeat('b', 32),
                'warehouse_id' => 1,
                'location_id' => 1,
                'lines' => [$line, $line],
            ],
            1
        );
    }

    public function testNonPositiveQuantityAndCostAreRejected(): void
    {
        foreach ([
            ['qty' => 0, 'unit_cost' => 10, 'message' => 'positive quantity'],
            ['qty' => 1, 'unit_cost' => 0, 'message' => 'positive cost price'],
        ] as $case) {
            try {
                (new SalesOrderQuickStockService())->addMissingStock(
                    ['id' => 10, 'status' => 'confirmed'],
                    [
                        'request_key' => str_repeat('c', 32),
                        'warehouse_id' => 1,
                        'location_id' => 1,
                        'lines' => [[
                            'sales_order_line_id' => 1,
                            'qty' => $case['qty'],
                            'unit_cost' => $case['unit_cost'],
                            'vendor_id' => 1,
                        ]],
                    ],
                    1
                );
                self::fail('Invalid decimal input was accepted.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($case['message'], $e->getMessage());
            }
        }
    }

    public function testMissingGlobalAndPerLineDestinationIsRejectedBeforeDatabaseAccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('global storage destination or an individual warehouse and location');

        (new SalesOrderQuickStockService())->addMissingStock(
            ['id' => 10, 'status' => 'confirmed'],
            [
                'request_key' => str_repeat('d', 32),
                'warehouse_id' => 0,
                'location_id' => 0,
                'lines' => [[
                    'sales_order_line_id' => 1,
                    'qty' => 1,
                    'unit_cost' => 10,
                    'vendor_id' => 1,
                ]],
            ],
            1
        );
    }

    public function testImplementationContainsRequiredInventorySafetyGuards(): void
    {
        $service = (string) file_get_contents(ROOTPATH . 'app/Services/SalesOrderQuickStockService.php');
        $controller = (string) file_get_contents(ROOTPATH . 'app/Controllers/StockAdjustments.php');
        $permissions = (string) file_get_contents(ROOTPATH . 'app/Config/RoutePermissions.php');

        self::assertStringContainsString('stock_adjustment_requests', $service, 'Duplicate-submit ledger was removed.');
        self::assertStringContainsString('stock_adjustment_request_batches', $service, 'Multi-destination replay ledger was removed.');
        self::assertStringContainsString('$adjustmentGroups', $service, 'Per-destination batching was removed.');
        self::assertStringContainsString("'warehouse_id' => \$lineWarehouseId", $service);
        self::assertStringContainsString("'location_id' => \$lineLocationId", $service);
        self::assertStringContainsString('FOR UPDATE', $service, 'Shortage/stock rows are no longer locked.');
        self::assertStringContainsString("'opening_stock'", $service, 'Quick entry bypasses the audited adjustment type.');
        self::assertStringContainsString('Requested quantity exceeds the current shortage', $service);
        self::assertStringContainsString("['exclude_sales_order_line_id' => \$lineId]", $service);
        self::assertStringContainsString("! empty(\$location['vendor_id'])", $service, 'Vendor locations must not count as ready stock.');
        self::assertStringContainsString("\$policy->can('inventory', 'edit')", $controller);
        self::assertStringContainsString("\$policy->can('sales_orders', 'read')", $controller);
        self::assertStringContainsString(
            "'POST:inventory/adjustments/sales-order/*' => 'inventory.edit'",
            $permissions
        );
    }
}
