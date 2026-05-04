<?php

namespace App\Controllers;

use Config\Database;

class AccountingReports extends BaseController
{
    private function dateRange(): array
    {
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        if (!$from) { $from = date('Y-01-01'); }
        if (!$to) { $to = date('Y-m-d'); }
        return [$from, $to];
    }

    public function trialBalance()
    {
        [$from, $to] = $this->dateRange();
        $am = new \App\Models\Accounting\AccountModel();
        $rows = $am->getBalances($from, $to);
        return view('accounting/reports/trial_balance', compact('rows','from','to'));
    }

    public function incomeStatement()
    {
        [$from, $to] = $this->dateRange();
        $am = new \App\Models\Accounting\AccountModel();
        $rows = $am->getBalances($from, $to, ['Revenue','Expense']);
        return view('accounting/reports/income_statement', compact('rows','from','to'));
    }

    public function balanceSheet()
    {
        [$from, $to] = $this->dateRange();
        $am = new \App\Models\Accounting\AccountModel();
        $rows = $am->getBalances(null, $to, ['Asset','Liability','Equity']);
        return view('accounting/reports/balance_sheet', ['rows'=>$rows, 'from'=>$from, 'to'=>$to]);
    }
}
