<?php

namespace App\Controllers;

use App\Models\WarehouseModel;
use App\Models\WarehouseLocationModel;
use Config\Database;
use CodeIgniter\HTTP\ResponseInterface;

class InventoryLocations extends BaseController
{
    public function index()
    {
        return view('inventory/locations');
    }

    public function warehouses()
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405); }
        $m = new WarehouseModel();
        $rows = $m->orderBy('name','ASC')->findAll();
        return $this->response->setJSON(['success'=>true,'data'=>$rows]);
    }

    public function save_warehouse()
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        $name = trim($data['name'] ?? '');
        if ($name === '') return $this->response->setStatusCode(400)->setJSON(['error'=>'Name required']);
        $id = isset($data['id']) ? (int)$data['id'] : null;
        $m = new WarehouseModel();
        $payload = [
            'name' => $name,
            'code' => $data['code'] ?? null,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ];
        if ($id) { $m->update($id, $payload); }
        else { $m->insert($payload); $id = $m->getInsertID(); }
        return $this->response->setJSON(['success'=>true,'warehouse_id'=>$id]);
    }

    public function deactivate_warehouse($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);
        $db = Database::connect();
        // Block if stock movements exist
        $movements = $db->table('stock_movements')->where('warehouse_id',$id)->countAllResults();
        $balances = $db->table('stock_balances')->where('warehouse_id',$id)->countAllResults();
        if ($movements>0 || $balances>0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Cannot deactivate warehouse with stock history']);
        $m = new WarehouseModel();
        $m->update($id, ['is_active'=>0]);
        return $this->response->setJSON(['success'=>true]);
    }

    public function warehouse($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);
        $wm = new WarehouseModel();
        $wh = $wm->find($id);
        if (!$wh) return $this->response->setStatusCode(404)->setJSON(['error'=>'Not found']);
        $lm = new WarehouseLocationModel();
        $locs = $lm->where('warehouse_id',$id)->orderBy('parent_id','ASC')->orderBy('name','ASC')->findAll();
        return $this->response->setJSON(['success'=>true,'warehouse'=>$wh,'locations'=>$locs]);
    }

    public function create_location($warehouseId = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $warehouseId = (int)$warehouseId; if ($warehouseId<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid warehouse id']);
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        $name = trim($data['name'] ?? '');
        $parentId = isset($data['parent_id']) && $data['parent_id']!=='' ? (int)$data['parent_id'] : null;
        if ($name === '') return $this->response->setStatusCode(400)->setJSON(['error'=>'Name required']);
        $lm = new WarehouseLocationModel();
        if ($parentId) {
            $parent = $lm->find($parentId);
            if (!$parent || (int)$parent['warehouse_id'] !== $warehouseId) {
                return $this->response->setStatusCode(400)->setJSON(['error'=>'Parent must belong to same warehouse']);
            }
        }
        $id = $lm->insert([
            'warehouse_id'=>$warehouseId,
            'name'=>$name,
            'parent_id'=>$parentId ?: null,
            'is_active'=>1,
        ]);
        return $this->response->setJSON(['success'=>true,'location_id'=>$id]);
    }

    public function deactivate_location($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);
        $db = Database::connect();
        $lm = new WarehouseLocationModel();
        $loc = $lm->find($id);
        if (!$loc) return $this->response->setStatusCode(404)->setJSON(['error'=>'Location not found']);
        $movements = $db->table('stock_movements')->where('location_id',$id)->countAllResults();
        $balances = $db->table('stock_balances')->where('location_id',$id)->countAllResults();
        if ($movements>0 || $balances>0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Cannot deactivate location with stock history']);
        $lm->update($id, ['is_active'=>0]);
        return $this->response->setJSON(['success'=>true]);
    }

    // ── Rename a location ────────────────────────────────────────────────────
    public function rename_location($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        $name = trim($data['name'] ?? '');
        if ($name === '') return $this->response->setStatusCode(400)->setJSON(['error'=>'Name required']);

        $lm = new WarehouseLocationModel();
        $loc = $lm->find($id);
        if (!$loc) return $this->response->setStatusCode(404)->setJSON(['error'=>'Location not found']);

        $lm->update($id, ['name' => $name]);
        return $this->response->setJSON(['success'=>true]);
    }

    // ── Move location to a new parent (or root) ─────────────────────────────
    public function move_location($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        $newParentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null
            ? (int)$data['parent_id'] : null;

        $lm  = new WarehouseLocationModel();
        $loc = $lm->find($id);
        if (!$loc) return $this->response->setStatusCode(404)->setJSON(['error'=>'Location not found']);

        // Can't be own parent
        if ($newParentId === $id) {
            return $this->response->setStatusCode(400)->setJSON(['error'=>'A location cannot be its own parent']);
        }

        // Validate parent belongs to same warehouse and is not a descendant
        if ($newParentId) {
            $parent = $lm->find($newParentId);
            if (!$parent) return $this->response->setStatusCode(404)->setJSON(['error'=>'Parent location not found']);
            if ((int)$parent['warehouse_id'] !== (int)$loc['warehouse_id']) {
                return $this->response->setStatusCode(400)->setJSON(['error'=>'Parent must belong to the same warehouse']);
            }
            // Prevent circular reference — walk up from newParentId and make sure we never hit $id
            $visited = [];
            $cursor  = $newParentId;
            while ($cursor) {
                if ($cursor === $id) {
                    return $this->response->setStatusCode(400)->setJSON(['error'=>'Cannot move under its own descendant (circular)']);
                }
                if (isset($visited[$cursor])) break; // safety
                $visited[$cursor] = true;
                $p = $lm->find($cursor);
                $cursor = $p ? ((int)($p['parent_id'] ?? 0) ?: null) : null;
            }
        }

        $lm->update($id, ['parent_id' => $newParentId]);
        return $this->response->setJSON(['success'=>true]);
    }

    // ── Delete location (hard delete — only if no stock) ─────────────────────
    public function delete_location($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);

        $db = Database::connect();
        $lm = new WarehouseLocationModel();
        $loc = $lm->find($id);
        if (!$loc) return $this->response->setStatusCode(404)->setJSON(['error'=>'Location not found']);

        // Collect this location + all descendants
        $allLocs = $lm->where('warehouse_id', (int)$loc['warehouse_id'])->findAll();
        $idsToCheck = [$id];
        $found = true;
        while ($found) {
            $found = false;
            foreach ($allLocs as $l) {
                $pid = (int)($l['parent_id'] ?? 0);
                $lid = (int)$l['id'];
                if ($pid && in_array($pid, $idsToCheck) && !in_array($lid, $idsToCheck)) {
                    $idsToCheck[] = $lid;
                    $found = true;
                }
            }
        }

        // Check stock for this location AND all children
        $movements = $db->table('stock_movements')->whereIn('location_id', $idsToCheck)->countAllResults();
        $balQty    = $db->table('stock_balances')->whereIn('location_id', $idsToCheck)
                        ->selectSum('quantity')->get()->getRow()->quantity ?? 0;
        $balRows   = $db->table('stock_balances')->whereIn('location_id', $idsToCheck)->countAllResults();

        if ($movements > 0 || (float)$balQty != 0) {
            $details = [];
            if ($movements > 0) $details[] = $movements . ' stock movement(s)';
            if ((float)$balQty != 0) $details[] = 'non-zero balance (' . number_format((float)$balQty, 2) . ')';
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Cannot delete — this location has ' . implode(' and ', $details)
                    . '. Please remove or transfer all product stock from this location first.',
            ]);
        }

        // Safe to delete — remove balance rows (all zero) then children bottom-up, then self
        if ($balRows > 0) {
            $db->table('stock_balances')->whereIn('location_id', $idsToCheck)->delete();
        }
        // Delete children bottom-up (reverse the collected list)
        $childIds = array_filter($idsToCheck, fn($x) => $x !== $id);
        if ($childIds) {
            // Set parent_id to null first to avoid FK issues, then delete
            $db->table('warehouse_locations')->whereIn('id', $childIds)->update(['parent_id' => null]);
            $db->table('warehouse_locations')->whereIn('id', $childIds)->delete();
        }
        $lm->delete($id);

        return $this->response->setJSON(['success'=>true, 'deleted_count' => count($idsToCheck)]);
    }

    // ── Stock check — does a location (+ children) have stock? ───────────────
    public function location_stock_check($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405); }
        $id = (int)$id; if ($id<=0) return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid id']);

        $db = Database::connect();
        $lm = new WarehouseLocationModel();
        $loc = $lm->find($id);
        if (!$loc) return $this->response->setStatusCode(404)->setJSON(['error'=>'Location not found']);

        // Collect descendants
        $allLocs = $lm->where('warehouse_id', (int)$loc['warehouse_id'])->findAll();
        $idsToCheck = [$id];
        $found = true;
        while ($found) {
            $found = false;
            foreach ($allLocs as $l) {
                $pid = (int)($l['parent_id'] ?? 0);
                $lid = (int)$l['id'];
                if ($pid && in_array($pid, $idsToCheck) && !in_array($lid, $idsToCheck)) {
                    $idsToCheck[] = $lid;
                    $found = true;
                }
            }
        }

        $movements = $db->table('stock_movements')->whereIn('location_id', $idsToCheck)->countAllResults();
        $balQty    = (float)($db->table('stock_balances')->whereIn('location_id', $idsToCheck)
                        ->selectSum('quantity')->get()->getRow()->quantity ?? 0);
        $childCount = count($idsToCheck) - 1; // exclude self

        return $this->response->setJSON([
            'success'     => true,
            'has_stock'   => ($movements > 0 || $balQty != 0),
            'movements'   => $movements,
            'balance'     => $balQty,
            'child_count' => $childCount,
        ]);
    }
}
