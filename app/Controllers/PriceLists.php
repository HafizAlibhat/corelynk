<?php
namespace App\Controllers;

use App\Models\PriceListModel;
use App\Models\PriceListItemModel;
use App\Services\PriceListService;

class PriceLists extends BaseController
{
    protected $model;
    protected $items;

    public function __construct()
    {
        $this->model = new PriceListModel();
        $this->items = new PriceListItemModel();
    }

    public function index()
    {
        $lists = $this->model->orderBy('party_type', 'ASC')->orderBy('name', 'ASC')->findAll();

        $db = \Config\Database::connect();
        foreach ($lists as &$l) {
            $l['party_name'] = $this->partyName($db, $l);
            $l['item_count'] = (int)$this->items->where('price_list_id', $l['id'])->countAllResults();
        }
        unset($l);

        return view('price_lists/index', ['priceLists' => $lists]);
    }

    public function manage($id = null)
    {
        $db   = \Config\Database::connect();
        $list = $id ? $this->model->find((int)$id) : null;

        return view('price_lists/manage', [
            'priceList' => $list,
            'items'     => $list ? $this->itemsWithProduct($db, (int)$list['id']) : [],
            'customers' => $db->table('customers')->select('id, name, customer_code')->orderBy('name', 'ASC')->get()->getResultArray(),
            'vendors'   => $db->table('vendors')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray(),
            'currencies' => $this->currencyCodes($db),
        ]);
    }

    public function save($id = null)
    {
        $post      = $this->request->getPost();
        $partyType = ($post['party_type'] ?? 'customer') === 'vendor' ? 'vendor' : 'customer';
        $mode      = in_array($post['pricing_mode'] ?? '', [PriceListService::MODE_FIXED, PriceListService::MODE_MARGIN, PriceListService::MODE_DISCOUNT], true)
            ? $post['pricing_mode'] : PriceListService::MODE_FIXED;

        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'Price list name is required.');
        }

        $row = [
            'party_type'     => $partyType,
            'customer_id'    => $partyType === 'customer' && !empty($post['customer_id']) ? (int)$post['customer_id'] : null,
            'vendor_id'      => $partyType === 'vendor' && !empty($post['vendor_id']) ? (int)$post['vendor_id'] : null,
            'name'           => $name,
            'currency'       => strtoupper(trim((string)($post['currency'] ?? ''))) ?: null,
            'pricing_mode'   => $mode,
            'margin_percent' => $mode === PriceListService::MODE_FIXED || ($post['margin_percent'] ?? '') === '' ? null : (float)$post['margin_percent'],
            'margin_method'  => ($post['margin_method'] ?? 'markup') === 'margin' ? 'margin' : 'markup',
            'is_active'      => !empty($post['is_active']) ? 1 : 0,
            'valid_from'     => !empty($post['valid_from']) ? $post['valid_from'] : null,
            'valid_to'       => !empty($post['valid_to']) ? $post['valid_to'] : null,
        ];

        $db = \Config\Database::connect();
        $db->transStart();

        if ($id) {
            $this->model->update((int)$id, $row);
        } else {
            $row['created_by'] = session()->get('user_id');
            $this->model->insert($row);
            $id = (int)$this->model->getInsertID();
        }

        // Items are replaced wholesale: the form always posts the full list.
        $this->items->where('price_list_id', (int)$id)->delete();
        foreach ((array)($post['items'] ?? []) as $it) {
            $productId = (int)($it['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $this->items->insert([
                'price_list_id'  => (int)$id,
                'product_id'     => $productId,
                // Empty variant = the price applies to every variant of the product.
                'variant_id'     => !empty($it['variant_id']) ? (int)$it['variant_id'] : null,
                'pricing_mode'   => ($it['pricing_mode'] ?? '') === PriceListService::MODE_MARGIN ? PriceListService::MODE_MARGIN : PriceListService::MODE_FIXED,
                'special_price'  => (float)($it['special_price'] ?? 0),
                'margin_percent' => ($it['margin_percent'] ?? '') === '' ? null : (float)$it['margin_percent'],
                // Items always carry the list currency: a per-item currency on top
                // of the list currency has no meaning and only invites mistakes.
                'currency'       => $row['currency'] ?? '',
                'min_quantity'   => max(1, (int)($it['min_quantity'] ?? 1)),
            ]);
        }

        $db->transComplete();

        return redirect()->to(site_url('price-lists'))->with('success', 'Price list saved.');
    }

    public function delete($id = null)
    {
        $this->items->where('price_list_id', (int)$id)->delete();
        $this->model->delete((int)$id);

        return redirect()->to(site_url('price-lists'))->with('success', 'Price list deleted.');
    }

    private function itemsWithProduct($db, int $listId): array
    {
        $rows = $this->items->where('price_list_id', $listId)->orderBy('id', 'ASC')->findAll();
        $ids  = array_values(array_filter(array_column($rows, 'product_id')));
        $map  = [];
        if ($ids) {
            foreach ($db->table('products')->select('id, code, name, cost_price, cost_currency')->whereIn('id', $ids)->get()->getResultArray() as $p) {
                $map[(int)$p['id']] = $p;
            }
        }
        $variants = [];
        $vids     = array_values(array_filter(array_column($rows, 'variant_id')));
        if ($vids && $db->tableExists('product_variants')) {
            foreach ($db->table('product_variants')->select('id, art_number, name, attributes, cost, cost_currency')->whereIn('id', $vids)->get()->getResultArray() as $v) {
                $variants[(int)$v['id']] = $v;
            }
        }

        foreach ($rows as &$r) {
            $p                  = $map[(int)$r['product_id']] ?? [];
            $v                  = $variants[(int)($r['variant_id'] ?? 0)] ?? [];
            $r['product_code']  = $v['art_number'] ?? ($p['code'] ?? '');
            $r['product_name']  = $p['name'] ?? '';
            $r['variant_text']  = $v ? \App\Models\ProductModel::attributesSummary($v['attributes'] ?? null) ?: (string)($v['name'] ?? '') : '';
            $hasVariantCost     = $v && (float)($v['cost'] ?? 0) > 0;
            $r['cost_price']    = $hasVariantCost ? (float)$v['cost'] : (float)($p['cost_price'] ?? 0);
            $r['cost_currency'] = $hasVariantCost ? ($v['cost_currency'] ?? '') : ($p['cost_currency'] ?? '');
        }

        return $rows;
    }

    private function partyName($db, array $list): string
    {
        if (($list['party_type'] ?? 'customer') === 'vendor') {
            if (empty($list['vendor_id'])) {
                return 'All vendors';
            }
            $v = $db->table('vendors')->select('name')->where('id', (int)$list['vendor_id'])->get()->getRowArray();

            return $v['name'] ?? ('Vendor #' . (int)$list['vendor_id']);
        }
        if (empty($list['customer_id'])) {
            return 'All customers';
        }
        $c = $db->table('customers')->select('name')->where('id', (int)$list['customer_id'])->get()->getRowArray();

        return $c['name'] ?? ('Customer #' . (int)$list['customer_id']);
    }

    private function currencyCodes($db): array
    {
        try {
            return array_column($db->table('currencies')->select('code')->where('is_active', 1)->orderBy('code', 'ASC')->get()->getResultArray(), 'code');
        } catch (\Throwable $e) {
            return ['USD'];
        }
    }
}
