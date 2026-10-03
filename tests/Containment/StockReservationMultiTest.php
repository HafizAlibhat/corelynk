<?php

namespace Tests\Containment;

use App\Services\StockReservationService;
use PHPUnit\Framework\TestCase;

final class StockReservationMultiTest extends TestCase
{
    public function testReserveMultiRejectsEmptyAllocations(): void
    {
        $service = new StockReservationService();
        $result = $service->reserveMulti(1, 1, 1, null, [], 1);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('at least one location allocation are required', $result['message']);
    }

    public function testReserveMultiRejectsZeroOrNegativeAllocations(): void
    {
        $service = new StockReservationService();
        $result = $service->reserveMulti(1, 1, 1, null, [
            ['location_id' => 10, 'quantity' => 0.0],
            ['location_id' => 11, 'quantity' => -5.0],
        ], 1);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('positive reservation quantity', $result['message']);
    }

    public function testReserveMultiValidatesSalesOrderIds(): void
    {
        $service = new StockReservationService();
        $result = $service->reserveMulti(0, 1, 1, null, [
            ['location_id' => 10, 'quantity' => 5.0],
        ], 1);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('at least one location allocation are required', $result['message']);
    }

    public function testPreparationExecutionViewContainsMultiBoxFeatures(): void
    {
        $viewPath = APPPATH . 'Views/sales_orders/partials/preparation_execution.php';
        self::assertFileExists($viewPath);
        $content = (string) file_get_contents($viewPath);

        self::assertStringContainsString('pe_r_locations_tbody', $content, 'View must render multi-location table body');
        self::assertStringContainsString('pe_r_btn_autofill', $content, 'View must include auto-distribute button');
        self::assertStringContainsString('pe_r_allocations_json', $content, 'View must submit allocations payload');
        self::assertStringContainsString('pe-reserve-strip', $content, 'View must render per-line active reservation progress strips');
        self::assertStringContainsString('Reserve Remaining', $content, 'View must indicate remaining needed reservation amount');
        self::assertStringContainsString('pe-active-reservations-card', $content, 'View must render Apple-grade active reservations card');
        self::assertStringContainsString('pe-res-chips-grid', $content, 'View must render grouped reservation chips');
        self::assertStringContainsString('pe-mat-kpi-bar', $content, 'View must render compact material KPI bar');
        self::assertStringContainsString('Reserve Extra', $content, 'Fully reserved lines must still allow a safety overage');
        self::assertStringContainsString('reserved_locations', $content, 'Vendor modal payload must carry the line reservations');
        self::assertStringContainsString('syncVendorQuantityToSource', $content, 'Vendor modal must select the reserved box and quantity');
        self::assertStringContainsString('pe-buffer-badge', $content, 'Safety overage must be visually distinguished as buffer stock');
        self::assertStringContainsString('pe-primary-action', $content, 'Vendor send must render as a recognizable primary action');
        self::assertStringContainsString('data-label="Material"', $content, 'Execution cells must expose labels for the responsive card layout');
    }

    public function testPreparationExecutionStylesCoverResponsiveAndAccessibleStates(): void
    {
        $styles = (string) file_get_contents(FCPATH . 'assets/css/style.css');

        self::assertStringContainsString('@media (max-width: 991.98px)', $styles);
        self::assertStringContainsString('@media (max-width: 420px)', $styles);
        self::assertStringContainsString('.pe-primary-action:focus-visible', $styles);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
        self::assertStringContainsString('html[data-bs-theme="light"] .cl-sales-order-detail .pe-exec-surface', $styles);
    }

    public function testReservedSafetyOverageCanExtendVendorExecutionLimit(): void
    {
        self::assertSame(32.0, StockReservationService::executionQuantityLimit(30.0, 32.0));
        self::assertSame(30.0, StockReservationService::executionQuantityLimit(30.0, 0.0));
        self::assertSame(16.0, StockReservationService::executionQuantityLimit(14.0, 16.0));
        self::assertSame(0.0, StockReservationService::executionQuantityLimit(0.0, 0.0), 'A repeated full-send submission must have no executable quantity');
    }

    public function testVendorReceiveKeepsOverageOnTheExactLineAndProducesAcceptedQuantity(): void
    {
        $receiveController = (string) file_get_contents(APPPATH . 'Controllers/VendorReceive.php');
        $processingModel = (string) file_get_contents(APPPATH . 'Models/ProcessingRecordModel.php');

        self::assertStringContainsString("(int) (\$sendNote['sales_order_line_id'] ?? 0) ?: null", $receiveController);
        self::assertStringContainsString("\$builder->where('parent_send_note_id', \$parentSendNoteId)", $processingModel);
        self::assertStringContainsString("\$qtyAccepted,", $receiveController, 'All accepted pieces, including safety overage, must flow to preparation output stock');
    }

    public function testVendorSendValidatesSalesOrderLineOwnership(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/PreparationExecution.php');

        self::assertStringContainsString("(int) (\$line['sales_order_id'] ?? 0) !== \$salesOrderId", $controller);
        self::assertStringContainsString("(int) (\$line['product_id'] ?? 0) !== \$productId", $controller);
    }

    public function testInventoryStockViewRendersCompactLocationBreakdown(): void
    {
        $viewPath = APPPATH . 'Views/inventory/stock.php';
        self::assertFileExists($viewPath);
        $content = (string) file_get_contents($viewPath);

        self::assertStringContainsString('stk-loc-row', $content, 'View must contain location row container');
        self::assertStringContainsString('stk-loc-breadcrumb', $content, 'View must render location breadcrumb');
        self::assertStringContainsString('stk-wh-badge', $content, 'View must render warehouse badge');
        self::assertStringContainsString('stk-res-chip', $content, 'View must render compact reservation chips');
        self::assertStringContainsString('stk-qty-cell', $content, 'View must render aligned quantity cell');
        self::assertStringContainsString('stk-stat-total', $content, 'View must render total stock stat pill');
        self::assertStringContainsString('stk-stat-left', $content, 'View must render available left stat pill');
        self::assertStringContainsString('stk-stat-total-sm', $content, 'View must render location total pill');
        self::assertStringContainsString('stk-stat-left-sm', $content, 'View must render location left pill');
    }
}
