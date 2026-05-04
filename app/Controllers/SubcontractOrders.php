<?php

namespace App\Controllers;

use App\Models\SubcontractOrderModel;
use App\Models\SubcontractOrderLineModel;
use App\Models\ProductModel;
use App\Models\VendorModel;
use Config\Database;

class SubcontractOrders extends BaseController
{
    protected SubcontractOrderModel $orderModel;
    protected SubcontractOrderLineModel $lineModel;

    public function __construct()
    {
        $this->orderModel = new SubcontractOrderModel();
        $this->lineModel  = new SubcontractOrderLineModel();
    }

    // -------------------------------------------------------------------
    //  LIST
    // -------------------------------------------------------------------
    public function index()
    {
        $this->requireAuth();

        $search   = $this->request->getGet('search');
        $status   = $this->request->getGet('status');
        $vendorId = $this->request->getGet('vendor_id') ? (int) $this->request->getGet('vendor_id') : null;
        $perPage  = max(10, min(100, (int) ($this->request->getGet('per_page') ?? 20)));

        $orders = $this->orderModel->getListFiltered($search, $status, $vendorId, $perPage);

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        $data = $this->setPageData([
            'page_title'  => 'Subcontract Orders',
            'orders'      => $orders,
            'pager'       => $this->orderModel->pager,
            'vendors'     => $vendors,
            'statuses'    => SubcontractOrderModel::statusOptions(),
            'filters'     => ['search' => $search, 'status' => $status, 'vendor_id' => $vendorId, 'per_page' => $perPage],
        ]);

        return view('subcontract_orders/index', $data);
    }

    // -------------------------------------------------------------------
    //  CREATE FORM
    // -------------------------------------------------------------------
    public function create()
    {
        $this->requireAuth();

        $vendorModel   = new VendorModel();
        $vendors       = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        // Only service products for the service picker
        $productModel  = new ProductModel();
        $serviceProducts = $productModel->where('detailed_type', 'service')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        // Storable products for the materials lines
        $storableProducts = $productModel->where('detailed_type', 'storable')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        // Warehouses
        $db = Database::connect();
        $warehouses = $db->table('warehouses')->orderBy('name', 'ASC')->get()->getResultArray();
        $locations  = $db->table('warehouse_locations')->orderBy('name', 'ASC')->get()->getResultArray();

        // Currencies
        $currencies = [];
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
        } catch (\Throwable $e) {}

        $company = (new \App\Models\CompanySettingsModel())->first();
        $defaultCurrency = $company['base_currency'] ?? 'PKR';

        $data = $this->setPageData([
            'page_title'       => 'New Subcontract Order',
            'order'            => null,
            'lines'            => [],
            'vendors'          => $vendors,
            'serviceProducts'  => $serviceProducts,
            'storableProducts' => $storableProducts,
            'warehouses'       => $warehouses,
            'locations'        => $locations,
            'currencies'       => $currencies,
            'defaultCurrency'  => $defaultCurrency,
            'validation'       => \Config\Services::validation(),
        ]);

        return view('subcontract_orders/form', $data);
    }

    // -------------------------------------------------------------------
    //  STORE
    // -------------------------------------------------------------------
    public function store()
    {
        $this->requireAuth();

        $db = Database::connect();
        $db->transStart();

        $orderData = [
            'order_number'         => $this->orderModel->generateOrderNumber(),
            'vendor_id'            => (int) $this->request->getPost('vendor_id'),
            'service_product_id'   => (int) $this->request->getPost('service_product_id'),
            'status'               => 'draft',
            'quantity'             => (float) $this->request->getPost('quantity'),
            'unit_price'           => (float) $this->request->getPost('unit_price'),
            'currency'             => $this->request->getPost('currency') ?: 'PKR',
            'total'                => round((float) $this->request->getPost('quantity') * (float) $this->request->getPost('unit_price'), 2),
            'expected_return_date' => $this->request->getPost('expected_return_date') ?: null,
            'warehouse_id'         => $this->request->getPost('warehouse_id') ?: null,
            'location_id'          => $this->request->getPost('location_id') ?: null,
            'notes'                => $this->request->getPost('notes'),
            'created_by'           => $this->currentUser['id'] ?? null,
        ];

        if (!$this->orderModel->insert($orderData)) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to create order: ' . implode(', ', $this->orderModel->errors()));
        }

        $orderId = $this->orderModel->getInsertID();

        // Save material lines
        $lineProducts  = $this->request->getPost('line_product_id') ?? [];
        $lineVariants  = $this->request->getPost('line_variant_id') ?? [];
        $lineQtys      = $this->request->getPost('line_qty_sent') ?? [];
        $lineDescs     = $this->request->getPost('line_description') ?? [];

        for ($i = 0; $i < count($lineProducts); $i++) {
            $pid = (int) ($lineProducts[$i] ?? 0);
            if ($pid <= 0) continue;

            $this->lineModel->insert([
                'subcontract_order_id' => $orderId,
                'product_id'           => $pid,
                'variant_id'           => !empty($lineVariants[$i]) ? (int) $lineVariants[$i] : null,
                'description'          => $lineDescs[$i] ?? '',
                'qty_sent'             => (float) ($lineQtys[$i] ?? 0),
                'qty_received'         => 0,
                'qty_scrap'            => 0,
                'warehouse_id'         => $orderData['warehouse_id'],
                'location_id'          => $orderData['location_id'],
            ]);
        }

        $db->transComplete();

        if ($db->transStatus()) {
            return redirect()->to('/subcontract-orders/' . $orderId)->with('success', 'Subcontract Order ' . $orderData['order_number'] . ' created.');
        }

        return redirect()->back()->withInput()->with('error', 'Failed to save order.');
    }

    // -------------------------------------------------------------------
    //  SHOW
    // -------------------------------------------------------------------
    public function show($id = null)
    {
        $this->requireAuth();

        $orderRecord = $this->orderModel->findByPublicIdOrId($id);
        if (!$orderRecord) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Subcontract order not found');
        }
        $numericId = (int)$orderRecord['id'];
        $order = $this->orderModel->getWithDetails($numericId);
        if (!$order) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Subcontract order not found');
        }

        $lines = $this->orderModel->getLines($numericId);
        $totals = $this->lineModel->getOrderTotals($numericId);

        $data = $this->setPageData([
            'page_title' => 'Subcontract Order ' . $order['order_number'],
            'order'      => $order,
            'lines'      => $lines,
            'totals'     => $totals,
            'statuses'   => SubcontractOrderModel::statusOptions(),
        ]);

        return view('subcontract_orders/show', $data);
    }

    // -------------------------------------------------------------------
    //  EDIT FORM
    // -------------------------------------------------------------------
    public function edit($id = null)
    {
        $this->requireAuth();

        $orderRecord = $this->orderModel->findByPublicIdOrId($id);
        if (!$orderRecord) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Subcontract order not found');
        }
        $numericId = (int)$orderRecord['id'];
        $order = $this->orderModel->getWithDetails($numericId);
        if (!$order) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Subcontract order not found');
        }

        if (!in_array($order['status'], ['draft', 'confirmed'])) {
            return redirect()->to('/subcontract-orders/' . $id)->with('error', 'Only draft or confirmed orders can be edited.');
        }

        $lines = $this->orderModel->getLines($numericId);

        $vendorModel   = new VendorModel();
        $vendors       = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        $productModel  = new ProductModel();
        $serviceProducts = $productModel->where('detailed_type', 'service')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $storableProducts = $productModel->where('detailed_type', 'storable')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();

        $db = Database::connect();
        $warehouses = $db->table('warehouses')->orderBy('name', 'ASC')->get()->getResultArray();
        $locations  = $db->table('warehouse_locations')->orderBy('name', 'ASC')->get()->getResultArray();

        $currencies = [];
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
        } catch (\Throwable $e) {}

        $company = (new \App\Models\CompanySettingsModel())->first();
        $defaultCurrency = $company['base_currency'] ?? 'PKR';

        $data = $this->setPageData([
            'page_title'       => 'Edit Subcontract Order ' . $order['order_number'],
            'order'            => $order,
            'lines'            => $lines,
            'vendors'          => $vendors,
            'serviceProducts'  => $serviceProducts,
            'storableProducts' => $storableProducts,
            'warehouses'       => $warehouses,
            'locations'        => $locations,
            'currencies'       => $currencies,
            'defaultCurrency'  => $defaultCurrency,
            'validation'       => \Config\Services::validation(),
        ]);

        return view('subcontract_orders/form', $data);
    }

    // -------------------------------------------------------------------
    //  UPDATE
    // -------------------------------------------------------------------
    public function update($id = null)
    {
        $this->requireAuth();

        $order = $this->orderModel->findByPublicIdOrId($id);
        if (!$order) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Subcontract order not found');
        }
        $id = (int)$order['id'];

        if (!in_array($order['status'], ['draft', 'confirmed'])) {
            return redirect()->back()->with('error', 'Only draft or confirmed orders can be updated.');
        }

        $db = Database::connect();
        $db->transStart();

        $orderData = [
            'vendor_id'            => (int) $this->request->getPost('vendor_id'),
            'service_product_id'   => (int) $this->request->getPost('service_product_id'),
            'quantity'             => (float) $this->request->getPost('quantity'),
            'unit_price'           => (float) $this->request->getPost('unit_price'),
            'currency'             => $this->request->getPost('currency') ?: 'PKR',
            'total'                => round((float) $this->request->getPost('quantity') * (float) $this->request->getPost('unit_price'), 2),
            'expected_return_date' => $this->request->getPost('expected_return_date') ?: null,
            'warehouse_id'         => $this->request->getPost('warehouse_id') ?: null,
            'location_id'          => $this->request->getPost('location_id') ?: null,
            'notes'                => $this->request->getPost('notes'),
        ];

        $this->orderModel->update($id, $orderData);

        // Rebuild lines: delete old, insert new
        $db->table('subcontract_order_lines')->where('subcontract_order_id', (int) $id)->delete();

        $lineProducts  = $this->request->getPost('line_product_id') ?? [];
        $lineVariants  = $this->request->getPost('line_variant_id') ?? [];
        $lineQtys      = $this->request->getPost('line_qty_sent') ?? [];
        $lineDescs     = $this->request->getPost('line_description') ?? [];

        for ($i = 0; $i < count($lineProducts); $i++) {
            $pid = (int) ($lineProducts[$i] ?? 0);
            if ($pid <= 0) continue;

            $this->lineModel->insert([
                'subcontract_order_id' => (int) $id,
                'product_id'           => $pid,
                'variant_id'           => !empty($lineVariants[$i]) ? (int) $lineVariants[$i] : null,
                'description'          => $lineDescs[$i] ?? '',
                'qty_sent'             => (float) ($lineQtys[$i] ?? 0),
                'qty_received'         => 0,
                'qty_scrap'            => 0,
                'warehouse_id'         => $orderData['warehouse_id'],
                'location_id'          => $orderData['location_id'],
            ]);
        }

        $db->transComplete();

        if ($db->transStatus()) {
            return redirect()->to('/subcontract-orders/' . $id)->with('success', 'Order updated.');
        }

        return redirect()->back()->withInput()->with('error', 'Failed to update order.');
    }

    // -------------------------------------------------------------------
    //  CONFIRM  (draft → confirmed)
    // -------------------------------------------------------------------
    public function confirm($id = null)
    {
        $this->requireAuth();
        $order = $this->orderModel->findByPublicIdOrId($id);
        if (!$order || $order['status'] !== 'draft') {
            return redirect()->back()->with('error', 'Order cannot be confirmed.');
        }
        $id = (int)$order['id'];

        $this->orderModel->update($id, ['status' => 'confirmed']);
        return redirect()->to('/subcontract-orders/' . $id)->with('success', 'Order confirmed.');
    }

    // -------------------------------------------------------------------
    //  ISSUE MATERIALS  (confirmed → issued)
    //  Deduces stock from warehouse/location for each material line
    // -------------------------------------------------------------------
    public function issueMaterials($id = null)
    {
        $this->requireAuth();
        $order = $this->orderModel->findByPublicIdOrId($id);
        if (!$order || !in_array($order['status'], ['confirmed'])) {
            return redirect()->back()->with('error', 'Materials can only be issued for confirmed orders.');
        }
        $id = (int)$order['id'];

        $lines = $this->lineModel->where('subcontract_order_id', (int) $id)->findAll();
        if (empty($lines)) {
            return redirect()->back()->with('error', 'No material lines to issue.');
        }

        $db = Database::connect();
        $db->transStart();

        $warehouseId = (int) ($order['warehouse_id'] ?? 1);
        $locationId  = (int) ($order['location_id'] ?? 0);

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $variantId = !empty($line['variant_id']) ? (int) $line['variant_id'] : null;
            $qty       = (float) $line['qty_sent'];

            if ($qty <= 0) continue;

            // Deduct from stock_balances
            $sbWhere = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'location_id' => $locationId];
            if ($variantId) $sbWhere['variant_id'] = $variantId;

            $existing = $db->table('stock_balances')->where($sbWhere)->get()->getRowArray();
            if ($existing) {
                $newQty = max(0, (float) $existing['quantity'] - $qty);
                $db->table('stock_balances')->where('id', $existing['id'])->update(['quantity' => $newQty, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                // Record negative balance entry
                $sbWhere['quantity']   = -$qty;
                $sbWhere['created_at'] = date('Y-m-d H:i:s');
                $sbWhere['updated_at'] = date('Y-m-d H:i:s');
                $db->table('stock_balances')->insert($sbWhere);
            }

            // Deduct from variant_inventory if variant
            if ($variantId) {
                $viWhere = ['variant_id' => $variantId, 'warehouse_id' => $warehouseId];
                $viRow = $db->table('variant_inventory')->where($viWhere)->get()->getRowArray();
                if ($viRow) {
                    $newVi = max(0, (float) $viRow['quantity'] - $qty);
                    $db->table('variant_inventory')->where('id', $viRow['id'])->update(['quantity' => $newVi, 'updated_at' => date('Y-m-d H:i:s')]);
                }
            }

            // Record stock movement
            $db->table('stock_movements')->insert([
                'product_id'     => $productId,
                'variant_id'     => $variantId,
                'warehouse_id'   => $warehouseId,
                'location_id'    => $locationId,
                'qty_change'     => -$qty,
                'movement_type'  => 'subcontract_out',
                'reference_type' => 'subcontract_order',
                'reference_id'   => (int) $id,
                'created_by'     => $this->currentUser['id'] ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        $this->orderModel->update($id, [
            'status'      => 'issued',
            'issued_date' => date('Y-m-d'),
        ]);

        $db->transComplete();

        if ($db->transStatus()) {
            return redirect()->to('/subcontract-orders/' . $id)->with('success', 'Materials issued. Stock has been deducted.');
        }
        return redirect()->back()->with('error', 'Failed to issue materials.');
    }

    // -------------------------------------------------------------------
    //  RECEIVE MATERIALS  (issued → partial_return or done)
    //  Adds stock back for received items, records scrap
    // -------------------------------------------------------------------
    public function receiveMaterials($id = null)
    {
        $this->requireAuth();
        $order = $this->orderModel->findByPublicIdOrId($id);
        if (!$order || !in_array($order['status'], ['issued', 'partial_return'])) {
            return redirect()->back()->with('error', 'Materials can only be received for issued or partially returned orders.');
        }
        $id = (int)$order['id'];

        $lineIds      = $this->request->getPost('line_id') ?? [];
        $qtyReceived  = $this->request->getPost('qty_received') ?? [];
        $qtyScrap     = $this->request->getPost('qty_scrap') ?? [];

        if (empty($lineIds)) {
            return redirect()->back()->with('error', 'No lines to receive.');
        }

        $db = Database::connect();
        $db->transStart();

        $warehouseId = (int) ($order['warehouse_id'] ?? 1);
        $locationId  = (int) ($order['location_id'] ?? 0);

        foreach ($lineIds as $i => $lineId) {
            $line = $this->lineModel->find((int) $lineId);
            if (!$line || (int) $line['subcontract_order_id'] !== (int) $id) continue;

            $received = (float) ($qtyReceived[$i] ?? 0);
            $scrap    = (float) ($qtyScrap[$i] ?? 0);

            if ($received <= 0 && $scrap <= 0) continue;

            // Update line
            $newReceived = (float) $line['qty_received'] + $received;
            $newScrap    = (float) $line['qty_scrap'] + $scrap;
            $this->lineModel->update($lineId, [
                'qty_received' => $newReceived,
                'qty_scrap'    => $newScrap,
            ]);

            $productId = (int) $line['product_id'];
            $variantId = !empty($line['variant_id']) ? (int) $line['variant_id'] : null;

            // Add received quantity back to stock_balances
            if ($received > 0) {
                $sbWhere = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'location_id' => $locationId];
                if ($variantId) $sbWhere['variant_id'] = $variantId;

                $existing = $db->table('stock_balances')->where($sbWhere)->get()->getRowArray();
                if ($existing) {
                    $newQty = (float) $existing['quantity'] + $received;
                    $db->table('stock_balances')->where('id', $existing['id'])->update(['quantity' => $newQty, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    $sbWhere['quantity']   = $received;
                    $sbWhere['created_at'] = date('Y-m-d H:i:s');
                    $sbWhere['updated_at'] = date('Y-m-d H:i:s');
                    $db->table('stock_balances')->insert($sbWhere);
                }

                // Add to variant_inventory
                if ($variantId) {
                    $viRow = $db->table('variant_inventory')->where(['variant_id' => $variantId, 'warehouse_id' => $warehouseId])->get()->getRowArray();
                    if ($viRow) {
                        $db->table('variant_inventory')->where('id', $viRow['id'])->update(['quantity' => (float) $viRow['quantity'] + $received, 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }

                // Record stock movement IN
                $db->table('stock_movements')->insert([
                    'product_id'     => $productId,
                    'variant_id'     => $variantId,
                    'warehouse_id'   => $warehouseId,
                    'location_id'    => $locationId,
                    'qty_change'     => $received,
                    'movement_type'  => 'subcontract_in',
                    'reference_type' => 'subcontract_order',
                    'reference_id'   => (int) $id,
                    'created_by'     => $this->currentUser['id'] ?? null,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }

            // Record scrap movement (stock lost)
            if ($scrap > 0) {
                $db->table('stock_movements')->insert([
                    'product_id'     => $productId,
                    'variant_id'     => $variantId,
                    'warehouse_id'   => $warehouseId,
                    'location_id'    => $locationId,
                    'qty_change'     => -$scrap,
                    'movement_type'  => 'subcontract_scrap',
                    'reference_type' => 'subcontract_order',
                    'reference_id'   => (int) $id,
                    'created_by'     => $this->currentUser['id'] ?? null,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Determine new status based on line totals
        $allLines = $this->lineModel->where('subcontract_order_id', (int) $id)->findAll();
        $allDone = true;
        foreach ($allLines as $l) {
            $totalAccountedFor = (float) $l['qty_received'] + (float) $l['qty_scrap'];
            if ($totalAccountedFor < (float) $l['qty_sent']) {
                $allDone = false;
                break;
            }
        }

        $newStatus = $allDone ? 'done' : 'partial_return';
        $updateData = ['status' => $newStatus];
        if ($allDone) {
            $updateData['actual_return_date'] = date('Y-m-d');
        }
        $this->orderModel->update($id, $updateData);

        $db->transComplete();

        if ($db->transStatus()) {
            $msg = $allDone ? 'All materials received. Order completed.' : 'Partial receipt recorded.';
            return redirect()->to('/subcontract-orders/' . $id)->with('success', $msg);
        }
        return redirect()->back()->with('error', 'Failed to receive materials.');
    }

    // -------------------------------------------------------------------
    //  CANCEL
    // -------------------------------------------------------------------
    public function cancel($id = null)
    {
        $this->requireAuth();
        $order = $this->orderModel->findByPublicIdOrId($id);
        if (!$order) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Order not found');
        }
        $id = (int)$order['id'];

        // Cannot cancel if materials are already partially/fully returned
        if (in_array($order['status'], ['done'])) {
            return redirect()->back()->with('error', 'Completed orders cannot be cancelled.');
        }

        $db = Database::connect();
        $db->transStart();

        // If materials were issued, reverse the stock deductions
        if (in_array($order['status'], ['issued', 'partial_return'])) {
            $lines = $this->lineModel->where('subcontract_order_id', (int) $id)->findAll();
            $warehouseId = (int) ($order['warehouse_id'] ?? 1);
            $locationId  = (int) ($order['location_id'] ?? 0);

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $variantId = !empty($line['variant_id']) ? (int) $line['variant_id'] : null;
                $qtyToRestore = (float) $line['qty_sent'] - (float) $line['qty_received'];

                if ($qtyToRestore <= 0) continue;

                // Restore stock_balances
                $sbWhere = ['product_id' => $productId, 'warehouse_id' => $warehouseId, 'location_id' => $locationId];
                if ($variantId) $sbWhere['variant_id'] = $variantId;

                $existing = $db->table('stock_balances')->where($sbWhere)->get()->getRowArray();
                if ($existing) {
                    $db->table('stock_balances')->where('id', $existing['id'])->update([
                        'quantity' => (float) $existing['quantity'] + $qtyToRestore,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Restore variant_inventory
                if ($variantId) {
                    $viRow = $db->table('variant_inventory')->where(['variant_id' => $variantId, 'warehouse_id' => $warehouseId])->get()->getRowArray();
                    if ($viRow) {
                        $db->table('variant_inventory')->where('id', $viRow['id'])->update([
                            'quantity' => (float) $viRow['quantity'] + $qtyToRestore,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                // Record reversal movement
                $db->table('stock_movements')->insert([
                    'product_id'     => $productId,
                    'variant_id'     => $variantId,
                    'warehouse_id'   => $warehouseId,
                    'location_id'    => $locationId,
                    'qty_change'     => $qtyToRestore,
                    'movement_type'  => 'subcontract_cancel',
                    'reference_type' => 'subcontract_order',
                    'reference_id'   => (int) $id,
                    'created_by'     => $this->currentUser['id'] ?? null,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->orderModel->update($id, ['status' => 'cancelled']);

        $db->transComplete();

        if ($db->transStatus()) {
            return redirect()->to('/subcontract-orders/' . $id)->with('success', 'Order cancelled. Stock restored where applicable.');
        }
        return redirect()->back()->with('error', 'Failed to cancel order.');
    }

    // -------------------------------------------------------------------
    //  AJAX: Search service products
    // -------------------------------------------------------------------
    public function searchServiceProducts()
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $productModel = new ProductModel();
        $results = $productModel->where('detailed_type', 'service')
                                ->where('is_active', 1)
                                ->groupStart()
                                    ->like('name', $q)
                                    ->orLike('code', $q)
                                ->groupEnd()
                                ->orderBy('name', 'ASC')
                                ->findAll(20);

        return $this->response->setJSON(['success' => true, 'data' => $results]);
    }

    // -------------------------------------------------------------------
    //  AJAX: Search storable products (for material lines)
    // -------------------------------------------------------------------
    public function searchStorableProducts()
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $productModel = new ProductModel();
        $results = $productModel->where('detailed_type', 'storable')
                                ->where('is_active', 1)
                                ->groupStart()
                                    ->like('name', $q)
                                    ->orLike('code', $q)
                                ->groupEnd()
                                ->orderBy('name', 'ASC')
                                ->findAll(20);

        return $this->response->setJSON(['success' => true, 'data' => $results]);
    }

    // -------------------------------------------------------------------
    //  AJAX: Variants for a selected storable product
    // -------------------------------------------------------------------
    public function productVariants($productId = null)
    {
        $pid = (int)$productId;
        if ($pid <= 0) {
            return $this->response->setJSON(['success' => false, 'data' => []]);
        }

        $db = Database::connect();
        if (! $db->tableExists('product_variants')) {
            return $this->response->setJSON(['success' => true, 'data' => []]);
        }

        $rows = $db->table('product_variants')
            ->select('id, art_number, name, attributes')
            ->where('product_id', $pid)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $data = [];
        foreach ($rows as $r) {
            $attrs = [];
            if (!empty($r['attributes'])) {
                try {
                    $attrs = is_string($r['attributes']) ? (json_decode($r['attributes'], true) ?? []) : (is_array($r['attributes']) ? $r['attributes'] : []);
                } catch (\Throwable $e) {
                    $attrs = [];
                }
            }
            $attrParts = [];
            if (is_array($attrs)) {
                foreach ($attrs as $k => $v) {
                    $attrParts[] = trim((string)$k) . ': ' . trim((string)$v);
                }
            }

            $data[] = [
                'id' => (int)$r['id'],
                'art_number' => (string)($r['art_number'] ?? ''),
                'name' => (string)($r['name'] ?? ''),
                'attributes' => $attrs,
                'label' => trim((string)($r['art_number'] ?? '') . ' ' . (!empty($attrParts) ? '• ' . implode(' • ', $attrParts) : '')),
            ];
        }

        return $this->response->setJSON(['success' => true, 'data' => $data]);
    }
}
