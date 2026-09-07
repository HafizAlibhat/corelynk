<?php

namespace App\Services;

use Config\Database;

/**
 * Provides additive, user-specific dashboard notifications for the Corelynk Activity Center.
 *
 * This service only reads source workflow tables and writes to dedicated
 * core_notifications/core_notification_reads tables.
 */
class NotificationService
{
    protected $db;
    protected ReadyToShipService $readyToShipService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->readyToShipService = new ReadyToShipService();
    }

    /**
     * Build and return the latest activity feed for a user.
     *
     * @param int $userId
     * @param int $limit Per section limit.
     * @return array<string,mixed>
     */
    public function getActivityCenterFeed(int $userId, int $limit = 10): array
    {
        $userId = (int) $userId;
        $limit = max(1, min(50, (int) $limit));

        if ($userId <= 0 || !$this->requiredTablesExist()) {
            return [
                'active_sales_orders' => [],
                'ready_to_ship_orders' => [],
                'unread_total' => 0,
            ];
        }

        try {
            $this->db->transStart();
            $this->syncSnapshot($limit);
            $this->db->transComplete();

            if (!$this->db->transStatus()) {
                throw new \RuntimeException('Notification snapshot transaction failed.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService.getActivityCenterFeed sync failed: ' . $e->getMessage());
        }

        try {
            $rows = $this->db->table('core_notifications n')
                ->select('n.id, n.notification_type, n.source_id, n.source_status, n.title, n.message, n.payload_json, n.created_at, nr.read_at')
                ->join(
                    'core_notification_reads nr',
                    'nr.notification_id = n.id AND nr.user_id = ' . $userId,
                    'left'
                )
                ->where('n.is_active', 1)
                ->whereIn('n.notification_type', ['active_sales_order', 'ready_to_ship_order'])
                ->orderBy('n.created_at', 'DESC')
                ->orderBy('n.id', 'DESC')
                ->get()
                ->getResultArray();

            $active = [];
            $ready = [];
            $unreadTotal = 0;

            foreach ($rows as $row) {
                $payload = [];
                if (!empty($row['payload_json'])) {
                    $decoded = json_decode((string) $row['payload_json'], true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }

                $item = [
                    'notification_id' => (int) ($row['id'] ?? 0),
                    'source_id' => (int) ($row['source_id'] ?? 0),
                    'status' => (string) ($row['source_status'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'message' => (string) ($row['message'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'is_read' => !empty($row['read_at']),
                    'order_number' => (string) ($payload['order_number'] ?? ''),
                    'customer_name' => (string) ($payload['customer_name'] ?? ''),
                    'view_url' => (string) ($payload['view_url'] ?? ''),
                ];

                if (!$item['is_read']) {
                    $unreadTotal++;
                }

                if (($row['notification_type'] ?? '') === 'active_sales_order') {
                    if (count($active) < $limit) {
                        $active[] = $item;
                    }
                } elseif (($row['notification_type'] ?? '') === 'ready_to_ship_order') {
                    if (count($ready) < $limit) {
                        $ready[] = $item;
                    }
                }
            }

            return [
                'active_sales_orders' => $active,
                'ready_to_ship_orders' => $ready,
                'unread_total' => $unreadTotal,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService.getActivityCenterFeed read failed: ' . $e->getMessage());
            return [
                'active_sales_orders' => [],
                'ready_to_ship_orders' => [],
                'unread_total' => 0,
            ];
        }
    }

    /**
     * Mark one notification as read for a user.
     */
    public function markAsRead(int $userId, int $notificationId): bool
    {
        $userId = (int) $userId;
        $notificationId = (int) $notificationId;

        if ($userId <= 0 || $notificationId <= 0 || !$this->requiredTablesExist()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        try {
            $this->db->transStart();

            $existing = $this->db->table('core_notification_reads')
                ->where('user_id', $userId)
                ->where('notification_id', $notificationId)
                ->get()
                ->getRowArray();

            if ($existing) {
                $this->db->table('core_notification_reads')
                    ->where('id', (int) $existing['id'])
                    ->update([
                        'read_at' => $now,
                        'updated_at' => $now,
                    ]);
            } else {
                $this->db->table('core_notification_reads')->insert([
                    'notification_id' => $notificationId,
                    'user_id' => $userId,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->db->transComplete();
            $ok = $this->db->transStatus();

            if ($ok) {
                log_message('info', 'Notification marked read. user_id=' . $userId . ' notification_id=' . $notificationId);
            }

            return (bool) $ok;
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService.markAsRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all active notifications as read for a user.
     *
     * @return int number of notifications newly marked as read.
     */
    public function markAllAsRead(int $userId): int
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !$this->requiredTablesExist()) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $affected = 0;

        try {
            $activeRows = $this->db->table('core_notifications')
                ->select('id')
                ->where('is_active', 1)
                ->whereIn('notification_type', ['active_sales_order', 'ready_to_ship_order'])
                ->get()
                ->getResultArray();

            if (empty($activeRows)) {
                return 0;
            }

            $this->db->transStart();

            foreach ($activeRows as $row) {
                $notificationId = (int) ($row['id'] ?? 0);
                if ($notificationId <= 0) {
                    continue;
                }

                $existing = $this->db->table('core_notification_reads')
                    ->where('user_id', $userId)
                    ->where('notification_id', $notificationId)
                    ->get()
                    ->getRowArray();

                if ($existing) {
                    if (empty($existing['read_at'])) {
                        $this->db->table('core_notification_reads')
                            ->where('id', (int) $existing['id'])
                            ->update([
                                'read_at' => $now,
                                'updated_at' => $now,
                            ]);
                        $affected++;
                    }
                } else {
                    $this->db->table('core_notification_reads')->insert([
                        'notification_id' => $notificationId,
                        'user_id' => $userId,
                        'read_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $affected++;
                }
            }

            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                return 0;
            }

            log_message('info', 'All notifications marked read. user_id=' . $userId . ' count=' . $affected);
            return $affected;
        } catch (\Throwable $e) {
            log_message('error', 'NotificationService.markAllAsRead failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Synchronize the additive notification snapshot from source workflow tables.
     */
    protected function syncSnapshot(int $limit): void
    {
        $activeOrders = $this->fetchActiveSalesOrders($limit);
        $readyOrders = $this->fetchReadyToShipOrders($limit);

        $activeIds = [];
        foreach ($activeOrders as $order) {
            $sourceId = (int) ($order['id'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }
            $activeIds[] = $sourceId;
            $orderNumber = (string) ($order['order_number'] ?? ('SO-' . $sourceId));
            $customerName = (string) ($order['customer_name'] ?? 'Customer');
            $status = strtolower((string) ($order['status'] ?? ''));

            $this->upsertNotification(
                'active_sales_order',
                'sales_orders',
                $sourceId,
                $status,
                'Active Sales Order: ' . $orderNumber,
                $customerName . ' • status: ' . ($status !== '' ? $status : 'open'),
                [
                    'order_number' => $orderNumber,
                    'customer_name' => $customerName,
                    'view_url' => base_url('/sales-orders/view/' . $sourceId),
                ]
            );
        }

        $readyIds = [];
        foreach ($readyOrders as $order) {
            $sourceId = (int) ($order['id'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }
            $readyIds[] = $sourceId;
            $orderNumber = (string) ($order['order_number'] ?? ('SO-' . $sourceId));
            $customerName = (string) ($order['customer_name'] ?? 'Customer');
            $status = strtolower((string) ($order['status'] ?? 'ready'));

            $this->upsertNotification(
                'ready_to_ship_order',
                'sales_orders',
                $sourceId,
                $status,
                'Ready to Ship: ' . $orderNumber,
                $customerName . ' • stock available now',
                [
                    'order_number' => $orderNumber,
                    'customer_name' => $customerName,
                    'view_url' => base_url('/sales-orders/view/' . $sourceId),
                ]
            );
        }

        $this->deactivateMissing('active_sales_order', 'sales_orders', $activeIds);
        $this->deactivateMissing('ready_to_ship_order', 'sales_orders', $readyIds);
    }

    /**
     * Fetch latest sales orders considered active for dashboard visibility.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchActiveSalesOrders(int $limit): array
    {
        if (!$this->db->tableExists('sales_orders')) {
            return [];
        }

        $soCols = $this->safeFieldNames('sales_orders');
        if (!in_array('id', $soCols, true)) {
            return [];
        }

        $builder = $this->db->table('sales_orders so')
            ->select('so.id, so.order_number, so.status, so.customer_id, so.created_at, so.updated_at');

        if ($this->db->tableExists('customers')) {
            $builder->select('COALESCE(c.name, c.company_name, CONCAT("Customer #", so.customer_id)) AS customer_name', false)
                ->join('customers c', 'c.id = so.customer_id', 'left');
        } else {
            $builder->select('CONCAT("Customer #", so.customer_id) AS customer_name', false);
        }

        if (in_array('status', $soCols, true)) {
            $builder->where("LOWER(COALESCE(so.status, '')) NOT IN ('cancelled','closed','completed','delivered')", null, false);
        }

        if (in_array('updated_at', $soCols, true)) {
            $builder->orderBy('so.updated_at', 'DESC');
        }

        return $builder
            ->orderBy('so.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Fetch latest ready-to-ship sales orders.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fetchReadyToShipOrders(int $limit): array
    {
        if (!$this->db->tableExists('sales_orders')) {
            return [];
        }

        $readyIds = $this->readyToShipService->getReadySalesOrders();
        $readyIds = array_values(array_unique(array_filter(array_map('intval', $readyIds))));

        if (empty($readyIds)) {
            return [];
        }

        $builder = $this->db->table('sales_orders so')
            ->select('so.id, so.order_number, so.status, so.customer_id, so.created_at, so.updated_at')
            ->whereIn('so.id', $readyIds);

        $soCols = $this->safeFieldNames('sales_orders');
        if (in_array('status', $soCols, true)) {
            $builder->where("LOWER(COALESCE(so.status, '')) NOT IN ('cancelled','closed','completed','delivered')", null, false);
        }

        if ($this->db->tableExists('customers')) {
            $builder->select('COALESCE(c.name, c.company_name, CONCAT("Customer #", so.customer_id)) AS customer_name', false)
                ->join('customers c', 'c.id = so.customer_id', 'left');
        } else {
            $builder->select('CONCAT("Customer #", so.customer_id) AS customer_name', false);
        }

        if (in_array('updated_at', $soCols, true)) {
            $builder->orderBy('so.updated_at', 'DESC');
        }

        return $builder
            ->orderBy('so.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Create or update one additive notification row.
     */
    protected function upsertNotification(
        string $type,
        string $sourceTable,
        int $sourceId,
        string $status,
        string $title,
        string $message,
        array $payload
    ): void {
        $existing = $this->db->table('core_notifications')
            ->where('notification_type', $type)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->get()
            ->getRowArray();

        $now = date('Y-m-d H:i:s');
        $data = [
            'source_status' => substr($status, 0, 50),
            'title' => substr($title, 0, 255),
            'message' => $message,
            'payload_json' => json_encode($payload),
            'is_active' => 1,
            'became_inactive_at' => null,
            'updated_at' => $now,
        ];

        if ($existing) {
            $this->db->table('core_notifications')
                ->where('id', (int) $existing['id'])
                ->update($data);
            return;
        }

        $data['notification_type'] = $type;
        $data['source_table'] = $sourceTable;
        $data['source_id'] = $sourceId;
        $data['created_at'] = $now;

        $this->db->table('core_notifications')->insert($data);
    }

    /**
     * Set notification rows inactive when source entities are no longer visible.
     *
     * @param array<int> $activeIds
     */
    protected function deactivateMissing(string $type, string $sourceTable, array $activeIds): void
    {
        $builder = $this->db->table('core_notifications')
            ->where('notification_type', $type)
            ->where('source_table', $sourceTable)
            ->where('is_active', 1);

        if (!empty($activeIds)) {
            $builder->whereNotIn('source_id', $activeIds);
        }

        $builder->update([
            'is_active' => 0,
            'became_inactive_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check required additive notification tables.
     */
    protected function requiredTablesExist(): bool
    {
        return $this->db->tableExists('core_notifications')
            && $this->db->tableExists('core_notification_reads');
    }

    /**
     * Safe field-name lookup helper.
     *
     * @return array<int,string>
     */
    protected function safeFieldNames(string $table): array
    {
        try {
            return $this->db->getFieldNames($table);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
