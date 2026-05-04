<?php

namespace App\Controllers\Api;

use App\Models\Accounting\JournalEntryModel;
use App\Models\Accounting\JournalLineModel;

/**
 * Expense management via journal entries (no separate expense table).
 *
 * GET  /api/expenses                — List expense journal entries
 * POST /api/expenses                — Record a new expense (creates journal entry)
 * GET  /api/expense-accounts        — List available expense GL accounts
 * GET  /api/payment-accounts        — List cash/bank accounts for payment source
 */
class ExpenseApi extends BaseApiController
{
    /**
     * GET /api/expenses
     *
     * Lists journal entries that have at least one debit line to an expense account (type=Expense).
     */
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'read')) {
            return $this->response;
        }

        $db   = \Config\Database::connect();
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 30)));
        $offset  = ($page - 1) * $perPage;

        // Count
        $total = (int) ($db->query(
            "SELECT COUNT(DISTINCT je.id) AS c
             FROM journal_entries je
             INNER JOIN journal_lines jl ON jl.entry_id = je.id
             INNER JOIN accounts a ON jl.account_id = a.id AND a.type = 'Expense'
             WHERE jl.debit > 0"
        )->getRowArray()['c'] ?? 0);

        // List: get entry header + the expense details from the debit side
        $rows = $db->query(
            "SELECT je.id, je.entry_date, je.memo, je.total_debits AS amount,
                    je.currency_code AS currency,
                    GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS expense_categories
             FROM journal_entries je
             INNER JOIN journal_lines jl ON jl.entry_id = je.id
             INNER JOIN accounts a ON jl.account_id = a.id AND a.type = 'Expense'
             WHERE jl.debit > 0
             GROUP BY je.id
             ORDER BY je.entry_date DESC, je.id DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        )->getResultArray();

        return $this->success([
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ]);
    }

    /**
     * POST /api/expenses
     *
     * Create an expense by posting a balanced journal entry.
     *
     * Payload:
     * {
     *   "expense_account_id": 15,        // GL account id (type=Expense)
     *   "payment_account_id": 1,         // Cash/Bank account id
     *   "amount": 5000,
     *   "date": "2026-04-04",            // optional, defaults to today
     *   "description": "Office rent April",
     *   "currency": "PKR"                // optional
     * }
     */
    public function create(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'write')) {
            return $this->response;
        }

        $body = $this->getJsonBody();

        // Validate
        if (empty($body['expense_account_id'])) {
            return $this->error('expense_account_id is required.');
        }
        if (empty($body['payment_account_id'])) {
            return $this->error('payment_account_id is required (cash or bank account).');
        }
        $amount = (float) ($body['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->error('amount must be greater than zero.');
        }

        $db = \Config\Database::connect();

        // Verify expense account exists and is type=Expense
        $expenseAcct = $db->query("SELECT id, name, type FROM accounts WHERE id = ?",
            [(int) $body['expense_account_id']])->getRowArray();
        if (!$expenseAcct || $expenseAcct['type'] !== 'Expense') {
            return $this->error('Invalid expense account. Must be an Expense type account.');
        }

        // Verify payment account exists (Asset type)
        $paymentAcct = $db->query("SELECT id, name, type FROM accounts WHERE id = ?",
            [(int) $body['payment_account_id']])->getRowArray();
        if (!$paymentAcct) {
            return $this->error('Invalid payment account.');
        }

        $entryDate   = $body['date'] ?? date('Y-m-d');
        $description = trim($body['description'] ?? 'Expense');
        $currency    = strtoupper($body['currency'] ?? 'PKR');

        $db->transStart();

        $entryModel = new JournalEntryModel();
        $lineModel  = new JournalLineModel();

        // Create journal entry header
        $entryModel->insert([
            'entry_date'    => $entryDate,
            'memo'          => $description,
            'currency_code' => $currency,
            'total_debits'  => round($amount, 2),
            'total_credits' => round($amount, 2),
            'source_type'   => 'mobile_expense',
        ]);
        $entryId = (int) $db->insertID();

        if (!$entryId) {
            $db->transComplete();
            return $this->error('Failed to create journal entry.');
        }

        // Debit line (expense account) — increases expense
        $lineModel->insert([
            'entry_id'      => $entryId,
            'account_id'    => (int) $body['expense_account_id'],
            'description'   => $description,
            'debit'         => round($amount, 2),
            'credit'        => 0,
            'currency_code' => $currency,
        ]);

        // Credit line (payment account) — decreases cash/bank
        $lineModel->insert([
            'entry_id'      => $entryId,
            'account_id'    => (int) $body['payment_account_id'],
            'description'   => "Payment: {$description}",
            'debit'         => 0,
            'credit'        => round($amount, 2),
            'currency_code' => $currency,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('Transaction failed.');
        }

        return $this->success([
            'journal_entry_id' => $entryId,
            'amount'           => round($amount, 2),
            'expense_account'  => $expenseAcct['name'],
            'payment_account'  => $paymentAcct['name'],
        ], 'Expense recorded successfully.', 201);
    }

    /**
     * GET /api/expense-accounts
     *
     * Returns all active GL accounts of type Expense.
     */
    public function expenseAccounts(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $rows = $db->query(
            "SELECT id, code, name FROM accounts WHERE type = 'Expense' AND is_active = 1 ORDER BY code"
        )->getResultArray();

        return $this->success($rows);
    }

    /**
     * GET /api/payment-accounts
     *
     * Returns cash and bank accounts for expense payment source.
     */
    public function paymentAccounts(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $rows = $db->query(
            "SELECT id, code, name, is_bank FROM accounts
             WHERE type = 'Asset' AND is_active = 1 AND (code LIKE '1%')
             AND code NOT IN ('1200','1300','1401','1500')
             ORDER BY code"
        )->getResultArray();

        return $this->success($rows);
    }
}
