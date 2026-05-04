<?php

namespace App\Controllers\Api;

/**
 * GET /api/warehouse/delivery-orders        — All delivery orders with status
 * GET /api/warehouse/delivery-orders/{id}   — Single DO with lines + readiness
 * GET /api/warehouse/ready-to-ship          — Sales orders that have ready stock
 */
class WarehouseApi extends BaseApiController
{
    public function deliveryOrders(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('delivery_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        // Check table exists
        if (!$db->tableExists('delivery_orders')) {
            return $this->success(['items' => [], 'total' => 0]);
        }

        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 50)));
        $status  = $this->request->getGet('status');

        $where  = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]  = 'do.status = ?';
            $params[] = $status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT do.id, do.do_number, do.status, do.created_at, do.updated_at,
                       do.sales_order_id,
                       so.order_number AS so_number, so.status AS so_status,
                       COALESCE(c.name, c.company_name, 'Unknown') AS customer_name,
                       (SELECT COUNT(*) FROM delivery_order_lines dol WHERE dol.delivery_order_id = do.id) AS line_count,
                       COALESCE((SELECT SUM(dol.qty_to_ship)  FROM delivery_order_lines dol WHERE dol.delivery_order_id = do.id), 0) AS total_qty,
                       COALESCE((SELECT SUM(dol.ready_qty)    FROM delivery_order_lines dol WHERE dol.delivery_order_id = do.id), 0) AS ready_qty
                FROM delivery_orders do
                LEFT JOIN sales_orders so ON so.id = do.sales_order_id
                LEFT JOIN customers c ON c.id = so.customer_id
                {$whereClause}
                ORDER BY do.id DESC
                LIMIT ?";

        $params[] = $perPage;

        $items = $db->query($sql, $params)->getResultArray();

        $items = array_map(static function (array $row): array {
            $row['id']             = (int)   $row['id'];
            $row['sales_order_id'] = (int)   $row['sales_order_id'];
            $row['line_count']     = (int)   $row['line_count'];
            $row['total_qty']      = (float) $row['total_qty'];
            $row['ready_qty']      = (float) $row['ready_qty'];
            $row['is_ready']       = ($row['ready_qty'] > 0 && $row['ready_qty'] >= $row['total_qty'] && $row['total_qty'] > 0);
            $row['partial_ready']  = ($row['ready_qty'] > 0 && $row['ready_qty'] < $row['total_qty']);
            return $row;
        }, $items);

        return $this->success(['items' => $items, 'total' => count($items)]);
    }

    public function showDeliveryOrder(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('delivery_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        if (!$db->tableExists('delivery_orders')) {
            return $this->error('Delivery orders not available.', 404);
        }

        $do = $db->query(
            "SELECT do.*, so.order_number AS so_number, so.status AS so_status, so.total AS so_total,
                    COALESCE(c.name, c.company_name, 'Unknown') AS customer_name
             FROM delivery_orders do
             LEFT JOIN sales_orders so ON so.id = do.sales_order_id
             LEFT JOIN customers c ON c.id = so.customer_id
             WHERE do.id = ?",
            [$id]
        )->getRowArray();

        if (!$do) {
            return $this->error('Delivery order not found.', 404);
        }

        $lines = [];
        if ($db->tableExists('delivery_order_lines')) {
            $rawLines = $db->query(
                "SELECT dol.*, COALESCE(p.name, '') AS product_name, COALESCE(p.sku, p.code, '') AS sku
                 FROM delivery_order_lines dol
                 LEFT JOIN products p ON p.id = dol.product_id
                 WHERE dol.delivery_order_id = ?
                 ORDER BY dol.id",
                [$id]
            )->getResultArray();

            foreach ($rawLines as $line) {
                $qtyOrdered = (float) $line['quantity_ordered'];
                $readyQty   = (float) $line['ready_qty'];
                $qtyToShip  = (float) $line['qty_to_ship'];
                $lines[] = [
                    'id'             => (int)   $line['id'],
                    'product_name'   => $line['product_name'],
                    'sku'            => $line['sku'],
                    'quantity_ordered'=> $qtyOrdered,
                    'ready_qty'      => $readyQty,
                    'qty_to_ship'    => $qtyToShip,
                    'is_ready'       => ($readyQty >= $qtyOrdered && $qtyOrdered > 0),
                    'short_qty'      => max(0.0, $qtyOrdered - $readyQty),
                ];
            }
        }

        $do['id']             = (int) $do['id'];
        $do['sales_order_id'] = (int) $do['sales_order_id'];
        $do['lines']          = $lines;

        return $this->success($do);
    }

    public function readyToShip(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('delivery_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        // Simple query: sales orders that are confirmed/processing and have
        // delivery orders not yet shipped
        if (!$db->tableExists('delivery_orders')) {
            return $this->success(['items' => [], 'total' => 0]);
        }

        $sql = "SELECT 
                    so.id, so.order_number, so.status,
                    COALESCE(c.name, c.company_name, 'Unknown') AS customer_name,
                    so.total,
                    COALESCE(so.currency, 'PKR') AS currency,
                    so.order_date,
                    COUNT(DISTINCT do.id) AS delivery_count,
                    SUM(CASE WHEN do.status = 'ready' THEN 1 ELSE 0 END) AS ready_count,
                    SUM(CASE WHEN do.status = 'shipped' THEN 1 ELSE 0 END) AS shipped_count,
                    SUM(CASE WHEN do.status = 'draft' THEN 1 ELSE 0 END) AS draft_count
                FROM sales_orders so
                LEFT JOIN customers c ON c.id = so.customer_id
                LEFT JOIN delivery_orders do ON do.sales_order_id = so.id
                WHERE so.status NOT IN ('cancelled', 'closed', 'draft')
                GROUP BY so.id, so.order_number, so.status, c.name, c.company_name,
                         so.total, so.currency, so.order_date
                ORDER BY so.id DESC
                LIMIT 100";

        $rows = $db->query($sql)->getResultArray();

        $rows = array_map(static function (array $row): array {
            $deliveryCount = (int)   $row['delivery_count'];
            $readyCount    = (int)   $row['ready_count'];
            $shippedCount  = (int)   $row['shipped_count'];
            $draftCount    = (int)   $row['draft_count'];
            $total         = (float) $row['total'];

            // Determine shipment status
            if ($deliveryCount === 0) {
                $shipStatus = 'no_do';          // No delivery order created
            } elseif ($shippedCount >= $deliveryCount) {
                $shipStatus = 'shipped';         // All shipped
            } elseif ($readyCount > 0) {
                $shipStatus = 'ready';           // At least one ready
            } elseif ($draftCount > 0) {
                $shipStatus = 'preparing';       // DOs exist but still draft
            } else {
                $shipStatus = 'partial';         // Partially shipped
            }

            return [
                'id'             => (int)   $row['id'],
                'order_number'   => $row['order_number'],
                'status'         => $row['status'],
                'customer_name'  => $row['customer_name'],
                'total'          => $total,
                'currency'       => $row['currency'],
                'order_date'     => $row['order_date'],
                'delivery_count' => $deliveryCount,
                'ready_count'    => $readyCount,
                'shipped_count'  => $shippedCount,
                'ship_status'    => $shipStatus,
            ];
        }, $rows);

        return $this->success(['items' => $rows, 'total' => count($rows)]);
    }
}
