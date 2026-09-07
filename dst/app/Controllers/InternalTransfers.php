<?php

namespace App\Controllers;

use App\Services\InventoryService;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;

class InternalTransfers extends BaseController
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Index – list all internal transfers
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        $this->requireAuth();
        $this->ensureTable();
        $db = Database::connect();

        $page    = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        $search = trim((string)($this->request->getGet('q') ?? ''));

        $baseQ = $db->table('internal_transfers it')
            ->select('it.*, p.name AS product_name, p.code AS product_code,
                      pv.name AS variant_name, pv.art_number,
                      fw.name AS from_warehouse, fl.name AS from_location,
                      tw.name AS to_warehouse,   tl.name AS to_location,
                      CONCAT(u.first_name," ",u.last_name) AS created_by_name')
            ->join('products p',               'p.id = it.product_id', 'left')
            ->join('product_variants pv',      'pv.id = it.variant_id', 'left')
            ->join('warehouses fw',            'fw.id = it.from_warehouse_id', 'left')
            ->join('warehouse_locations fl',   'fl.id = it.from_location_id', 'left')
            ->join('warehouses tw',            'tw.id = it.to_warehouse_id', 'left')
            ->join('warehouse_locations tl',   'tl.id = it.to_location_id', 'left')
            ->join('users u',                  'u.id = it.created_by', 'left');

        if ($search !== '') {
            $baseQ->groupStart()
                ->like('it.transfer_number', $search)
                ->orLike('p.name',  $search)
                ->orLike('p.code',  $search)
                ->orLike('it.reason', $search)
            ->groupEnd();
        }

        $total   = (clone $baseQ)->countAllResults(false);
        $records = $baseQ->orderBy('it.id', 'DESC')->limit($perPage, $offset)->get()->getResultArray();

        $totalPages = max(1, (int)ceil($total / $perPage));

        return view('inventory/transfers_index', [
            'records'    => $records,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'search'     => $search,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Create – show the form
    // ─────────────────────────────────────────────────────────────────────────
    public function create()
    {
        $this->requireAuth();
        $db = Database::connect();

        $warehouses = $db->table('warehouses')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        $locations = $db->table('warehouse_locations wl')
            ->select('wl.id, wl.name, wl.warehouse_id, w.name AS warehouse_name')
            ->join('warehouses w', 'w.id = wl.warehouse_id', 'left')
            ->where('wl.is_active', 1)
            ->orderBy('w.name', 'ASC')
            ->orderBy('wl.name', 'ASC')
            ->get()->getResultArray();

        return view('inventory/transfers_create', [
            'warehouses' => $warehouses,
            'locations'  => $locations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Store – handle POST and execute the transfer
    // ─────────────────────────────────────────────────────────────────────────
    public function store()
    {
        $this->requireAuth();

        $post = $this->request->getPost();

        $productId       = (int)($post['product_id']       ?? 0);
        $variantId       = (int)($post['variant_id']       ?? 0) ?: null;
        $fromWarehouseId = (int)($post['from_warehouse_id'] ?? 0);
        $fromLocationId  = (int)($post['from_location_id']  ?? 0);
        $toWarehouseId   = (int)($post['to_warehouse_id']   ?? 0);
        $toLocationId    = (int)($post['to_location_id']    ?? 0);
        $quantity        = (float)($post['quantity']        ?? 0);
        $reason          = trim((string)($post['reason']    ?? ''));
        $notes           = trim((string)($post['notes']     ?? ''));

        if ($productId <= 0 || $fromLocationId <= 0 || $toLocationId <= 0 || $quantity <= 0 || $reason === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'All required fields must be filled (product, source, destination, quantity, reason).',
            ]);
        }

        try {
            $svc        = new InventoryService();
            $userId     = $this->getCurrentUserId();
            $transferId = $svc->internalTransfer(
                $productId,
                $fromWarehouseId,
                $fromLocationId,
                $toWarehouseId,
                $toLocationId,
                $quantity,
                $reason,
                $userId,
                $variantId,
                $notes
            );

            return $this->response->setJSON([
                'success'     => true,
                'transfer_id' => $transferId,
                'redirect'    => base_url('/inventory/transfers/' . $transferId),
                'message'     => 'Stock transfer completed successfully.',
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            log_message('error', 'InternalTransfer store error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', 'InternalTransfer unexpected: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Show – view detail of a single transfer
    // ─────────────────────────────────────────────────────────────────────────
    public function show($id = 0)
    {
        $this->requireAuth();
        $this->ensureTable();
        $db = Database::connect();

        $transfer = $db->query(
            'SELECT it.*, p.name AS product_name, p.code AS product_code,
                    pv.name AS variant_name, pv.art_number,
                    fw.name AS from_warehouse, fl.name AS from_location,
                    tw.name AS to_warehouse,   tl.name AS to_location,
                    CONCAT(u.first_name," ",u.last_name) AS created_by_name
             FROM internal_transfers it
             LEFT JOIN products p             ON p.id = it.product_id
             LEFT JOIN product_variants pv    ON pv.id = it.variant_id
             LEFT JOIN warehouses fw          ON fw.id = it.from_warehouse_id
             LEFT JOIN warehouse_locations fl ON fl.id = it.from_location_id
             LEFT JOIN warehouses tw          ON tw.id = it.to_warehouse_id
             LEFT JOIN warehouse_locations tl ON tl.id = it.to_location_id
             LEFT JOIN users u                ON u.id = it.created_by
             WHERE it.id = ?',
            [(int)$id]
        )->getRowArray();

        if (!$transfer) {
            return redirect()->to('/inventory/transfers')->with('error', 'Transfer not found.');
        }

        return view('inventory/transfers_show', ['transfer' => $transfer]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX: product search (search storable products + variants)
    // ─────────────────────────────────────────────────────────────────────────
    public function productSearch()
    {
        $this->requireAuth();
        $q  = trim((string)($this->request->getGet('q') ?? ''));
        $db = Database::connect();

        if ($q === '') {
            return $this->response->setJSON(['success' => true, 'data' => []]);
        }

        $like    = '%' . $q . '%';
        $results = [];

        // Simple products
        try {
            $builder = $db->table('products')
                ->select('id, name, sku, code, product_type')
                ->groupStart()
                    ->like('name', $q)
                    ->orLike('sku',  $q)
                    ->orLike('code', $q)
                ->groupEnd()
                ->limit(15);
            try {
                $pCols = $db->getFieldNames('products');
                if (in_array('detailed_type', $pCols, true)) {
                    $builder->where('detailed_type', 'storable');
                }
            } catch (\Throwable $e) {}

            foreach ($builder->get()->getResultArray() as $p) {
                $isVariable = ($p['product_type'] === 'variable');
                $results[] = [
                    'product_id' => (int)$p['id'],
                    'variant_id' => 0,
                    'label'      => ($p['code'] ? '[' . $p['code'] . '] ' : '') . $p['name'],
                    'sub'        => $isVariable ? 'Variable Product' : 'Product',
                    'type'       => $isVariable ? 'parent' : 'product',
                ];
            }
        } catch (\Throwable $e) {}

        // Variants
        try {
            $variants = $db->query(
                'SELECT pv.id AS variant_id, pv.art_number, pv.name AS variant_name,
                        p.id AS product_id, p.code, p.name AS product_name
                 FROM product_variants pv
                 JOIN products p ON p.id = pv.product_id
                 WHERE pv.name LIKE ? OR pv.art_number LIKE ?
                 ORDER BY pv.art_number LIMIT 15',
                [$like, $like]
            )->getResultArray();

            foreach ($variants as $v) {
                $results[] = [
                    'product_id' => (int)$v['product_id'],
                    'variant_id' => (int)$v['variant_id'],
                    'label'      => ($v['code'] ? '[' . $v['code'] . '] ' : '') . $v['product_name']
                                    . ' — ' . ($v['variant_name'] ?: $v['art_number']),
                    'sub'        => 'Variant · ' . $v['art_number'],
                    'type'       => 'variant',
                ];
            }
        } catch (\Throwable $e) {}

        return $this->response->setJSON(['success' => true, 'data' => $results]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX: get stock balance for a product/variant at a specific location
    // ─────────────────────────────────────────────────────────────────────────
    public function locationStock()
    {
        $this->requireAuth();
        $productId   = (int)($this->request->getGet('product_id')   ?? 0);
        $variantId   = (int)($this->request->getGet('variant_id')   ?? 0);
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $locationId  = (int)($this->request->getGet('location_id')  ?? 0);

        if ($productId <= 0 || $locationId <= 0) {
            return $this->response->setJSON(['success' => true, 'quantity' => 0]);
        }

        $db = Database::connect();
        try {
            $itemKey = $variantId > 0 ? 'v' . $variantId : 'p' . $productId;
            $q = $db->table('stock_balances')
                ->select('SUM(quantity) AS qty')
                ->where('item_key', $itemKey)
                ->where('location_id', $locationId);
            if ($warehouseId > 0) $q->where('warehouse_id', $warehouseId);
            $row = $q->get()->getRowArray();
            $qty = (float)($row['qty'] ?? 0);
        } catch (\Throwable $e) {
            $qty = 0;
        }

        return $this->response->setJSON(['success' => true, 'quantity' => $qty]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX: get locations for a warehouse (for cascading selects)
    // ─────────────────────────────────────────────────────────────────────────
    public function apiLocations()
    {
        $this->requireAuth();
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $db = Database::connect();

        $builder = $db->table('warehouse_locations')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC');
        if ($warehouseId > 0) $builder->where('warehouse_id', $warehouseId);

        $locations = $builder->get()->getResultArray();
        return $this->response->setJSON(['success' => true, 'locations' => $locations]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function getCurrentUserId(): int
    {
        try {
            $session = session();
            return (int)($session->get('user_id') ?? $session->get('id') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function ensureTable(): void
    {
        try {
            $db = Database::connect();
            if (!$db->tableExists('internal_transfers')) {
                $forge = \Config\Database::forge();
                $forge->addField([
                    'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'transfer_number'   => ['type' => 'VARCHAR', 'constraint' => 30],
                    'product_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                    'variant_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                    'item_key'          => ['type' => 'VARCHAR', 'constraint' => 32],
                    'quantity'          => ['type' => 'DECIMAL', 'constraint' => '18,4'],
                    'from_warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                    'from_location_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                    'to_warehouse_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                    'to_location_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                    'reason'            => ['type' => 'TEXT'],
                    'notes'             => ['type' => 'TEXT', 'null' => true],
                    'out_movement_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'in_movement_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'created_by'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'created_at'        => ['type' => 'DATETIME', 'null' => true],
                    'updated_at'        => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->addUniqueKey('transfer_number');
                $forge->addKey('product_id');
                $forge->createTable('internal_transfers', true);
            }
        } catch (\Throwable $e) {
            log_message('error', 'ensureTable(internal_transfers) failed: ' . $e->getMessage());
        }
    }
}
