<?php

namespace App\Controllers;

use App\Models\CompanySettingsModel;
use App\Models\Accounting\CurrencyModel;

class NewPurchaseUI extends BaseController
{
    public function rfqpo()
    {
        try {
            $currencyModel = new CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code','ASC')->findAll();
        } catch (\Throwable $_) {
            $currencies = [];
        }
        $company = null;
        try { $company = (new CompanySettingsModel())->first(); } catch (\Throwable $_) { $company = null; }
        $defaultPurchaseCurrency = $company['default_purchase_currency'] ?? ($company['base_currency'] ?? 'PKR');

        return view('purchase_ui/rfqpo', [
            'currencies' => $currencies,
            'defaultCurrency' => $defaultPurchaseCurrency
        ]);
    }

    public function rfqs()
    {
        return redirect()->to(site_url('newpurchaseui/rfqpo'));
    }

    public function pos()
    {
        return redirect()->to(site_url('newpurchaseui/rfqpo'));
    }

    // Show full page for a single PO (frontend view)
    public function po($id = null)
    {
        // optional: you can validate $id here
        $company = null;
        try { $company = (new CompanySettingsModel())->first(); } catch (\Throwable $_) { $company = null; }
        $defaultPurchaseCurrency = $company['default_purchase_currency'] ?? ($company['base_currency'] ?? 'PKR');
        return view('purchase_ui/po_view', [
            'defaultCurrency' => $defaultPurchaseCurrency,
            'poIdentifier'    => $id,
        ]);
    }

    // RFQ detail view (full page)
    public function rfq($id = null)
    {
        return view('purchase_ui/rfq_view', [
            'rfqIdentifier' => $id,
        ]);
    }

    public function grn()
    {
        return view('purchase_ui/grn');
    }
}
