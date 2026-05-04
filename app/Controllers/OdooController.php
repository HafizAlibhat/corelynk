<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class OdooController extends BaseController
{
    protected $odooUrl;
    protected $dbName;
    protected $username;
    protected $password;

    public function __construct()
    {
        // Prefer settings from DB (saved via Settings UI). Fall back to environment vars.
        try {
            $db = \Config\Database::connect();
            // Ensure helper table for claims exists
            $db->query("CREATE TABLE IF NOT EXISTS odoo_screen_claims (odoo_id INT PRIMARY KEY, claimed_by VARCHAR(150), claimed_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            // Ensure a small cache table for expensive screen aggregations
            $db->query("CREATE TABLE IF NOT EXISTS odoo_screen_cache (`cache_key` VARCHAR(191) PRIMARY KEY, `data` LONGTEXT, `expires_at` DATETIME NULL, `updated_at` DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $row = $db->query("SELECT * FROM odoo_settings LIMIT 1")->getRowArray();
            if ($row) {
                $host = rtrim($row['host'] ?? '', '/');
                $port = !empty($row['port']) ? (int)$row['port'] : null;
                $this->odooUrl = $host . ($port ? ':' . $port : '');
                $this->dbName = $row['db_name'] ?? ($this->dbName ?? getenv('ODOO_DB') ?: 'odoo');
                $this->username = $row['username'] ?? getenv('ODOO_USER');
                $this->password = $row['password'] ?? getenv('ODOO_PWD');
                return;
            }
        } catch (\Throwable $e) {
            // ignore and fall back to env
        }
        

        // Fall back to environment with sensible defaults for local development
        $this->odooUrl = getenv('ODOO_URL') ?: 'http://localhost:8069';
        $this->dbName = getenv('ODOO_DB') ?: 'odoo';
        $this->username = getenv('ODOO_USER') ?: 'admin';
        $this->password = getenv('ODOO_PWD') ?: 'admin';
        // Note: avoid performing HTTP calls or returning responses from the constructor.
        // Initialization should only set configuration values; any test/login actions
        // are performed by dedicated controller methods (e.g. apiTest).
    }

    /**
     * Cached recent sales list (reads from sales_cache) to be used by the UI so we can show customer ref.
     */
    public function screenApiSales()
    {
        $limit = (int)($this->request->getGet('limit') ?? 20);
        $sort = $this->request->getGet('sort') ?? 'newest';
        $dir = ($sort === 'oldest') ? 'asc' : 'desc';
        $offset = (int)($this->request->getGet('offset') ?? 0);
        try {
            $db = \Config\Database::connect();
            // enforce an upper cap to avoid accidental huge queries
            $limit = max(1, min(100, $limit));
            $rows = $db->table('sales_cache')->orderBy('date_order',$dir)->limit($limit, $offset)->get()->getResultArray();
            $out = [];
            foreach ($rows as $r) {
                $out[] = ['odoo_id' => $r['odoo_id'], 'name' => $r['name'], 'customer_code' => $r['partner_code'], 'date_order' => $r['date_order']];
            }
            return $this->response->setJSON(['ok'=>true,'data'=>$out]);
        } catch (\Throwable $e) {
            log_message('error','screenApiSales failed: '.$e->getMessage());
            return $this->response->setJSON(['ok'=>false,'error'=>'db_error']);
        }
    }

    public function index()
    {
        return view('odoo/index');
    }

    public function apiSales()
    {
        // Return a small list of recent sales orders with pagination
        $limit = (int)($this->request->getGet('limit') ?? 10);
        $offset = (int)($this->request->getGet('offset') ?? 0);
        $fields = ['id','name','partner_id','date_order','commitment_date','state','amount_total'];
        $domain = [['state','!=','cancel']];
        $opts = ['limit' => $limit, 'offset' => $offset, 'order' => 'date_order desc'];
        $data = $this->callOdooModel('sale.order', $domain, $fields, $opts);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    public function apiCustomers()
    {
        // Fetch partners (customers) from Odoo. We limit fields to common ones used for import/preview.
        $fields = ['id','name','ref','email','phone','mobile','street','street2','city','zip','state_id','country_id','website','customer_rank','is_company','company_id','company_name'];
        // domain: customer_rank > 0 or where is a contact with commercial partner
        $domain = [['customer_rank','>',0]];
        $data = $this->callOdooModel('res.partner', $domain, $fields, 1000);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    /**
     * Test connection to Odoo by performing a login RPC call and returning the result.
     */
    public function apiTest()
    {
        try {
            $loginPayload = [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'login',
                    'args' => [$this->dbName, $this->username, $this->password]
                ],
                'id' => uniqid()
            ];
            $res = $this->postJson($this->odooUrl . '/jsonrpc', $loginPayload);
            return $this->response->setJSON(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            log_message('error', 'apiTest failed: '.$e->getMessage());
            return $this->response->setJSON(['ok' => false, 'error' => 'test_failed', 'message' => $e->getMessage()]);
        }
    }

    /** Screen view (interactive + kiosk) */
    public function screenView()
    {
        return view('odoo/screen');
    }

    /** Helper: fetch sale orders (recent/active) with basic fields */
    protected function fetchSaleOrders(array $domain = [], int $limit = 200)
    {
        $fields = ['id','name','partner_id','date_order','commitment_date','user_id','state','order_line','amount_total'];
        return $this->callOdooModel('sale.order', $domain, $fields, $limit);
    }

    protected function fetchSaleOrderLines(array $lineIds)
    {
        if (empty($lineIds)) return [];
        $fields = ['id','order_id','product_id','product_uom_qty','qty_delivered','product_uom','price_unit'];
        // Use the generic callOdooModel with a proper domain to fetch lines in batch
        $domain = [['id','in',$lineIds]];
        $res = $this->callOdooModel('sale.order.line', $domain, $fields, ['limit'=>count($lineIds), 'order'=>'id']);
        if (isset($res['error'])) return [];
        return is_array($res) ? $res : [];
    }

    protected function fetchPurchaseLinesByProduct($productId)
    {
        $fields = ['id','order_id','product_id','product_qty','qty_received','name','state','order_id'];
        $domain = [['product_id','=', $productId]];
        $res = $this->callOdooModel('purchase.order.line', $domain, $fields, 200);
        return $res;
    }

    public function screenSummary()
    {
        // Simple aggregated KPIs
        // Use short-lived cache to avoid expensive repeated Odoo calls
        $cache = $this->getOdooCache('screen_summary');
        if ($cache !== null) {
            return $this->response->setJSON($cache);
        }

        // If we have local mapping/cache populated, prefer it for fast responses
        try {
            $db = \Config\Database::connect();
            $cnt = $db->table('sales_cache')->countAllResults(false);
            if ($cnt > 0) {
                $pendingCount = (int)$db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 1)->countAllResults(false);
                $pendingQty = (float)$db->table('sales_cache')->selectSum('remaining_qty')->where('remaining_qty >', 0)->where('has_pending_po', 1)->get()->getRowArray()['remaining_qty'] ?? 0;
                $readyCount = (int)$db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 0)->countAllResults(false);
                $readyQty = (float)$db->table('sales_cache')->selectSum('remaining_qty')->where('remaining_qty >', 0)->where('has_pending_po', 0)->get()->getRowArray()['remaining_qty'] ?? 0;
                $openPosCount = (int)$db->table('purchases_cache')->where('outstanding_qty >', 0)->countAllResults(false);
                $alertsCount = (int)$db->table('sales_cache')->where('remaining_qty >', 0)->where('commitment_date <', date('Y-m-d'))->countAllResults(false);
                $out = [
                    'ok' => true,
                    'last_refresh' => date('c'),
                    'pending_to_ship_count' => $pendingCount,
                    'pending_to_ship_qty' => $pendingQty,
                    'ready_to_ship_count' => $readyCount,
                    'ready_to_ship_qty' => $readyQty,
                    'open_pos_count' => $openPosCount,
                    'alerts_count' => $alertsCount
                ];
                $this->setOdooCache('screen_summary', $out, 30);
                return $this->response->setJSON($out);
            }
        } catch (\Throwable $e) {
            // fallback to live aggregation if cache read fails
            log_message('error','screenSummary cache read failed: '.$e->getMessage());
        }

        $sales = $this->fetchSaleOrders([['state','in',['sale','done']]], 500);
        $pendingCount = 0; $pendingQty = 0; $readyCount = 0; $readyQty = 0;

        // Batch approach: gather all line ids and product ids, fetch once
        $allLineIds = [];
        foreach ($sales as $s) {
            if (!empty($s['order_line']) && is_array($s['order_line'])) {
                foreach ($s['order_line'] as $li) $allLineIds[] = $li;
            }
        }
        $allLineIds = array_values(array_unique($allLineIds));
        $lines = $this->fetchSaleOrderLines($allLineIds);

        $linesByOrder = [];
        $productIds = [];
        foreach ($lines as $l) {
            $orderId = is_array($l['order_id']) ? $l['order_id'][0] : $l['order_id'];
            if (!$orderId) continue;
            $linesByOrder[$orderId][] = $l;
            $prodId = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
            if ($prodId) $productIds[$prodId] = $prodId;
        }
        $productIds = array_values($productIds);
        $pls = [];
        if (!empty($productIds)) {
            $pls = $this->callOdooModel('purchase.order.line', [['product_id','in',$productIds]], ['id','order_id','product_id','product_qty','qty_received'], 1000);
        }
        $plsByProduct = [];
        foreach ($pls as $pl) {
            $pid = is_array($pl['product_id']) ? $pl['product_id'][0] : $pl['product_id'];
            if ($pid) $plsByProduct[$pid][] = $pl;
        }

        foreach ($sales as $s) {
            $orderId = $s['id'];
            $sLines = $linesByOrder[$orderId] ?? [];
            $remaining = 0; $hasPendingPo = false;
            foreach ($sLines as $l) {
                $need = ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0);
                if ($need > 0) {
                    $remaining += $need;
                    $prodId = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
                    $productPLs = $plsByProduct[$prodId] ?? [];
                    foreach ($productPLs as $pl) {
                        $outstanding = ($pl['product_qty'] ?? 0) - ($pl['qty_received'] ?? 0);
                        if ($outstanding > 0) { $hasPendingPo = true; break; }
                    }
                }
            }
            if ($remaining > 0) {
                if ($hasPendingPo) { $pendingCount++; $pendingQty += $remaining; }
                else { $readyCount++; $readyQty += $remaining; }
            }
        }
        // POs count
        $pos = $this->callOdooModel('purchase.order', [], ['id'], 500);
        $openPosCount = is_array($pos) ? count($pos) : 0;

        $out = [
            'ok' => true,
            'last_refresh' => date('c'),
            'pending_to_ship_count' => $pendingCount,
            'pending_to_ship_qty' => $pendingQty,
            'ready_to_ship_count' => $readyCount,
            'ready_to_ship_qty' => $readyQty,
            'open_pos_count' => $openPosCount,
            'alerts_count' => 0
        ];
        // cache for 30 seconds
        $this->setOdooCache('screen_summary', $out, 30);
        return $this->response->setJSON($out);
    }

    public function screenPending()
    {
    // limit rows returned to avoid flooding the screen (default 20)
    $limit = (int)($this->request->getGet('limit') ?? 20);
    $limit = max(1, min(200, $limit));
    $sort = $this->request->getGet('sort') ?? 'newest';
    $dir = ($sort === 'oldest') ? 'asc' : 'desc';
        // Use cache to reduce load
        $cache = $this->getOdooCache('screen_pending');
        if ($cache !== null) {
            // respect sort param and limit when returning cached data
            if (isset($cache['data']) && is_array($cache['data'])) {
                $data = $cache['data'];
                usort($data, function($a,$b) use ($dir) {
                    $ad = $a['due_date'] ?? $a['commitment_date'] ?? $a['date_order'] ?? null;
                    $bd = $b['due_date'] ?? $b['commitment_date'] ?? $b['date_order'] ?? null;
                    $at = $ad ? strtotime($ad) : 0;
                    $bt = $bd ? strtotime($bd) : 0;
                    $cmp = $at <=> $bt;
                    return ($dir === 'asc') ? $cmp : -$cmp;
                });
                $cache['data'] = array_slice($data, 0, $limit);
            }
            return $this->response->setJSON($cache);
        }
        $rows = [];
        try {
            $db = \Config\Database::connect();
            $cnt = $db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 1)->countAllResults(false);
                if ($cnt > 0) {
                // order by sale date by default; accept sort param (newest/oldest)
                $results = $db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 1)->orderBy('date_order', $dir)->limit($limit)->get()->getResultArray();
                foreach ($results as $r) {
                    $orderId = $r['odoo_id'];
                    $lines = $db->table('sale_lines_cache')->where('order_id', $orderId)->get()->getResultArray();
                    $products = [];
                    $productIds = [];
                    foreach ($lines as $l) {
                        $need = max(0, ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0));
                        if ($need > 0) {
                            // determine product display name robustly (handle variants)
                            $pname = $l['product_name'] ?? null;
                            $product_code = null;
                            if (empty($pname) && !empty($l['metadata'])) {
                                $m = json_decode($l['metadata'], true);
                                if (!empty($m)) {
                                    if (!empty($m['product_id']) && is_array($m['product_id'])) {
                                        $pname = $m['product_id'][1] ?? $pname;
                                        // metadata.product_id[0] is sometimes the numeric id — keep as fallback code
                                        $product_code = $product_code ?? ($m['product_id'][0] ?? $product_code);
                                    }
                                    if (empty($pname) && !empty($m['display_name'])) $pname = $m['display_name'];
                                    if (empty($pname) && !empty($m['name'])) $pname = $m['name'];
                                    if (empty($product_code) && !empty($m['default_code'])) $product_code = $m['default_code'];
                                    if (empty($product_code) && !empty($m['product_template_id']) && is_array($m['product_template_id'])) $product_code = $m['product_template_id'][1] ?? $product_code;
                                }
                            }
                            // final fallback: try local products table (if product_id refers to local product)
                            if (empty($pname) && !empty($l['product_id'])) {
                                try {
                                    $prow = $db->table('products')->where('id', $l['product_id'])->get()->getRowArray();
                                    if ($prow) {
                                        $pname = $prow['name'] ?? $prow['product_name'] ?? $pname;
                                        $product_code = $product_code ?? ($prow['code'] ?? $prow['product_code'] ?? null);
                                    }
                                } catch (\Throwable $e) {
                                    // ignore lookup failures
                                }
                            }
                            $products[] = ['product_id'=>$l['product_id'],'code'=>$product_code,'name'=>$pname,'qty_needed'=>$need];
                            if ($l['product_id']) $productIds[$l['product_id']] = $l['product_id'];
                        }
                    }

                    // Find related purchase orders for these products (outstanding)
                    $relatedPos = [];
                    if (!empty($productIds)) {
                        $pids = array_values($productIds);
                        $pls = $db->table('purchase_lines_cache')->select('order_id,product_id,product_qty,qty_received')->whereIn('product_id', $pids)->where('product_qty > qty_received')->get()->getResultArray();
                        $poMap = [];
                        foreach ($pls as $pl) {
                            $poMap[$pl['order_id']] = $poMap[$pl['order_id']] ?? ['id'=>$pl['order_id'],'products'=>[],'outstanding'=>0];
                            $poMap[$pl['order_id']]['products'][$pl['product_id']] = ['product_qty'=>$pl['product_qty'],'qty_received'=>$pl['qty_received']];
                            $poMap[$pl['order_id']]['outstanding'] += max(0, ($pl['product_qty'] ?? 0) - ($pl['qty_received'] ?? 0));
                        }
                        if (!empty($poMap)) {
                            $poIds = array_keys($poMap);
                            $poRows = $db->table('purchases_cache')->whereIn('odoo_id', $poIds)->get()->getResultArray();
                            foreach ($poRows as $pr) {
                                $pid = $pr['odoo_id'];
                                $relatedPos[] = ['odoo_id'=>$pid,'name'=>$pr['name'],'outstanding_qty'=> (float)($poMap[$pid]['outstanding'] ?? 0)];
                            }
                        }
                    }

                    $due = $r['commitment_date'] ?? null;
                    if (is_string($due) && strtolower(trim($due)) === 'not selected') $due = null;
                    $orderDate = $r['date_order'] ?? null;
                    if (is_string($orderDate) && strtolower(trim($orderDate)) === 'not selected') $orderDate = null;
                    $rows[] = [
                        'odoo_id'=>$r['odoo_id'],
                        'name'=>$r['name'],
                        'customer_code'=>$r['partner_code'],
                        'order_date'=>$orderDate,
                        'delivery_date'=>$due,
                        'remaining_qty'=> (float)$r['remaining_qty'],
                        'products'=>$products,
                        'related_pos'=>$relatedPos,
                        'assigned_user'=> !empty($r['user_id']) ? $this->getUserDisplay($r['user_id']) : null
                    ];
                }
                $out = ['ok'=>true,'data'=>$rows];
                $this->setOdooCache('screen_pending', $out, 30);
                return $this->response->setJSON($out);
            }
        } catch (\Throwable $e) {
            log_message('error','screenPending cache read failed: '.$e->getMessage());
        }
    // slice just in case fallback path produced > limit
    $out = ['ok'=>true,'data'=>array_slice($rows,0,$limit)];
        $this->setOdooCache('screen_pending', $out, 30);
        return $this->response->setJSON($out);
    }

    public function screenReady()
    {
        $limit = (int)($this->request->getGet('limit') ?? 20);
        $limit = max(1, min(200, $limit));
        $sort = $this->request->getGet('sort') ?? 'newest';
        $dir = ($sort === 'oldest') ? 'asc' : 'desc';
        $cache = $this->getOdooCache('screen_ready');
        if ($cache !== null) {
            if (isset($cache['data']) && is_array($cache['data'])) {
                $data = $cache['data'];
                usort($data, function($a,$b) use ($dir) {
                    $ad = $a['ship_by'] ?? $a['commitment_date'] ?? $a['date_order'] ?? null;
                    $bd = $b['ship_by'] ?? $b['commitment_date'] ?? $b['date_order'] ?? null;
                    $at = $ad ? strtotime($ad) : 0;
                    $bt = $bd ? strtotime($bd) : 0;
                    $cmp = $at <=> $bt;
                    return ($dir === 'asc') ? $cmp : -$cmp;
                });
                $cache['data'] = array_slice($data,0,$limit);
            }
            return $this->response->setJSON($cache);
        }
        $rows = [];
        try {
            $db = \Config\Database::connect();
            $cnt = $db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 0)->countAllResults(false);
            if ($cnt > 0) {
                $results = $db->table('sales_cache')->where('remaining_qty >', 0)->where('has_pending_po', 0)->orderBy('date_order', $dir)->limit($limit)->get()->getResultArray();
                foreach ($results as $r) {
                    $orderId = $r['odoo_id'];
                    $lines = $db->table('sale_lines_cache')->where('order_id', $orderId)->get()->getResultArray();
                    $products = [];
                    foreach ($lines as $l) {
                        $need = max(0, ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0));
                        if ($need > 0) {
                            $pname = $l['product_name'] ?? null;
                            $product_code = null;
                            if (empty($pname) && !empty($l['metadata'])) {
                                $m = json_decode($l['metadata'], true);
                                if (!empty($m)) {
                                    if (!empty($m['product_id']) && is_array($m['product_id'])) {
                                        $pname = $m['product_id'][1] ?? $pname;
                                        $product_code = $product_code ?? ($m['product_id'][0] ?? $product_code);
                                    }
                                    if (empty($pname) && !empty($m['display_name'])) $pname = $m['display_name'];
                                    if (empty($pname) && !empty($m['name'])) $pname = $m['name'];
                                    if (empty($product_code) && !empty($m['default_code'])) $product_code = $m['default_code'];
                                    if (empty($product_code) && !empty($m['product_template_id']) && is_array($m['product_template_id'])) $product_code = $m['product_template_id'][1] ?? $product_code;
                                }
                            }
                            if (empty($pname) && !empty($l['product_id'])) {
                                try {
                                    $prow = $db->table('products')->where('id', $l['product_id'])->get()->getRowArray();
                                    if ($prow) {
                                        $pname = $prow['name'] ?? $prow['product_name'] ?? $pname;
                                        $product_code = $product_code ?? ($prow['code'] ?? $prow['product_code'] ?? null);
                                    }
                                } catch (\Throwable $e) {}
                            }
                            $products[] = ['product_id'=>$l['product_id'],'code'=>$product_code,'name'=>$pname,'qty_needed'=>$need];
                        }
                    }
                    $ship = $r['commitment_date'] ?? $r['date_order'] ?? null;
                    if (is_string($ship) && strtolower(trim($ship)) === 'not selected') $ship = null;
                    $orderDate = $r['date_order'] ?? null;
                    if (is_string($orderDate) && strtolower(trim($orderDate)) === 'not selected') $orderDate = null;
                    $rows[] = [
                        'odoo_id'=>$r['odoo_id'],
                        'name'=>$r['name'],
                        'customer_code'=>$r['partner_code'],
                        'order_date'=>$orderDate,
                        'delivery_date'=>$ship,
                        'qty_ready'=> (float)$r['remaining_qty'],
                        'products'=>$products,
                        'assigned_user'=> !empty($r['user_id']) ? $this->getUserDisplay($r['user_id']) : null
                    ];
                }
                $out = ['ok'=>true,'data'=>array_slice($rows,0,$limit)];
                $this->setOdooCache('screen_ready', $out, 30);
                return $this->response->setJSON($out);
            }
        } catch (\Throwable $e) {
            log_message('error','screenReady cache read failed: '.$e->getMessage());
        }
        $out = ['ok'=>true,'data'=>$rows];
        $this->setOdooCache('screen_ready', $out, 30);
        return $this->response->setJSON($out);
    }
    /**
     * Helper to get display name for assigned user (from users table)
     */
    protected function getUserDisplay($userId) {
        try {
            $db = \Config\Database::connect();
            $row = $db->table('users')->where('id', $userId)->get()->getRowArray();
            if ($row) {
                return [
                    'id' => $row['id'],
                    'name' => $row['username'],
                    'full_name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))
                ];
            }
        } catch (\Throwable $e) {}
        return null;

        // Fetch sale orders
        $sales = $this->fetchSaleOrders([['state','in',['sale','done']]], 500);
        // Collect line ids
        $allLineIds = [];
        foreach ($sales as $s) {
            if (!empty($s['order_line']) && is_array($s['order_line'])) {
                foreach ($s['order_line'] as $li) $allLineIds[] = $li;
            }
        }
        $allLineIds = array_values(array_unique($allLineIds));
        $lines = $this->fetchSaleOrderLines($allLineIds);

        // Map lines by order and collect products
        $linesByOrder = [];
        $productIds = [];
        foreach ($lines as $l) {
            $orderId = is_array($l['order_id']) ? $l['order_id'][0] : $l['order_id'];
            if (!$orderId) continue;
            $linesByOrder[$orderId][] = $l;
            $prodId = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
            if ($prodId) $productIds[$prodId] = $prodId;
        }
        $productIds = array_values($productIds);

        // Fetch purchase lines for these products (to determine if there are pending POs)
        $pls = [];
        if (!empty($productIds)) {
            $pls = $this->callOdooModel('purchase.order.line', [['product_id','in',$productIds]], ['id','order_id','product_id','product_qty','qty_received','name','state'], 1000);
        }
        $plsByProduct = [];
        foreach ($pls as $pl) {
            $pid = is_array($pl['product_id']) ? $pl['product_id'][0] : $pl['product_id'];
            if ($pid) $plsByProduct[$pid][] = $pl;
        }

        // Build rows for ready (remaining >0 & no pending PO)
        foreach ($sales as $s) {
            $orderId = $s['id'];
            $sLines = $linesByOrder[$orderId] ?? [];
            $remaining = 0; $products = [];
            $hasPendingPo = false;
            foreach ($sLines as $l) {
                $need = ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0);
                if ($need > 0) {
                    $remaining += $need;
                    $prodId = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
                    $prodName = is_array($l['product_id']) ? ($l['product_id'][1] ?? '') : '';
                    $products[] = ['product_id'=>$prodId,'name'=>$prodName,'qty_needed'=>$need];
                    $productPLs = $plsByProduct[$prodId] ?? [];
                    foreach ($productPLs as $pl) {
                        $outstanding = ($pl['product_qty'] ?? 0) - ($pl['qty_received'] ?? 0);
                        if ($outstanding > 0) { $hasPendingPo = true; break; }
                    }
                }
            }
                if ($remaining > 0 && !$hasPendingPo) {
                // Prefer partner ref if available in partnerRefMap; otherwise keep empty for privacy
                $pid = is_array($s['partner_id']) ? ($s['partner_id'][0] ?? null) : ($s['partner_id'] ?? null);
                $custCode = isset($partnerRefMap) ? ($partnerRefMap[$pid] ?? '') : '';
                $rows[] = [
                    'odoo_id'=>$s['id'],'name'=>$s['name'],'customer_code'=>$custCode,
                    'ship_by'=>$s['commitment_date'] ?? $s['date_order'] ?? null,
                    'qty_ready'=>$remaining,'products'=>$products,
                    'assigned_user'=> is_array($s['user_id'])?['id'=>$s['user_id'][0],'name'=>$s['user_id'][1]]:null,
                ];
            }
        }

        $out = ['ok'=>true,'data'=>$rows];
        $this->setOdooCache('screen_ready', $out, 30);
        return $this->response->setJSON($out);
    }

    public function screenPurchases()
    {
        $limit = (int)($this->request->getGet('limit') ?? 20);
        $limit = max(1, min(200, $limit));
        $sort = $this->request->getGet('sort') ?? 'newest';
        $dir = ($sort === 'oldest') ? 'asc' : 'desc';
        $cache = $this->getOdooCache('screen_purchases');
        if ($cache !== null) {
            if (isset($cache['data']) && is_array($cache['data'])) {
                $data = $cache['data'];
                usort($data, function($a,$b) use ($dir) {
                    $ad = $a['order_date'] ?? $a['date_order'] ?? null;
                    $bd = $b['order_date'] ?? $b['date_order'] ?? null;
                    $at = $ad ? strtotime($ad) : 0;
                    $bt = $bd ? strtotime($bd) : 0;
                    $cmp = $at <=> $bt;
                    return ($dir === 'asc') ? $cmp : -$cmp;
                });
                $cache['data'] = array_slice($data,0,$limit);
            }
            return $this->response->setJSON($cache);
        }
        try {
            $db = \Config\Database::connect();
            $cnt = $db->table('purchases_cache')->countAllResults(false);
            if ($cnt > 0) {
                $rows = $db->table('purchases_cache')->orderBy('date_order',$dir)->limit($limit)->get()->getResultArray();
                $out = [];
                foreach ($rows as $r) {
                    $meta = [];
                    if (!empty($r['metadata'])) {
                        $meta = json_decode($r['metadata'], true) ?: [];
                    }
                    $vendorName = $meta['partner_id'][1] ?? null;
                    // pick expected/receipt date if present in metadata
                    $expected = $meta['date_planned'] ?? $meta['expected_date'] ?? null;
                    $orderDate = $r['date_order'] ?? null;
                    $out[] = [
                        'odoo_id'=>$r['odoo_id'],
                        'name'=>$r['name'],
                        'vendor'=>['id'=>$r['partner_id'],'code'=>$r['partner_code'],'name'=>$vendorName],
                        'order_date'=>$orderDate,
                        'expected_date'=>$expected,
                        'ordered_qty'=> (float)$r['ordered_qty'],'received_qty'=> (float)$r['received_qty'],'outstanding_qty'=> (float)$r['outstanding_qty'],'status'=>$r['state']
                    ];
                }
                $res = ['ok'=>true,'data'=>$out];
                $this->setOdooCache('screen_purchases', $res, 60);
                return $this->response->setJSON($res);
            }
        } catch (\Throwable $e) {
            log_message('error','screenPurchases cache read failed: '.$e->getMessage());
        }

        $rows = $this->callOdooModel('purchase.order', [], ['id','name','partner_id','date_order','state','order_line'], 500);
        // expand lines to compute outstanding
        $out=[];
        foreach ($rows as $r) {
            $ol = [];$outstanding=0;$ordered=0;$received=0;
            $lineIds = $r['order_line'] ?? [];
            if (!empty($lineIds)) {
                $lines = $this->callOdooModel('purchase.order.line', [['order_id','=', $r['id']]], ['id','product_id','product_qty','qty_received'], 200);
                foreach ($lines as $l) {
                    $ordered += $l['product_qty'] ?? 0;
                    $received += $l['qty_received'] ?? 0;
                    $outstanding += max(0, ($l['product_qty'] ?? 0) - ($l['qty_received'] ?? 0));
                    $ol[] = $l;
                }
            }
            $out[] = ['odoo_id'=>$r['id'],'name'=>$r['name'],'vendor'=>is_array($r['partner_id'])?['id'=>$r['partner_id'][0],'code'=>$r['partner_id'][1]]:null,'ordered_qty'=>$ordered,'received_qty'=>$received,'outstanding_qty'=>$outstanding,'status'=>$r['state']];
        }
        $res = ['ok'=>true,'data'=>array_slice($out,0,$limit)];
        $this->setOdooCache('screen_purchases', $res, 60);
        return $this->response->setJSON($res);
    }

    /**
     * Debug helper: return raw sale_lines_cache rows for a given sales order name or odoo_id.
     * Usage: GET ?so_name=S00400  or ?odoo_id=400
     */
    public function screenDebugSaleLines()
    {
        $soName = $this->request->getGet('so_name');
        $odooId = $this->request->getGet('odoo_id');
        try {
            $db = \Config\Database::connect();
            if ($soName) {
                $s = $db->table('sales_cache')->select('odoo_id')->where('name', $soName)->get()->getRowArray();
                if (!$s) return $this->response->setJSON(['ok'=>false,'error'=>'so_not_found']);
                $odooId = $s['odoo_id'];
            }
            if (!$odooId) return $this->response->setJSON(['ok'=>false,'error'=>'odoo_id_or_so_name_required']);
            $rows = $db->table('sale_lines_cache')->where('order_id', $odooId)->get()->getResultArray();
            return $this->response->setJSON(['ok'=>true,'count'=>count($rows),'data'=>$rows]);
        } catch (\Throwable $e) {
            log_message('error','screenDebugSaleLines failed: '.$e->getMessage());
            return $this->response->setJSON(['ok'=>false,'error'=>'db_error','message'=>$e->getMessage()]);
        }
    }

    /**
     * Return a normalized product image for a sale line or product id.
     * Query params: sale_line_id OR product_id
     * Returns JSON: { ok:true, url: <public url> } or { ok:true, data: <data-uri> }
     */
    public function screenProductImage()
    {
        $saleLineId = $this->request->getGet('sale_line_id');
        $productId = $this->request->getGet('product_id');
        $includeStock = ($this->request->getGet('include_stock') == '1');
        $includeDebug = ($this->request->getGet('include_debug') == '1');
        try {
            $db = \Config\Database::connect();
            // First: try sale_lines_cache metadata (if sale_line_id provided). Prefer metadata images and metadata.product_id as the Odoo id.
            $odooProductId = null;
            if ($saleLineId) {
                $row = $db->table('sale_lines_cache')->where('odoo_id', $saleLineId)->get()->getRowArray();
                if ($row && !empty($row['metadata'])) {
                    $m = json_decode($row['metadata'], true) ?: [];
                    if (!empty($m['product_id']) && is_array($m['product_id'])) {
                        $odooProductId = (int)$m['product_id'][0];
                    }
                    $keys = ['image_1920','image_1024','image_512','image_256','image_128','image_small','image'];
                    foreach ($keys as $k) {
                        if (!empty($m[$k])) {
                            $v = $m[$k];
                            // full URL
                            if (is_string($v) && (strpos($v, 'http') === 0 || strpos($v, '/') === 0)) {
                                $out = ['ok'=>true,'url'=>$v];
                                if ($includeStock && $odooProductId) $out['odoo_stock'] = $this->fetchOdooStockForProduct($odooProductId);
                                return $this->response->setJSON($out);
                            }
                            // data URI
                            if (is_string($v) && preg_match('#^data:image#', $v)) {
                                $out = ['ok'=>true,'data'=>$v];
                                if ($includeStock && $odooProductId) $out['odoo_stock'] = $this->fetchOdooStockForProduct($odooProductId);
                                return $this->response->setJSON($out);
                            }
                            // raw base64
                            if (is_string($v) && base64_decode(preg_replace('/\s+/', '', $v), true) !== false) {
                                $b = preg_replace('/\s+/', '', $v);
                                $out = ['ok'=>true,'data'=>'data:image/png;base64,'.$b];
                                if ($includeStock && $odooProductId) $out['odoo_stock'] = $this->fetchOdooStockForProduct($odooProductId);
                                return $this->response->setJSON($out);
                            }
                        }
                    }
                }
            }

            // Second: try local products table if product_id provided (images stored as filenames in products.images)
            if ($productId) {
                try {
                    $prow = $db->table('products')->where('id', $productId)->get()->getRowArray();
                    if ($prow && !empty($prow['images'])) {
                        $imgs = json_decode($prow['images'], true) ?: [];
                        if (!empty($imgs) && is_array($imgs)) {
                            $first = $imgs[0];
                            if ($first) {
                                $url = base_url('/uploads/products/' . ltrim($first, '/'));
                                $out = ['ok'=>true,'url'=>$url];
                                if ($includeStock) {
                                    try {
                                        $maybeOdooId = null;
                                        if (!empty($prow['metadata'])) {
                                            $pm = json_decode($prow['metadata'], true) ?: [];
                                            if (!empty($pm['product_id']) && is_array($pm['product_id'])) $maybeOdooId = (int)$pm['product_id'][0];
                                            if (empty($maybeOdooId) && !empty($pm['odoo_product_id'])) $maybeOdooId = (int)$pm['odoo_product_id'];
                                        }
                                        $stockPid = $maybeOdooId ?: (is_numeric($productId) ? (int)$productId : null);
                                        if ($stockPid) $out['odoo_stock'] = $this->fetchOdooStockForProduct($stockPid);
                                    } catch (\Throwable $e) { /* ignore */ }
                                }
                                return $this->response->setJSON($out);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore and continue
                }
            }

            // Third: as a last resort, ask Odoo for product image. Try product.product first, then product.template.
            // Determine which Odoo product id to query: prefer explicit param, then metadata-derived id
            $queryProductId = null;
            if (!empty($productId) && is_numeric($productId)) $queryProductId = (int)$productId;
            if (empty($queryProductId) && !empty($odooProductId)) $queryProductId = $odooProductId;
            if ($queryProductId) {
                try {
                    // Try product.product
                    // Request only known image fields; Odoo newer versions don't expose a generic 'image' field on product models
                    $res = $this->callOdooModel('product.product', [['id','=', $queryProductId]], ['id','display_name','product_tmpl_id','image_1920','image_1024','image_512','image_256','image_128','qty_available','virtual_available','incoming_qty','outgoing_qty'], ['limit'=>1]);
                    if (is_array($res) && !empty($res[0])) {
                        $p = $res[0];
                        $keys = ['image_1920','image_1024','image_512','image_256','image_128'];
                        foreach ($keys as $k) {
                            if (!empty($p[$k])) {
                                $v = $p[$k];
                                if (is_string($v) && preg_match('#^data:image#', $v)) {
                                    $out = ['ok'=>true,'data'=>$v];
                                    if ($includeStock) $out['odoo_stock'] = ['on_hand'=>($p['qty_available'] ?? null),'virtual'=>($p['virtual_available'] ?? null),'incoming'=>($p['incoming_qty'] ?? null),'reserved'=>($p['outgoing_qty'] ?? null)];
                                    return $this->response->setJSON($out);
                                }
                                if (is_string($v) && base64_decode(preg_replace('/\s+/', '', $v), true) !== false) {
                                    $b = preg_replace('/\s+/', '', $v);
                                    $out = ['ok'=>true,'data'=>'data:image/png;base64,'.$b];
                                    if ($includeStock) $out['odoo_stock'] = ['on_hand'=>($p['qty_available'] ?? null),'virtual'=>($p['virtual_available'] ?? null),'incoming'=>($p['incoming_qty'] ?? null),'reserved'=>($p['outgoing_qty'] ?? null)];
                                    return $this->response->setJSON($out);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore network/odoo errors
                }

                // If product.product didn't return image, try product.template (some Odoo setups store images there).
                // Prefer using product_tmpl_id returned by product.product if available.
                try {
                    $tmplId = null;
                    if (!empty($p['product_tmpl_id']) && is_array($p['product_tmpl_id'])) $tmplId = (int)$p['product_tmpl_id'][0];
                    $templateQueryId = $tmplId ?: $queryProductId;
                    $res2 = $this->callOdooModel('product.template', [['id','=', $templateQueryId]], ['id','display_name','image_1920','image_1024','image_512','image_256','image_128','qty_available','virtual_available','incoming_qty','outgoing_qty'], ['limit'=>1]);
                    if (is_array($res2) && !empty($res2[0])) {
                        $pt = $res2[0];
                        $keys = ['image_1920','image_1024','image_512','image_256','image_128'];
                        foreach ($keys as $k) {
                            if (!empty($pt[$k])) {
                                $v = $pt[$k];
                                if (is_string($v) && preg_match('#^data:image#', $v)) { $out=['ok'=>true,'data'=>$v]; if($includeStock) $out['odoo_stock']=['on_hand'=>($pt['qty_available']??null),'virtual'=>($pt['virtual_available']??null),'incoming'=>($pt['incoming_qty']??null),'reserved'=>($pt['outgoing_qty']??null)]; return $this->response->setJSON($out); }
                                if (is_string($v) && base64_decode(preg_replace('/\s+/', '', $v), true) !== false) { $b=preg_replace('/\s+/', '', $v); $out=['ok'=>true,'data'=>'data:image/png;base64,'.$b]; if($includeStock) $out['odoo_stock']=['on_hand'=>($pt['qty_available']??null),'virtual'=>($pt['virtual_available']??null),'incoming'=>($pt['incoming_qty']??null),'reserved'=>($pt['outgoing_qty']??null)]; return $this->response->setJSON($out); }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                // Final fallback: look for image attachments on product.product or product.template
                try {
                    $attCandidates = [];
                    $attCandidates[] = ['res_model'=>'product.product','res_id'=>$queryProductId];
                    if (!empty($templateQueryId)) $attCandidates[] = ['res_model'=>'product.template','res_id'=>$templateQueryId];
                    foreach ($attCandidates as $c) {
                        $attDomain = [[ 'res_model', '=', $c['res_model'] ], [ 'res_id', '=', (int)$c['res_id'] ], [ 'mimetype', 'ilike', 'image/%' ]];
                        $atts = $this->callOdooModel('ir.attachment', $attDomain, ['id','name','datas','url','mimetype'], ['limit'=>5]);
                        if (is_array($atts) && !empty($atts)) {
                            foreach ($atts as $a) {
                                if (!empty($a['datas'])) {
                                    $mtype = $a['mimetype'] ?? 'image/png';
                                    $b64 = preg_replace('/\s+/', '', $a['datas']);
                                    $out = ['ok'=>true,'data'=>'data:'.$mtype.';base64,'.$b64];
                                    if ($includeStock) $out['odoo_stock'] = $this->fetchOdooStockForProduct($queryProductId);
                                    return $this->response->setJSON($out);
                                }
                                if (!empty($a['url'])) {
                                    $url = $a['url'];
                                    if (strpos($url, 'http') !== 0) {
                                        $url = rtrim($this->odooUrl, '/') . '/web/content/' . ($a['id'] ?? '') . '?download=true';
                                    }
                                    $out = ['ok'=>true,'url'=>$url];
                                    if ($includeStock) $out['odoo_stock'] = $this->fetchOdooStockForProduct($queryProductId);
                                    return $this->response->setJSON($out);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }

            if ($includeDebug && !empty($queryProductId)) {
                // return raw Odoo product & template rows for debugging
                $dbg = ['ok'=>false,'error'=>'no_image','debug'=>[]];
                try { $dbg['debug']['product'] = $this->callOdooModel('product.product', [['id','=', $queryProductId]], ['id','display_name','product_tmpl_id','image_1920','image_1024','image_512','image_256','image_128','qty_available','virtual_available','incoming_qty','outgoing_qty'], ['limit'=>1]); } catch (\Throwable $e) { $dbg['debug']['product_error'] = $e->getMessage(); }
                try { $dbg['debug']['template'] = $this->callOdooModel('product.template', [['id','=', $queryProductId]], ['id','display_name','image_1920','image_1024','image_512','image_256','image_128','qty_available','virtual_available','incoming_qty','outgoing_qty'], ['limit'=>1]); } catch (\Throwable $e) { $dbg['debug']['template_error'] = $e->getMessage(); }
                try { $dbg['debug']['attachments'] = $this->callOdooModel('ir.attachment', [['res_model','in',['product.product','product.template']], ['res_id','in', [$queryProductId]]], ['id','name','url','mimetype'], ['limit'=>10]); } catch (\Throwable $e) { $dbg['debug']['attachments_error'] = $e->getMessage(); }
                return $this->response->setJSON($dbg);
            }
            return $this->response->setJSON(['ok'=>false,'error'=>'no_image']);
        } catch (\Throwable $e) {
            log_message('error','screenProductImage failed: '.$e->getMessage());
            return $this->response->setJSON(['ok'=>false,'error'=>'server_error','message'=>$e->getMessage()]);
        }
    }

    public function screenAlerts()
    {
        $cache = $this->getOdooCache('screen_alerts');
        if ($cache !== null) return $this->response->setJSON($cache);

        $alerts = [];
        // Simple alert generation: overdue SOs
        $sales = $this->fetchSaleOrders([['state','in',['sale','done']]], 500);
        $today = date('Y-m-d');
        foreach ($sales as $s) {
            $due = $s['commitment_date'] ?? $s['date_order'] ?? null;
            if ($due && $due < $today) {
                // check remaining
                $lineIds = $s['order_line'] ?? [];
                $lines = $this->fetchSaleOrderLines($lineIds);
                $remaining = 0;
                foreach ($lines as $l) {
                    $need = ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0);
                    if ($need > 0) $remaining += $need;
                }
                if ($remaining > 0) {
                    $alerts[] = ['type'=>'so_overdue','message'=>($s['name'] ?? 'SO').' overdue','odoo_id'=>$s['id'],'severity'=>'high'];
                }
            }
        }
        $out = ['ok'=>true,'data'=>$alerts];
        $this->setOdooCache('screen_alerts', $out, 30);
        return $this->response->setJSON($out);
    }

    // Actions: claim and close (simple placeholders stored in a transient table)
    public function screenActionClaim()
    {
        $post = $this->request->getJSON(true);
        $odooId = $post['odoo_id'] ?? null;
        $user = session()->get('username') ?? 'web';
        if (!$odooId) return $this->response->setJSON(['ok'=>false,'error'=>'odoo_id required']);
        // store in cache table
        try { $db = \Config\Database::connect(); $db->table('odoo_screen_claims')->replace(['odoo_id'=>$odooId,'claimed_by'=>$user,'claimed_at'=>date('Y-m-d H:i:s')]); } catch (\Throwable $e) { log_message('error','Claim save failed: '.$e->getMessage()); }
        return $this->response->setJSON(['ok'=>true]);
    }

    public function screenActionClose()
    {
        $post = $this->request->getJSON(true);
        $odooId = $post['odoo_id'] ?? null;
        if (!$odooId) return $this->response->setJSON(['ok'=>false,'error'=>'odoo_id required']);
        try { $db = \Config\Database::connect(); $db->table('odoo_screen_claims')->where('odoo_id',$odooId)->delete(); } catch (\Throwable $e) { log_message('error','Close action failed: '.$e->getMessage()); }
        return $this->response->setJSON(['ok'=>true]);
    }

    /**
     * Manual refresh: trigger cache refresh for screen endpoints.
     * This can be called from settings UI to fetch fresh data from Odoo and populate cache.
     */
    public function screenActionRefresh()
    {
        // simple permission: only POST
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setJSON(['ok'=>false,'error'=>'POST required']);
        }
        // Call the existing endpoint methods to populate cache (they call setOdooCache internally)
        try {
            // Spawn a background PHP script to warm the caches so the request returns quickly.
            $script = FCPATH . 'tools\\run_odoo_refresh.php';
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                // Windows: start /B
                $cmd = 'start /B "" "' . PHP_BINARY . '" "' . $script . '"';
            } else {
                $cmd = PHP_BINARY . ' "' . $script . '" > /dev/null 2>&1 &';
            }
            @exec($cmd);
            return $this->response->setJSON(['ok'=>true,'message'=>'refresh_started','last_run'=>date('c')]);
        } catch (\Throwable $e) {
            log_message('error','Odoo manual refresh spawn failed: '.$e->getMessage());
            return $this->response->setJSON(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    /**
     * Pull fresh data from Odoo and store into local mapping/cache tables.
     * This is intended to be run in background (spawned) and will populate sales_cache and purchases_cache.
     */
    public function screenActionPull()
    {
        // Allow POST only
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setJSON(['ok' => false, 'error' => 'POST required']);
        }
        $post = $this->request->getJSON(true) ?: [];
        $lookback = isset($post['lookback_days']) ? (int)$post['lookback_days'] : 90;
        $pageLimit = isset($post['page_limit']) ? (int)$post['page_limit'] : 200;
        $dateFrom = date('Y-m-d', strtotime("-{$lookback} days"));

    $db = \Config\Database::connect();
    $logFile = FCPATH . 'tools/odoo_pull.log';
    @file_put_contents($logFile, "--- pull start: " . date('c') . "\n", FILE_APPEND);
    $salesProcessed = 0; $linesProcessed = 0; $purchasesProcessed = 0; $purchLines = 0;
        try {
            // Fetch sales in pages
            $offset = 0;
            while (true) {
                $domain = [["date_order", ">=", $dateFrom], ['state','in',['sale','done']]];
                $opts = ['limit' => $pageLimit, 'offset' => $offset, 'order' => 'date_order desc', 'fields' => ['id','name','partner_id','date_order','commitment_date','user_id','state','order_line','amount_total']];
                $sales = $this->callOdooModel('sale.order', $domain, ['id','name','partner_id','date_order','commitment_date','user_id','state','order_line','amount_total'], $opts);
                if (empty($sales) || !is_array($sales)) break;

                // Fetch partner refs for this batch so we can store partner reference (customer number) instead of name
                $partnerIds = [];
                foreach ($sales as $s) {
                    $pid = is_array($s['partner_id']) ? ($s['partner_id'][0] ?? null) : ($s['partner_id'] ?? null);
                    if ($pid) $partnerIds[$pid] = $pid;
                }
                $partnerRefMap = [];
                if (!empty($partnerIds)) {
                    $prows = $this->callOdooModel('res.partner', [['id','in', array_values($partnerIds)]], ['id','ref','name'], 200);
                    if (is_array($prows)) {
                        foreach ($prows as $pr) {
                            $id = $pr['id'] ?? null;
                            $ref = $pr['ref'] ?? null;
                            // Do NOT fall back to partner name — only store the Odoo reference (ref).
                            // If ref is empty, keep customer code empty to preserve privacy.
                            $partnerRefMap[$id] = $ref ?: '';
                        }
                    }
                }

                // Collect all line ids and product ids
                $allLineIds = [];
                foreach ($sales as $s) {
                    if (!empty($s['order_line']) && is_array($s['order_line'])) {
                        foreach ($s['order_line'] as $li) $allLineIds[] = $li;
                    }
                }
                $allLineIds = array_values(array_unique($allLineIds));
                $lines = $this->fetchSaleOrderLines($allLineIds);
                if (empty($lines) && !empty($allLineIds)) {
                    // Log an unexpected empty batch fetch and continue; we'll fallback per order later
                    @file_put_contents($logFile, "batch fetchSaleOrderLines returned empty for lineIds: " . json_encode(array_slice($allLineIds,0,20)) . "\n", FILE_APPEND);
                }

                // Map lines by order and collect product ids
                $linesByOrder = []; $productIds = [];
                foreach ($lines as $l) {
                    $orderId = is_array($l['order_id']) ? $l['order_id'][0] : $l['order_id'];
                    if (!$orderId) continue;
                    $linesByOrder[$orderId][] = $l;
                    $pid = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
                    if ($pid) $productIds[$pid] = $pid;
                }
                $productIds = array_values($productIds);

                // Fetch purchase lines for these products to determine outstanding POs
                $pls = [];
                if (!empty($productIds)) {
                    $pls = $this->callOdooModel('purchase.order.line', [['product_id','in',$productIds]], ['id','order_id','product_id','product_qty','qty_received'], 1000);
                }
                $plsByProduct = [];
                foreach ($pls as $pl) {
                    $pid = is_array($pl['product_id']) ? $pl['product_id'][0] : $pl['product_id'];
                    if ($pid) $plsByProduct[$pid][] = $pl;
                }

                // Upsert sales and lines into cache tables
                foreach ($sales as $s) {
                    $odooId = $s['id'];
                    $sLines = $linesByOrder[$odooId] ?? [];
                    $remaining = 0; $hasPendingPo = 0;
                    $lineIdsForRow = [];
                    foreach ($sLines as $l) {
                        $need = ($l['product_uom_qty'] ?? 0) - ($l['qty_delivered'] ?? 0);
                        if ($need > 0) $remaining += $need;
                        $pid = is_array($l['product_id']) ? $l['product_id'][0] : $l['product_id'];
                        if ($pid) {
                            $productPLs = $plsByProduct[$pid] ?? [];
                            foreach ($productPLs as $pl) {
                                $outstanding = ($pl['product_qty'] ?? 0) - ($pl['qty_received'] ?? 0);
                                if ($outstanding > 0) { $hasPendingPo = 1; break 2; }
                            }
                        }
                    }
                    // upsert sales_cache
                    $row = [
                        'odoo_id' => $odooId,
                        'name' => $s['name'] ?? null,
                        'partner_id' => is_array($s['partner_id']) ? ($s['partner_id'][0] ?? null) : ($s['partner_id'] ?? null),
                        // prefer partner.ref (customer number) when available, fallback to partner name
                        'partner_code' => ($partnerRefMap[is_array($s['partner_id'])?($s['partner_id'][0]??null):($s['partner_id']??null)] ?? ''),
                        'date_order' => $s['date_order'] ?? null,
                        'commitment_date' => $s['commitment_date'] ?? null,
                        'user_id' => is_array($s['user_id']) ? ($s['user_id'][0] ?? null) : ($s['user_id'] ?? null),
                        'state' => $s['state'] ?? null,
                        'amount_total' => $s['amount_total'] ?? null,
                        'remaining_qty' => $remaining,
                        'has_pending_po' => $hasPendingPo,
                        'order_line_ids' => json_encode($s['order_line'] ?? []),
                        'metadata' => json_encode($s),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    try {
                        $db->table('sales_cache')->replace($row);
                        $salesProcessed++;
                    } catch (\Throwable $e) { log_message('error','sales_cache upsert failed: '.$e->getMessage()); }

                    // upsert lines; if batch had none for this order, try fetching by order_id as fallback
                    if (empty($sLines)) {
                        try {
                            $sLines = $this->callOdooModel('sale.order.line', [['order_id','=', $odooId]], ['id','order_id','product_id','product_uom_qty','qty_delivered','price_unit'], 200);
                        } catch (\Throwable $e) { $sLines = []; }
                    }
                    foreach ($sLines as $l) {
                        $lrow = [
                            'odoo_id' => $l['id'],
                            'order_id' => is_array($l['order_id'])?($l['order_id'][0]??null):($l['order_id']??null),
                            'product_id' => is_array($l['product_id'])?($l['product_id'][0]??null):($l['product_id']??null),
                            'product_name' => is_array($l['product_id'])?($l['product_id'][1]??null):null,
                            'product_uom_qty' => $l['product_uom_qty'] ?? 0,
                            'qty_delivered' => $l['qty_delivered'] ?? 0,
                            'price_unit' => $l['price_unit'] ?? 0,
                            'metadata' => json_encode($l),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        try { $db->table('sale_lines_cache')->replace($lrow); $linesProcessed++; } catch (\Throwable $e) { log_message('error','sale_lines_cache upsert failed: '.$e->getMessage()); }
                    }
                }

                // advance
                $offset += $pageLimit;
                if (count($sales) < $pageLimit) break;
            }

            // Purchases: fetch and cache
            $offset = 0;
            while (true) {
                $domain = [["date_order", ">=", $dateFrom], ['state','!=','cancel']];
                $purchases = $this->callOdooModel('purchase.order', $domain, ['id','name','partner_id','date_order','state','order_line'], ['limit' => $pageLimit, 'offset' => $offset, 'order' => 'date_order desc']);
                if (empty($purchases) || !is_array($purchases)) break;
                // fetch partner refs for purchases in this batch so vendor.code will be the vendor ref when present
                $pPartnerIds = [];
                foreach ($purchases as $p) {
                    $ppid = is_array($p['partner_id']) ? ($p['partner_id'][0] ?? null) : ($p['partner_id'] ?? null);
                    if ($ppid) $pPartnerIds[$ppid] = $ppid;
                }
                $pPartnerRefMap = [];
                if (!empty($pPartnerIds)) {
                    $pprows = $this->callOdooModel('res.partner', [['id','in', array_values($pPartnerIds)]], ['id','ref','name'], 200);
                    if (is_array($pprows)) {
                        foreach ($pprows as $pr) {
                            $id = $pr['id'] ?? null;
                            $ref = $pr['ref'] ?? null;
                            // Only keep the partner ref (vendor code) — do not expose vendor name here.
                            $pPartnerRefMap[$id] = $ref ?: '';
                        }
                    }
                }
                // collect line ids
                $allPLIds = [];
                foreach ($purchases as $p) {
                    if (!empty($p['order_line']) && is_array($p['order_line'])) foreach ($p['order_line'] as $li) $allPLIds[] = $li;
                }
                $allPLIds = array_values(array_unique($allPLIds));
                $plines = [];
                if (!empty($allPLIds)) {
                    // fetch lines via a search_read approach
                    $plines = $this->callOdooModel('purchase.order.line', [['id','in',$allPLIds]], ['id','order_id','product_id','product_qty','qty_received'], 1000);
                }
                $linesByPurchase = [];
                foreach ($plines as $l) {
                    $oid = is_array($l['order_id']) ? ($l['order_id'][0] ?? null) : ($l['order_id'] ?? null);
                    if ($oid) $linesByPurchase[$oid][] = $l;
                }
                foreach ($purchases as $p) {
                    $ordered = 0; $received = 0; $outstanding = 0;
                    $plsFor = $linesByPurchase[$p['id']] ?? [];
                    foreach ($plsFor as $l) {
                        $ordered += $l['product_qty'] ?? 0;
                        $received += $l['qty_received'] ?? 0;
                        $outstanding += max(0, ($l['product_qty'] ?? 0) - ($l['qty_received'] ?? 0));
                        // upsert purchase line
                        $lrow = [
                            'odoo_id' => $l['id'],
                            'order_id' => $p['id'],
                            'product_id' => is_array($l['product_id'])?($l['product_id'][0]??null):($l['product_id']??null),
                            'product_name' => is_array($l['product_id'])?($l['product_id'][1]??null):null,
                            'product_qty' => $l['product_qty'] ?? 0,
                            'qty_received' => $l['qty_received'] ?? 0,
                            'metadata' => json_encode($l),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        try { $db->table('purchase_lines_cache')->replace($lrow); $purchLines++; } catch (\Throwable $e) { log_message('error','purchase_lines_cache upsert failed: '.$e->getMessage()); }
                    }
                    $prow = [
                        'odoo_id' => $p['id'],
                        'name' => $p['name'] ?? null,
                        'partner_id' => is_array($p['partner_id'])?($p['partner_id'][0]??null):($p['partner_id']??null),
                        'partner_code' => ($pPartnerRefMap[is_array($p['partner_id'])?($p['partner_id'][0]??null):($p['partner_id']??null)] ?? ''),
                        'date_order' => $p['date_order'] ?? null,
                        'state' => $p['state'] ?? null,
                        'ordered_qty' => $ordered,
                        'received_qty' => $received,
                        'outstanding_qty' => $outstanding,
                        'order_line_ids' => json_encode($p['order_line'] ?? []),
                        'metadata' => json_encode($p),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    try { $db->table('purchases_cache')->replace($prow); $purchasesProcessed++; } catch (\Throwable $e) { log_message('error','purchases_cache upsert failed: '.$e->getMessage()); }
                }
                $offset += $pageLimit;
                if (count($purchases) < $pageLimit) break;
            }

            // Update last_run in settings
            try { $db->table('odoo_settings')->set('last_run', date('Y-m-d H:i:s'))->where('id IS NOT NULL')->update(); } catch (\Throwable $e) { log_message('error','updating last_run failed: '.$e->getMessage()); }

        } catch (\Throwable $e) {
            log_message('error', 'screenActionPull failed: '.$e->getMessage());
            return $this->response->setJSON(['ok'=>false,'error'=>'pull_failed','message'=>$e->getMessage()]);
        }

        return $this->response->setJSON(['ok'=>true,'sales'=>$salesProcessed,'sale_lines'=>$linesProcessed,'purchases'=>$purchasesProcessed,'purchase_lines'=>$purchLines,'last_run'=>date('c')]);
    }

    /**
     * Import customers from Odoo into Corelynk.
     * This will create or update local customers, add addresses to customer_addresses,
     * and store extra Odoo data in the metadata field.
     */
    public function importCustomers()
    {
        // Only allow POST to perform import
        $method = strtolower($this->request->getMethod());
        if ($method !== 'post') {
            return $this->response->setJSON(['ok' => false, 'error' => 'POST required']);
        }

        $db = \Config\Database::connect();
        // Ensure customers table has expected columns (odoo_id, email, phone, mobile, website)
        $cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'")->getResultArray();
        $have = [];
        foreach ($cols as $c) $have[] = $c['COLUMN_NAME'];
        $queries = [];
        if (!in_array('odoo_id', $have)) {
            $queries[] = "ALTER TABLE customers ADD COLUMN odoo_id INT NULL AFTER id;";
            $queries[] = "CREATE INDEX idx_customers_odoo_id ON customers(odoo_id);";
        }
        if (!in_array('email', $have)) {
            $queries[] = "ALTER TABLE customers ADD COLUMN email VARCHAR(150) NULL AFTER company_name;";
        }
        if (!in_array('phone', $have)) {
            $queries[] = "ALTER TABLE customers ADD COLUMN phone VARCHAR(100) NULL AFTER email;";
        }
        if (!in_array('mobile', $have)) {
            $queries[] = "ALTER TABLE customers ADD COLUMN mobile VARCHAR(100) NULL AFTER phone;";
        }
        if (!in_array('website', $have)) {
            $queries[] = "ALTER TABLE customers ADD COLUMN website VARCHAR(255) NULL AFTER mobile;";
        }
        foreach ($queries as $q) {
            try { $db->query($q); } catch (\Throwable $e) { log_message('error', 'ImportCustomers schema change error: '.$e->getMessage()); }
        }

        // Remove all existing customers and addresses per user request, then paginate through Odoo partners
        try {
            // delete addresses first
            $db->query("DELETE FROM customer_addresses");
            // delete customers
            $db->query("DELETE FROM customers");
        } catch (\Throwable $e) {
            log_message('error', 'Odoo import cleanup error: ' . $e->getMessage());
        }

        // Paginate through Odoo partners (no filter per user request: import all partners)
        $limit = 200;
        $offset = 0;
        $created = 0; $updated = 0; $skipped = 0;
        while (true) {
            // Prepare search_read with offset
            $loginPayload = [
                'jsonrpc' => '2.0', 'method' => 'call', 'params' => ['service' => 'common', 'method' => 'login', 'args' => [$this->dbName, $this->username, $this->password]], 'id' => uniqid()
            ];
            $res = $this->postJson($this->odooUrl . '/jsonrpc', $loginPayload);
            if (!isset($res['result'])) return $this->response->setJSON(['ok'=>false,'error'=>'login_failed','response'=>$res]);
            $uid = $res['result'];

            $fields = ['id','name','ref','email','phone','mobile','street','street2','city','zip','state_id','country_id','website','is_company','child_ids','company_id'];
            $args = [$this->dbName, $uid, $this->password, 'res.partner', 'search_read', [[], $fields], ['limit' => $limit, 'offset' => $offset]];
            $callPayload = ['jsonrpc'=>'2.0','method'=>'call','params'=>['service'=>'object','method'=>'execute_kw','args'=>$args],'id'=>uniqid()];
            $res2 = $this->postJson($this->odooUrl . '/jsonrpc', $callPayload);
            if (!isset($res2['result'])) break;
            $rows = $res2['result'];
            if (empty($rows)) break;

            foreach ($rows as $p) {
                // normalize partner row
                $odooId = $p['id'] ?? null;
                $name = $p['name'] ?? null;
                $ref = $p['ref'] ?? null;
                $email = $p['email'] ?? null;
                $phone = $p['phone'] ?? null;
                $mobile = $p['mobile'] ?? null;
                $website = $p['website'] ?? null;

                // Try to find existing customer by odoo_id first
                $cust = null;
                if ($odooId) {
                    $cust = $db->table('customers')->where('odoo_id', $odooId)->get()->getRowArray();
                }
                // if not found, try by email
                if (!$cust && !empty($email)) {
                    $cust = $db->table('customers')->where('email', $email)->get()->getRowArray();
                }

                $customerData = [];
                // If ref present use as customer_code else generate new code
                if (!empty($ref)) $customerData['customer_code'] = $ref; else $customerData['customer_code'] = (new \App\Models\CustomerModel())->generateCustomerCode();
                $customerData['name'] = $name ?: 'Unknown';
                $customerData['company_name'] = $p['company_id'] ?? null;
                $customerData['email'] = $email;
                $customerData['phone'] = $phone;
                $customerData['mobile'] = $mobile;
                $customerData['website'] = $website;
                $customerData['odoo_id'] = $odooId;
                $customerData['status'] = 'active';
                // metadata: include raw odoo partner for later reference and child contacts
                $meta = ['odoo_raw' => $p];
                $customerData['metadata'] = json_encode($meta);

                if ($cust) {
                    // update existing (user requested overwrite)
                    try {
                        $db->table('customers')->where('id', $cust['id'])->update($customerData);
                        $updated++;
                        $localId = $cust['id'];
                    } catch (\Throwable $e) {
                        log_message('error', 'Odoo import update failed for odoo_id '.$odooId.': '.$e->getMessage());
                        $skipped++; continue;
                    }
                } else {
                    try {
                        $db->table('customers')->insert($customerData);
                        $localId = $db->insertID();
                        $created++;
                    } catch (\Throwable $e) {
                        log_message('error', 'Odoo import insert failed for odoo_id '.$odooId.': '.$e->getMessage());
                        $skipped++; continue;
                    }
                }

                // Insert primary address if street/city provided. Store country as name only (do NOT attempt to match local country_id).
                if (!empty($p['street']) || !empty($p['city']) || !empty($p['zip'])) {
                    $countryNameToSave = null;
                    if (!empty($p['country_id']) && is_array($p['country_id'])) {
                        $countryNameToSave = $p['country_id'][1] ?? null;
                    } elseif (!empty($p['country']) && is_string($p['country'])) {
                        $countryNameToSave = $p['country'];
                    }
                    // Put country name into address line2 so it's visible on the customer view.
                    $street2 = isset($p['street2']) ? trim($p['street2']) : '';
                    $countryPart = $countryNameToSave ? trim($countryNameToSave) : '';
                    $line2 = $street2;
                    if ($countryPart) {
                        $line2 = $line2 ? ($line2 . ' | ' . $countryPart) : $countryPart;
                    }
                    $addr = [
                        'customer_id' => $localId,
                        'line1' => $p['street'] ?? null,
                        'line2' => $line2 ?: null,
                        'city_name' => $p['city'] ?? null,
                        'state_name' => is_array($p['state_id']) ? ($p['state_id'][1] ?? null) : ($p['state_id'] ?? null),
                        'postal_code' => $p['zip'] ?? null,
                        'country_id' => null,
                        'country_name' => $countryNameToSave,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    try {
                        // Upsert: if a similar address exists for this customer (by line1+city+postal) skip insert
                        $exists = $db->table('customer_addresses')->where('customer_id', $localId)->where('line1', $addr['line1'])->where('city_name', $addr['city_name'])->get()->getRowArray();
                        if (!$exists) $db->table('customer_addresses')->insert($addr);
                    } catch (\Throwable $e) {
                        log_message('error', 'Odoo import address insert failed for customer '.$localId.': '.$e->getMessage());
                    }
                }
            }

            // advance
            $offset += $limit;
            // safety: break if less than limit returned
            if (count($rows) < $limit) break;
        }

        return $this->response->setJSON(['ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped]);
    }

    public function apiPurchases()
    {
        $limit = (int)($this->request->getGet('limit') ?? 10);
        $offset = (int)($this->request->getGet('offset') ?? 0);
        $fields = ['id','name','partner_id','date_order','state','amount_total'];
        $domain = [['state','!=','cancel']];
        $opts = ['limit' => $limit, 'offset' => $offset, 'order' => 'date_order desc'];
        $data = $this->callOdooModel('purchase.order', $domain, $fields, $opts);
        return $this->response->setJSON(['ok' => true, 'data' => $data]);
    }

    protected function callOdooModel(string $model, array $domain = [], array $fields = [], $opts = [])
    {
        // Step 1: login to get uid
        $loginPayload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'common',
                'method' => 'login',
                'args' => [$this->dbName, $this->username, $this->password]
            ],
            'id' => uniqid()
        ];

        $res = $this->postJson($this->odooUrl . '/jsonrpc', $loginPayload);
        if (!isset($res['result'])) {
            return ['error' => 'login_failed', 'response' => $res];
        }
        $uid = $res['result'];

        // Step 2: call object execute_kw via object service
        // opts may be int (limit) or array with limit/offset/order
        $options = [];
        if (is_int($opts)) {
            $options['limit'] = $opts;
        } elseif (is_array($opts)) {
            $options = $opts;
        }
        // ensure fields included
        $options['fields'] = $fields;
        $args = [$this->dbName, $uid, $this->password, $model, 'search_read', [$domain], $options];
        $callPayload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'object',
                'method' => 'execute_kw',
                'args' => $args
            ],
            'id' => uniqid()
        ];

        $res2 = $this->postJson($this->odooUrl . '/jsonrpc', $callPayload);
        if (isset($res2['result'])) return $res2['result'];
        return ['error' => 'call_failed', 'response' => $res2];
    }

    /**
     * Simple DB-backed cache helpers for Odoo screen data.
     */
    protected function getOdooCache(string $key)
    {
        try {
            $db = \Config\Database::connect();
            $row = $db->table('odoo_screen_cache')->where('cache_key', $key)->get()->getRowArray();
            if (!$row) return null;
            if (!empty($row['expires_at']) && $row['expires_at'] < date('Y-m-d H:i:s')) {
                // expired
                $db->table('odoo_screen_cache')->where('cache_key', $key)->delete();
                return null;
            }
            return json_decode($row['data'], true);
        } catch (\Throwable $e) {
            log_message('error', 'Odoo cache read failed: '.$e->getMessage());
            return null;
        }
    }

    protected function setOdooCache(string $key, $data, int $ttlSeconds = 30)
    {
        try {
            $db = \Config\Database::connect();
            $expires = $ttlSeconds > 0 ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
            $row = ['cache_key' => $key, 'data' => json_encode($data), 'expires_at' => $expires, 'updated_at' => date('Y-m-d H:i:s')];
            // use replace to upsert
            $db->table('odoo_screen_cache')->replace($row);
        } catch (\Throwable $e) {
            log_message('error', 'Odoo cache write failed: '.$e->getMessage());
        }
    }

    /**
     * Helper: fetch stock fields for an Odoo product id and normalize them.
     */
    protected function fetchOdooStockForProduct(int $productId)
    {
        try {
            $res = $this->callOdooModel('product.product', [['id','=', $productId]], ['qty_available','virtual_available','incoming_qty','outgoing_qty'], ['limit'=>1]);
            if (is_array($res) && !empty($res[0])) {
                $s = $res[0];
                return [
                    'on_hand' => isset($s['qty_available']) ? $s['qty_available'] : null,
                    'virtual' => isset($s['virtual_available']) ? $s['virtual_available'] : null,
                    'incoming' => isset($s['incoming_qty']) ? $s['incoming_qty'] : null,
                    'reserved' => isset($s['outgoing_qty']) ? $s['outgoing_qty'] : null,
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    protected function postJson(string $url, array $payload)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => $err];
        $decoded = json_decode($resp, true);
        return $decoded ?: ['raw' => $resp];
    }
}
