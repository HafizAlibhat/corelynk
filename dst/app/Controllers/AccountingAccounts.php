<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\Accounting\CurrencyModel;

class AccountingAccounts extends BaseController
{
    /**
     * Best-effort: clear any prior echoed output to avoid corrupting JSON responses (e.g., stray debug like "200;").
     */
    private function clearOutputBuffer(): void
    {
        try {
            while (ob_get_level() > 0) { ob_end_clean(); }
        } catch (\Throwable $e) { /* ignore */ }
    }
    public function index()
    {
        // Ensure bank column exists before we rely on it for flags/icons
        try { $this->ensureBankColumn(); } catch (\Throwable $e) { /* ignore */ }
        $m = new AccountModel();
        $accounts = $m->orderBy('code', 'ASC')->findAll();
        if (empty($accounts)) {
            $this->seedDefaultAccounts();
            $accounts = $m->orderBy('code', 'ASC')->findAll();
        }
        $cm = new CurrencyModel();
        $currencies = $cm->orderBy('code', 'ASC')->findAll();
        // UI types (show "Income" label for Revenue)
        $typesOptions = [
            'Asset'    => 'Asset',
            'Liability'=> 'Liability',
            'Equity'   => 'Equity',
            'Revenue'  => 'Income',
            'Expense'  => 'Expense',
        ];

        // Group accounts by major category for best-practice presentation
        $grouped = [
            'Asset' => [],
            'Liability' => [],
            'Equity' => [],
            'Revenue' => [],
            'Expense' => [],
        ];
        foreach ($accounts as $a) {
            $type = $a['type'] ?? '';
            $key = in_array($type, array_keys($grouped), true)
                ? $type
                : (strtolower($type) === 'income' ? 'Revenue' : null);
            if ($key === null) { $key = 'Asset'; } // fallback bucket
            $grouped[$key][] = $a;
        }
            // Pre-update snapshot & column list
            try {
                $before = $m->find($id);
                $dbCols = [];
                try { $db = \Config\Database::connect(); $colRes = $db->query("SHOW COLUMNS FROM accounts")->getResultArray(); foreach ($colRes as $c) { $dbCols[] = $c['Field']; } } catch (\Throwable $eCol) { /* ignore */ }
                log_message('debug', 'Accounts update BEFORE id='.$id.' row='.json_encode($before).' cols='.json_encode($dbCols));
            } catch (\Throwable $eSnap) { log_message('debug','Accounts update BEFORE snapshot failed: '.$eSnap->getMessage()); }

        // Precompute balances & usage counts (single query) for conditional actions
        $balances = [];
        try {
            $db = \Config\Database::connect();
            $rows = $db->query('SELECT account_id, COUNT(*) lines, COALESCE(SUM(debit),0) debits, COALESCE(SUM(credit),0) credits FROM journal_lines GROUP BY account_id')->getResultArray();
            foreach ($rows as $r) {
                $bal = (float)$r['debits'] - (float)$r['credits'];
                $balances[(int)$r['account_id']] = [
                    'lines' => (int)$r['lines'],
                    'debits' => (float)$r['debits'],
                    'credits' => (float)$r['credits'],
                    'balance' => $bal,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Balance precompute failed: '.$e->getMessage());
        }

        // Build hierarchical structure (attach children arrays) for display
        $byId = [];
        foreach ($accounts as $a) {
            $byId[$a['id']] = $a;
            $byId[$a['id']]['children'] = [];
        }
        // Attach children to their parent
                        try {
                            $db = \Config\Database::connect();
                            $db->query('UPDATE accounts SET is_bank=? WHERE id=?', [(int)$data['is_bank'], $id]);
                            $fixed = $m->find($id);
                            log_message('error','Direct SQL fix post-update is_bank='.(int)($fixed['is_bank'] ?? -1));
                        } catch (\Throwable $eFix) {
                            log_message('error','Direct SQL fix failed: '.$eFix->getMessage());
                        }
        foreach ($accounts as $a) {
            $pid = $a['parent_id'] ?? null;
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$a['id']];
            log_message('debug','Accounts update AFTER id='.(isset($a['id']) ? $a['id'] : 'NULL'));
            }
        }
        // Prepare hierarchical buckets by type
        $hierarchical = [
            'Asset' => [], 'Liability' => [], 'Equity' => [], 'Revenue' => [], 'Expense' => [],
        ];
        foreach ($byId as $id => &$node) {
            $type = $node['type'] ?? '';
            $bucket = in_array($type, array_keys($hierarchical), true) ? $type : 'Asset';
            $pid = $node['parent_id'] ?? null;
            // Root nodes only
            if (!$pid || !isset($byId[$pid])) {
                $hierarchical[$bucket][] = &$node;
            }
        }
        // Sort children by code inside each node (recursive)
        $sortFn = function (&$arr) use (&$sortFn) {
            usort($arr, function($a,$b){ return strcmp($a['code'],$b['code']); });
            foreach ($arr as &$n) { if (!empty($n['children'])) { $sortFn($n['children']); } }
        };
        foreach ($hierarchical as &$hGroup) { $sortFn($hGroup); }
        unset($node); // break reference
        return view('accounting/accounts/index', [
            'accounts' => $accounts,
            'allAccounts' => $accounts,
            'currencies' => $currencies,
            'typesOptions' => $typesOptions,
            'groupedAccounts' => $grouped,
            'hierarchicalAccounts' => $hierarchical,
            'accountBalances' => $balances,
        ]);
    }

    /**
     * Simplified Hierarchy Editor page.
     */
    public function hierarchy()
    {
        // New simplified hierarchy: client builds from JSON endpoint; this page only hosts tree UI.
        return view('accounting/accounts/hierarchy_tree');
    }

    /** Bulk save hierarchy assignments */
    public function saveHierarchy()
    {
        if (!$this->request->isAJAX() && $this->request->getMethod() !== 'post') {
            return redirect()->to('/accounting/accounts/hierarchy')->with('error','Invalid method');
        }
        $m = new AccountModel();
        try { $this->ensureParentColumn(); } catch (\Throwable $e) { /* ignore */ }
        $all = $m->orderBy('code','ASC')->findAll();
        $byId = [];
        foreach ($all as $a) { $byId[$a['id']] = $a; }
        $byParent = [];
        foreach ($all as $a) { $pid = $a['parent_id'] ?? null; $byParent[$pid][] = $a['id']; }
        $getDesc = function($rootId) use (&$getDesc, &$byParent) {
            $out = []; $stack = [$rootId];
            while ($stack) { $cur = array_pop($stack); foreach ($byParent[$cur] ?? [] as $c) { $out[]=$c; $stack[]=$c; } }
            return $out;
        };
        $parentMap = $this->request->getPost('parent_id');
        $updates = []; $errors = [];
        if (!is_array($parentMap)) { $parentMap = []; }
        foreach ($parentMap as $id => $parentId) {
            $id = (int)$id; $parentId = ($parentId === '' ? null : (int)$parentId);
            if (!isset($byId[$id])) { continue; }
            $acc = $byId[$id];
            if ($parentId !== null) {
                if (!isset($byId[$parentId])) { $errors[$id] = 'Parent missing'; continue; }
                if ($parentId === $id) { $errors[$id] = 'Cannot parent itself'; continue; }
                if ($byId[$parentId]['type'] !== $acc['type']) { $errors[$id] = 'Type mismatch'; continue; }
                $desc = $getDesc($id);
                if (in_array($parentId, $desc, true)) { $errors[$id] = 'Cycle detected'; continue; }
            }
            if (($acc['parent_id'] ?? null) !== $parentId) { $updates[$id] = $parentId; }
        }
        foreach ($updates as $id => $pid) {
            try { $m->update($id, ['parent_id'=>$pid]); } catch (\Throwable $e) { $errors[$id] = 'DB error'; }
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success'=>empty($errors),'updated'=>count($updates),'errors'=>$errors]);
        }
        $flash = empty($errors) ? 'Hierarchy saved ('.count($updates).' updates)' : 'Partial save: '.count($updates).' updates, '.count($errors).' errors';
        $key = empty($errors) ? 'success' : 'error';
        return redirect()->to('/accounting/accounts/hierarchy')->with($key,$flash)->with('hier_errors',$errors);
    }

    public function create()
    {
        // Make sure is_bank column exists so inserts don't silently drop field
        try { $this->ensureBankColumn(); } catch (\Throwable $e) { /* ignore */ }
        // Be permissive about the method to avoid environment-specific issues (treat as submit if fields exist)
        $method = $this->request->getMethod(); // lower-case
    // Use getVar() to accept from POST (preferred) or fallback sources
    $typeRaw = trim((string) $this->request->getVar('type'));
        // Normalize type aliases (Income -> Revenue, plural forms -> singular)
        $map = [
            'income' => 'Revenue',
            'revenue' => 'Revenue',
            'revenues' => 'Revenue',
            'asset' => 'Asset',
            'assets' => 'Asset',
            'liability' => 'Liability',
            'liabilities' => 'Liability',
            'expense' => 'Expense',
            'expenses' => 'Expense',
            'equity' => 'Equity',
        ];
        $normType = $map[strtolower($typeRaw)] ?? $typeRaw;

        $data = [
            'code' => trim((string) $this->request->getVar('code')),
            'name' => trim((string) $this->request->getVar('name')),
            'type' => $normType,
            'currency_code' => $this->request->getVar('currency') ?: ($this->request->getVar('currency_code') ?: 'PKR'),
            'parent_id' => $this->request->getVar('parent_id') ? (int)$this->request->getVar('parent_id') : null,
            'is_bank' => $this->request->getVar('is_bank') ? 1 : 0,
            'is_active' => $this->request->getVar('is_active') ? 1 : 1,
        ];
        $allowedTypes = ['Asset','Liability','Equity','Revenue','Expense'];
        $errors = [];
        if ($data['code'] === '') $errors['code'] = 'Code is required';
        if ($data['name'] === '') $errors['name'] = 'Name is required';
        if ($data['type'] === '' || !in_array($data['type'], $allowedTypes, true)) $errors['type'] = 'Type is invalid';
        if (!empty($errors)) {
            return redirect()->to('/accounting/accounts')->with('error', 'Please fix the errors')->with('form_errors', $errors)->withInput();
        }
        $model = new AccountModel();

        // Prevent duplicate code
        if ($model->where('code', $data['code'])->first()) {
            $errors['code'] = 'An account with this code already exists';
            if ($this->request->isAJAX()) {
                $this->clearOutputBuffer();
                return $this->response->setJSON(['success'=>false, 'message'=>'Please fix the errors', 'errors'=>$errors]);
            }
            return redirect()->to('/accounting/accounts')
                ->with('error', 'Please fix the errors')
                ->with('form_errors', $errors)
                ->withInput();
        }
        // Circular/self-parent check is skipped on create because the new id isn't known yet.
        // Parent_id simply points to an existing account; deeper validation handled later if needed.
        if (!empty($errors)) {
            return redirect()->to('/accounting/accounts')->with('error', 'Please fix the errors')->with('form_errors', $errors)->withInput();
        }
        try {
            $id = $model->insert($data, true);
            if ($this->request->isAJAX()) {
                $this->clearOutputBuffer();
                return $this->response->setJSON(['success'=>true, 'id'=>$id]);
            }
            return redirect()->to('/accounting/accounts')->with('success', 'Account created');
        } catch (\Throwable $e) {
            log_message('error', 'Account create failed: ' . $e->getMessage());
            $msg = (stripos($e->getMessage(), 'duplicate') !== false) ? 'An account with this code already exists' : 'Failed to create account';
            if ($msg !== 'Failed to create account') { $errors['code'] = $msg; }
            if ($this->request->isAJAX()) {
                $this->clearOutputBuffer();
                return $this->response->setJSON(['success'=>false, 'message'=>$msg, 'errors'=>$errors, 'debug'=>$e->getMessage()]);
            }
            return redirect()->to('/accounting/accounts')
                ->with('error', $msg)
                ->with('form_errors', $errors)
                ->withInput();
        }
    }

    /**
     * Auto-assign parent accounts based on numeric code patterns.
     * Rule: For a purely numeric code, if last digit != 0 and an account exists whose code is floor(code/10)*10,
     *       assign that account as parent. E.g. 4201 -> parent 4200, 4215 -> 4210.
     * Optional GET/POST param force=1 to override existing parent assignments.
     * Returns JSON for AJAX or redirects back with a flash message.
     */
    public function autoAssignParents()
    {
        // Ensure column exists
        try { $this->ensureParentColumn(); } catch (\Throwable $e) { /* ignore */ }
        $force = (int)$this->request->getVar('force') === 1;
        $dry = (int)$this->request->getVar('dry_run') === 1;
        $m = new AccountModel();
        $all = $m->orderBy('code','ASC')->findAll();
        $codeMap = [];
        foreach ($all as $a) { $codeMap[$a['code']] = $a; }
        $updates = [];
        foreach ($all as $a) {
            $code = $a['code'];
            if ($code === null) continue;
            if (!preg_match('/^\d+$/', $code)) continue; // numeric only
            $parentCode = (string) (int) (floor(((int)$code)/10)*10);
            // Pad with leading zeros if original had them
            if (strlen($parentCode) < strlen($code)) {
                $parentCode = str_pad($parentCode, strlen($code), '0', STR_PAD_LEFT);
            }
            if ($parentCode === $code) continue; // already a top-level (ends with 0)
            if (!isset($codeMap[$parentCode])) continue; // no parent existing
            $currentParentId = $a['parent_id'] ?? null;
            $targetParentId = $codeMap[$parentCode]['id'];
            if ($currentParentId && $currentParentId == $targetParentId && !$force) continue; // already assigned
            if ($dry) {
                $updates[] = [
                    'id' => $a['id'],
                    'code' => $code,
                    'from_parent' => $currentParentId,
                    'to_parent' => $targetParentId,
                    'parent_code' => $parentCode,
                ];
            } else {
                try { $m->update($a['id'], ['parent_id' => $targetParentId]); } catch (\Throwable $e) {
                    log_message('error', 'AutoAssignParents update failed for '.$code.': '.$e->getMessage());
                }
                $updates[] = [
                    'id' => $a['id'],
                    'code' => $code,
                    'assigned_parent_id' => $targetParentId,
                    'parent_code' => $parentCode,
                ];
            }
        }
        $summary = [
            'total' => count($all),
            'updated' => count($updates),
            'dry_run' => $dry,
            'force' => $force,
            'changes' => $updates,
        ];
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success'=>true,'message'=>'Parent assignment complete','data'=>$summary]);
        }
        return redirect()->to('/accounting/accounts')->with('success', 'Parent assignment '.($dry?'(dry-run) ':'').'completed: '.$summary['updated'].' rows')->with('parent_assign_summary', $summary);
    }

    /** Helper: compute detailed balance for an account */
    private function getAccountBalance(int $accountId): array
    {
        $out = ['debits'=>0.0,'credits'=>0.0,'balance'=>0.0,'lines'=>0];
        try {
            $db = \Config\Database::connect();
            $row = $db->query('SELECT COUNT(*) lines, COALESCE(SUM(debit),0) debits, COALESCE(SUM(credit),0) credits FROM journal_lines WHERE account_id = ?', [$accountId])->getRowArray();
            if ($row) {
                $out['lines'] = (int)$row['lines'];
                $out['debits'] = (float)$row['debits'];
                $out['credits'] = (float)$row['credits'];
                $out['balance'] = $out['debits'] - $out['credits'];
            }
        } catch (\Throwable $e) {
            log_message('error','getAccountBalance failed: '.$e->getMessage());
        }
        return $out;
    }

    /** Ensure logging table exists for extreme account actions */
    private function ensureLogTable(): void
    {
        try {
            $db = \Config\Database::connect();
            $exists = $db->query("SHOW TABLES LIKE 'account_action_logs'")->getNumRows() > 0;
            if (!$exists) {
                $db->query("CREATE TABLE account_action_logs (\n                    id INT AUTO_INCREMENT PRIMARY KEY,\n                    account_id INT NULL,\n                    target_account_id INT NULL,\n                    action VARCHAR(50) NOT NULL,\n                    reason VARCHAR(255) NULL,\n                    meta JSON NULL,\n                    user_id INT NULL,\n                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                    KEY idx_account (account_id),\n                    KEY idx_action (action)\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
        } catch (\Throwable $e) {
            log_message('error','ensureLogTable failed: '.$e->getMessage());
        }
    }

    private function logAccountAction(?int $accountId, string $action, ?string $reason = null, array $meta = [], ?int $targetAccountId = null): void
    {
        $this->ensureLogTable();
        try {
            $db = \Config\Database::connect();
            $jsonMeta = json_encode($meta); if (strlen($jsonMeta) > 1000) { $jsonMeta = substr($jsonMeta,0,1000); }
            $userId = session('user_id') ?: null; // dev bypass may not set
            $db->query('INSERT INTO account_action_logs(account_id,target_account_id,action,reason,meta,user_id) VALUES (?,?,?,?,?,?)', [
                $accountId, $targetAccountId, $action, $reason, $jsonMeta, $userId
            ]);
        } catch (\Throwable $e) {
            log_message('error','logAccountAction failed: '.$e->getMessage());
        }
    }

    /** Delete account if it has zero balance and no journal lines; else refuse */
    public function delete($id)
    {
        $id = (int)$id;
        $m = new AccountModel();
        $acc = $m->find($id);
        if (!$acc) return $this->respondAccountAction(false,'Account not found');
        $reason = trim((string)$this->request->getPost('reason'));
        $bal = $this->getAccountBalance($id);
        $hasChildren = (bool)$m->where('parent_id',$id)->countAllResults();
        if ($hasChildren) return $this->respondAccountAction(false,'Account has children; reassign or merge first');
        $absBal = abs($bal['balance']);
        if ($bal['lines'] > 0) {
            return $this->respondAccountAction(false,'Account has activity; merge or deactivate instead');
        }
        if ($absBal > 0.0001) {
            return $this->respondAccountAction(false,'Balance not zero; cannot delete');
        }
        if ($reason === '') { return $this->respondAccountAction(false,'Provide a reason for deletion'); }
        try {
            $m->delete($id);
            $this->logAccountAction($id,'delete',$reason,['code'=>$acc['code']??null,'name'=>$acc['name']??null]);
            return $this->respondAccountAction(true,'Account deleted');
        } catch (\Throwable $e) {
            log_message('error','Account delete failed: '.$e->getMessage());
            return $this->respondAccountAction(false,'Delete failed');
        }
    }

    /** Mark account inactive */
    public function deactivate($id)
    {
        $id = (int)$id; $m = new AccountModel(); $acc = $m->find($id);
        if (!$acc) return $this->respondAccountAction(false,'Account not found');
        $reason = trim((string)$this->request->getPost('reason'));
        if ($reason === '') { return $this->respondAccountAction(false,'Provide a reason to inactivate'); }
        try { $m->update($id,['is_active'=>0]); return $this->respondAccountAction(true,'Account deactivated'); }
        catch (\Throwable $e) { log_message('error','Deactivate failed: '.$e->getMessage()); return $this->respondAccountAction(false,'Deactivate failed'); }
        finally { $this->logAccountAction($id,'deactivate',$reason,['code'=>$acc['code']??null]); }
    }

    /** Merge account into target: move journal lines & children; then delete source */
    public function merge($id)
    {
        $id = (int)$id; $targetId = (int)$this->request->getPost('target_id');
        if ($id === $targetId || $targetId <= 0) return $this->respondAccountAction(false,'Invalid target');
        $reason = trim((string)$this->request->getPost('reason'));
        if ($reason === '') { return $this->respondAccountAction(false,'Provide a reason for merge'); }
        $m = new AccountModel();
        $src = $m->find($id); $dst = $m->find($targetId);
        if (!$src || !$dst) return $this->respondAccountAction(false,'Source or target not found');
        // Types should match to maintain reporting consistency
        if ($src['type'] !== $dst['type']) {
            return $this->respondAccountAction(false,'Types differ; change type or choose matching account');
        }
    $db = \Config\Database::connect();
        // Prevent merge into descendant causing orphan cycle
        if ($this->isDescendant($m,$targetId,$id)) {
            return $this->respondAccountAction(false,'Cannot merge into descendant');
        }
        $db->transBegin();
        try {
            // Move journal lines
            $db->query('UPDATE journal_lines SET account_id = ? WHERE account_id = ?', [$targetId,$id]);
            // Reparent children to target
            $db->query('UPDATE accounts SET parent_id = ? WHERE parent_id = ?', [$targetId,$id]);
            // Delete source
            $m->delete($id);
            $db->transCommit();
            $this->logAccountAction($id,'merge',$reason,[
                'source_code'=>$src['code']??null,
                'target_code'=>$dst['code']??null,
                'moved_lines'=>true
            ], $targetId);
            return $this->respondAccountAction(true,'Merged into '.$dst['code']);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error','Merge failed: '.$e->getMessage());
            return $this->respondAccountAction(false,'Merge failed');
        }
    }

    /** Unified response helper */
    private function respondAccountAction(bool $ok, string $msg)
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success'=>$ok,'message'=>$msg]);
        }
        $flashType = $ok ? 'success' : 'error';
        return redirect()->to('/accounting/accounts')->with($flashType,$msg);
    }

    /**
     * Drag-and-drop reparent endpoint.
     * POST: child_id (int), new_parent_id (nullable int), new_type (nullable string: Asset/Liability/Equity/Revenue/Expense)
     * If new_parent_id provided, child's type will be set to parent's type. If null, new_type is required.
     */
    public function reparent()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'AJAX required']);
        }
        $allowedTypes = ['Asset','Liability','Equity','Revenue','Expense'];
        $childId = (int) $this->request->getPost('child_id');
        $newParentId = $this->request->getPost('new_parent_id');
        $newParentId = ($newParentId === '' || $newParentId === null) ? null : (int)$newParentId;
        $newType = $this->request->getPost('new_type');

        $m = new AccountModel();

        // Ensure schema supports parenting (adds parent_id column if missing)
        try { $this->ensureParentColumn(); } catch (\Throwable $e) {
            log_message('error', 'ensureParentColumn failed: ' . $e->getMessage());
        }
        $child = $m->find($childId);
        if (!$child) {
            return $this->response->setJSON(['success'=>false,'message'=>'Child not found']);
        }

        // Validate parent and check for cycles
        if ($newParentId !== null) {
            $parent = $m->find($newParentId);
            if (!$parent) {
                return $this->response->setJSON(['success'=>false,'message'=>'Parent not found']);
            }
            
            // Parent and child must be same type
            if ($parent['type'] !== $child['type']) {
                return $this->response->setJSON(['success'=>false,'message'=>'Parent and child must be same type']);
            }

            // Prevent cycles: parent cannot be a descendant of child
            if ($this->isDescendant($m, $newParentId, $childId)) {
                return $this->response->setJSON(['success'=>false,'message'=>'Cannot create circular reference']);
            }
        }

        try {
            // Only update parent_id, keep child's type unchanged
            $m->update($childId, [
                'parent_id' => $newParentId,
            ]);
            return $this->response->setJSON(['success'=>true]);
        } catch (\Throwable $e) {
            log_message('error', 'COA reparent failed: ' . $e->getMessage());
            return $this->response->setJSON(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
        }
    }

    /**
     * Helper: is candidateId a descendant of rootId?
     */
    private function isDescendant(AccountModel $m, int $candidateId, int $rootId): bool
    {
        if ($candidateId === $rootId) return true;
        // Walk up the tree from candidate to root
        $visited = [];
        $current = $candidateId;
        while ($current) {
            if (isset($visited[$current])) break; // safety
            $visited[$current] = true;
            $row = $m->find($current);
            if (!$row) break;
            $pid = $row['parent_id'] ?? null;
            if ($pid === $rootId) return true;
            $current = $pid ? (int)$pid : 0;
        }
        return false;
    }

    /**
     * Add parent_id column and FK if missing (best-effort, safe to call repeatedly).
     */
    private function ensureParentColumn(): void
    {
    $db = \Config\Database::connect();
        $exists = false;
        try {
            $q = $db->query("SHOW COLUMNS FROM accounts LIKE 'parent_id'");
            $exists = ($q && $q->getNumRows() > 0);
        } catch (\Throwable $e) {
            // ignore
        }
        if ($exists) return;
        try {
            $db->query("ALTER TABLE accounts ADD COLUMN parent_id INT NULL");
        } catch (\Throwable $e) { /* ignore if already added */ }
        try {
            $db->query("ALTER TABLE accounts ADD CONSTRAINT fk_acc_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL");
        } catch (\Throwable $e) { /* ignore if FK exists */ }
    }

    public function edit($id)
    {
        $id = (int) $id;
        $m = new AccountModel();
        $acc = $m->find($id);
        if (!$acc) return redirect()->to('/accounting/accounts')->with('error', 'Account not found');
        $cm = new CurrencyModel();
        $currencies = $cm->orderBy('code','ASC')->findAll();
        $all = $m->orderBy('code','ASC')->findAll();
        return view('accounting/accounts/edit', [
            'account' => $acc,
            'allAccounts' => $all,
            'currencies' => $currencies,
            'types' => ['Asset','Liability','Equity','Revenue','Expense'],
        ]);
    }

    public function update($id)
    {
        // Ensure is_bank column exists so update persists
        try { $this->ensureBankColumn(); } catch (\Throwable $e) { /* ignore */ }
        $id = (int) $id;
        // Read using getVar() to tolerate environment quirks
        $code = trim((string)$this->request->getVar('code'));
        $name = trim((string)$this->request->getVar('name'));
        $typeRaw = trim((string)$this->request->getVar('type'));
        $cur = $this->request->getVar('currency') ?: ($this->request->getVar('currency_code') ?: 'PKR');
        if ($cur) { $cur = strtoupper(trim((string)$cur)); }
        $parentVar = $this->request->getVar('parent_id');
        // Capture raw posted flag (string or null) for diagnostics
        $rawIsBank = $this->request->getVar('is_bank');
        $data = [
            'code' => $code,
            'name' => $name,
            'type' => $typeRaw,
            'currency_code' => $cur,
            'parent_id' => ($parentVar ? (int)$parentVar : null),
            'is_bank' => $rawIsBank ? 1 : 0,
            'is_active' => $this->request->getVar('is_active') ? 1 : 1,
        ];
        if ($data['parent_id'] === $id) { $data['parent_id'] = null; }
        // Normalize Income -> Revenue
        $map = ['income'=>'Revenue','revenues'=>'Revenue','revenue'=>'Revenue'];
        $tl = strtolower($data['type']);
        if (isset($map[$tl])) { $data['type'] = $map[$tl]; }
        $allowedTypes = ['Asset','Liability','Equity','Revenue','Expense'];
        if ($data['code'] === '' || $data['name'] === '' || !in_array($data['type'], $allowedTypes, true)) {
            return redirect()->back()->with('error', 'Invalid data')->withInput();
        }
        $m = new AccountModel();
        // Prevent self-parenting and circular references on update
        if ($data['parent_id']) {
            if ($data['parent_id'] === $id) {
                return redirect()->back()->with('error', 'Account cannot be its own parent')->withInput();
            }
            // Check for circular reference
            $checkId = $data['parent_id'];
            $visited = [$id];
            $am = new AccountModel();
            while ($checkId) {
                if (in_array($checkId, $visited, true)) {
                    return redirect()->back()->with('error', 'Circular parent relationship detected')->withInput();
                }
                $visited[] = $checkId;
                $row = $am->find($checkId);
                $checkId = $row ? $row['parent_id'] : null;
            }
        }
        try {
            log_message('debug', 'Accounts update: incoming is_bank raw=' . var_export($rawIsBank,true) . ' normalized=' . $data['is_bank'] . ' for id=' . $id);
            $m->update($id, $data);
            // Re-fetch to verify persistence of bank flag; if mismatch, log for diagnostics
            try {
                $updated = $m->find($id);
                if ($updated && (int)($updated['is_bank'] ?? 0) !== (int)$data['is_bank']) {
                    log_message('error', 'Bank flag mismatch after update for account '.$id.' expected '.$data['is_bank'].' got '.($updated['is_bank'] ?? 'null'));
                }
                else {
                    log_message('debug', 'Bank flag persisted for id='.$id.' value='.(int)($updated['is_bank'] ?? 0));
                }
            } catch (\Throwable $e2) { /* ignore */ }
            return redirect()->to('/accounting/accounts')->with('success', 'Account updated');
        } catch (\Throwable $e) {
            log_message('error', 'Account update failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update');
        }
    }

    private function seedDefaultAccounts(): void
    {
        $accts = [
            ['code'=>'1000','name'=>'Cash','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'1100','name'=>'Bank','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'1200','name'=>'Accounts Receivable','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'1300','name'=>'Inventory','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'1400','name'=>'Prepaid Expenses','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'1500','name'=>'Fixed Assets','type'=>'Asset','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'2000','name'=>'Accounts Payable','type'=>'Liability','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'2100','name'=>'Accrued Expenses','type'=>'Liability','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'2200','name'=>'Taxes Payable','type'=>'Liability','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'3000','name'=>'Owner\'s Capital','type'=>'Equity','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'3100','name'=>'Retained Earnings','type'=>'Equity','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'4000','name'=>'Sales Revenue','type'=>'Revenue','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'4100','name'=>'Service Revenue','type'=>'Revenue','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5000','name'=>'Cost of Goods Sold','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5100','name'=>'Rent Expense','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5200','name'=>'Salaries Expense','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5300','name'=>'Utilities Expense','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5400','name'=>'Office Supplies','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5500','name'=>'Depreciation Expense','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
            ['code'=>'5600','name'=>'Marketing Expense','type'=>'Expense','currency_code'=>'PKR','is_active'=>1],
        ];
        $model = new AccountModel();
        foreach ($accts as $a) {
            try { $model->insert($a); } catch (\Throwable $e) { /* ignore */ }
        }
    }

    /** AJAX: set parent relationship (strict parent_id update). */
    public function setParent()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX required']);
        }
        $childId = (int)$this->request->getPost('childId');
        $parentRaw = trim((string)$this->request->getPost('parentId'));
        $parentId = ($parentRaw === '' ? null : (int)$parentRaw);
        try {
            $svc = \Config\Services::accountsHierarchy(false);
            $updated = $svc->updateParent($childId, $parentId);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Parent updated',
                'data' => ['account' => $updated]
            ]);
        } catch (\DomainException $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', 'setParent error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Internal error']);
        }
    }

    /** Ensure the is_bank column exists; add if missing (idempotent). */
    private function ensureBankColumn(): void
    {
        try {
            $db = \Config\Database::connect();
            $q = $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'");
            if ($q && $q->getNumRows() > 0) { return; }
            $db->query("ALTER TABLE accounts ADD COLUMN is_bank TINYINT(1) DEFAULT 0 AFTER currency_code");
            log_message('debug','Added is_bank column to accounts table');
        } catch (\Throwable $e) { /* ignore */ }
    }

    /** AJAX: return strict hierarchy JSON (roots + flatCount). */
    public function hierarchyJson()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX required']);
        }
        try {
            $svc = \Config\Services::accountsHierarchy(false);
            $tree = $svc->getAccountsHierarchy();
            return $this->response->setJSON(['success' => true, 'data' => $tree]);
        } catch (\Throwable $e) {
            log_message('error', 'hierarchyJson failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to build hierarchy']);
        }
    }
}
