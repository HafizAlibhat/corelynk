<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($customer['name'] ?? 'Customer') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
helper('url');
helper('currency');
$customerIdentifier = entityRouteIdentifier($customer);

$biz       = $analytics ?? [];
$bizLife   = $biz['lifetime'] ?? [];
$bizYear   = $biz['yearly'] ?? [];
$bizMonth  = $biz['monthly'] ?? [];
$bizCustom = $biz['custom'] ?? [];
$sum       = $receivableSummary ?? [];
$metadata  = json_decode($customer['metadata'] ?? '{}', true) ?: [];

$payUrl = site_url('accounting/customer-payments/pay?customer_id=' . (int) $customer['id']);

/**
 * The page is read in ONE currency at a time. Orders, invoices and payments each keep
 * the currency they were raised in; nothing is converted and nothing is added across
 * currencies. $active selects which set of figures is on screen.
 */
$base   = $biz['base_currency'] ?? base_currency_code();
$usage  = $currencyUsage ?? [];
$active = $activeCurrency ?? $base;
$multi  = count($usage) > 1;

$m = static fn ($v, ?string $code = null): string => format_money($v, $code ?: $active);

// The active currency's slice of a per-currency map.
$pick = static function (array $map, ?string $field = null) use ($active): float {
    $val = $map[$active] ?? null;
    if ($val === null) {
        return 0.0;
    }
    return (float) (($field !== null && is_array($val)) ? ($val[$field] ?? 0) : $val);
};

// Rows carry their own currency; only the active one is listed.
$only = static function (array $rows) use ($active): array {
    return array_values(array_filter($rows, static fn ($r) => currency_code_or_base($r['currency_code'] ?? null) === $active));
};

// A real date, or null — legacy rows carry '' and '0000-00-00'.
$realDate = static function ($value): ?string {
    $value = trim((string) $value);
    return ($value === '' || str_starts_with($value, '0000-00-00')) ? null : $value;
};

// Status word -> chip colour, shared by orders, quotations, invoices and payments.
$statusTone = static function (?string $status): string {
    $s = strtolower(trim((string) $status));
    if (in_array($s, ['posted', 'paid', 'delivered', 'completed', 'accepted', 'converted', 'approved'], true)) {
        return 'is-success';
    }
    if (in_array($s, ['cancelled', 'canceled', 'void', 'rejected', 'expired', 'overdue'], true)) {
        return 'is-danger';
    }
    if (in_array($s, ['draft', 'pending', 'sent', 'partially_paid', 'partial'], true)) {
        return 'is-warning';
    }
    return 'is-muted';
};

$firstOrder = $realDate($bizLife['first_order_date'] ?? null);
$lastOrder  = $realDate($bizLife['last_order_date'] ?? null);
$daysSince  = $lastOrder ? (int) floor((time() - strtotime($lastOrder)) / 86400) : null;

$lifeCur   = $bizLife['by_currency'] ?? [];
$recvCur   = $sum['receivable_by_currency'] ?? [];
$postedCur = $sum['posted_by_currency'] ?? [];
$draftCur  = $sum['draft_by_currency'] ?? [];

$revenue     = $pick($lifeCur, 'revenue');
$avgOrder    = $pick($lifeCur, 'avg_order_value');
$orderCount  = (int) ($lifeCur[$active]['order_count'] ?? 0);
$units       = (float) ($lifeCur[$active]['units_bought'] ?? 0);
$outstanding = $pick($recvCur);
$collected   = $pick($postedCur);
$draft       = $pick($draftCur);
$billed      = $collected + $outstanding;
$collectRate = $billed > 0 ? ($collected / $billed) * 100 : null;

$yearRevenue   = $pick($bizYear['by_currency'] ?? [], 'revenue');
$monthRevenue  = $pick($bizMonth['by_currency'] ?? [], 'revenue');
$customRevenue = $pick($bizCustom['by_currency'] ?? [], 'revenue');

$contactList = array_values($contacts ?? []);
$addressList = array_values($addresses ?? []);
$countryList = $countries ?? [];
$orders      = $recentOrders ?? [];
$quotes      = $recentQuotes ?? [];
$topProducts = $only($biz['top_products'] ?? []);
$yearTrend   = $only($biz['yearly_trend'] ?? []);
$monthTrend  = $only($biz['monthly_trend'] ?? []);
$unpaid      = $only($unpaidInvoices ?? []);
$soPending   = $only($orderReceivables ?? []);
$history     = $only($paymentHistory ?? []);

$openCount = count($unpaid);

$topSalesMax = 0.0;
foreach ($topProducts as $tp) {
    $topSalesMax = max($topSalesMax, (float) ($tp['total_sales'] ?? 0));
}

$email   = $customer['email'] ?? ($metadata['primary_email'] ?? '');
$phone   = $customer['phone'] ?? ($metadata['phone'] ?? '');
$mobile  = $customer['mobile'] ?? ($metadata['mobile'] ?? '');
$website = $customer['website'] ?? ($metadata['website'] ?? '');
$company = $customer['company_name'] ?? ($metadata['company_name'] ?? '');

$initials = strtoupper(mb_substr(trim((string) ($customer['name'] ?? 'C')), 0, 2));
$isActive = ($customer['status'] ?? '') === 'active';

// Period picker. Years come from the trend data already loaded -- no extra query --
// widened with this year and whatever the user picked, so the select is never empty.
$pickedYear  = $bizCustom['year'] ?? null;
$pickedMonth = $bizCustom['month'] ?? null;
$isCustom    = $pickedYear === null && !empty($bizCustom['enabled']);

$yearOptions = [(int) date('Y')];
foreach (($biz['yearly_trend'] ?? []) as $row) {
    $y = (int) ($row['period'] ?? 0);
    if ($y > 1970) { $yearOptions[] = $y; }
}
if ($pickedYear !== null) { $yearOptions[] = (int) $pickedYear; }
$yearOptions = array_unique($yearOptions);
rsort($yearOptions);

// Currency switching preserves whichever period is on screen.
$baseQuery = array_filter([
    'from'  => $isCustom ? ($bizCustom['from'] ?? null) : null,
    'to'    => $isCustom ? ($bizCustom['to'] ?? null) : null,
    'year'  => $pickedYear,
    'month' => $pickedMonth,
]);
$currencyUrl = static function (string $code) use ($customerIdentifier, $baseQuery): string {
    return site_url('customers/' . $customerIdentifier) . '?' . http_build_query($baseQuery + ['currency' => $code]);
};
?>

<div class="cl-detail" data-customer="<?= esc($customerIdentifier) ?>">

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success mb-0"><?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>

    <!-- ------------------------------------------------------------- hero -->
    <header class="cl-detail-hero">
        <div class="cl-detail-identity">
            <div class="cl-detail-avatar">
                <?php if (!empty($customer['avatar_path'])): ?>
                    <img src="<?= base_url($customer['avatar_path']) ?>" alt="<?= esc($customer['name']) ?>">
                <?php else: ?>
                    <?= esc($initials) ?>
                <?php endif; ?>
            </div>
            <div>
                <p class="cl-detail-eyebrow">Customer</p>
                <h1 class="cl-detail-title"><?= esc($customer['name']) ?></h1>
                <div class="cl-detail-chips">
                    <span class="cl-chip is-mono"><i class="bi bi-hash"></i><?= esc($customer['customer_code']) ?></span>
                    <span class="cl-chip"><i class="bi bi-tag"></i><?= esc($customer['type']) ?></span>
                    <span class="cl-chip <?= $isActive ? 'is-success' : 'is-muted' ?>">
                        <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-pause-circle' ?>"></i><?= esc($customer['status']) ?>
                    </span>
                    <?php if ($company): ?>
                        <span class="cl-chip is-muted"><i class="bi bi-building"></i><?= esc($company) ?></span>
                    <?php endif; ?>
                    <?php if ($email): ?>
                        <a class="cl-chip" href="mailto:<?= esc($email) ?>"><i class="bi bi-envelope"></i><?= esc($email) ?></a>
                    <?php endif; ?>
                    <?php if ($phone || $mobile): ?>
                        <a class="cl-chip" href="tel:<?= esc($phone ?: $mobile) ?>"><i class="bi bi-telephone"></i><?= esc($phone ?: $mobile) ?></a>
                    <?php endif; ?>
                    <?php if ($website): ?>
                        <a class="cl-chip" href="<?= esc($website) ?>" target="_blank" rel="noopener"><i class="bi bi-globe"></i>Website</a>
                    <?php endif; ?>
                    <?php if ($firstOrder): ?>
                        <span class="cl-chip is-muted"><i class="bi bi-calendar-check"></i>Customer since <?= esc($firstOrder) ?></span>
                    <?php endif; ?>
                    <?php if ($daysSince !== null): ?>
                        <span class="cl-chip <?= $daysSince > 90 ? 'is-warning' : 'is-muted' ?>">
                            <i class="bi bi-clock-history"></i>
                            <?= $daysSince === 0 ? 'Ordered today' : 'Last order ' . $daysSince . 'd ago' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="cl-detail-actions">
            <a href="<?= $payUrl ?>" class="cl-detail-btn is-primary"><i class="bi bi-cash-coin"></i>Receive Payment</a>
            <a href="<?= site_url('customers/' . $customerIdentifier . '/edit') ?>" class="cl-detail-btn is-accent"><i class="bi bi-pencil"></i>Edit</a>
            <a href="<?= site_url('customers') ?>" class="cl-detail-btn"><i class="bi bi-arrow-left"></i>Back</a>
        </div>
    </header>

    <!-- ------- snapshot: the filters are this card's head, not a bar of their own -->
    <section class="cl-detail-card">
    <form method="get" action="<?= site_url('customers/' . $customerIdentifier) ?>" class="cl-toolbar">
        <div class="cl-toolbar-group">
            <span class="cl-toolbar-label">Currency</span>
            <?php if ($multi): ?>
                <?php /* Only a customer who actually trades in several currencies gets a switch. */ ?>
                <div class="cl-seg" role="group" aria-label="Reporting currency">
                    <?php foreach ($usage as $code => $ordersInCode): ?>
                        <a class="cl-seg-btn<?= $code === $active ? ' is-on' : '' ?>" href="<?= esc($currencyUrl((string) $code)) ?>">
                            <?= esc($code) ?><?php if ($ordersInCode > 0): ?><span class="cl-seg-count"><?= (int) $ordersInCode ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <span class="cl-toolbar-note"><i class="bi bi-info-circle"></i>Trades in <?= count($usage) ?> currencies — everything below is <?= esc($active) ?> only.</span>
            <?php else: ?>
                <span class="cl-chip is-mono"><?= esc($active) ?></span>
            <?php endif; ?>
        </div>

        <div class="cl-toolbar-group cl-detail-spacer" id="periodPicker">
            <input type="hidden" name="currency" value="<?= esc($active) ?>">
            <span class="cl-toolbar-label" aria-hidden="true"><i class="bi bi-calendar3"></i></span>

            <select name="year" class="cl-select" data-period aria-label="Year">
                <option value="">All time</option>
                <?php foreach ($yearOptions as $y): ?>
                    <option value="<?= (int) $y ?>"<?= (int) $y === (int) $pickedYear ? ' selected' : '' ?>><?= (int) $y ?></option>
                <?php endforeach; ?>
            </select>

            <select name="month" class="cl-select" data-period aria-label="Month"<?= $pickedYear === null ? ' disabled' : '' ?>>
                <option value="">Whole year</option>
                <?php for ($mo = 1; $mo <= 12; $mo++): ?>
                    <option value="<?= $mo ?>"<?= $mo === (int) $pickedMonth ? ' selected' : '' ?>>
                        <?= date('F', (int) mktime(0, 0, 0, $mo, 1, 2000)) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <?php /* The two date fields live in a popover so the row never wraps. */ ?>
            <div class="cl-pop-wrap">
                <button type="button" class="cl-detail-btn is-sm<?= $isCustom ? ' is-accent' : '' ?>" id="customToggle"
                        aria-expanded="<?= $isCustom ? 'true' : 'false' ?>" title="Pick an exact date range">
                    <i class="bi bi-calendar-range"></i><?= $isCustom ? esc($bizCustom['label']) : 'Custom' ?>
                </button>

                <div class="cl-range is-pop<?= $isCustom ? '' : ' d-none' ?>" id="customRange">
                    <div>
                        <label for="biFrom">From</label>
                        <input id="biFrom" type="date" name="from" value="<?= esc($isCustom ? ($bizCustom['from'] ?? '') : '') ?>">
                    </div>
                    <div>
                        <label for="biTo">To</label>
                        <input id="biTo" type="date" name="to" value="<?= esc($isCustom ? ($bizCustom['to'] ?? '') : '') ?>">
                    </div>
                    <button class="cl-detail-btn is-accent is-sm" type="submit"><i class="bi bi-funnel"></i>Apply</button>
                </div>
            </div>

            <?php if ($pickedYear !== null || $isCustom): ?>
                <a class="cl-icon-btn" title="Clear the period filter"
                   href="<?= esc(site_url('customers/' . $customerIdentifier) . '?currency=' . urlencode($active)) ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>

        <?php if (!empty($biz['date_error'])): ?>
            <div class="cl-alert is-inset"><i class="bi bi-exclamation-triangle"></i><?= esc($biz['date_error']) ?></div>
        <?php endif; ?>

        <div class="cl-detail-card-body">
            <div class="cl-kpi-grid">
                <div class="cl-kpi is-success">
                    <div class="cl-kpi-label"><i class="bi bi-cash-stack"></i>Lifetime Revenue</div>
                    <div class="cl-kpi-value"><?= esc($m($revenue)) ?></div>
                    <div class="cl-kpi-foot"><strong><?= $orderCount ?></strong> orders · Avg <?= esc($m($avgOrder)) ?></div>
                </div>
                <div class="cl-kpi <?= $outstanding > 0 ? 'is-danger' : 'is-success' ?>">
                    <div class="cl-kpi-label"><i class="bi bi-hourglass-split"></i>Outstanding</div>
                    <div class="cl-kpi-value"><?= esc($m($outstanding)) ?></div>
                    <div class="cl-kpi-foot"><strong><?= $openCount ?></strong> open invoice(s)</div>
                </div>
                <div class="cl-kpi is-info">
                    <div class="cl-kpi-label"><i class="bi bi-check2-circle"></i>Collected</div>
                    <div class="cl-kpi-value"><?= esc($m($collected)) ?></div>
                    <div class="cl-kpi-foot"><?= $draft > 0 ? 'Draft ' . esc($m($draft)) : 'No draft payments' ?></div>
                </div>
                <div class="cl-kpi">
                    <div class="cl-kpi-label"><i class="bi bi-box-seam"></i>Volume</div>
                    <div class="cl-kpi-value"><?= number_format($units, 0) ?></div>
                    <div class="cl-kpi-foot">units · <strong><?= (int) ($bizLife['unique_products'] ?? 0) ?></strong> product(s)</div>
                </div>
            </div>

            <div class="cl-strip">
                <span><span class="cl-strip-label">This year</span><strong><?= esc($m($yearRevenue)) ?></strong></span>
                <span><span class="cl-strip-label">This month</span><strong><?= esc($m($monthRevenue)) ?></strong></span>
                <span><span class="cl-strip-label">Selected range</span><strong><?= ($bizCustom['enabled'] ?? false) ? esc($m($customRevenue)) : '—' ?></strong></span>
                <span><span class="cl-strip-label">Last order</span><strong><?= $lastOrder ? esc($lastOrder) : '—' ?></strong></span>
            </div>

            <?php if ($collectRate !== null): ?>
                <div class="cl-meter">
                    <div class="cl-meter-head">
                        <span>Collection health</span>
                        <strong><?= number_format($collectRate, 1) ?>% collected</strong>
                    </div>
                    <div class="cl-meter-track" role="img" aria-label="<?= number_format($collectRate, 1) ?>% of billed value collected in <?= esc($active) ?>">
                        <span class="cl-meter-fill" style="width: <?= number_format($collectRate, 2, '.', '') ?>%"></span>
                        <span class="cl-meter-fill is-open" style="width: <?= number_format(100 - $collectRate, 2, '.', '') ?>%"></span>
                    </div>
                    <div class="cl-meter-legend">
                        <span style="color: var(--cl-color-success)"><i></i>Collected <?= esc($m($collected)) ?></span>
                        <span style="color: var(--cl-color-danger)"><i></i>Outstanding <?= esc($m($outstanding)) ?></span>
                        <?php if ($draft > 0): ?>
                            <span><i style="background: var(--cl-color-warning)"></i>Draft <?= esc($m($draft)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ----------------------------------------------- activity / people -->
    <div class="cl-split">

        <!-- One card, four views: the whole trading record without four boxes. -->
        <section class="cl-detail-card" data-tabs>
            <div class="cl-detail-card-head is-tabs">
                <div class="cl-tabs" role="tablist">
                    <button type="button" class="cl-tab is-on" data-tab="orders"><i class="bi bi-receipt"></i>Sales Orders</button>
                    <button type="button" class="cl-tab" data-tab="quotes"><i class="bi bi-file-earmark-text"></i>Quotations</button>
                    <button type="button" class="cl-tab" data-tab="invoices">
                        <i class="bi bi-exclamation-circle"></i>Pending Invoices
                        <?php if ($openCount): ?><span class="cl-tab-count is-danger"><?= $openCount ?></span><?php endif; ?>
                    </button>
                    <button type="button" class="cl-tab" data-tab="payments"><i class="bi bi-cash-coin"></i>Payments</button>
                </div>
            </div>

            <!-- Sales orders -->
            <div class="cl-detail-card-body is-flush" data-panel="orders">
                <?php if (empty($orders)): ?>
                    <div class="cl-empty"><i class="bi bi-receipt"></i><span class="cl-empty-title">No <?= esc($active) ?> sales orders</span></div>
                <?php else: ?>
                    <table class="cl-detail-table">
                        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="is-num">Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td><a href="<?= site_url('sales-orders/view/' . (int) $o['id']) ?>" target="_blank"><?= esc($o['order_number'] ?? ('SO-' . (int) $o['id'])) ?></a></td>
                                    <td><?= esc($realDate($o['order_date'] ?? null) ?? '—') ?></td>
                                    <td><span class="cl-chip <?= $statusTone($o['status'] ?? null) ?>"><?= esc($o['status'] ?? '—') ?></span></td>
                                    <td class="is-num is-strong"><?= esc($m($o['total'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cl-card-foot"><span>Latest 5 of <?= $orderCount ?> <?= esc($active) ?> order(s)</span><a href="<?= site_url('sales-orders') ?>">View all <i class="bi bi-arrow-right"></i></a></div>
                <?php endif; ?>
            </div>

            <!-- Quotations -->
            <div class="cl-detail-card-body is-flush d-none" data-panel="quotes">
                <?php if (empty($quotes)): ?>
                    <div class="cl-empty"><i class="bi bi-file-earmark-text"></i><span class="cl-empty-title">No <?= esc($active) ?> quotations</span></div>
                <?php else: ?>
                    <table class="cl-detail-table">
                        <thead><tr><th>Quote</th><th>Issued</th><th>Valid Until</th><th>Status</th><th class="is-num">Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($quotes as $q): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('quotations/view/' . (int) $q['id']) ?>" target="_blank"><?= esc($q['quote_number'] ?? ('QT-' . (int) $q['id'])) ?></a>
                                        <?php if (!empty($q['converted_to_sales_order_id'])): ?>
                                            <span class="cl-chip is-success">Won</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($realDate($q['issue_date'] ?? null) ?? '—') ?></td>
                                    <td><?= esc($realDate($q['valid_until'] ?? null) ?? '—') ?></td>
                                    <td><span class="cl-chip <?= $statusTone($q['status'] ?? null) ?>"><?= esc($q['status'] ?? '—') ?></span></td>
                                    <td class="is-num is-strong"><?= esc($m($q['total'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cl-card-foot"><span>Latest 5 quotation(s)</span><a href="<?= site_url('quotations') ?>">View all <i class="bi bi-arrow-right"></i></a></div>
                <?php endif; ?>
            </div>

            <!-- Pending invoices -->
            <div class="cl-detail-card-body is-flush d-none" data-panel="invoices">
                <?php if (empty($unpaid)): ?>
                    <div class="cl-empty">
                        <i class="bi bi-check2-circle"></i>
                        <span class="cl-empty-title">Nothing outstanding</span>
                        <span class="cl-empty-hint">This customer is fully settled in <?= esc($active) ?>.</span>
                    </div>
                <?php else: ?>
                    <div class="cl-detail-scroll">
                        <table class="cl-detail-table">
                            <thead><tr><th>Invoice</th><th>Sales Order</th><th>Due</th><th>Status</th><th class="is-num">Outstanding</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($unpaid as $inv): ?>
                                    <tr>
                                        <td><a href="<?= site_url('customer-invoices/view/' . (int) $inv['id']) ?>" target="_blank"><?= esc($inv['invoice_number'] ?? ('INV-' . (int) $inv['id'])) ?></a></td>
                                        <td>
                                            <?php if (!empty($inv['sales_order_id'])): ?>
                                                <a href="<?= site_url('sales-orders/view/' . (int) $inv['sales_order_id']) ?>" target="_blank"><?= esc($inv['sales_order_number'] ?? ('SO-' . (int) $inv['sales_order_id'])) ?></a>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td><?= esc($realDate($inv['due_date'] ?? null) ?? '—') ?></td>
                                        <td><span class="cl-chip <?= $statusTone($inv['status'] ?? null) ?>"><?= esc($inv['status'] ?? 'open') ?></span></td>
                                        <td class="is-num is-strong" style="color: var(--cl-color-danger)"><?= esc($m($inv['outstanding'] ?? 0)) ?></td>
                                        <td class="is-num"><a class="cl-detail-btn is-sm" href="<?= $payUrl . '&invoice_id=' . (int) $inv['id'] . '&amount=' . (float) ($inv['outstanding'] ?? 0) ?>">Pay</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><th colspan="4">Total outstanding</th><th class="is-num" style="color: var(--cl-color-danger)"><?= esc($m($outstanding)) ?></th><th></th></tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php if (!empty($soPending)): ?>
                        <div class="cl-card-foot is-wrap">
                            <span class="cl-strip-label">Pending by order</span>
                            <?php foreach ($soPending as $so): ?>
                                <a class="cl-chip" href="<?= site_url('sales-orders/view/' . (int) $so['sales_order_id']) ?>" target="_blank">
                                    <?= esc($so['sales_order_number'] ?? ('SO-' . (int) $so['sales_order_id'])) ?>
                                    <strong><?= esc($m($so['pending_amount'] ?? 0)) ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Payments -->
            <div class="cl-detail-card-body is-flush d-none" data-panel="payments">
                <?php if (empty($history)): ?>
                    <div class="cl-empty"><i class="bi bi-cash-stack"></i><span class="cl-empty-title">No <?= esc($active) ?> payments yet</span></div>
                <?php else: ?>
                    <div class="cl-detail-scroll">
                        <table class="cl-detail-table">
                            <thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Status</th><th class="is-num">Amount</th><th class="is-num">Allocated</th></tr></thead>
                            <tbody>
                                <?php foreach ($history as $p): ?>
                                    <tr>
                                        <td><a href="<?= site_url('accounting/customer-payments/view/' . (int) $p['id']) ?>" target="_blank">#<?= (int) ($p['id'] ?? 0) ?></a></td>
                                        <td><?= esc($realDate($p['payment_date'] ?? null) ?? '—') ?></td>
                                        <td><?= esc($p['payment_method'] ?? '—') ?></td>
                                        <td><span class="cl-chip <?= $statusTone($p['status'] ?? 'draft') ?>"><?= esc($p['status'] ?? 'draft') ?></span></td>
                                        <td class="is-num is-strong"><?= esc($m($p['amount'] ?? 0)) ?></td>
                                        <td class="is-num"><?= esc($m($p['allocated_amount'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- People & places: one card, both lists, everything editable in place. -->
        <section class="cl-detail-card" data-tabs id="relationsCard">
            <div class="cl-detail-card-head is-tabs">
                <div class="cl-tabs" role="tablist">
                    <button type="button" class="cl-tab is-on" data-tab="contacts">
                        <i class="bi bi-people"></i>Contacts<span class="cl-tab-count" data-count="contacts"><?= count($contactList) ?></span>
                    </button>
                    <button type="button" class="cl-tab" data-tab="addresses">
                        <i class="bi bi-geo-alt"></i>Addresses<span class="cl-tab-count" data-count="addresses"><?= count($addressList) ?></span>
                    </button>
                    <button type="button" class="cl-tab" data-tab="preview">
                        <i class="bi bi-file-earmark-richtext"></i>On Documents
                    </button>
                </div>
            </div>

            <div class="cl-detail-card-body" data-panel="contacts">
                <div class="cl-relation-head">
                    <span class="cl-strip-label">Tick one to make it the primary contact</span>
                    <button type="button" class="cl-detail-btn is-accent is-sm" data-add="contact"><i class="bi bi-plus-lg"></i>Add</button>
                </div>
                <div class="cl-relation-list" id="contactList"></div>
            </div>

            <div class="cl-detail-card-body d-none" data-panel="addresses">
                <div class="cl-relation-head">
                    <span class="cl-strip-label">Tick one to make it the default address</span>
                    <button type="button" class="cl-detail-btn is-accent is-sm" data-add="address"><i class="bi bi-plus-lg"></i>Add</button>
                </div>
                <div class="cl-relation-list" id="addressList"></div>
            </div>

            <div class="cl-detail-card-body d-none" data-panel="preview">
                <div class="cl-relation-head">
                    <span class="cl-strip-label">The party block exactly as it prints</span>
                    <button type="button" class="cl-detail-btn is-sm" id="previewRefresh" title="Re-read contacts and addresses from the server">
                        <i class="bi bi-arrow-clockwise"></i>Refresh
                    </button>
                </div>
                <div id="docPreview"></div>
            </div>
        </section>
    </div>

    <!-- --------------------------------------------------------- insights -->
    <section class="cl-detail-card" data-tabs>
        <div class="cl-detail-card-head is-tabs">
            <div class="cl-tabs" role="tablist">
                <button type="button" class="cl-tab is-on" data-tab="products"><i class="bi bi-bag-check"></i>Top Products</button>
                <button type="button" class="cl-tab" data-tab="monthly"><i class="bi bi-activity"></i>Monthly Trend</button>
                <button type="button" class="cl-tab" data-tab="yearly"><i class="bi bi-calendar3"></i>Yearly Performance</button>
            </div>
        </div>

        <div class="cl-detail-card-body is-flush" data-panel="products">
            <?php if (empty($topProducts)): ?>
                <div class="cl-empty"><i class="bi bi-basket"></i><span class="cl-empty-title">No <?= esc($active) ?> purchases yet</span></div>
            <?php else: ?>
                <div class="cl-detail-scroll">
                    <table class="cl-detail-table">
                        <thead><tr><th>Product</th><th class="is-num">Qty</th><th class="is-num">Orders</th><th class="is-num">Sales</th></tr></thead>
                        <tbody>
                            <?php foreach ($topProducts as $tp): ?>
                                <?php $share = $topSalesMax > 0 ? ((float) ($tp['total_sales'] ?? 0) / $topSalesMax) * 100 : 0; ?>
                                <tr>
                                    <?php
                                    $tpName   = trim((string) ($tp['product_name'] ?? ''));
                                    $tpDetail = trim((string) ($tp['product_detail'] ?? ''));
                                    $tpCode   = trim((string) ($tp['product_code'] ?? ''));
                                    if ($tpDetail !== '' && strcasecmp($tpDetail, $tpName) === 0) { $tpDetail = ''; }
                                    ?>
                                    <td class="is-strong">
                                        <?= esc($tpName !== '' ? $tpName : '-') ?>
                                        <?php if ($tpCode !== '' || $tpDetail !== ''): ?>
                                            <small class="cl-top-product-meta">
                                                <?php if ($tpCode !== ''): ?><code><?= esc($tpCode) ?></code><?php endif; ?>
                                                <?php if ($tpDetail !== ''): ?><span><?= esc($tpDetail) ?></span><?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="is-num"><?= number_format((float) ($tp['total_qty'] ?? 0), 2) ?></td>
                                    <td class="is-num"><?= (int) ($tp['order_count'] ?? 0) ?></td>
                                    <td class="is-num">
                                        <span class="cl-bar">
                                            <span class="cl-bar-track"><span class="cl-bar-fill" style="width: <?= number_format($share, 2, '.', '') ?>%"></span></span>
                                            <span class="is-strong"><?= esc($m($tp['total_sales'] ?? 0)) ?></span>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Monthly and yearly render identically — same columns, same shape.
        $trends = ['monthly' => ['Month', $monthTrend], 'yearly' => ['Year', $yearTrend]];
        foreach ($trends as $panel => $trend):
            [$heading, $trendRows] = $trend;
        ?>
            <div class="cl-detail-card-body is-flush d-none" data-panel="<?= $panel ?>">
                <?php if (empty($trendRows)): ?>
                    <div class="cl-empty"><i class="bi bi-graph-up"></i><span class="cl-empty-title">No <?= esc($active) ?> data</span></div>
                <?php else: ?>
                    <div class="cl-detail-scroll">
                        <table class="cl-detail-table">
                            <thead><tr><th><?= esc($heading) ?></th><th class="is-num">Orders</th><th class="is-num">Revenue</th></tr></thead>
                            <tbody>
                                <?php foreach ($trendRows as $row): ?>
                                    <tr>
                                        <td class="is-strong"><?= esc($row['period'] ?? '-') ?></td>
                                        <td class="is-num"><?= (int) ($row['order_count'] ?? 0) ?></td>
                                        <td class="is-num is-strong"><?= esc($m($row['revenue'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<!-- ------------------------------------------------------------- dialogs -->
<dialog class="cl-dialog" id="contactDialog">
    <form id="contactForm">
        <header><h3><i class="bi bi-person-lines-fill"></i><span data-title>Add Contact</span></h3></header>
        <div class="cl-dialog-body">
            <input type="hidden" name="id" value="">
            <label>Name <span class="req">*</span><input type="text" name="name" required autocomplete="off"></label>
            <label>Title / Role<input type="text" name="title" placeholder="Consignee, Buyer, Agent" autocomplete="off"></label>
            <label>Email<input type="email" name="email" autocomplete="off"></label>
            <div class="cl-dialog-row">
                <label>Phone<input type="text" name="phone" autocomplete="off"></label>
                <label>Mobile<input type="text" name="mobile" autocomplete="off"></label>
            </div>
            <label class="cl-dialog-check"><input type="checkbox" name="is_primary_contact" value="1">Make this the primary contact</label>
        </div>
        <footer>
            <span class="cl-dialog-error" data-error></span>
            <button type="button" class="cl-detail-btn is-sm" data-close>Cancel</button>
            <button type="submit" class="cl-detail-btn is-primary is-sm">Save Contact</button>
        </footer>
    </form>
</dialog>

<dialog class="cl-dialog" id="addressDialog">
    <form id="addressForm">
        <header><h3><i class="bi bi-geo-alt"></i><span data-title>Add Address</span></h3></header>
        <div class="cl-dialog-body">
            <input type="hidden" name="id" value="">
            <label>Label<input type="text" name="label" placeholder="Head office, Karachi warehouse" autocomplete="off"></label>
            <label>Address Line 1 <span class="req">*</span><input type="text" name="line1" required autocomplete="off"></label>
            <label>Address Line 2<input type="text" name="line2" autocomplete="off"></label>
            <div class="cl-dialog-row">
                <label>Country
                    <select name="country_id">
                        <option value="">— Select —</option>
                        <?php foreach ($countryList as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>State / Province<input type="text" name="state_name" autocomplete="off"></label>
            </div>
            <div class="cl-dialog-row">
                <label>City<input type="text" name="city_name" autocomplete="off"></label>
                <label>Postal Code<input type="text" name="postal_code" autocomplete="off"></label>
            </div>
            <div class="cl-dialog-row">
                <label class="cl-dialog-check"><input type="checkbox" name="is_billing" value="1">Billing</label>
                <label class="cl-dialog-check"><input type="checkbox" name="is_shipping" value="1">Shipping</label>
            </div>
            <label class="cl-dialog-check"><input type="checkbox" name="is_default" value="1">Make this the default address</label>
        </div>
        <footer>
            <span class="cl-dialog-error" data-error></span>
            <button type="button" class="cl-detail-btn is-sm" data-close>Cancel</button>
            <button type="submit" class="cl-detail-btn is-primary is-sm">Save Address</button>
        </footer>
    </form>
</dialog>

<script>
(function () {
    'use strict';

    // ---- tabs: one handler serves every [data-tabs] card on the page -----
    document.querySelectorAll('[data-tabs]').forEach(function (card) {
        card.querySelector('.cl-tabs').addEventListener('click', function (e) {
            var tab = e.target.closest('.cl-tab');
            if (!tab) { return; }
            card.querySelectorAll('.cl-tab').forEach(function (t) { t.classList.toggle('is-on', t === tab); });
            card.querySelectorAll('[data-panel]').forEach(function (p) {
                p.classList.toggle('d-none', p.dataset.panel !== tab.dataset.tab);
            });
        });
    });

    // ---- period picker: choosing is applying -----------------------------
    var picker = document.getElementById('periodPicker');
    if (picker) {
        var form = picker.closest('form');
        var monthSelect = picker.querySelector('[name="month"]');
        var customRange = document.getElementById('customRange');

        picker.querySelectorAll('[data-period]').forEach(function (select) {
            select.addEventListener('change', function () {
                // A month without a year means nothing, so the year drives the month.
                if (select.name === 'year') {
                    monthSelect.disabled = select.value === '';
                    if (select.value === '') { monthSelect.value = ''; }
                }
                // Year/month and a custom range are two ways to say the same thing:
                // whichever the user touched last wins.
                customRange.querySelectorAll('input[type="date"]').forEach(function (i) { i.value = ''; });
                // form.submit() skips the submit event, so unlock the month here.
                monthSelect.disabled = false;
                form.submit();
            });
        });

        var toggle = document.getElementById('customToggle');
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var opening = customRange.classList.toggle('d-none') === false;
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            if (opening) { customRange.querySelector('input[type="date"]').focus(); }
        });

        // A popover that will not close is worse than no popover.
        customRange.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { customRange.classList.add('d-none'); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { customRange.classList.add('d-none'); }
        });

        // A disabled select posts nothing, so re-enable it just before submitting.
        form.addEventListener('submit', function () { monthSelect.disabled = false; });
    }

    // ---- contacts & addresses -------------------------------------------
    var relations = document.getElementById('relationsCard');
    var base = <?= json_encode(rtrim(site_url('customers/' . $customerIdentifier), '/') . '/') ?>;
    var csrfField = <?= json_encode(csrf_token()) ?>;
    var csrfHash = <?= json_encode(csrf_hash()) ?>;

    var state = {
        contact: <?= json_encode($contactList) ?>,
        address: <?= json_encode($addressList) ?>
    };

    function esc(v) {
        return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function post(url, body) {
        var data = new URLSearchParams(body || {});
        data.set(csrfField, csrfHash);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(function (res) {
            return res.json()
                .catch(function () { throw new Error('Unexpected server response.'); })
                .then(function (json) {
                    if (!res.ok || !json.ok) { throw new Error(json.message || 'Request failed.'); }
                    state.contact = json.contacts || [];
                    state.address = json.addresses || [];
                    render();
                });
        });
    }

    // ---- how this customer prints on invoices and quotations -------------
    // Mirrors app/Views/pdf/invoice_system.php: address lines first, then the
    // phone/mobile/email held on the customer record itself.
    var record = <?= json_encode([
        'name'   => (string) ($customer['name'] ?? ($customer['company_name'] ?? 'Customer')),
        'code'   => (string) ($customer['customer_code'] ?? ''),
        'phone'  => (string) ($customer['phone'] ?? ''),
        'mobile' => (string) ($customer['mobile'] ?? ''),
        'email'  => (string) ($customer['email'] ?? ''),
    ]) ?>;

    // Quotations rank the default address first; invoices only look at the
    // billing/shipping flags. Same ordering as the two controllers.
    function pickAddress(defaultFirst) {
        if (!state.address.length) { return null; }
        var keys = defaultFirst
            ? ['is_default', 'is_billing', 'is_shipping']
            : ['is_billing', 'is_shipping'];

        return state.address.slice().sort(function (a, b) {
            for (var i = 0; i < keys.length; i++) {
                var d = (Number(b[keys[i]]) === 1) - (Number(a[keys[i]]) === 1);
                if (d) { return d; }
            }
            return Number(a.id) - Number(b.id);
        })[0];
    }

    function primaryContact() {
        for (var i = 0; i < state.contact.length; i++) {
            if (Number(state.contact[i].is_primary_contact) === 1) { return state.contact[i]; }
        }

        return state.contact[0] || null;
    }

    function docSheet(tag, addr) {
        var who = primaryContact();

        // The person's own number beats the company switchboard when they have one.
        var lines = [];
        if (addr) {
            if (addr.line1) { lines.push(addr.line1); }
            if (addr.line2) { lines.push(addr.line2); }
            var cityState = [addr.city_name, addr.state_name].filter(Boolean).join(' ').trim();
            if (cityState) { lines.push(cityState); }
            if (addr.postal_code) { lines.push('Postal: ' + addr.postal_code); }
        }
        var phone  = (who && who.phone)  || record.phone;
        var mobile = (who && who.mobile) || record.mobile;
        var email  = (who && who.email)  || record.email;
        if (phone)  { lines.push('Phone: ' + phone); }
        if (mobile) { lines.push('Mobile: ' + mobile); }
        if (email)  { lines.push('Email: ' + email); }

        var used = [
            addr ? 'Address: ' + (addr.label || ('#' + addr.id)) : 'No address on file',
            who ? 'Contact: ' + who.name : 'No contact on file'
        ];

        return '<figure class="cl-doc">' +
            '<figcaption class="cl-doc-tag">' + esc(tag) + '</figcaption>' +
            '<p class="cl-doc-kicker">Bill To</p>' +
            '<p class="cl-doc-name">' + esc(record.name) +
                (record.code ? '<span class="cl-doc-code">' + esc(record.code) + '</span>' : '') +
            '</p>' +
            (who ? '<p class="cl-doc-attn">Attn: ' + esc(who.name) +
                (who.title ? ' — ' + esc(who.title) : '') + '</p>' : '') +
            (lines.length
                ? lines.map(function (l) { return '<p class="cl-doc-line">' + esc(l) + '</p>'; }).join('')
                : '<p class="cl-doc-line is-muted">No address on file</p>') +
            '<p class="cl-doc-source">' + esc(used.join('  ·  ')) + '</p>' +
        '</figure>';
    }

    function renderPreview() {
        var forInvoice = pickAddress(false);
        var forQuote   = pickAddress(true);
        var same = forInvoice && forQuote
            ? Number(forInvoice.id) === Number(forQuote.id)
            : forInvoice === forQuote;

        var who = primaryContact();
        var notes = [who
            ? 'Addressed to ' + who.name + '. Their own phone/email is used where they have one, the customer record fills the rest.'
            : 'No contact person yet — the customer record supplies the phone, mobile and email.'];
        if (!same) {
            notes.unshift('Quotations take the default address, invoices take the one ticked Billing. Tick Billing on the default address to make both match.');
        }

        document.getElementById('docPreview').innerHTML =
            '<div class="cl-doc-grid">' +
                (same
                    ? docSheet('Invoices & Quotations', forInvoice)
                    : docSheet('Invoices & Delivery Orders', forInvoice) + docSheet('Quotations', forQuote)) +
            '</div>' +
            notes.map(function (t) {
                return '<p class="cl-doc-note"><i class="bi bi-info-circle"></i>' + esc(t) + '</p>';
            }).join('');
    }

    function contactRow(c) {
        var lines = [];
        if (c.email) { lines.push('<a href="mailto:' + esc(c.email) + '"><i class="bi bi-envelope"></i> ' + esc(c.email) + '</a>'); }
        if (c.phone) { lines.push('<a href="tel:' + esc(c.phone) + '"><i class="bi bi-telephone"></i> ' + esc(c.phone) + '</a>'); }
        if (c.mobile) { lines.push('<a href="tel:' + esc(c.mobile) + '"><i class="bi bi-phone"></i> ' + esc(c.mobile) + '</a>'); }
        var primary = Number(c.is_primary_contact) === 1;

        return '<div class="cl-relation' + (primary ? ' is-default' : '') + '">' +
            '<label class="cl-relation-pick" title="Set as primary contact">' +
                '<input type="radio" name="primaryContact" value="' + c.id + '" data-pick="contact"' + (primary ? ' checked' : '') + '>' +
            '</label>' +
            '<div class="cl-relation-body">' +
                '<p class="cl-relation-title">' + esc(c.name) +
                    (c.title ? '<span class="cl-chip is-muted">' + esc(c.title) + '</span>' : '') +
                    (primary ? '<span class="cl-chip is-success">Primary</span>' : '') +
                '</p>' +
                (lines.length ? '<p class="cl-relation-meta">' + lines.join('<span class="sep">·</span>') + '</p>' : '') +
            '</div>' +
            '<div class="cl-relation-actions">' +
                '<button type="button" class="cl-icon-btn" data-edit="contact" data-id="' + c.id + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="cl-icon-btn is-danger" data-remove="contact" data-id="' + c.id + '" title="Remove"><i class="bi bi-trash"></i></button>' +
            '</div>' +
        '</div>';
    }

    function addressRow(a) {
        var street = [a.line1, a.line2].filter(Boolean).map(esc).join(', ');
        var place = [a.city_name, a.state_name, a.postal_code, a.country_name].filter(Boolean).map(esc).join(', ');
        var isDefault = Number(a.is_default) === 1;
        var tags = '';
        if (Number(a.is_billing)) { tags += '<span class="cl-chip is-warning">Billing</span>'; }
        if (Number(a.is_shipping)) { tags += '<span class="cl-chip is-info">Shipping</span>'; }
        if (isDefault) { tags += '<span class="cl-chip is-success">Default</span>'; }

        return '<div class="cl-relation' + (isDefault ? ' is-default' : '') + '">' +
            '<label class="cl-relation-pick" title="Set as default address">' +
                '<input type="radio" name="defaultAddress" value="' + a.id + '" data-pick="address"' + (isDefault ? ' checked' : '') + '>' +
            '</label>' +
            '<div class="cl-relation-body">' +
                '<p class="cl-relation-title">' + esc(a.label || 'Address') + tags + '</p>' +
                '<p class="cl-relation-meta">' + (street || '—') + (place ? '<br>' + place : '') + '</p>' +
            '</div>' +
            '<div class="cl-relation-actions">' +
                '<button type="button" class="cl-icon-btn" data-edit="address" data-id="' + a.id + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="cl-icon-btn is-danger" data-remove="address" data-id="' + a.id + '" title="Remove"><i class="bi bi-trash"></i></button>' +
            '</div>' +
        '</div>';
    }

    function render() {
        var empty = function (icon, text) {
            return '<div class="cl-empty"><i class="bi bi-' + icon + '"></i><span class="cl-empty-title">' + text + '</span></div>';
        };
        document.getElementById('contactList').innerHTML = state.contact.length
            ? state.contact.map(contactRow).join('')
            : empty('person-plus', 'No contact persons yet');
        document.getElementById('addressList').innerHTML = state.address.length
            ? state.address.map(addressRow).join('')
            : empty('map', 'No addresses yet');
        relations.querySelector('[data-count="contacts"]').textContent = state.contact.length;
        relations.querySelector('[data-count="addresses"]').textContent = state.address.length;
        renderPreview();
    }

    document.getElementById('previewRefresh').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        fetch(base + 'relations', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.message || 'Refresh failed.'); }
                state.contact = json.contacts || [];
                state.address = json.addresses || [];
                render();
            })
            .catch(function (err) { window.alert(err.message || 'Refresh failed.'); })
            .then(function () { btn.disabled = false; });
    });

    var dialogs = {
        contact: { el: document.getElementById('contactDialog'), form: document.getElementById('contactForm') },
        address: { el: document.getElementById('addressDialog'), form: document.getElementById('addressForm') }
    };

    function openDialog(kind, record) {
        var d = dialogs[kind];
        d.form.reset();
        d.form.querySelector('[data-error]').textContent = '';
        d.form.querySelector('[data-title]').textContent = (record ? 'Edit ' : 'Add ') + (kind === 'contact' ? 'Contact' : 'Address');
        d.form.querySelector('[name="id"]').value = record ? record.id : '';

        if (record) {
            Object.keys(record).forEach(function (key) {
                var field = d.form.querySelector('[name="' + key + '"]');
                if (!field || field.type === 'hidden') { return; }
                if (field.type === 'checkbox') { field.checked = Number(record[key]) === 1; }
                else { field.value = record[key] === null ? '' : record[key]; }
            });
        }
        d.el.showModal();
    }

    Object.keys(dialogs).forEach(function (kind) {
        var d = dialogs[kind];
        d.el.querySelector('[data-close]').addEventListener('click', function () { d.el.close(); });

        d.form.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = {};
            new FormData(d.form).forEach(function (value, key) { body[key] = value; });
            var id = body.id;
            delete body.id;

            post(id ? base + 'update-' + kind + '/' + id : base + 'add-' + kind, body)
                .then(function () { d.el.close(); })
                .catch(function (err) { d.form.querySelector('[data-error]').textContent = err.message; });
        });
    });

    relations.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add]');
        if (add) { openDialog(add.dataset.add, null); return; }

        var edit = e.target.closest('[data-edit]');
        if (edit) {
            var record = state[edit.dataset.edit].filter(function (r) { return String(r.id) === edit.dataset.id; })[0];
            if (record) { openDialog(edit.dataset.edit, record); }
            return;
        }

        var remove = e.target.closest('[data-remove]');
        if (remove) {
            if (!window.confirm('Remove this ' + remove.dataset.remove + '?')) { return; }
            post(base + 'delete-' + remove.dataset.remove + '/' + remove.dataset.id)
                .catch(function (err) { window.alert(err.message); });
        }
    });

    // Picking the default is a single click, so it saves straight away.
    relations.addEventListener('change', function (e) {
        var pick = e.target.closest('[data-pick]');
        if (!pick) { return; }
        var url = pick.dataset.pick === 'contact'
            ? base + 'contacts/' + pick.value + '/set-primary'
            : base + 'addresses/' + pick.value + '/set-default';
        post(url).catch(function (err) { window.alert(err.message); render(); });
    });

    render();
})();
</script>

<?= $this->endSection() ?>
