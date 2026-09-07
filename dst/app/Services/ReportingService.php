<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Owner-facing reporting.
 *
 * Every method returns one render spec that app/Views/reports/report.php draws,
 * so all ten reports share a single layout, a single filter bar and a single
 * export path. The spec is deliberately dumb data: no HTML, no SQL, no money
 * arithmetic left for the view to get wrong.
 *
 * Two rules run through the whole file:
 *
 *  1. Currencies are never silently added together. Sales are billed in USD and
 *     purchases in PKR, so any figure that mixes them is converted explicitly
 *     through rate() and labelled as converted, or it is reported per currency.
 *  2. A number the data cannot support is not invented. Where cost or a rate is
 *     missing the report says how much it could measure instead of quietly
 *     reporting a smaller total as if it were the whole picture.
 */
class ReportingService
{
    private BaseConnection $db;
    private string $base;
    private string $display;
    private string $from;
    private string $to;
    private string $prevFrom;
    private string $prevTo;

    /** Invoice/order states that never represent real business. */
    private const DEAD_STATES  = "'cancelled','void','rejected'";
    private const DRAFT_STATES = "'draft','' ";

    public function __construct(?string $from = null, ?string $to = null, ?string $display = null)
    {
        helper('currency');

        $this->db   = Database::connect();
        $this->base = base_currency_code();

        $this->to   = $this->validDate($to) ?: date('Y-m-d');
        $this->from = $this->validDate($from) ?: date('Y-m-d', strtotime($this->to . ' -11 months first day of this month'));
        if ($this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }

        // The immediately preceding window of equal length, for like-for-like growth.
        $span           = max(1, (int) ((strtotime($this->to) - strtotime($this->from)) / 86400) + 1);
        $this->prevTo   = date('Y-m-d', strtotime($this->from . ' -1 day'));
        $this->prevFrom = date('Y-m-d', strtotime($this->prevTo . ' -' . ($span - 1) . ' days'));

        $display = strtoupper(trim((string) $display));
        $this->display = $display !== '' && isset(currency_catalog()[$display]) ? $display : $this->base;
    }

    // ---------------------------------------------------------------- catalogue

    /**
     * The report directory. Order is the reading order an owner should follow:
     * how the business is doing, then the cash, then where it comes from.
     */
    public static function catalogue(): array
    {
        return [
            'health' => [
                'title' => 'Business Health Scorecard',
                'lede'  => 'One page: what you sold, what you were paid, what you are owed and what you owe.',
                'icon'  => 'bi-heart-pulse',
                'group' => 'Start here',
                'for'   => 'Read this first, every week.',
            ],
            'receivables' => [
                'title' => 'Money Owed To You',
                'lede'  => 'Every unpaid customer invoice, sorted by how late it is.',
                'icon'  => 'bi-cash-coin',
                'group' => 'Cash',
                'for'   => 'Decide who to chase today.',
            ],
            'payables' => [
                'title' => 'Money You Owe',
                'lede'  => 'Unpaid vendor bills and the cash you need in the next 30 days.',
                'icon'  => 'bi-wallet2',
                'group' => 'Cash',
                'for'   => 'Plan payments without surprises.',
            ],
            'sales' => [
                'title' => 'Sales Performance',
                'lede'  => 'What you sold over time, and whether it is growing.',
                'icon'  => 'bi-graph-up-arrow',
                'group' => 'Revenue',
                'for'   => 'Spot the trend before it becomes a problem.',
            ],
            'funnel' => [
                'title' => 'Quote To Cash',
                'lede'  => 'How many quotes become orders, invoices and finally money in the bank.',
                'icon'  => 'bi-funnel',
                'group' => 'Revenue',
                'for'   => 'Find where deals and cash get stuck.',
            ],
            'customers' => [
                'title' => 'Customer Insights',
                'lede'  => 'Your best customers, how dependent you are on them, and who has gone quiet.',
                'icon'  => 'bi-people',
                'group' => 'Revenue',
                'for'   => 'Protect the accounts that pay the bills.',
            ],
            'products' => [
                'title' => 'Product & Margin',
                'lede'  => 'What sells, and what actually makes money after cost.',
                'icon'  => 'bi-box-seam',
                'group' => 'Operations',
                'for'   => 'Push the profitable lines, fix the rest.',
            ],
            'inventory' => [
                'title' => 'Inventory Health',
                'lede'  => 'Stock you are holding, stock you have run out of, and stock that is not moving.',
                'icon'  => 'bi-boxes',
                'group' => 'Operations',
                'for'   => 'Free up cash sitting on the shelf.',
            ],
            'purchasing' => [
                'title' => 'Purchasing & Vendors',
                'lede'  => 'What you spend, who you spend it with, and who delivers on time.',
                'icon'  => 'bi-truck',
                'group' => 'Operations',
                'for'   => 'Negotiate from facts, not memory.',
            ],
            'fulfilment' => [
                'title' => 'Order Fulfilment',
                'lede'  => 'Orders taken but not yet shipped, and how long shipping actually takes.',
                'icon'  => 'bi-box-arrow-up',
                'group' => 'Operations',
                'for'   => 'Keep promises you already made.',
            ],
        ];
    }

    public function meta(): array
    {
        return [
            'from'      => $this->from,
            'to'        => $this->to,
            'prev_from' => $this->prevFrom,
            'prev_to'   => $this->prevTo,
            'display'   => $this->display,
            'base'      => $this->base,
        ];
    }

    // ------------------------------------------------------------------ report 1

    public function health(): array
    {
        $invoiced  = $this->invoicedTotals($this->from, $this->to);
        $invoicedP = $this->invoicedTotals($this->prevFrom, $this->prevTo);
        $collected = $this->collectedTotals($this->from, $this->to);
        $collectedP = $this->collectedTotals($this->prevFrom, $this->prevTo);
        $sold      = $this->soldTotals($this->from, $this->to);
        $soldP     = $this->soldTotals($this->prevFrom, $this->prevTo);
        $paidOut   = $this->vendorPaidTotals($this->from, $this->to);

        $ar = $this->openInvoices();
        $ap = $this->openBills();

        $arTotal      = $this->convertRows($ar, 'outstanding', 'currency_code');
        $arOverdue    = $this->convertRows(array_filter($ar, fn ($r) => $r['days_late'] > 0), 'outstanding', 'currency_code');
        $apTotal      = $this->convertRows($ap, 'outstanding', 'currency_code');
        $apDue30      = $this->convertRows(array_filter($ap, fn ($r) => $r['days_to_due'] <= 30), 'outstanding', 'currency_code');

        $backlog = $this->orderBacklog();

        $kpis = [
            $this->kpi('Sold (orders won)', $this->money($sold['display']), $this->delta($sold['display'], $soldP['display']), 'neutral',
                'Value of sales orders confirmed in this period. This is demand, not cash.'),
            $this->kpi('Invoiced', $this->money($invoiced['display']), $this->delta($invoiced['display'], $invoicedP['display']), 'neutral',
                'Value of invoices you actually issued. This is revenue.'),
            $this->kpi('Cash collected', $this->money($collected['display']), $this->delta($collected['display'], $collectedP['display']), 'good',
                'Money that reached your account in this period.'),
            $this->kpi('Owed to you', $this->money($arTotal), $arOverdue > 0 ? $this->money($arOverdue) . ' already late' : 'Nothing overdue', $arOverdue > 0 ? 'bad' : 'good',
                'Unpaid customer invoices. The single biggest risk in most small businesses.'),
            $this->kpi('You owe', $this->money($apTotal), $this->money($apDue30) . ' due within 30 days', 'warn',
                'Unpaid vendor bills. Compare against what you expect to collect.'),
            $this->kpi('Order book', $this->money($backlog['value']), $backlog['count'] . ' order(s) not yet invoiced', 'neutral',
                'Work already won that has not been billed yet. Future revenue.'),
        ];

        $months = $this->monthlyMovement();

        $callouts = [];
        if ($arOverdue > 0) {
            $late = array_values(array_filter($ar, fn ($r) => $r['days_late'] > 0));
            usort($late, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);
            $worst = $late[0];
            $callouts[] = [
                'tone'  => $worst['days_late'] > 60 ? 'danger' : 'warning',
                'title' => count($late) . ' overdue invoice(s) worth ' . $this->money($arOverdue),
                'text'  => 'The oldest is ' . esc($worst['customer']) . ' — invoice ' . esc($worst['number'])
                    . ' is ' . $worst['days_late'] . ' days past its due date. Chasing this is the cheapest cash you will raise today.',
            ];
        }
        if ($arTotal > 0 && $collected['display'] > 0 && $arTotal > $collected['display'] * 2) {
            $callouts[] = [
                'tone'  => 'warning',
                'title' => 'You are owed far more than you collected',
                'text'  => 'Outstanding invoices are more than twice the cash collected in this period. Either billing is running ahead of collection, or collection needs attention.',
            ];
        }
        if ($apDue30 > $arTotal && $apDue30 > 0) {
            $callouts[] = [
                'tone'  => 'danger',
                'title' => 'Payments due soon exceed what customers owe you',
                'text'  => $this->money($apDue30) . ' of vendor bills fall due within 30 days against ' . $this->money($arTotal)
                    . ' owed to you. Line up the cash before it becomes urgent.',
            ];
        }
        if ($callouts === []) {
            $callouts[] = ['tone' => 'good', 'title' => 'Nothing is on fire', 'text' => 'No overdue invoices and no cash squeeze in the next 30 days for this period.'];
        }

        $rows = [];
        foreach ($months as $m) {
            $rows[] = [
                'period'    => $m['label'],
                'sold'      => $this->money($m['sold']),
                'invoiced'  => $this->money($m['invoiced']),
                'collected' => $this->money($m['collected']),
                'purchased' => $this->money($m['purchased']),
                'net'       => $this->money($m['collected'] - $m['purchased']),
                'net_raw'   => $m['collected'] - $m['purchased'],
            ];
        }

        return $this->spec('health', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Invoiced vs collected, month by month',
                    'note'  => 'The gap between the two bars is money you have earned but not yet been paid.',
                    'chart' => [
                        'kind'   => 'bar',
                        'labels' => array_column($months, 'label'),
                        'datasets' => [
                            ['label' => 'Invoiced', 'data' => array_map(fn ($m) => round($m['invoiced'], 2), $months), 'color' => '#8a72f0'],
                            ['label' => 'Collected', 'data' => array_map(fn ($m) => round($m['collected'], 2), $months), 'color' => '#2dd4bf'],
                        ],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'The same numbers, month by month',
                    'note'    => 'Net cash is what you collected less what you spent with vendors in that month.',
                    'columns' => [
                        ['key' => 'period', 'label' => 'Month'],
                        ['key' => 'sold', 'label' => 'Sold', 'align' => 'right'],
                        ['key' => 'invoiced', 'label' => 'Invoiced', 'align' => 'right'],
                        ['key' => 'collected', 'label' => 'Collected', 'align' => 'right'],
                        ['key' => 'purchased', 'label' => 'Vendor spend', 'align' => 'right'],
                        ['key' => 'net', 'label' => 'Net cash', 'align' => 'right', 'tone' => 'net_raw'],
                    ],
                    'rows'  => $rows,
                    'empty' => 'No activity in this period.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 2

    public function receivables(): array
    {
        $rows = $this->openInvoices();

        $buckets = ['Not due yet' => 0.0, '1-30 days late' => 0.0, '31-60 days late' => 0.0, '61-90 days late' => 0.0, '90+ days late' => 0.0];
        $byCustomer = [];
        $total = 0.0;
        $overdue = 0.0;

        foreach ($rows as $r) {
            $amount = $this->toDisplay((float) $r['outstanding'], $r['currency_code']);
            if ($amount === null) {
                continue;
            }
            $bucket = $this->ageBucket((int) $r['days_late']);
            $buckets[$bucket] += $amount;
            $total += $amount;
            if ($r['days_late'] > 0) {
                $overdue += $amount;
            }

            $cid = (int) $r['customer_id'];
            $byCustomer[$cid] ??= ['customer' => $r['customer'], 'total' => 0.0, 'overdue' => 0.0, 'oldest' => 0, 'count' => 0];
            $byCustomer[$cid]['total']   += $amount;
            $byCustomer[$cid]['overdue'] += $r['days_late'] > 0 ? $amount : 0;
            $byCustomer[$cid]['oldest']   = max($byCustomer[$cid]['oldest'], (int) $r['days_late']);
            $byCustomer[$cid]['count']++;
        }

        usort($rows, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);
        uasort($byCustomer, fn ($a, $b) => $b['total'] <=> $a['total']);

        $dso = $this->daysSalesOutstanding();

        $kpis = [
            $this->kpi('Total owed to you', $this->money($total), count($rows) . ' open invoice(s)', $total > 0 ? 'warn' : 'good',
                'Everything customers have been invoiced for and have not paid.'),
            $this->kpi('Already overdue', $this->money($overdue), $total > 0 ? $this->pct($overdue / max($total, 0.01) * 100) . ' of the total' : '—', $overdue > 0 ? 'bad' : 'good',
                'Past the agreed due date. This is the part to chase.'),
            $this->kpi('Worst delay', $rows ? $rows[0]['days_late'] . ' days' : '0 days', $rows && $rows[0]['days_late'] > 0 ? $rows[0]['customer'] : 'Nothing late', $rows && $rows[0]['days_late'] > 60 ? 'bad' : 'neutral',
                'How long your oldest unpaid invoice has been sitting there.'),
            $this->kpi('Average days to get paid', $dso === null ? 'Not enough data' : round($dso) . ' days', 'Across invoices actually settled', $dso !== null && $dso > 45 ? 'warn' : 'good',
                'From invoice date to payment date. Lower is healthier.'),
        ];

        $callouts = [];
        if ($overdue > 0 && $total > 0 && ($overdue / $total) > 0.5) {
            $callouts[] = ['tone' => 'danger', 'title' => 'More than half of what you are owed is late',
                'text' => 'This is not a timing issue, it is a collection issue. Agree a chase order for the top three names in the table below.'];
        }
        $big = reset($byCustomer);
        if ($big && $total > 0 && ($big['total'] / $total) > 0.5) {
            $callouts[] = ['tone' => 'warning', 'title' => esc($big['customer']) . ' holds most of your outstanding cash',
                'text' => $this->money($big['total']) . ' of ' . $this->money($total) . ' is with one customer. If they pay late, you feel it immediately.'];
        }

        $customerRows = [];
        foreach ($byCustomer as $c) {
            $customerRows[] = [
                'customer'   => $c['customer'],
                'invoices'   => $c['count'],
                'total'      => $this->money($c['total']),
                'total_raw'  => $c['total'],
                'overdue'    => $this->money($c['overdue']),
                'oldest'     => $c['oldest'] > 0 ? $c['oldest'] . ' days late' : 'On time',
                'share'      => $total > 0 ? round($c['total'] / $total * 100, 1) : 0,
            ];
        }

        $invoiceRows = [];
        foreach ($rows as $r) {
            $invoiceRows[] = [
                'number'   => $r['number'],
                'customer' => $r['customer'],
                'issued'   => $this->date($r['issue_date']),
                'due'      => $this->date($r['due_date']),
                'status'   => $r['days_late'] > 0 ? $r['days_late'] . ' days late' : ($r['days_late'] === 0 ? 'Due today' : 'Not due yet'),
                'tone'     => $r['days_late'] > 30 ? 'bad' : ($r['days_late'] > 0 ? 'warn' : 'good'),
                'billed'   => format_money($r['total'], $r['currency_code']),
                'open'     => format_money($r['outstanding'], $r['currency_code']),
                'open_raw' => (float) $r['outstanding'],
            ];
        }

        return $this->spec('receivables', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'How late is the money?',
                    'note'  => 'Anything to the right of the first bar is past its due date.',
                    'chart' => [
                        'kind'     => 'bar',
                        'labels'   => array_keys($buckets),
                        'datasets' => [['label' => 'Outstanding', 'data' => array_map(fn ($v) => round($v, 2), array_values($buckets)),
                            'colors' => ['#2dd4bf', '#facc15', '#fb923c', '#f43f6d', '#dc2626']]],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Who owes you the most',
                    'note'    => 'Start at the top. The share column shows how exposed you are to each name.',
                    'columns' => [
                        ['key' => 'customer', 'label' => 'Customer', 'strong' => true],
                        ['key' => 'invoices', 'label' => 'Invoices', 'align' => 'right'],
                        ['key' => 'total', 'label' => 'Owed', 'align' => 'right'],
                        ['key' => 'overdue', 'label' => 'Of which late', 'align' => 'right'],
                        ['key' => 'oldest', 'label' => 'Oldest'],
                        ['key' => 'share', 'label' => 'Share of total', 'type' => 'bar'],
                    ],
                    'rows'  => $customerRows,
                    'empty' => 'Every invoice has been paid. Enjoy it.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Every open invoice, latest first',
                    'note'    => 'Amounts are shown in the currency each invoice was raised in.',
                    'columns' => [
                        ['key' => 'number', 'label' => 'Invoice', 'strong' => true],
                        ['key' => 'customer', 'label' => 'Customer'],
                        ['key' => 'issued', 'label' => 'Issued'],
                        ['key' => 'due', 'label' => 'Due'],
                        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'billed', 'label' => 'Invoiced', 'align' => 'right'],
                        ['key' => 'open', 'label' => 'Still open', 'align' => 'right'],
                    ],
                    'rows'  => $invoiceRows,
                    'empty' => 'Nothing outstanding.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 3

    public function payables(): array
    {
        $bills = $this->openBills();

        $buckets = ['Not due yet' => 0.0, '1-30 days late' => 0.0, '31-60 days late' => 0.0, '61-90 days late' => 0.0, '90+ days late' => 0.0];
        $byVendor = [];
        $total = $overdue = $due30 = 0.0;

        foreach ($bills as $b) {
            $amount = $this->toDisplay((float) $b['outstanding'], $b['currency_code']);
            if ($amount === null) {
                continue;
            }
            $buckets[$this->ageBucket((int) $b['days_late'])] += $amount;
            $total += $amount;
            if ($b['days_late'] > 0) {
                $overdue += $amount;
            }
            if ($b['days_to_due'] <= 30) {
                $due30 += $amount;
            }

            $vid = (int) $b['vendor_id'];
            $byVendor[$vid] ??= ['vendor' => $b['vendor'], 'total' => 0.0, 'overdue' => 0.0, 'count' => 0, 'oldest' => 0];
            $byVendor[$vid]['total']   += $amount;
            $byVendor[$vid]['overdue'] += $b['days_late'] > 0 ? $amount : 0;
            $byVendor[$vid]['oldest']   = max($byVendor[$vid]['oldest'], (int) $b['days_late']);
            $byVendor[$vid]['count']++;
        }

        usort($bills, fn ($a, $b) => $a['days_to_due'] <=> $b['days_to_due']);
        uasort($byVendor, fn ($a, $b) => $b['total'] <=> $a['total']);

        $ar30 = $this->convertRows(array_filter($this->openInvoices(), fn ($r) => $r['days_to_due'] <= 30), 'outstanding', 'currency_code');

        $kpis = [
            $this->kpi('Total you owe', $this->money($total), count($bills) . ' open bill(s)', 'neutral',
                'Vendor bills received and not yet paid in full.'),
            $this->kpi('Due within 30 days', $this->money($due30), 'Cash you need to find', $due30 > $ar30 ? 'bad' : 'warn',
                'The bills that will actually hit your bank account soon.'),
            $this->kpi('Already overdue', $this->money($overdue), $overdue > 0 ? 'Supplier goodwill at risk' : 'All current', $overdue > 0 ? 'bad' : 'good',
                'Paying late costs you leverage on the next negotiation.'),
            $this->kpi('Expected in from customers', $this->money($ar30), 'Invoices due in the same 30 days', $ar30 >= $due30 ? 'good' : 'bad',
                'Compare this with what you owe. It should be the larger number.'),
        ];

        $callouts = [];
        if ($due30 > $ar30) {
            $callouts[] = ['tone' => 'danger', 'title' => 'Next 30 days: more going out than coming in',
                'text' => 'You owe ' . $this->money($due30) . ' and expect ' . $this->money($ar30)
                    . '. Either accelerate collection, or agree extended terms with a vendor before the date, not after it.'];
        } else {
            $callouts[] = ['tone' => 'good', 'title' => 'Next 30 days are covered',
                'text' => 'Expected collections of ' . $this->money($ar30) . ' cover the ' . $this->money($due30) . ' falling due.'];
        }
        $topVendor = reset($byVendor);
        if ($topVendor && $total > 0 && ($topVendor['total'] / $total) > 0.6) {
            $callouts[] = ['tone' => 'info', 'title' => 'Most of your debt sits with ' . esc($topVendor['vendor']),
                'text' => 'That is leverage in both directions. Worth a conversation about terms rather than a rushed payment.'];
        }

        $vendorRows = [];
        foreach ($byVendor as $v) {
            $vendorRows[] = [
                'vendor'    => $v['vendor'],
                'bills'     => $v['count'],
                'total'     => $this->money($v['total']),
                'total_raw' => $v['total'],
                'overdue'   => $this->money($v['overdue']),
                'oldest'    => $v['oldest'] > 0 ? $v['oldest'] . ' days late' : 'On time',
                'share'     => $total > 0 ? round($v['total'] / $total * 100, 1) : 0,
            ];
        }

        $billRows = [];
        foreach ($bills as $b) {
            $billRows[] = [
                'number'   => $b['number'],
                'vendor'   => $b['vendor'],
                'billed'   => $this->date($b['bill_date']),
                'due'      => $this->date($b['due_date']),
                'status'   => $b['days_late'] > 0 ? $b['days_late'] . ' days late' : 'In ' . max(0, $b['days_to_due']) . ' days',
                'tone'     => $b['days_late'] > 0 ? 'bad' : ($b['days_to_due'] <= 7 ? 'warn' : 'good'),
                'amount'   => format_money($b['total'], $b['currency_code']),
                'open'     => format_money($b['outstanding'], $b['currency_code']),
                'open_raw' => (float) $b['outstanding'],
            ];
        }

        return $this->spec('payables', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'When the bills fall due',
                    'chart' => [
                        'kind'     => 'bar',
                        'labels'   => array_keys($buckets),
                        'datasets' => [['label' => 'Outstanding', 'data' => array_map(fn ($v) => round($v, 2), array_values($buckets)),
                            'colors' => ['#2dd4bf', '#facc15', '#fb923c', '#f43f6d', '#dc2626']]],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Who you owe',
                    'columns' => [
                        ['key' => 'vendor', 'label' => 'Vendor', 'strong' => true],
                        ['key' => 'bills', 'label' => 'Bills', 'align' => 'right'],
                        ['key' => 'total', 'label' => 'Owed', 'align' => 'right'],
                        ['key' => 'overdue', 'label' => 'Of which late', 'align' => 'right'],
                        ['key' => 'oldest', 'label' => 'Oldest'],
                        ['key' => 'share', 'label' => 'Share', 'type' => 'bar'],
                    ],
                    'rows'  => $vendorRows,
                    'empty' => 'No unpaid vendor bills.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Payment run: soonest due first',
                    'note'    => 'Work down this list when you plan the week\'s payments.',
                    'columns' => [
                        ['key' => 'number', 'label' => 'Bill', 'strong' => true],
                        ['key' => 'vendor', 'label' => 'Vendor'],
                        ['key' => 'billed', 'label' => 'Bill date'],
                        ['key' => 'due', 'label' => 'Due'],
                        ['key' => 'status', 'label' => 'When', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'amount', 'label' => 'Bill total', 'align' => 'right'],
                        ['key' => 'open', 'label' => 'Still open', 'align' => 'right'],
                    ],
                    'rows'  => $billRows,
                    'empty' => 'Nothing to pay.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 4

    public function sales(): array
    {
        $months = $this->monthlyMovement();
        $sold   = $this->soldTotals($this->from, $this->to);
        $soldP  = $this->soldTotals($this->prevFrom, $this->prevTo);

        $orders = $this->db->query(
            'SELECT so.id, so.order_number, so.order_date, so.currency_code, so.total, so.status,
                    c.name AS customer
               FROM sales_orders so
               LEFT JOIN customers c ON c.id = so.customer_id
              WHERE so.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              ORDER BY so.order_date DESC',
            [$this->from, $this->to]
        )->getResultArray();

        $count = count($orders);
        $avg   = $count > 0 ? $sold['display'] / $count : 0.0;
        $avgP  = $soldP['count'] > 0 ? $soldP['display'] / $soldP['count'] : 0.0;

        $best = null;
        foreach ($months as $m) {
            if ($best === null || $m['sold'] > $best['sold']) {
                $best = $m;
            }
        }

        $kpis = [
            $this->kpi('Sales in period', $this->money($sold['display']), $this->delta($sold['display'], $soldP['display']), 'good',
                'Total value of orders won between ' . $this->date($this->from) . ' and ' . $this->date($this->to) . '.'),
            $this->kpi('Orders won', (string) $count, $this->delta($count, $soldP['count']), 'neutral',
                'How many separate orders customers placed.'),
            $this->kpi('Average order value', $this->money($avg), $this->delta($avg, $avgP), 'neutral',
                'Sales divided by number of orders. Rising is good: same effort, more money.'),
            $this->kpi('Best month', $best ? $best['label'] : '—', $best ? $this->money($best['sold']) : '', 'neutral',
                'Your strongest month in this window. Worth asking what was different.'),
        ];

        $callouts = [];
        $growth = $soldP['display'] > 0 ? ($sold['display'] - $soldP['display']) / $soldP['display'] * 100 : null;
        if ($growth !== null && $growth < -15) {
            $callouts[] = ['tone' => 'danger', 'title' => 'Sales are down ' . $this->pct(abs($growth)) . ' on the previous period',
                'text' => 'Compare the customer table below with the same period before. A drop usually traces to one or two accounts that stopped ordering, not to the whole market.'];
        } elseif ($growth !== null && $growth > 15) {
            $callouts[] = ['tone' => 'good', 'title' => 'Sales are up ' . $this->pct($growth) . ' on the previous period',
                'text' => 'Check the cash reports too: growth that is invoiced but not collected consumes cash rather than producing it.'];
        }

        $byCustomer = $this->salesByCustomer();
        $byProduct  = $this->salesByProduct();

        $custRows = [];
        $custTotal = array_sum(array_column($byCustomer, 'value'));
        foreach (array_slice($byCustomer, 0, 25) as $c) {
            $custRows[] = [
                'customer'  => $c['customer'],
                'orders'    => $c['orders'],
                'value'     => $this->money($c['value']),
                'value_raw' => $c['value'],
                'avg'       => $this->money($c['orders'] > 0 ? $c['value'] / $c['orders'] : 0),
                'last'      => $this->date($c['last_order']),
                'share'     => $custTotal > 0 ? round($c['value'] / $custTotal * 100, 1) : 0,
            ];
        }

        $prodRows = [];
        $prodTotal = array_sum(array_column($byProduct, 'value'));
        foreach (array_slice($byProduct, 0, 25) as $p) {
            $prodRows[] = [
                'product'   => $p['product'],
                'code'      => $p['code'],
                'qty'       => $this->num($p['qty']),
                'value'     => $this->money($p['value']),
                'value_raw' => $p['value'],
                'orders'    => $p['orders'],
                'share'     => $prodTotal > 0 ? round($p['value'] / $prodTotal * 100, 1) : 0,
            ];
        }

        $monthRows = [];
        foreach ($months as $m) {
            $monthRows[] = [
                'period'   => $m['label'],
                'orders'   => $m['orders'],
                'sold'     => $this->money($m['sold']),
                'sold_raw' => $m['sold'],
                'avg'      => $this->money($m['orders'] > 0 ? $m['sold'] / $m['orders'] : 0),
            ];
        }

        return $this->spec('sales', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Sales by month',
                    'note'  => 'A steady line matters more than a tall one. Spikes you cannot repeat are not a trend.',
                    'chart' => [
                        'kind'     => 'line',
                        'labels'   => array_column($months, 'label'),
                        'datasets' => [['label' => 'Sales', 'data' => array_map(fn ($m) => round($m['sold'], 2), $months), 'color' => '#8a72f0']],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Month by month',
                    'columns' => [
                        ['key' => 'period', 'label' => 'Month'],
                        ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
                        ['key' => 'sold', 'label' => 'Sales', 'align' => 'right'],
                        ['key' => 'avg', 'label' => 'Average order', 'align' => 'right'],
                    ],
                    'rows'  => $monthRows,
                    'empty' => 'No orders in this period.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Who bought the most',
                    'columns' => [
                        ['key' => 'customer', 'label' => 'Customer', 'strong' => true],
                        ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Sales', 'align' => 'right'],
                        ['key' => 'avg', 'label' => 'Average order', 'align' => 'right'],
                        ['key' => 'last', 'label' => 'Last order'],
                        ['key' => 'share', 'label' => 'Share', 'type' => 'bar'],
                    ],
                    'rows'  => $custRows,
                    'empty' => 'No customer orders in this period.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'What sold the most',
                    'columns' => [
                        ['key' => 'product', 'label' => 'Product', 'strong' => true],
                        ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
                        ['key' => 'qty', 'label' => 'Units', 'align' => 'right'],
                        ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Sales', 'align' => 'right'],
                        ['key' => 'share', 'label' => 'Share', 'type' => 'bar'],
                    ],
                    'rows'  => $prodRows,
                    'empty' => 'No product lines in this period.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 5

    public function funnel(): array
    {
        $q = $this->db->query(
            'SELECT q.id, q.quote_number, q.issue_date, q.valid_until, q.status, q.total,
                    COALESCE(NULLIF(q.quote_currency, ""), NULLIF(q.base_currency, ""), ?) AS currency_code,
                    q.converted_to_sales_order_id AS so_id, c.name AS customer,
                    DATE(COALESCE(NULLIF(q.issue_date, "0000-00-00"), q.created_at)) AS dated
               FROM quotations q
               LEFT JOIN customers c ON c.id = q.customer_id
              WHERE q.deleted_at IS NULL
                AND DATE(COALESCE(NULLIF(q.issue_date, "0000-00-00"), q.created_at)) BETWEEN ? AND ?',
            [$this->base, $this->from, $this->to]
        )->getResultArray();

        $quoteValue = $quoteCount = 0;
        $wonValue   = $wonCount = 0;
        $open       = [];

        foreach ($q as $row) {
            $amount = $this->toDisplay((float) $row['total'], $row['currency_code']) ?? 0.0;
            $quoteValue += $amount;
            $quoteCount++;
            if (!empty($row['so_id'])) {
                $wonValue += $amount;
                $wonCount++;
            } elseif (!in_array(strtolower((string) $row['status']), ['cancelled', 'rejected', 'lost'], true)) {
                $row['amount'] = $amount;
                $open[] = $row;
            }
        }

        $invoiced  = $this->invoicedTotals($this->from, $this->to);
        $collected = $this->collectedTotals($this->from, $this->to);

        $winRate = $quoteCount > 0 ? $wonCount / $quoteCount * 100 : 0;
        $lag     = $this->stageLags();

        $kpis = [
            $this->kpi('Quotes sent', $this->num($quoteCount) . '', $this->money($quoteValue), 'neutral',
                'Opportunities you put in front of a customer in this period.'),
            $this->kpi('Win rate', $this->pct($winRate), $wonCount . ' became orders', $winRate < 30 ? 'warn' : 'good',
                'Share of quotes that turned into a real order. Below 30% usually means pricing or follow-up.'),
            $this->kpi('Still open', $this->money(array_sum(array_column($open, 'amount'))), count($open) . ' quote(s) waiting', count($open) > 0 ? 'warn' : 'neutral',
                'Quoted but neither won nor lost. This is the cheapest revenue in the business — it just needs chasing.'),
            $this->kpi('Quote to cash time', $lag['quote_to_cash'] === null ? 'Not enough data' : round($lag['quote_to_cash']) . ' days', 'Average, end to end', 'neutral',
                'From the day you quote to the day the money lands. Every day here is a day your cash is tied up.'),
        ];

        $stages = [
            ['stage' => 'Quoted', 'count' => $quoteCount, 'value' => $quoteValue],
            ['stage' => 'Became an order', 'count' => $wonCount, 'value' => $wonValue],
            ['stage' => 'Invoiced', 'count' => $invoiced['count'], 'value' => $invoiced['display']],
            ['stage' => 'Paid', 'count' => $collected['count'], 'value' => $collected['display']],
        ];

        $stageRows = [];
        $first = $stages[0]['value'] ?: 1;
        $prev = null;
        foreach ($stages as $s) {
            $stageRows[] = [
                'stage'     => $s['stage'],
                'count'     => $s['count'],
                'value'     => $this->money($s['value']),
                'value_raw' => $s['value'],
                'step'      => $prev === null ? '—' : ($prev > 0 ? $this->pct($s['value'] / $prev * 100) : '—'),
                'share'     => round($s['value'] / $first * 100, 1),
            ];
            $prev = $s['value'];
        }

        usort($open, fn ($a, $b) => strcmp((string) $a['dated'], (string) $b['dated']));
        $openRows = [];
        foreach ($open as $o) {
            $age = $this->daysBetween($o['dated'], date('Y-m-d'));
            $expired = !empty($o['valid_until']) && $o['valid_until'] !== '0000-00-00' && $o['valid_until'] < date('Y-m-d');
            $openRows[] = [
                'number'   => $o['quote_number'],
                'customer' => $o['customer'] ?: 'Unnamed customer',
                'dated'    => $this->date($o['dated']),
                'age'      => $age . ' days',
                'valid'    => $expired ? 'Expired' : ($o['valid_until'] && $o['valid_until'] !== '0000-00-00' ? $this->date($o['valid_until']) : 'No expiry set'),
                'tone'     => $expired ? 'bad' : ($age > 30 ? 'warn' : 'good'),
                'value'    => format_money($o['total'], $o['currency_code']),
                'value_raw' => (float) $o['total'],
            ];
        }

        $callouts = [];
        $stale = array_filter($open, fn ($o) => $this->daysBetween($o['dated'], date('Y-m-d')) > 30);
        if ($stale) {
            $callouts[] = ['tone' => 'warning', 'title' => count($stale) . ' quote(s) have been open more than 30 days',
                'text' => 'Worth ' . $this->money(array_sum(array_column($stale, 'amount')))
                    . '. A quote nobody has chased in a month is usually a no. Ask for a decision and free up the follow-up time.'];
        }
        if ($winRate > 0 && $winRate < 30) {
            $callouts[] = ['tone' => 'info', 'title' => 'Fewer than a third of quotes convert',
                'text' => 'Either the price is wrong or the follow-up is. Both are fixable, but they need different answers, so ask the last five lost customers which it was.'];
        }

        return $this->spec('funnel', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Value at each stage',
                    'note'  => 'Each bar should be shorter than the one before. A big drop shows you exactly where value leaks.',
                    'chart' => [
                        'kind'     => 'bar',
                        'labels'   => array_column($stages, 'stage'),
                        'datasets' => [['label' => 'Value', 'data' => array_map(fn ($s) => round($s['value'], 2), $stages),
                            'colors' => ['#8a72f0', '#6366f1', '#2dd4bf', '#22c55e']]],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Where deals stop',
                    'note'    => 'The step column shows how much value survives from the stage above.',
                    'columns' => [
                        ['key' => 'stage', 'label' => 'Stage', 'strong' => true],
                        ['key' => 'count', 'label' => 'Documents', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                        ['key' => 'step', 'label' => 'Survives from previous', 'align' => 'right'],
                        ['key' => 'share', 'label' => 'Of quoted value', 'type' => 'bar'],
                    ],
                    'rows'  => $stageRows,
                    'empty' => 'No quotes in this period.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Quotes waiting for an answer — oldest first',
                    'note'    => 'Your follow-up list. Anything marked Expired needs re-quoting, not chasing.',
                    'columns' => [
                        ['key' => 'number', 'label' => 'Quote', 'strong' => true],
                        ['key' => 'customer', 'label' => 'Customer'],
                        ['key' => 'dated', 'label' => 'Sent'],
                        ['key' => 'age', 'label' => 'Waiting', 'align' => 'right'],
                        ['key' => 'valid', 'label' => 'Valid until', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                    ],
                    'rows'  => $openRows,
                    'empty' => 'No open quotes — everything has been won or closed.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 6

    public function customers(): array
    {
        $byCustomer = $this->salesByCustomer();
        $total = array_sum(array_column($byCustomer, 'value'));

        $newCustomers = (int) ($this->db->query(
            'SELECT COUNT(*) n FROM customers WHERE DATE(created_at) BETWEEN ? AND ?',
            [$this->from, $this->to]
        )->getRow()->n ?? 0);

        $activeCount = count($byCustomer);
        $topShare    = $total > 0 && $byCustomer ? $byCustomer[0]['value'] / $total * 100 : 0;
        $top3Share   = $total > 0 ? array_sum(array_column(array_slice($byCustomer, 0, 3), 'value')) / $total * 100 : 0;

        $repeat = count(array_filter($byCustomer, fn ($c) => $c['orders'] > 1));
        $repeatRate = $activeCount > 0 ? $repeat / $activeCount * 100 : 0;

        $kpis = [
            $this->kpi('Customers who bought', (string) $activeCount, 'In this period', 'neutral',
                'Names that placed at least one order. Everyone else on your list is a prospect, not a customer.'),
            $this->kpi('New customers added', (string) $newCustomers, 'Records created in this period', 'good',
                'New relationships started. Growth needs this number to stay above zero.'),
            $this->kpi('Repeat rate', $this->pct($repeatRate), $repeat . ' bought more than once', $repeatRate < 30 ? 'warn' : 'good',
                'Repeat customers cost nothing to win. A low rate means you are refilling a leaking bucket.'),
            $this->kpi('Top customer share', $this->pct($topShare), $byCustomer ? $byCustomer[0]['customer'] : '—', $topShare > 40 ? 'bad' : 'good',
                'How much of your revenue depends on one name. Above 40% is a real risk to the business.'),
        ];

        $callouts = [];
        if ($topShare > 40) {
            $callouts[] = ['tone' => 'danger', 'title' => 'You are dependent on one customer',
                'text' => esc($byCustomer[0]['customer']) . ' is ' . $this->pct($topShare)
                    . ' of your sales in this period. If they leave or pay late, the business feels it immediately. Winning two mid-sized accounts is worth more than growing this one.'];
        }
        if ($top3Share > 70 && $activeCount > 3) {
            $callouts[] = ['tone' => 'warning', 'title' => 'Three customers make up ' . $this->pct($top3Share) . ' of sales',
                'text' => 'Concentration like this is normal early on, but it should fall as you grow. Track this number every quarter.'];
        }

        $dormant = $this->dormantCustomers();
        if ($dormant) {
            $callouts[] = ['tone' => 'info', 'title' => count($dormant) . ' customer(s) have gone quiet',
                'text' => 'They bought before but nothing in the last 90 days. Re-ordering from a past customer is the cheapest sale available to you.'];
        }

        $rows = [];
        foreach ($byCustomer as $c) {
            $rows[] = [
                'customer'  => $c['customer'],
                'orders'    => $c['orders'],
                'value'     => $this->money($c['value']),
                'value_raw' => $c['value'],
                'avg'       => $this->money($c['orders'] > 0 ? $c['value'] / $c['orders'] : 0),
                'last'      => $this->date($c['last_order']),
                'gap'       => $this->daysBetween($c['last_order'], date('Y-m-d')) . ' days ago',
                'share'     => $total > 0 ? round($c['value'] / $total * 100, 1) : 0,
            ];
        }

        $dormantRows = [];
        foreach ($dormant as $d) {
            $dormantRows[] = [
                'customer' => $d['customer'],
                'last'     => $this->date($d['last_order']),
                'gap'      => $d['days'] . ' days',
                'orders'   => $d['orders'],
                'value'    => $this->money($d['value']),
                'value_raw' => $d['value'],
                'tone'     => $d['days'] > 180 ? 'bad' : 'warn',
            ];
        }

        return $this->spec('customers', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Where your revenue comes from',
                    'note'  => 'Top ten customers in this period. A single dominant slice is a concentration risk.',
                    'chart' => [
                        'kind'     => 'doughnut',
                        'labels'   => array_column(array_slice($byCustomer, 0, 10), 'customer'),
                        'datasets' => [['label' => 'Sales', 'data' => array_map(fn ($c) => round($c['value'], 2), array_slice($byCustomer, 0, 10))]],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Every customer who bought, best first',
                    'columns' => [
                        ['key' => 'customer', 'label' => 'Customer', 'strong' => true],
                        ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Sales', 'align' => 'right'],
                        ['key' => 'avg', 'label' => 'Average order', 'align' => 'right'],
                        ['key' => 'last', 'label' => 'Last order'],
                        ['key' => 'gap', 'label' => 'How long ago'],
                        ['key' => 'share', 'label' => 'Share', 'type' => 'bar'],
                    ],
                    'rows'  => $rows,
                    'empty' => 'Nobody ordered in this period.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Customers who have gone quiet',
                    'note'    => 'Bought from you before, nothing in the last 90 days. One phone call each.',
                    'columns' => [
                        ['key' => 'customer', 'label' => 'Customer', 'strong' => true],
                        ['key' => 'last', 'label' => 'Last order'],
                        ['key' => 'gap', 'label' => 'Silent for', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'orders', 'label' => 'Orders ever', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Spent with you', 'align' => 'right'],
                    ],
                    'rows'  => $dormantRows,
                    'empty' => 'Every past customer has ordered recently. Rare and excellent.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 7

    public function products(): array
    {
        $lines = $this->db->query(
            'SELECT sol.product_id, sol.product_variant_id,
                    COALESCE(NULLIF(TRIM(CONVERT(p.name USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(sol.description USING utf8mb4)), ""), "Unnamed line") AS product,
                    COALESCE(NULLIF(TRIM(CONVERT(pv.art_number USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(p.code USING utf8mb4)), ""), "") AS code,
                    so.currency_code, SUM(sol.quantity) AS qty, SUM(sol.line_total) AS value,
                    COUNT(DISTINCT so.id) AS orders,
                    MAX(COALESCE(NULLIF(pv.cost, 0), NULLIF(p.cost_price, 0), 0)) AS unit_cost,
                    MAX(COALESCE(NULLIF(p.cost_currency, ""), "")) AS cost_currency
               FROM sales_order_lines sol
               INNER JOIN sales_orders so ON so.id = sol.sales_order_id
               LEFT JOIN products p ON p.id = sol.product_id
               LEFT JOIN product_variants pv ON pv.id = sol.product_variant_id
              WHERE so.deleted_at IS NULL AND sol.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              GROUP BY 1, 2, 3, 4, 5
              ORDER BY value DESC',
            [$this->from, $this->to]
        )->getResultArray();

        // Real purchase cost beats a catalogue field: GRN unit costs are what you
        // actually paid. Fall back to the catalogue only where no receipt exists.
        $grnCost = [];
        foreach ($this->db->query(
            'SELECT gl.product_id, AVG(gl.unit_cost) AS cost
               FROM purchase_grn_lines gl
              WHERE gl.unit_cost > 0 AND gl.product_id IS NOT NULL
              GROUP BY gl.product_id'
        )->getResultArray() as $g) {
            $grnCost[(int) $g['product_id']] = (float) $g['cost'];
        }

        $rows = [];
        $revenue = $costed = $costKnownRevenue = $margin = 0.0;
        $missing = [];

        foreach ($lines as $l) {
            $value = $this->toDisplay((float) $l['value'], $l['currency_code']);
            if ($value === null) {
                continue;
            }
            $revenue += $value;

            $pid      = (int) $l['product_id'];
            $unitCost = (float) $l['unit_cost'];
            $costCur  = $l['cost_currency'] ?: $this->base;
            $source   = 'Catalogue';
            if (isset($grnCost[$pid]) && $grnCost[$pid] > 0) {
                $unitCost = $grnCost[$pid];
                $costCur  = $this->base;   // GRN costs are recorded in the purchase currency (base)
                $source   = 'Actual receipts';
            }

            $lineCost = $unitCost > 0 ? $this->toDisplay($unitCost * (float) $l['qty'], $costCur) : null;
            $lineMargin = $lineCost === null ? null : $value - $lineCost;

            if ($lineMargin !== null) {
                $costed += $lineCost;
                $costKnownRevenue += $value;
                $margin += $lineMargin;
            } else {
                $missing[$l['product'] . '|' . $l['code']] = true;
            }

            $rows[] = [
                'product'    => $l['product'],
                'code'       => $l['code'],
                'qty'        => $this->num($l['qty']),
                'orders'     => (int) $l['orders'],
                'value'      => $this->money($value),
                'value_raw'  => $value,
                'cost'       => $lineCost === null ? 'Not set' : $this->money($lineCost),
                'margin'     => $lineMargin === null ? '—' : $this->money($lineMargin),
                'margin_raw' => $lineMargin ?? 0,
                'pct'        => $lineMargin === null ? '—' : $this->pct($value > 0 ? $lineMargin / $value * 100 : 0),
                'source'     => $lineCost === null ? 'No cost recorded' : $source,
                'tone'       => $lineMargin === null ? 'neutral' : ($lineMargin <= 0 ? 'bad' : ($value > 0 && $lineMargin / $value < 0.15 ? 'warn' : 'good')),
            ];
        }

        $coverage = $revenue > 0 ? $costKnownRevenue / $revenue * 100 : 0;
        $marginPct = $costKnownRevenue > 0 ? $margin / $costKnownRevenue * 100 : 0;

        $kpis = [
            $this->kpi('Sales value', $this->money($revenue), count($rows) . ' product line(s)', 'neutral',
                'Everything sold in this period, by product.'),
            $this->kpi('Gross margin', $costKnownRevenue > 0 ? $this->money($margin) : 'Cannot calculate', $costKnownRevenue > 0 ? $this->pct($marginPct) . ' on measurable sales' : 'No product costs recorded', $marginPct < 15 && $costKnownRevenue > 0 ? 'bad' : 'good',
                'Sales less what the goods cost you. This is the money the business actually runs on.'),
            $this->kpi('Margin coverage', $this->pct($coverage), $this->money($costKnownRevenue) . ' of ' . $this->money($revenue) . ' has a cost', $coverage < 70 ? 'warn' : 'good',
                'How much of your sales the margin figure is based on. Below 70% and the margin above is a sample, not the answer.'),
            $this->kpi('Products missing cost', (string) count($missing), 'Fix these to see true profit', count($missing) > 0 ? 'warn' : 'good',
                'Each one is a product you cannot tell whether you make money on.'),
        ];

        $callouts = [];
        if ($coverage < 70) {
            $callouts[] = ['tone' => 'warning', 'title' => 'Margin is based on ' . $this->pct($coverage) . ' of your sales',
                'text' => 'The rest have no purchase cost recorded, so they are excluded rather than counted as free. Add cost prices, or receive the goods through a GRN, and this report becomes the most valuable page in the system.'];
        }
        $lossMakers = array_filter($rows, fn ($r) => $r['tone'] === 'bad');
        if ($lossMakers) {
            $callouts[] = ['tone' => 'danger', 'title' => count($lossMakers) . ' line(s) sold at or below cost',
                'text' => 'Every unit of these made the business poorer. Check the price list before the next quote goes out.'];
        }
        $thin = array_filter($rows, fn ($r) => $r['tone'] === 'warn');
        if ($thin) {
            $callouts[] = ['tone' => 'info', 'title' => count($thin) . ' line(s) earn less than 15% margin',
                'text' => 'Thin margins survive only at volume. If these are also slow movers, they are costing you shelf space and attention.'];
        }

        return $this->spec('products', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Top products by sales value',
                    'chart' => [
                        'kind'     => 'bar',
                        'horizontal' => true,
                        'labels'   => array_map(fn ($r) => $r['product'], array_slice($rows, 0, 10)),
                        'datasets' => [['label' => 'Sales', 'data' => array_map(fn ($r) => round($r['value_raw'], 2), array_slice($rows, 0, 10)), 'color' => '#8a72f0']],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Product profitability',
                    'note'    => 'Cost source tells you how much to trust each row: actual receipts beat a catalogue figure.',
                    'columns' => [
                        ['key' => 'product', 'label' => 'Product', 'strong' => true],
                        ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
                        ['key' => 'qty', 'label' => 'Units', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Sales', 'align' => 'right'],
                        ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
                        ['key' => 'margin', 'label' => 'Margin', 'align' => 'right', 'tone' => 'margin_raw'],
                        ['key' => 'pct', 'label' => 'Margin %', 'align' => 'right', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'source', 'label' => 'Cost source'],
                    ],
                    'rows'  => $rows,
                    'empty' => 'Nothing sold in this period.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 8

    public function inventory(): array
    {
        $stock = $this->db->query(
            'SELECT sb.product_id, sb.variant_id, SUM(sb.quantity) AS qty,
                    COALESCE(NULLIF(TRIM(CONVERT(p.name USING utf8mb4)), ""), "Unnamed product") AS product,
                    COALESCE(NULLIF(TRIM(CONVERT(pv.art_number USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(p.code USING utf8mb4)), ""), "") AS code,
                    MAX(COALESCE(NULLIF(pv.cost, 0), NULLIF(p.cost_price, 0), 0)) AS unit_cost
               FROM stock_balances sb
               LEFT JOIN products p ON p.id = sb.product_id
               LEFT JOIN product_variants pv ON pv.id = sb.variant_id
              GROUP BY 1, 2, 4, 5
             HAVING qty <> 0'
        )->getResultArray();

        $grnCost = [];
        foreach ($this->db->query(
            'SELECT product_id, AVG(unit_cost) AS cost FROM purchase_grn_lines WHERE unit_cost > 0 AND product_id IS NOT NULL GROUP BY product_id'
        )->getResultArray() as $g) {
            $grnCost[(int) $g['product_id']] = (float) $g['cost'];
        }

        $lastMove = [];
        foreach ($this->db->query(
            'SELECT product_id, MAX(created_at) AS last_move FROM stock_movements WHERE product_id IS NOT NULL GROUP BY product_id'
        )->getResultArray() as $m) {
            $lastMove[(int) $m['product_id']] = $m['last_move'];
        }

        $soldQty = [];
        foreach ($this->db->query(
            'SELECT sol.product_id, SUM(sol.quantity) AS qty
               FROM sales_order_lines sol
               INNER JOIN sales_orders so ON so.id = sol.sales_order_id
              WHERE so.deleted_at IS NULL AND sol.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              GROUP BY sol.product_id',
            [$this->from, $this->to]
        )->getResultArray() as $s) {
            $soldQty[(int) $s['product_id']] = (float) $s['qty'];
        }

        $days = max(1, $this->daysBetween($this->from, $this->to));
        $rows = [];
        $totalValue = 0.0;
        $valued = 0;
        $deadValue = 0.0;
        $deadRows = [];

        foreach ($stock as $s) {
            $pid  = (int) $s['product_id'];
            $cost = $grnCost[$pid] ?? (float) $s['unit_cost'];
            $qty  = (float) $s['qty'];
            $value = $cost > 0 ? $cost * $qty : null;
            if ($value !== null) {
                $totalValue += $value;
                $valued++;
            }

            $moved   = $lastMove[$pid] ?? null;
            $idleDays = $moved ? $this->daysBetween(substr($moved, 0, 10), date('Y-m-d')) : null;
            $rate    = ($soldQty[$pid] ?? 0) / $days;
            $cover   = $rate > 0 ? $qty / $rate : null;

            $row = [
                'product'   => $s['product'],
                'code'      => $s['code'],
                'qty'       => $this->num($qty),
                'qty_raw'   => $qty,
                'value'     => $value === null ? 'Cost not set' : $this->money($value),
                'value_raw' => $value ?? 0,
                'idle'      => $idleDays === null ? 'Never moved' : $idleDays . ' days ago',
                'cover'     => $cover === null ? 'Not selling' : round($cover) . ' days',
                'tone'      => $idleDays === null || $idleDays > 90 ? 'bad' : ($idleDays > 45 ? 'warn' : 'good'),
            ];
            $rows[] = $row;

            if ($idleDays === null || $idleDays > 90) {
                $deadValue += $value ?? 0;
                $deadRows[] = $row;
            }
        }

        usort($rows, fn ($a, $b) => $b['value_raw'] <=> $a['value_raw']);
        usort($deadRows, fn ($a, $b) => $b['value_raw'] <=> $a['value_raw']);

        // Sold in the period but nothing on the shelf: the expensive kind of empty.
        $stockedIds = array_column($stock, 'product_id');
        $outRows = [];
        foreach ($soldQty as $pid => $qty) {
            if (in_array((string) $pid, array_map('strval', $stockedIds), true)) {
                continue;
            }
            $p = $this->db->query('SELECT name, code FROM products WHERE id = ?', [$pid])->getRowArray();
            if (!$p) {
                continue;
            }
            $outRows[] = ['product' => $p['name'], 'code' => $p['code'] ?: '', 'qty' => $this->num($qty), 'qty_raw' => $qty];
        }
        usort($outRows, fn ($a, $b) => $b['qty_raw'] <=> $a['qty_raw']);

        $kpis = [
            $this->kpi('Stock on hand', $this->money($totalValue), $valued . ' of ' . count($stock) . ' item(s) have a cost', 'neutral',
                'Cash sitting on your shelves. Stock is money you have already spent and not yet earned back.'),
            $this->kpi('Items in stock', (string) count($stock), 'Distinct products and variants', 'neutral',
                'How many different things you are holding.'),
            $this->kpi('Not moving', $this->money($deadValue), count($deadRows) . ' item(s) idle 90+ days', $deadValue > 0 ? 'bad' : 'good',
                'Stock with no movement for three months. This is the first place to look for trapped cash.'),
            $this->kpi('Sold with no stock', (string) count($outRows), 'Products you sold but do not hold', count($outRows) > 0 ? 'warn' : 'good',
                'Either you buy to order, or you are running out. Only one of those is a plan.'),
        ];

        $callouts = [];
        if ($deadValue > 0 && $totalValue > 0 && $deadValue / $totalValue > 0.3) {
            $callouts[] = ['tone' => 'danger', 'title' => $this->pct($deadValue / $totalValue * 100) . ' of your stock value has not moved in 90 days',
                'text' => 'That is ' . $this->money($deadValue) . ' of cash you cannot spend. Discount it, bundle it, or return it — holding it costs you every month.'];
        }
        if ($valued < count($stock)) {
            $callouts[] = ['tone' => 'info', 'title' => (count($stock) - $valued) . ' stocked item(s) have no cost price',
                'text' => 'They are counted in quantity but excluded from the value above, so your real stock value is higher than shown.'];
        }

        return $this->spec('inventory', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Where your stock money is tied up',
                    'chart' => [
                        'kind'       => 'bar',
                        'horizontal' => true,
                        'labels'     => array_map(fn ($r) => $r['product'], array_slice($rows, 0, 10)),
                        'datasets'   => [['label' => 'Stock value', 'data' => array_map(fn ($r) => round($r['value_raw'], 2), array_slice($rows, 0, 10)), 'color' => '#2dd4bf']],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Stock that is not moving',
                    'note'    => 'No movement in 90 days or more. Deal with the top of this list first.',
                    'columns' => [
                        ['key' => 'product', 'label' => 'Product', 'strong' => true],
                        ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
                        ['key' => 'qty', 'label' => 'On hand', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                        ['key' => 'idle', 'label' => 'Last movement', 'type' => 'badge', 'tone' => 'tone'],
                    ],
                    'rows'  => $deadRows,
                    'empty' => 'Everything on the shelf has moved recently.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Sold in this period but not held in stock',
                    'columns' => [
                        ['key' => 'product', 'label' => 'Product', 'strong' => true],
                        ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
                        ['key' => 'qty', 'label' => 'Units sold', 'align' => 'right'],
                    ],
                    'rows'  => $outRows,
                    'empty' => 'Everything you sold, you also stock.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'All stock by value',
                    'columns' => [
                        ['key' => 'product', 'label' => 'Product', 'strong' => true],
                        ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
                        ['key' => 'qty', 'label' => 'On hand', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                        ['key' => 'cover', 'label' => 'Covers demand for', 'align' => 'right'],
                        ['key' => 'idle', 'label' => 'Last movement'],
                    ],
                    'rows'  => $rows,
                    'empty' => 'No stock recorded.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 9

    public function purchasing(): array
    {
        $pos = $this->db->query(
            'SELECT po.id, po.po_number, po.status, po.currency_code, po.total, po.vendor_id,
                    DATE(COALESCE(NULLIF(po.order_date, "0000-00-00"), po.created_at)) AS ordered,
                    NULLIF(po.delivery_date, "0000-00-00") AS promised,
                    v.name AS vendor,
                    (SELECT MIN(g.received_at) FROM purchase_grns g WHERE g.po_id = po.id) AS received
               FROM purchase_orders po
               LEFT JOIN vendors v ON v.id = po.vendor_id
              WHERE po.deleted_at IS NULL
                AND LOWER(COALESCE(po.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(po.order_date, "0000-00-00"), po.created_at)) BETWEEN ? AND ?
              ORDER BY ordered DESC',
            [$this->from, $this->to]
        )->getResultArray();

        $byVendor = [];
        $spend = 0.0;
        $leadTimes = [];
        $onTime = $late = 0;
        $openRows = [];

        foreach ($pos as $po) {
            $amount = $this->toDisplay((float) $po['total'], $po['currency_code']) ?? 0.0;
            $spend += $amount;

            $vid = (int) $po['vendor_id'];
            $byVendor[$vid] ??= ['vendor' => $po['vendor'] ?: 'Unknown vendor', 'spend' => 0.0, 'orders' => 0, 'lead' => [], 'late' => 0];
            $byVendor[$vid]['spend'] += $amount;
            $byVendor[$vid]['orders']++;

            if (!empty($po['received'])) {
                $lead = $this->daysBetween($po['ordered'], substr($po['received'], 0, 10));
                $leadTimes[] = $lead;
                $byVendor[$vid]['lead'][] = $lead;
                if (!empty($po['promised']) && substr($po['received'], 0, 10) > $po['promised']) {
                    $late++;
                    $byVendor[$vid]['late']++;
                } else {
                    $onTime++;
                }
            } else {
                $waiting = $this->daysBetween($po['ordered'], date('Y-m-d'));
                $openRows[] = [
                    'number'    => $po['po_number'],
                    'vendor'    => $po['vendor'] ?: 'Unknown vendor',
                    'ordered'   => $this->date($po['ordered']),
                    'waiting'   => $waiting . ' days',
                    'promised'  => $po['promised'] ? $this->date($po['promised']) : 'No date agreed',
                    'value'     => format_money($po['total'], $po['currency_code']),
                    'value_raw' => (float) $po['total'],
                    'tone'      => $waiting > 45 ? 'bad' : ($waiting > 21 ? 'warn' : 'good'),
                ];
            }
        }

        uasort($byVendor, fn ($a, $b) => $b['spend'] <=> $a['spend']);
        $avgLead = $leadTimes ? array_sum($leadTimes) / count($leadTimes) : null;
        $received = $onTime + $late;
        $onTimePct = $received > 0 ? $onTime / $received * 100 : null;

        $kpis = [
            $this->kpi('Purchase spend', $this->money($spend), count($pos) . ' purchase order(s)', 'neutral',
                'What you committed to vendors in this period.'),
            $this->kpi('Vendors used', (string) count($byVendor), 'Distinct suppliers', 'neutral',
                'Too few is a supply risk, too many is lost bargaining power.'),
            $this->kpi('Average delivery time', $avgLead === null ? 'Not enough data' : round($avgLead) . ' days', 'Order to goods received', $avgLead !== null && $avgLead > 30 ? 'warn' : 'good',
                'How long your money is tied up before goods arrive. Plan your cash around this number.'),
            $this->kpi('Delivered on time', $onTimePct === null ? 'No agreed dates' : $this->pct($onTimePct), $received . ' receipt(s) measured', $onTimePct !== null && $onTimePct < 80 ? 'bad' : 'good',
                'Against the delivery date on the purchase order. Only counts orders where a date was agreed.'),
        ];

        $callouts = [];
        $topVendor = reset($byVendor);
        if ($topVendor && $spend > 0 && $topVendor['spend'] / $spend > 0.5) {
            $callouts[] = ['tone' => 'warning', 'title' => $this->pct($topVendor['spend'] / $spend * 100) . ' of your spend goes to ' . esc($topVendor['vendor']),
                'text' => 'That is real negotiating power, and a real single point of failure. Make sure you have a second source priced and tested before you need it.'];
        }
        if ($openRows) {
            $stuck = array_filter($openRows, fn ($r) => $r['tone'] === 'bad');
            if ($stuck) {
                $callouts[] = ['tone' => 'danger', 'title' => count($stuck) . ' purchase order(s) unreceived after 45 days',
                    'text' => 'Either the goods are late or the receipt was never entered. Both are worth ten minutes today.'];
            }
        }
        if ($onTimePct !== null && $onTimePct < 80) {
            $callouts[] = ['tone' => 'warning', 'title' => 'One in five deliveries arrives late',
                'text' => 'Late goods become late shipments and late invoices. Use the vendor table below in your next supplier conversation.'];
        }

        $vendorRows = [];
        foreach ($byVendor as $v) {
            $lead = $v['lead'] ? array_sum($v['lead']) / count($v['lead']) : null;
            $vendorRows[] = [
                'vendor'    => $v['vendor'],
                'orders'    => $v['orders'],
                'spend'     => $this->money($v['spend']),
                'spend_raw' => $v['spend'],
                'lead'      => $lead === null ? 'Not received yet' : round($lead) . ' days',
                'late'      => $v['late'] > 0 ? $v['late'] . ' late' : 'None late',
                'tone'      => $v['late'] > 0 ? 'warn' : 'good',
                'share'     => $spend > 0 ? round($v['spend'] / $spend * 100, 1) : 0,
            ];
        }

        usort($openRows, fn ($a, $b) => strcmp($b['waiting'], $a['waiting']));

        return $this->spec('purchasing', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'  => 'chart',
                    'title' => 'Spend by vendor',
                    'chart' => [
                        'kind'       => 'bar',
                        'horizontal' => true,
                        'labels'     => array_map(fn ($v) => $v['vendor'], array_slice($vendorRows, 0, 10)),
                        'datasets'   => [['label' => 'Spend', 'data' => array_map(fn ($v) => round($v['spend_raw'], 2), array_slice($vendorRows, 0, 10)), 'color' => '#fb923c']],
                    ],
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Vendor scorecard',
                    'note'    => 'Spend buys you leverage. Delivery time and lateness tell you whether to use it.',
                    'columns' => [
                        ['key' => 'vendor', 'label' => 'Vendor', 'strong' => true],
                        ['key' => 'orders', 'label' => 'Orders', 'align' => 'right'],
                        ['key' => 'spend', 'label' => 'Spend', 'align' => 'right'],
                        ['key' => 'lead', 'label' => 'Average delivery', 'align' => 'right'],
                        ['key' => 'late', 'label' => 'Punctuality', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'share', 'label' => 'Share of spend', 'type' => 'bar'],
                    ],
                    'rows'  => $vendorRows,
                    'empty' => 'No purchases in this period.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Ordered but not yet received',
                    'note'    => 'Money committed with nothing on the shelf to show for it.',
                    'columns' => [
                        ['key' => 'number', 'label' => 'PO', 'strong' => true],
                        ['key' => 'vendor', 'label' => 'Vendor'],
                        ['key' => 'ordered', 'label' => 'Ordered'],
                        ['key' => 'promised', 'label' => 'Promised'],
                        ['key' => 'waiting', 'label' => 'Waiting', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                    ],
                    'rows'  => $openRows,
                    'empty' => 'Everything ordered has been received.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------ report 10

    public function fulfilment(): array
    {
        $orders = $this->db->query(
            'SELECT so.id, so.order_number, so.status, so.currency_code, so.total,
                    DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) AS ordered,
                    c.name AS customer,
                    (SELECT MIN(d.shipped_at) FROM delivery_orders d WHERE d.sales_order_id = so.id AND d.shipped_at IS NOT NULL) AS shipped,
                    (SELECT MIN(d.delivered_at) FROM delivery_orders d WHERE d.sales_order_id = so.id AND d.delivered_at IS NOT NULL) AS delivered,
                    (SELECT SUM(d.shipping_cost_pkr) FROM delivery_orders d WHERE d.sales_order_id = so.id) AS ship_cost
               FROM sales_orders so
               LEFT JOIN customers c ON c.id = so.customer_id
              WHERE so.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              ORDER BY ordered ASC',
            [$this->from, $this->to]
        )->getResultArray();

        $openRows = $shippedRows = [];
        $openValue = $shipCost = $shippedValue = 0.0;
        $shipDays = [];

        foreach ($orders as $o) {
            $value = $this->toDisplay((float) $o['total'], $o['currency_code']) ?? 0.0;

            if (empty($o['shipped'])) {
                $age = $this->daysBetween($o['ordered'], date('Y-m-d'));
                $openValue += $value;
                $openRows[] = [
                    'number'    => $o['order_number'],
                    'customer'  => $o['customer'] ?: 'Unnamed customer',
                    'ordered'   => $this->date($o['ordered']),
                    'age'       => $age . ' days',
                    'status'    => ucfirst((string) ($o['status'] ?: 'no status')),
                    'value'     => format_money($o['total'], $o['currency_code']),
                    'value_raw' => (float) $o['total'],
                    'tone'      => $age > 30 ? 'bad' : ($age > 14 ? 'warn' : 'good'),
                ];
                continue;
            }

            $days = $this->daysBetween($o['ordered'], substr($o['shipped'], 0, 10));
            $shipDays[] = $days;
            $shippedValue += $value;
            $cost = (float) ($o['ship_cost'] ?? 0);
            $shipCost += $cost;

            $shippedRows[] = [
                'number'    => $o['order_number'],
                'customer'  => $o['customer'] ?: 'Unnamed customer',
                'ordered'   => $this->date($o['ordered']),
                'shipped'   => $this->date(substr($o['shipped'], 0, 10)),
                'days'      => $days . ' days',
                'delivered' => $o['delivered'] ? $this->date(substr($o['delivered'], 0, 10)) : 'Not confirmed',
                'cost'      => $cost > 0 ? format_money($cost, $this->base) : 'Not recorded',
                'value'     => format_money($o['total'], $o['currency_code']),
                'value_raw' => (float) $o['total'],
                'tone'      => $days > 30 ? 'bad' : ($days > 14 ? 'warn' : 'good'),
            ];
        }

        $avgShip = $shipDays ? array_sum($shipDays) / count($shipDays) : null;
        $costRatio = $shippedValue > 0 ? $this->toDisplay($shipCost, $this->base) / $shippedValue * 100 : null;

        $kpis = [
            $this->kpi('Waiting to ship', (string) count($openRows), $this->money($openValue) . ' of orders', count($openRows) > 0 ? 'warn' : 'good',
                'Orders the customer is already waiting for. Every day here is a day you cannot invoice.'),
            $this->kpi('Shipped in period', (string) count($shippedRows), $this->money($shippedValue), 'good',
                'Orders that actually left the building.'),
            $this->kpi('Average time to ship', $avgShip === null ? 'Not enough data' : round($avgShip) . ' days', 'From order to dispatch', $avgShip !== null && $avgShip > 21 ? 'warn' : 'good',
                'Your real promise time. Quote this, not the number you hope for.'),
            $this->kpi('Shipping cost', $costRatio === null ? 'Not recorded' : $this->pct($costRatio), $this->money($this->toDisplay($shipCost, $this->base) ?? 0) . ' spent', $costRatio !== null && $costRatio > 10 ? 'warn' : 'good',
                'Freight as a share of order value. If it climbs, your pricing needs to follow.'),
        ];

        $callouts = [];
        $stuck = array_filter($openRows, fn ($r) => $r['tone'] === 'bad');
        if ($stuck) {
            $callouts[] = ['tone' => 'danger', 'title' => count($stuck) . ' order(s) unshipped after 30 days',
                'text' => 'These customers have been waiting a month. They are also the invoices you have not been able to raise yet, so this is a cash problem as much as a service one.'];
        }
        if ($avgShip !== null && $avgShip > 21) {
            $callouts[] = ['tone' => 'warning', 'title' => 'Orders take ' . round($avgShip) . ' days to ship on average',
                'text' => 'If your quotes promise faster than this, you are setting up every customer for disappointment. Either fix the process or change the promise.'];
        }
        if ($openRows === []) {
            $callouts[] = ['tone' => 'good', 'title' => 'Nothing is waiting to ship', 'text' => 'Every order in this period has been dispatched.'];
        }

        return $this->spec('fulfilment', [
            'kpis'     => $kpis,
            'callouts' => $callouts,
            'sections' => [
                [
                    'type'    => 'table',
                    'title'   => 'Orders waiting to ship — longest first',
                    'note'    => 'Work top down. These are promises already made.',
                    'columns' => [
                        ['key' => 'number', 'label' => 'Order', 'strong' => true],
                        ['key' => 'customer', 'label' => 'Customer'],
                        ['key' => 'ordered', 'label' => 'Ordered'],
                        ['key' => 'age', 'label' => 'Waiting', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'status', 'label' => 'Status'],
                        ['key' => 'value', 'label' => 'Value', 'align' => 'right'],
                    ],
                    'rows'  => array_reverse($openRows),
                    'empty' => 'Nothing outstanding.',
                ],
                [
                    'type'    => 'table',
                    'title'   => 'Shipped orders and how long they took',
                    'columns' => [
                        ['key' => 'number', 'label' => 'Order', 'strong' => true],
                        ['key' => 'customer', 'label' => 'Customer'],
                        ['key' => 'ordered', 'label' => 'Ordered'],
                        ['key' => 'shipped', 'label' => 'Shipped'],
                        ['key' => 'days', 'label' => 'Took', 'type' => 'badge', 'tone' => 'tone'],
                        ['key' => 'delivered', 'label' => 'Delivered'],
                        ['key' => 'cost', 'label' => 'Freight', 'align' => 'right'],
                        ['key' => 'value', 'label' => 'Order value', 'align' => 'right'],
                    ],
                    'rows'  => $shippedRows,
                    'empty' => 'No orders shipped in this period.',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------- shared queries

    /** Open customer invoices with age. Drafts are excluded: they are not owed yet. */
    private function openInvoices(): array
    {
        $rows = $this->db->query(
            'SELECT ci.id, ci.invoice_number AS number, ci.customer_id, ci.issue_date, ci.due_date,
                    COALESCE(NULLIF(ci.currency_code, ""), ?) AS currency_code,
                    ci.total_amount AS total,
                    COALESCE((SELECT SUM(cpa.allocated_amount) FROM customer_payment_allocations cpa WHERE cpa.invoice_id = ci.id), 0) AS paid,
                    COALESCE(c.name, "Unnamed customer") AS customer
               FROM customer_invoices ci
               LEFT JOIN customers c ON c.id = ci.customer_id
              WHERE ci.deleted_at IS NULL
                AND LOWER(COALESCE(ci.status, "")) NOT IN (' . self::DEAD_STATES . ', ' . self::DRAFT_STATES . ')',
            [$this->base]
        )->getResultArray();

        $today = date('Y-m-d');
        $out = [];
        foreach ($rows as $r) {
            $outstanding = round((float) $r['total'] - (float) $r['paid'], 2);
            if ($outstanding <= 0.005) {
                continue;
            }
            $due = $this->validDate($r['due_date']) ?: ($this->validDate($r['issue_date']) ?: $today);
            $r['outstanding'] = $outstanding;
            $r['days_late']   = $due < $today ? $this->daysBetween($due, $today) : ($due === $today ? 0 : -1);
            $r['days_to_due'] = $this->daysBetween($today, $due);
            $out[] = $r;
        }

        return $out;
    }

    /** Open vendor bills. bills.balance is not maintained, so allocations are the truth. */
    private function openBills(): array
    {
        $rows = $this->db->query(
            'SELECT vb.id, vb.bill_number AS number, vb.vendor_id, vb.bill_date, vb.due_date,
                    COALESCE(NULLIF(vb.currency_code, ""), ?) AS currency_code,
                    vb.total_amount AS total,
                    COALESCE((SELECT SUM(COALESCE(vpa.amount_allocated, vpa.amount))
                                FROM vendor_payment_allocations vpa WHERE vpa.vendor_bill_id = vb.id), 0) AS paid,
                    COALESCE(v.name, "Unknown vendor") AS vendor
               FROM vendor_bills vb
               LEFT JOIN vendors v ON v.id = vb.vendor_id
              WHERE LOWER(COALESCE(vb.status, "")) NOT IN (' . self::DEAD_STATES . ', ' . self::DRAFT_STATES . ')',
            [$this->base]
        )->getResultArray();

        $today = date('Y-m-d');
        $out = [];
        foreach ($rows as $r) {
            $outstanding = round((float) $r['total'] - (float) $r['paid'], 2);
            if ($outstanding <= 0.005) {
                continue;
            }
            $due = $this->validDate(substr((string) $r['due_date'], 0, 10)) ?: ($this->validDate(substr((string) $r['bill_date'], 0, 10)) ?: $today);
            $r['outstanding'] = $outstanding;
            $r['days_late']   = $due < $today ? $this->daysBetween($due, $today) : 0;
            $r['days_to_due'] = $this->daysBetween($today, $due);
            $out[] = $r;
        }

        return $out;
    }

    private function invoicedTotals(string $from, string $to): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(currency_code, ""), ?) AS currency_code, SUM(total_amount) AS amount, COUNT(*) AS n
               FROM customer_invoices
              WHERE deleted_at IS NULL
                AND LOWER(COALESCE(status, "")) NOT IN (' . self::DEAD_STATES . ', ' . self::DRAFT_STATES . ')
                AND DATE(COALESCE(NULLIF(issue_date, "0000-00-00"), created_at)) BETWEEN ? AND ?
              GROUP BY 1',
            [$this->base, $from, $to]
        )->getResultArray();

        return $this->totals($rows);
    }

    private function collectedTotals(string $from, string $to): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(currency_code, ""), ?) AS currency_code, SUM(amount) AS amount, COUNT(*) AS n
               FROM customer_payments
              WHERE DATE(COALESCE(NULLIF(payment_date, "0000-00-00"), created_at)) BETWEEN ? AND ?
              GROUP BY 1',
            [$this->base, $from, $to]
        )->getResultArray();

        return $this->totals($rows);
    }

    private function soldTotals(string $from, string $to): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(currency_code, ""), ?) AS currency_code, SUM(total) AS amount, COUNT(*) AS n
               FROM sales_orders
              WHERE deleted_at IS NULL
                AND LOWER(COALESCE(status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(order_date, "0000-00-00"), created_at)) BETWEEN ? AND ?
              GROUP BY 1',
            [$this->base, $from, $to]
        )->getResultArray();

        return $this->totals($rows);
    }

    private function vendorPaidTotals(string $from, string $to): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(currency_code, ""), ?) AS currency_code, SUM(amount) AS amount, COUNT(*) AS n
               FROM vendor_payments
              WHERE LOWER(COALESCE(status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(payment_date, "0000-00-00"), created_at)) BETWEEN ? AND ?
              GROUP BY 1',
            [$this->base, $from, $to]
        )->getResultArray();

        return $this->totals($rows);
    }

    /** Confirmed orders with no invoice raised against them yet. */
    private function orderBacklog(): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(so.currency_code, ""), ?) AS currency_code, SUM(so.total) AS amount, COUNT(*) AS n
               FROM sales_orders so
              WHERE so.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ', ' . self::DRAFT_STATES . ')
                AND NOT EXISTS (SELECT 1 FROM customer_invoices ci
                                 WHERE ci.sales_order_id = so.id AND ci.deleted_at IS NULL
                                   AND LOWER(COALESCE(ci.status, "")) NOT IN (' . self::DEAD_STATES . '))
              GROUP BY 1',
            [$this->base]
        )->getResultArray();

        $t = $this->totals($rows);

        return ['value' => $t['display'], 'count' => $t['count']];
    }

    private function monthlyMovement(): array
    {
        $months = [];
        $cursor = date('Y-m-01', strtotime($this->from));
        $last   = date('Y-m-01', strtotime($this->to));
        while ($cursor <= $last && count($months) < 36) {
            $end = date('Y-m-t', strtotime($cursor));
            $sold      = $this->soldTotals($cursor, $end);
            $invoiced  = $this->invoicedTotals($cursor, $end);
            $collected = $this->collectedTotals($cursor, $end);
            $purchased = $this->vendorPaidTotals($cursor, $end);

            $months[] = [
                'label'     => date('M Y', strtotime($cursor)),
                'sold'      => $sold['display'],
                'orders'    => $sold['count'],
                'invoiced'  => $invoiced['display'],
                'collected' => $collected['display'],
                'purchased' => $purchased['display'],
            ];
            $cursor = date('Y-m-01', strtotime($cursor . ' +1 month'));
        }

        return $months;
    }

    private function salesByCustomer(): array
    {
        $rows = $this->db->query(
            'SELECT so.customer_id, COALESCE(c.name, "Unnamed customer") AS customer,
                    so.currency_code, SUM(so.total) AS amount, COUNT(*) AS orders,
                    MAX(DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at))) AS last_order
               FROM sales_orders so
               LEFT JOIN customers c ON c.id = so.customer_id
              WHERE so.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              GROUP BY 1, 2, 3',
            [$this->from, $this->to]
        )->getResultArray();

        $agg = [];
        foreach ($rows as $r) {
            $amount = $this->toDisplay((float) $r['amount'], $r['currency_code']) ?? 0.0;
            $cid = (int) $r['customer_id'];
            $agg[$cid] ??= ['customer' => $r['customer'], 'value' => 0.0, 'orders' => 0, 'last_order' => $r['last_order']];
            $agg[$cid]['value']  += $amount;
            $agg[$cid]['orders'] += (int) $r['orders'];
            $agg[$cid]['last_order'] = max($agg[$cid]['last_order'], $r['last_order']);
        }

        usort($agg, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_values($agg);
    }

    private function salesByProduct(): array
    {
        $rows = $this->db->query(
            'SELECT COALESCE(NULLIF(TRIM(CONVERT(p.name USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(sol.description USING utf8mb4)), ""), "Unnamed line") AS product,
                    COALESCE(NULLIF(TRIM(CONVERT(pv.art_number USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(p.code USING utf8mb4)), ""), "") AS code,
                    so.currency_code, SUM(sol.quantity) AS qty, SUM(sol.line_total) AS amount,
                    COUNT(DISTINCT so.id) AS orders
               FROM sales_order_lines sol
               INNER JOIN sales_orders so ON so.id = sol.sales_order_id
               LEFT JOIN products p ON p.id = sol.product_id
               LEFT JOIN product_variants pv ON pv.id = sol.product_variant_id
              WHERE so.deleted_at IS NULL AND sol.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
                AND DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) BETWEEN ? AND ?
              GROUP BY sol.product_id, sol.product_variant_id, 1, 2, 3',
            [$this->from, $this->to]
        )->getResultArray();

        $agg = [];
        foreach ($rows as $r) {
            $amount = $this->toDisplay((float) $r['amount'], $r['currency_code']) ?? 0.0;
            $key = $r['product'] . '|' . $r['code'];
            $agg[$key] ??= ['product' => $r['product'], 'code' => $r['code'], 'value' => 0.0, 'qty' => 0.0, 'orders' => 0];
            $agg[$key]['value']  += $amount;
            $agg[$key]['qty']    += (float) $r['qty'];
            $agg[$key]['orders'] += (int) $r['orders'];
        }

        usort($agg, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_values($agg);
    }

    private function dormantCustomers(): array
    {
        $rows = $this->db->query(
            'SELECT so.customer_id, COALESCE(c.name, "Unnamed customer") AS customer, so.currency_code,
                    SUM(so.total) AS amount, COUNT(*) AS orders,
                    MAX(DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at))) AS last_order
               FROM sales_orders so
               LEFT JOIN customers c ON c.id = so.customer_id
              WHERE so.deleted_at IS NULL
                AND LOWER(COALESCE(so.status, "")) NOT IN (' . self::DEAD_STATES . ')
              GROUP BY 1, 2, 3'
        )->getResultArray();

        $agg = [];
        foreach ($rows as $r) {
            $cid = (int) $r['customer_id'];
            $agg[$cid] ??= ['customer' => $r['customer'], 'value' => 0.0, 'orders' => 0, 'last_order' => $r['last_order']];
            $agg[$cid]['value']  += $this->toDisplay((float) $r['amount'], $r['currency_code']) ?? 0.0;
            $agg[$cid]['orders'] += (int) $r['orders'];
            $agg[$cid]['last_order'] = max($agg[$cid]['last_order'], $r['last_order']);
        }

        $today = date('Y-m-d');
        $out = [];
        foreach ($agg as $c) {
            $days = $this->daysBetween($c['last_order'], $today);
            if ($days > 90) {
                $c['days'] = $days;
                $out[] = $c;
            }
        }
        usort($out, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $out;
    }

    /** Average days from invoice date to the payment that settled it. */
    private function daysSalesOutstanding(): ?float
    {
        $rows = $this->db->query(
            'SELECT ci.issue_date, MAX(cp.payment_date) AS paid_on
               FROM customer_invoices ci
               INNER JOIN customer_payment_allocations cpa ON cpa.invoice_id = ci.id
               INNER JOIN customer_payments cp ON cp.id = cpa.payment_id
              WHERE ci.deleted_at IS NULL
              GROUP BY ci.id, ci.issue_date'
        )->getResultArray();

        $days = [];
        foreach ($rows as $r) {
            $issued = $this->validDate($r['issue_date']);
            $paid   = $this->validDate(substr((string) $r['paid_on'], 0, 10));
            if ($issued && $paid && $paid >= $issued) {
                $days[] = $this->daysBetween($issued, $paid);
            }
        }

        return $days ? array_sum($days) / count($days) : null;
    }

    /** Average elapsed days across the quote → order → invoice → cash chain. */
    private function stageLags(): array
    {
        $rows = $this->db->query(
            'SELECT DATE(COALESCE(NULLIF(q.issue_date, "0000-00-00"), q.created_at)) AS quoted,
                    DATE(COALESCE(NULLIF(so.order_date, "0000-00-00"), so.created_at)) AS ordered,
                    DATE(COALESCE(NULLIF(ci.issue_date, "0000-00-00"), ci.created_at)) AS invoiced,
                    DATE(MAX(cp.payment_date)) AS paid
               FROM quotations q
               INNER JOIN sales_orders so ON so.id = q.converted_to_sales_order_id
               LEFT JOIN customer_invoices ci ON ci.sales_order_id = so.id AND ci.deleted_at IS NULL
               LEFT JOIN customer_payment_allocations cpa ON cpa.invoice_id = ci.id
               LEFT JOIN customer_payments cp ON cp.id = cpa.payment_id
              WHERE q.deleted_at IS NULL AND so.deleted_at IS NULL
              GROUP BY q.id, quoted, ordered, invoiced'
        )->getResultArray();

        $toCash = [];
        foreach ($rows as $r) {
            if (!empty($r['paid']) && !empty($r['quoted']) && $r['paid'] >= $r['quoted']) {
                $toCash[] = $this->daysBetween($r['quoted'], $r['paid']);
            }
        }

        return ['quote_to_cash' => $toCash ? array_sum($toCash) / count($toCash) : null];
    }

    // ---------------------------------------------------------------- primitives

    /** Sum per-currency rows into one display figure, keeping the split for context. */
    private function totals(array $rows): array
    {
        $display = 0.0;
        $count = 0;
        $byCurrency = [];
        foreach ($rows as $r) {
            $code = $r['currency_code'] ?: $this->base;
            $byCurrency[$code] = ($byCurrency[$code] ?? 0) + (float) $r['amount'];
            $count += (int) ($r['n'] ?? 0);
            $display += $this->toDisplay((float) $r['amount'], $code) ?? 0.0;
        }

        return ['display' => $display, 'count' => $count, 'by_currency' => $byCurrency];
    }

    private function convertRows(iterable $rows, string $amountKey, string $currencyKey): float
    {
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += $this->toDisplay((float) $r[$amountKey], $r[$currencyKey]) ?? 0.0;
        }

        return $sum;
    }

    /**
     * Convert into the report's display currency. Returns null when no rate exists
     * in either direction — the caller must then leave the figure out rather than
     * pass off a foreign amount as a converted one.
     */
    private function toDisplay(float $amount, ?string $from): ?float
    {
        $from = strtoupper(trim((string) $from)) ?: $this->base;
        if ($from === $this->display) {
            return $amount;
        }

        $rate = $this->rate($from, $this->display);

        return $rate === null ? null : round($amount * $rate, 2);
    }

    /** Direct rate, else the inverse of the opposite pair. Cached per request. */
    private function rate(string $from, string $to): ?float
    {
        static $cache = [];
        $key = $from . '>' . $to;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if ($from === $to) {
            return $cache[$key] = 1.0;
        }

        $direct = get_active_rate($from, $to);
        if ($direct && (float) $direct['rate'] > 0) {
            return $cache[$key] = (float) $direct['rate'];
        }

        $inverse = get_active_rate($to, $from);
        if ($inverse && (float) $inverse['rate'] > 0) {
            return $cache[$key] = 1 / (float) $inverse['rate'];
        }

        return $cache[$key] = null;
    }

    private function ageBucket(int $daysLate): string
    {
        if ($daysLate <= 0) {
            return 'Not due yet';
        }
        if ($daysLate <= 30) {
            return '1-30 days late';
        }
        if ($daysLate <= 60) {
            return '31-60 days late';
        }
        if ($daysLate <= 90) {
            return '61-90 days late';
        }

        return '90+ days late';
    }

    private function kpi(string $label, string $value, string $sub, string $tone, string $hint): array
    {
        return ['label' => $label, 'value' => $value, 'sub' => $sub, 'tone' => $tone, 'hint' => $hint];
    }

    private function spec(string $key, array $parts): array
    {
        $meta = self::catalogue()[$key];

        return array_merge([
            'key'      => $key,
            'title'    => $meta['title'],
            'lede'     => $meta['lede'],
            'icon'     => $meta['icon'],
            'range'    => $this->date($this->from) . ' to ' . $this->date($this->to),
            'currency' => $this->display,
            'kpis'     => [],
            'callouts' => [],
            'sections' => [],
        ], $parts);
    }

    private function delta(float $now, float $before): string
    {
        if ($before <= 0) {
            return $now > 0 ? 'No comparable previous period' : 'Nothing in either period';
        }
        $change = ($now - $before) / $before * 100;
        $word   = $change >= 0 ? 'up' : 'down';

        return $word . ' ' . $this->pct(abs($change)) . ' vs previous period';
    }

    private function money(?float $amount): string
    {
        return format_money((float) $amount, $this->display);
    }

    private function num($value): string
    {
        $value = (float) $value;

        return number_format($value, fmod($value, 1) === 0.0 ? 0 : 2);
    }

    private function pct(float $value): string
    {
        return number_format($value, 1) . '%';
    }

    private function date(?string $date): string
    {
        $date = $this->validDate($date);

        return $date ? date('d M Y', strtotime($date)) : '—';
    }

    private function daysBetween(?string $from, ?string $to): int
    {
        $from = $this->validDate($from);
        $to   = $this->validDate($to);
        if (!$from || !$to) {
            return 0;
        }

        return (int) round((strtotime($to) - strtotime($from)) / 86400);
    }

    /** Guards the zero dates ('0000-00-00') this database still carries. */
    private function validDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '' || str_starts_with($date, '0000')) {
            return null;
        }
        $ts = strtotime($date);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
