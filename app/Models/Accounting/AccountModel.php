<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class AccountModel extends Model
{
    // Unified DB: removed $DBGroup to use default connection
    protected $table = 'accounts';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'name', 'type', 'currency_code', 'is_active', 'parent_id', 'is_bank'];
    protected $useTimestamps = true;

    /**
     * Return accounts in hierarchical tree (parent -> children) for display.
     */
    public function getTree(): array
    {
        $rows = $this->orderBy('code','ASC')->findAll();
        $byParent = [];
        foreach ($rows as $r) {
            $pid = $r['parent_id'] ?? null;
            $byParent[$pid][] = $r;
        }
        $build = function($parentId) use (&$build, $byParent) {
            $children = $byParent[$parentId] ?? [];
            foreach ($children as &$child) {
                $child['children'] = $build($child['id']);
            }
            return $children;
        };
        return $build(null);
    }

    /**
     * Get raw debit/credit totals for an account within optional date filters.
     */
    public function getTotals(int $accountId, ?string $from = null, ?string $to = null): array
    {
    $db = \Config\Database::connect();
    $sql = "SELECT COALESCE(SUM(jl.debit),0) d, COALESCE(SUM(jl.credit),0) c FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id WHERE jl.account_id = ?";
        $params = [$accountId];
        if ($from) { $sql .= " AND je.entry_date >= ?"; $params[] = $from; }
        if ($to) { $sql .= " AND je.entry_date <= ?"; $params[] = $to; }
        $row = $db->query($sql, $params)->getRowArray() ?: ['d'=>0,'c'=>0];
        return ['debits'=>(float)$row['d'], 'credits'=>(float)$row['c']];
    }

    /** Natural-side balance (Assets/Expenses: debit-credit; Liability/Equity/Revenue: credit-debit). */
    public function getBalance(int $accountId, ?string $from = null, ?string $to = null): float
    {
        $acc = $this->find($accountId); if (!$acc) return 0.0;
        $t = $this->getTotals($accountId, $from, $to);
        return in_array($acc['type'], ['Asset','Expense'], true) ? ($t['debits'] - $t['credits']) : ($t['credits'] - $t['debits']);
    }

    /** Bulk balances for reporting. */
    public function getBalances(?string $from = null, ?string $to = null, ?array $types = null): array
    {
    $db = \Config\Database::connect();
        $conditions = [];$params=[];
        if ($types) { $in = implode(',', array_fill(0,count($types),'?')); $conditions[] = "a.type IN ($in)"; $params = array_merge($params,$types); }
        $dateFilter='';
        if ($from) { $dateFilter .= " AND je.entry_date >= ?"; $params[] = $from; }
        if ($to) { $dateFilter .= " AND je.entry_date <= ?"; $params[] = $to; }
        $where = $conditions ? ('WHERE '.implode(' AND ',$conditions)) : '';
    $sql = "SELECT a.id,a.code,a.name,a.type,COALESCE(SUM(jl.debit),0) debits,COALESCE(SUM(jl.credit),0) credits
        FROM accounts a
        LEFT JOIN journal_lines jl ON jl.account_id = a.id
        LEFT JOIN journal_entries je ON je.id = jl.entry_id $dateFilter
        $where
        GROUP BY a.id,a.code,a.name,a.type
        ORDER BY a.type,a.code";
        $rows = $db->query($sql,$params)->getResultArray();
        foreach ($rows as &$r) {
            $d=(float)$r['debits']; $c=(float)$r['credits'];
            if (in_array($r['type'], ['Asset','Expense'], true)) { $r['balance']=$d-$c; }
            elseif (in_array($r['type'], ['Liability','Equity','Revenue'], true)) { $r['balance']=$c-$d; }
            else { $r['balance']=$d-$c; }
        }
        return $rows;
    }
}
