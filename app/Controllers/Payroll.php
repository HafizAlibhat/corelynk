<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\EmployeeModel;
use App\Models\SalaryPaymentModel;
use App\Services\AccountingPostingService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Monthly salary sheet.
 *
 * The flow is deliberately staged, because each step is a different decision:
 *   1. Prepare  — draft what each person is owed this month (nothing posted)
 *   2. Confirm  — an accountant signs off the amounts before any money moves
 *   3. Pay      — money actually leaves a cash/bank account (posts to the ledger)
 *
 * Salaries paid before this system existed can be brought in via Import,
 * landing already as 'paid' (optionally posted to the ledger too).
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
            // Changing a confirmed amount invalidates the sign-off — it goes back to draft.
            if ($existing['status'] === 'pending') {
                $data['status']       = 'draft';
                $data['confirmed_by'] = null;
                $data['confirmed_at'] = null;
            }
            $this->salaries->update($existing['id'], $data);
        } else {
            $data['status']     = 'draft';
            $data['created_by'] = session('user_id');
            $this->salaries->insert($data);
        }

        session()->setFlashdata('success', 'Salary slip saved as draft. It needs to be confirmed before it can be paid.');

        return redirect()->to('/employees/payroll?month=' . $month);
    }

    /** Step 2: an accountant signs off the drafted amounts. Still nothing paid. */
    public function confirm(int $id)
    {
        $this->requireAuth();

        $slip = $this->salaries->find($id);
        if (! $slip) {
            session()->setFlashdata('error', 'Salary slip not found.');

            return redirect()->to('/employees/payroll');
        }

        $back = '/employees/payroll?month=' . $slip['period_month'];

        if ($slip['status'] !== 'draft') {
            session()->setFlashdata('error', 'Only a draft slip can be confirmed.');

            return redirect()->to($back);
        }

        $this->salaries->update($id, [
            'status'       => 'pending',
            'confirmed_by' => session('user_id'),
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('success', 'Slip confirmed. It is ready to be paid.');

        return redirect()->to($back);
    }

    /**
     * Step 3: record the actual payment and post it to the books.
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
        if ($slip['status'] === 'draft') {
            session()->setFlashdata('error', 'Confirm this slip before paying it.');

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

    /**
     * Bring in salaries paid before this system existed, from a spreadsheet
     * export of the old records. Landed as 'paid' slips (not draft — they
     * already happened), and only touch the ledger when the box is ticked,
     * since the old books may already carry the expense.
     *
     * Expected columns (any order, matched by header name):
     *   employee_code, month (YYYY-MM), basic_amount, allowances, commission,
     *   deductions, net_amount (optional — computed if blank), currency_code,
     *   paid_on, payment_method, account_code, cheque_number, notes
     */
    public function import()
    {
        $this->requireAuth();

        $file = $this->request->getFile('sheet');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            session()->setFlashdata('error', 'Choose a .xlsx, .xls or .csv file to import.');

            return redirect()->to('/employees/payroll');
        }
        if ($file->getSize() > 10 * 1024 * 1024 || ! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            session()->setFlashdata('error', 'The file must be an Excel (.xlsx/.xls) or CSV file under 10 MB.');

            return redirect()->to('/employees/payroll');
        }

        $postToLedger = (bool) $this->request->getPost('post_to_ledger');
        $userId       = session('user_id');

        try {
            $sheet = IOFactory::load($file->getTempName())->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Could not read that file: ' . $e->getMessage());

            return redirect()->to('/employees/payroll');
        }

        $header = array_map(
            static fn ($h) => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $h)), '_'),
            array_shift($rows) ?? []
        );

        $employees = [];
        foreach ((new EmployeeModel())->select('id, employee_code')->findAll() as $e) {
            if ($e['employee_code']) {
                $employees[strtoupper($e['employee_code'])] = (int) $e['id'];
            }
        }

        $imported = 0;
        $posted   = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $i => $cells) {
            $row = array_combine($header, array_pad($cells, count($header), null));
            if (! array_filter($row, static fn ($v) => trim((string) $v) !== '')) {
                continue; // blank row
            }

            $line = 'Row ' . ($i + 2);
            $code = strtoupper(trim((string) ($row['employee_code'] ?? '')));
            if ($code === '' || ! isset($employees[$code])) {
                $errors[] = "$line: unknown employee_code \"$code\".";
                continue;
            }

            $monthRaw = trim((string) ($row['month'] ?? ''));
            $monthTs  = $monthRaw !== '' ? strtotime(strlen($monthRaw) === 7 ? $monthRaw . '-01' : $monthRaw) : false;
            if (! $monthTs) {
                $errors[] = "$line: invalid or missing month.";
                continue;
            }
            $month = date('Y-m-01', $monthTs);

            $employeeId = $employees[$code];
            if ($this->slipFor($employeeId, $month)) {
                $skipped++;
                continue;
            }

            $basic      = (float) ($row['basic_amount'] ?? 0);
            $allowances = (float) ($row['allowances'] ?? 0);
            $commission = (float) ($row['commission'] ?? 0);
            $deductions = (float) ($row['deductions'] ?? 0);
            $net        = $row['net_amount'] !== null && $row['net_amount'] !== ''
                ? (float) $row['net_amount']
                : round($basic + $allowances + $commission - $deductions, 2);

            $accountId = 0;
            $accountCode = trim((string) ($row['account_code'] ?? ''));
            if ($accountCode !== '') {
                $accountId = $this->accountIdByCode($accountCode);
                if ($accountId <= 0) {
                    $errors[] = "$line: account_code \"$accountCode\" not found — imported without a ledger posting.";
                }
            }

            $method = in_array($row['payment_method'] ?? '', self::METHODS, true) ? $row['payment_method'] : 'cash';
            $paidOn = strtotime((string) ($row['paid_on'] ?? '')) ?: strtotime($month);

            $id = $this->salaries->insert([
                'employee_id'       => $employeeId,
                'period_month'      => $month,
                'basic_amount'      => $basic,
                'allowances'        => $allowances,
                'commission'        => $commission,
                'deductions'        => $deductions,
                'net_amount'        => $net,
                'currency_code'     => strtoupper((string) ($row['currency_code'] ?? '')) ?: base_currency_code(),
                'status'            => 'paid',
                'confirmed_by'      => $userId,
                'confirmed_at'      => date('Y-m-d H:i:s'),
                'paid_on'           => date('Y-m-d', $paidOn),
                'payment_method'    => $method,
                'source_account_id' => $accountId > 0 ? $accountId : null,
                'cheque_number'     => trim((string) ($row['cheque_number'] ?? '')) ?: null,
                'notes'             => trim((string) ($row['notes'] ?? '')) ?: null,
                'is_imported'       => 1,
                'created_by'        => $userId,
            ], true);
            $imported++;

            if ($postToLedger && $accountId > 0) {
                $posting = (new AccountingPostingService())->postSalaryPayment($id);
                if (! empty($posting['success'])) {
                    $posted++;
                } else {
                    $errors[] = "$line: not posted to the ledger — " . ($posting['message'] ?? 'unknown error');
                }
            }
        }

        $message = "$imported slip(s) imported" . ($postToLedger ? ", $posted posted to the ledger" : '')
            . ($skipped > 0 ? ", $skipped skipped (already had a slip for that month)" : '') . '.';
        if ($errors) {
            $message .= ' Issues: ' . implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' …' : '');
        }
        session()->setFlashdata($imported > 0 ? 'success' : 'error', $message);

        return redirect()->to('/employees/payroll');
    }

    private function accountIdByCode(string $code): int
    {
        foreach ($this->payFromAccounts() as $account) {
            if (strcasecmp($account['code'], $code) === 0) {
                return (int) $account['id'];
            }
        }

        return 0;
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
