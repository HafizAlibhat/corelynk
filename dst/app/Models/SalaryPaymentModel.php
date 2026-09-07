<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One payslip per employee per month (enforced by uniq_employee_month).
 */
class SalaryPaymentModel extends Model
{
    protected $table         = 'salary_payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'employee_id', 'period_month', 'basic_amount', 'allowances', 'deductions',
        'net_amount', 'currency_code', 'status', 'paid_on', 'payment_method',
        'source_account_id', 'cheque_number', 'cheque_image', 'posted_entry_id',
        'notes', 'created_by',
    ];

    /** Active employees for the month, each with its payslip if one exists. */
    public function sheetForMonth(string $month): array
    {
        return $this->db->query(
            'SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                    e.department, e.designation, e.monthly_salary, e.salary_currency,
                    sp.id AS payment_id, sp.basic_amount, sp.allowances, sp.deductions,
                    sp.net_amount, sp.currency_code, sp.status, sp.paid_on,
                    sp.payment_method, sp.notes, sp.source_account_id,
                    sp.cheque_number, sp.cheque_image, sp.posted_entry_id,
                    a.code AS account_code, a.name AS account_name
               FROM employees e
               LEFT JOIN salary_payments sp ON sp.employee_id = e.id AND sp.period_month = ?
               LEFT JOIN accounts a ON a.id = sp.source_account_id
              WHERE e.is_active = 1
              ORDER BY e.first_name ASC, e.last_name ASC',
            [$month]
        )->getResultArray();
    }

    public function historyFor(int $employeeId): array
    {
        return $this->select('salary_payments.*, a.code AS account_code, a.name AS account_name')
                    ->join('accounts a', 'a.id = salary_payments.source_account_id', 'left')
                    ->where('employee_id', $employeeId)
                    ->orderBy('period_month', 'DESC')
                    ->findAll(24);
    }

    /**
     * Create a pending payslip for everyone on a salary who has none for the month.
     * Re-runnable: existing payslips are never overwritten.
     */
    public function generateMonth(string $month, ?int $userId = null): int
    {
        $created = 0;
        foreach ($this->sheetForMonth($month) as $row) {
            if ($row['payment_id'] || $row['monthly_salary'] === null || (float) $row['monthly_salary'] <= 0) {
                continue;
            }
            $this->insert([
                'employee_id'   => $row['employee_id'],
                'period_month'  => $month,
                'basic_amount'  => (float) $row['monthly_salary'],
                'allowances'    => 0,
                'deductions'    => 0,
                'net_amount'    => (float) $row['monthly_salary'],
                'currency_code' => $row['salary_currency'] ?: base_currency_code(),
                'status'        => 'pending',
                'created_by'    => $userId,
            ]);
            $created++;
        }

        return $created;
    }

    /** Month totals, split by currency because pay may not be in one currency. */
    public function monthTotals(string $month): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(currency_code, ""), ?) AS currency_code, status,
                    SUM(net_amount) AS total, COUNT(*) AS n
               FROM salary_payments WHERE period_month = ? GROUP BY 1, 2',
            [base_currency_code(), $month]
        )->getResultArray();

        $out = ['paid' => [], 'pending' => [], 'paid_count' => 0, 'pending_count' => 0];
        foreach ($rows as $r) {
            $bucket = $r['status'] === 'paid' ? 'paid' : 'pending';
            $out[$bucket][$r['currency_code']] = ($out[$bucket][$r['currency_code']] ?? 0) + (float) $r['total'];
            $out[$bucket . '_count'] += (int) $r['n'];
        }

        return $out;
    }
}
