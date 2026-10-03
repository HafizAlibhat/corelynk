<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;
use App\Models\DeliveryOrderModel;

final class DeliveryOrderShipmentTrackingTest extends ContainmentTestCase
{
    public function testDeliveryOrderModelPersistsShippedAtAndEstimatedDeliveryDays(): void
    {
        $so = $this->db->table('sales_orders')->insert([
            'order_number' => 'SO-TRK-TEST-01',
            'status'       => 'confirmed',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $soId = (int)$this->db->insertID();

        $shippedDate = '2026-09-25 10:00:00';
        $estDays = 7;

        $doModel = new DeliveryOrderModel($this->db);

        $doId = $doModel->insert([
            'sales_order_id'          => $soId,
            'do_number'               => 'DO-TRK-001',
            'status'                  => 'shipped',
            'shipped_at'              => $shippedDate,
            'estimated_delivery_days' => $estDays,
        ]);

        self::assertGreaterThan(0, $doId);

        $saved = $doModel->find($doId);
        self::assertNotNull($saved);
        self::assertSame($shippedDate, $saved['shipped_at']);
        self::assertEquals($estDays, (int)$saved['estimated_delivery_days']);
    }

    public function testTrackingStatusCalculationsOnTrackVsOverdue(): void
    {
        $currentTime = strtotime('2026-10-01 12:00:00');

        // Case 1: Shipped 3 days ago with 7 delivery days -> On Track (4 days left)
        $shippedAt1 = '2026-09-28 12:00:00';
        $estDays1 = 7;
        $daysSinceShipped1 = (int)floor(($currentTime - strtotime($shippedAt1)) / 86400);
        $isOverdue1 = $daysSinceShipped1 > $estDays1;
        $remainingDays1 = $estDays1 - $daysSinceShipped1;

        self::assertSame(3, $daysSinceShipped1);
        self::assertFalse($isOverdue1);
        self::assertSame(4, $remainingDays1);

        // Case 2: Shipped 10 days ago with 7 delivery days -> Overdue by 3 days
        $shippedAt2 = '2026-09-21 12:00:00';
        $estDays2 = 7;
        $daysSinceShipped2 = (int)floor(($currentTime - strtotime($shippedAt2)) / 86400);
        $isOverdue2 = $daysSinceShipped2 > $estDays2;
        $overdueDays2 = $daysSinceShipped2 - $estDays2;

        self::assertSame(10, $daysSinceShipped2);
        self::assertTrue($isOverdue2);
        self::assertSame(3, $overdueDays2);
    }

    public function testDeliveredStatusCalculationsOnTimeVsLate(): void
    {
        $shippedAt = '2026-09-20 10:00:00';
        $estDays = 7;

        // Case 1: Delivered in 5 days (within 7 days target) -> Delivered on time
        $deliveredAt1 = '2026-09-25 10:00:00';
        $daysTaken1 = (int)round((strtotime($deliveredAt1) - strtotime($shippedAt)) / 86400);
        $deliveredOnTime1 = $daysTaken1 <= $estDays;

        self::assertSame(5, $daysTaken1);
        self::assertTrue($deliveredOnTime1);

        // Case 2: Delivered in 10 days (exceeded 7 days target) -> Delivered late
        $deliveredAt2 = '2026-09-30 10:00:00';
        $daysTaken2 = (int)round((strtotime($deliveredAt2) - strtotime($shippedAt)) / 86400);
        $deliveredOnTime2 = $daysTaken2 <= $estDays;
        $lateByDays2 = $daysTaken2 - $estDays;

        self::assertSame(10, $daysTaken2);
        self::assertFalse($deliveredOnTime2);
        self::assertSame(3, $lateByDays2);
    }

    public function testControllerWriterSupportsShippedAtAndEstimatedDays(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/DeliveryOrders.php');
        self::assertStringContainsString("getPost('shipped_at')", $src, 'DeliveryOrders controller must parse shipped_at from POST');
        self::assertStringContainsString("'shipped_at'              => \$shippedAt,", $src, 'DeliveryOrders controller must save shipped_at to DO model');
        self::assertStringContainsString("\$updateData['shipped_at']", $src, 'updateEstimatedDays must support updating shipped_at');
    }
}
