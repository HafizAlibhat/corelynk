<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\SalaryPaymentModel;
use App\Services\AccountingPostingService;

/**
 * Monthly salary sheet.
 *
 * The flow is deliberately two steps, because they are two different decisions:
 *   1. Prepare  — what each person is owed this month (a plan, nothing posted)
 *   2. Pay      — money actually leaves a cash/bank account (posts to the ledger)
 *
 * Routed under /employees/payroll so it inherits the HR permissions already
 * mapped in RoutePermissions — no new RBAC entries needed.
 */
class Payroll extends BaseController
{
    private const METHODS = ['cash', 'bank', 'online_transfer', 'cheque'];

    protected SalaryPaymentModel $salaries;

    public function __construct()
    {
        $this->salaries = new SalaryPaymentModel();
    }

    public function index()
    {
        $this->requireAuth();
        helper('currency');

        $month = $this->month($this->request->getGet('month'));

        return view('employees/payroll', [
            'page_title' => 'Salaries',
            'month'      => $month,
            'sheet'      => $this->salaries->sheetForMonth($month),
            'totals'     => $this->salaries->monthTotals($month),
            'currencies' => currency_catalog(),
            'accounts'   => $this->payFromAccounts(),
        ]);
    }

    /** Step 1: draft this month's payslips from each employee's agreed salary. */
    public function generate()
    {
        $this->requireAuth();
        helper('currency');

        $month = $this->month($this->request->getPost('month'));
        $count = $this->salaries->generateMonth($month, session('user_id'));

        session()->setFlashdata($count > 0 ? 'success' : 'error', $count > 0
            ? $count . ' salary slip(s) prepared for ' . date('F Y', strtotime($month)) . '. Review the amounts, then pay each one.'
            : 'Nothing to prepare — every employee on a salary already has a slip for this month.');

        return redirect()->to('/employees/payroll?month=' . $month);
    }

    /** Create or edit the amounts on a slip. Never touches the ledger. */
    public function save()
    {
        $this->requireAuth();
        helper('currency');

        $month      = $this->month($this->request->getPost('month'));
        $employeeId = (int) $this->request->getPost('employee_id');
        if ($employeeId <= 0) {
            session()->setFlashdata('error', 'Select an employee first.');

            return redirect()->to('/employees/payroll?month=' . $month);
        }

        $basic      = (float) $this->request->getPost('basic_amount');
        $allowances = (float) $this->request->getPost('allowances');
        $commission = (float) $this->request->getPost('commission');
        $deductions = (float) $this->request->getPost('deductions');

        $data = [
            'employee_id'     => $employeeId,
            'period_month'    => $month,
            'basic_amount'    => $basic,
            'allowances'      => $allowances,
            'commission'      => $commission,
            // The reason for the commission belongs with the month it was paid.
            'commission_note' => $commission > 0 ? ($this->request->getPost('commission_note') ?: null) : null,
            'deductions'      => $deductions,
            'net_amount'      => round($basic + $allowances + $commission - $deductions, 2),
            'currency_code'   => strtoupper((string) $this->request->getPost('currency_code')) ?: base_currency_code(),
            'notes'           => $this->request->getPost('notes') ?: null,
        ];

        $existing = $this->slipFor($employeeId, $month);
        if ($existing) {
            if ($existing['status'] === 'paid') {
                session()->setFlashdata('error', 'This slip is already paid and posted to the ledger. Reverse the payment first if it was wrong.');

                return redirect()->to('/employees/payroll?month=' . $month);
            }
            $this->salaries->update($existing['id'], $data);
        } else {
            $data['status']     = 'pending';
            $data['created_by'] = session('user_id');
            $this->salaries->insert($data);
        }

        session()->setFlashdata('success', 'Salary slip saved. It is still unpaid — use Pay when the money actually goes out.');

        return redirect()->to('/employees/payroll?month=' . $month);
    }

    /**
     * Step 2: record the actual payment and post it to the books.
     *
     * Dr Salaries Expense / Cr the chosen cash or bank account.
     */
    public function pay(int $id)
    {
        $this->requireAuth();

        $slip = $this->salaries->find($id);
        if (! $slip) {
            session()->setFlashdata('error', 'Salary slip not found.');

            return redirect()->to('/employees/payroll');
        }

        $month = $slip['period_month'];
        $back  = '/employees/payroll?month=' . $month;

        if ($slip['status'] === 'paid') {
            session()->setFlashdata('error', 'This slip has already been paid.');

            return redirect()->to($back);
        }

        $method    = in_array($this->request->getPost('payment_method'), self::METHODS, true)
            ? $this->request->getPost('payment_method') : 'cash';
        $accountId = (int) $this->request->getPost('source_account_id');
        $chequeNo  = trim((string) $this->request->getPost('cheque_number'));

        if ($accountId <= 0 || ! $this->isPayFromAccount($accountId)) {
            session()->setFlashdata('error', 'Choose the cash or bank account the salary is paid from.');

            return redirect()->to($back);
        }
        if ($method === 'cheque' && $chequeNo === '') {
            session()->setFlashdata('error', 'Enter the cheque number.');

            return redirect()->to($back);
        }

        $chequeImage = $this->storeChequeImage($id);
        if ($chequeImage === false) {
            session()->setFlashdata('error', 'The cheque picture must be a JPG, PNG or WEBP image under 5 MB.');

            return redirect()->to($back);
        }
        if ($method === 'cheque' && $chequeImage === null && empty($slip['cheque_image'])) {
            session()->setFlashdata('error', 'Attach a picture of the cheque so the payment can be traced later.');

            return redirect()->to($back);
        }

        $this->salaries->update($id, [
            'status'            => 'paid',
            'paid_on'           => $this->request->getPost('paid_on') ?: date('Y-m-d'),
            'payment_method'    => $method,
            'source_account_id' => $accountId,
            'cheque_number'     => $method === 'cheque' ? $chequeNo : null,
            'cheque_image'      => $chequeImage ?: ($slip['cheque_image'] ?? null),
        ]);

        $posting = (new AccountingPostingService())->postSalaryPayment($id);

        if (! empty($posting['success'])) {
            session()->setFlashdata('success', 'Salary paid and posted to the ledger (journal entry #'
                . (int) $posting['posted_entry_id'] . '): Salaries Expense debited, ' . $this->accountLabel($accountId) . ' credited.');
        } else {
            // The payment is recorded either way; the books just need attention.
            session()->setFlashdata('error', 'Salary marked as paid, but it could not be posted to the ledger: '
                . ($posting['message'] ?? 'unknown error'));
        }

        return redirect()->to($back);
    }

    /** Undo a payment: unposts nothing automatically, so say so plainly. */
    public function unpay(int $id)
    {
        $this->requireAuth();

        $slip = $this->salaries->find($id);
        if (! $slip) {
            session()->setFlashdata('error', 'Salary slip not found.');

            return redirect()->to('/employees/payroll');
        }

        if (! empty($slip['posted_entry_id'])) {
            session()->setFlashdata('error', 'This payment is already in the ledger (journal entry #'
                . (int) $slip['posted_entry_id'] . '). Reverse that entry in Accounting first — payroll will not silently delete it.');

            return redirect()->to('/employees/payroll?month=' . $slip['period_month']);
        }

        $this->salaries->update($id, ['status' => 'pending', 'paid_on' => null]);
        session()->setFlashdata('success', 'Payment undone. The slip is pending again.');

        return redirect()->to('/employees/payroll?month=' . $slip['period_month']);
    }

    /** Cash and bank accounts only — salaries never come out of a revenue head. */
    private function payFromAccounts(): array
    {
        return (new AccountModel())
            ->select('id, code, name, currency_code, is_bank')
            ->groupStart()->where('is_bank', 1)->orWhereIn('code', ['1000', '1100'])->groupEnd()
            ->where('is_active', 1)
            ->orderBy('code', 'ASC')
            ->findAll();
    }

    private function isPayFromAccount(int $accountId): bool
    {
        foreach ($this->payFromAccounts() as $account) {
            if ((int) $account['id'] === $accountId) {
                return true;
            }
        }

        return false;
    }

    private function accountLabel(int $accountId): string
    {
        foreach ($this->payFromAccounts() as $account) {
            if ((int) $account['id'] === $accountId) {
                return $account['code'] . ' ' . $account['name'];
            }
        }

        return 'account #' . $accountId;
    }

    /**
     * @return string|null|false path on success, null when nothing was uploaded,
     *                           false when the file was rejected
     */
    private function storeChequeImage(int $slipId)
    {
        $file = $this->request->getFile('cheque_image');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }

        if ($file->getSize() > 5 * 1024 * 1024 || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $dir = FCPATH . 'uploads/salary-cheques';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'salary_' . $slipId . '_' . time() . mt_rand(100, 999) . '.' . $file->getExtension();
        $file->move($dir, $name);

        return 'uploads/salary-cheques/' . $name;
    }

    private function slipFor(int $employeeId, string $month): ?array
    {
        return $this->salaries->where('employee_id', $employeeId)->where('period_month', $month)->first();
    }

    /** Always the first day of a real month. */
    private function month(?string $value): string
    {
        $value = trim((string) $value);
        $ts    = $value !== '' ? strtotime(strlen($value) === 7 ? $value . '-01' : $value) : false;

        return $ts ? date('Y-m-01', $ts) : date('Y-m-01');
    }
}
