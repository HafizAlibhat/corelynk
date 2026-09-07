<?php

namespace App\Controllers;

use App\Services\ReportingService;

/**
 * Owner reporting.
 *
 * Ten reports, one renderer. Every action asks ReportingService for a render
 * spec and hands it to the same view, so a new report is one service method
 * plus one catalogue entry — nothing here to keep in sync.
 */
class Reports extends BaseController
{
    /** Report key => service method. Keeps route names off the reflection path. */
    private const REPORTS = [
        'health'      => 'health',
        'receivables' => 'receivables',
        'payables'    => 'payables',
        'sales'       => 'sales',
        'funnel'      => 'funnel',
        'customers'   => 'customers',
        'products'    => 'products',
        'inventory'   => 'inventory',
        'purchasing'  => 'purchasing',
        'fulfilment'  => 'fulfilment',
    ];

    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('reports.read');

        helper('currency');
        $service = $this->service();

        return view('reports/index', $this->setPageData([
            'page_title' => 'Reports',
            'catalogue'  => ReportingService::catalogue(),
            'meta'       => $service->meta(),
            'currencies' => currency_catalog(),
            'headline'   => $service->health(),
        ]));
    }

    public function show(string $key)
    {
        $this->requireAuth();
        $this->requirePermission('reports.read');

        if (! isset(self::REPORTS[$key])) {
            return redirect()->to('/reports');
        }

        helper('currency');
        $service = $this->service();
        $method  = self::REPORTS[$key];

        return view('reports/report', $this->setPageData([
            'page_title' => ReportingService::catalogue()[$key]['title'],
            'report'     => $service->{$method}(),
            'catalogue'  => ReportingService::catalogue(),
            'meta'       => $service->meta(),
            'currencies' => currency_catalog(),
        ]));
    }

    /** CSV of every table in a report, one after another, for spreadsheet work. */
    public function export(string $key)
    {
        $this->requireAuth();
        $this->requirePermission('reports.read');

        if (! isset(self::REPORTS[$key])) {
            return redirect()->to('/reports');
        }

        helper('currency');
        $service = $this->service();
        $report  = $service->{self::REPORTS[$key]}();

        $out = fopen('php://temp', 'r+');
        fputcsv($out, [$report['title']]);
        fputcsv($out, ['Period', $report['range'], 'Currency', $report['currency']]);
        fputcsv($out, []);

        foreach ($report['kpis'] as $kpi) {
            fputcsv($out, [$kpi['label'], $kpi['value'], $kpi['sub']]);
        }

        foreach ($report['sections'] as $section) {
            if (($section['type'] ?? '') !== 'table') {
                continue;
            }
            fputcsv($out, []);
            fputcsv($out, [$section['title']]);
            fputcsv($out, array_column($section['columns'], 'label'));
            foreach ($section['rows'] as $row) {
                $line = [];
                foreach ($section['columns'] as $col) {
                    $value = $row[$col['key']] ?? '';
                    $line[] = ($col['type'] ?? '') === 'bar' ? $value . '%' : $value;
                }
                fputcsv($out, $line);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $key . '-' . date('Y-m-d') . '.csv"')
            ->setBody($csv);
    }

    private function service(): ReportingService
    {
        return new ReportingService(
            $this->request->getGet('from'),
            $this->request->getGet('to'),
            $this->request->getGet('currency')
        );
    }
}
