<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php helper('url'); $customerIdentifier = entityRouteIdentifier($customer); ?>

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Customer Details</h1>
        <div>
            <a href="<?= site_url('accounting/customer-payments/pay?customer_id=' . (int)$customer['id']) ?>" class="btn btn-success">Receive Payment</a>
            <a href="<?= site_url('customers/' . $customerIdentifier . '/edit') ?>" class="btn btn-warning">Edit</a>
            <a href="<?= site_url('customers') ?>" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <?php if(session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>

    <?php $biz = $analytics ?? []; ?>
    <?php $bizLife = $biz['lifetime'] ?? []; ?>
    <?php $bizYear = $biz['yearly'] ?? []; ?>
    <?php $bizMonth = $biz['monthly'] ?? []; ?>
    <?php $bizCustom = $biz['custom'] ?? []; ?>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">Business Intelligence</h5>
            <form method="get" action="<?= site_url('customers/' . $customerIdentifier) ?>" class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label form-label-sm mb-1">From</label>
                    <input type="date" class="form-control form-control-sm" name="from" value="<?= esc($bizCustom['from'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label form-label-sm mb-1">To</label>
                    <input type="date" class="form-control form-control-sm" name="to" value="<?= esc($bizCustom['to'] ?? '') ?>">
                </div>
                <div>
                    <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('customers/' . $customerIdentifier) ?>">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (!empty($biz['date_error'])): ?>
                <div class="alert alert-warning py-2 mb-3"><?= esc($biz['date_error']) ?></div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Lifetime Orders</div>
                        <div class="h4 mb-1"><?= (int)($bizLife['order_count'] ?? 0) ?></div>
                        <div class="small text-muted">Business Given: <?= number_format((float)($bizLife['revenue'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Current Year</div>
                        <div class="h4 mb-1"><?= (int)($bizYear['order_count'] ?? 0) ?> orders</div>
                        <div class="small text-muted">Revenue: <?= number_format((float)($bizYear['revenue'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Current Month</div>
                        <div class="h4 mb-1"><?= (int)($bizMonth['order_count'] ?? 0) ?> orders</div>
                        <div class="small text-muted">Revenue: <?= number_format((float)($bizMonth['revenue'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Custom Date Range</div>
                        <div class="h4 mb-1"><?= (int)($bizCustom['order_count'] ?? 0) ?> orders</div>
                        <div class="small text-muted">Revenue: <?= number_format((float)($bizCustom['revenue'] ?? 0), 2) ?></div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Total Quantity Bought</div>
                        <div class="h4 mb-1"><?= number_format((float)($bizLife['units_bought'] ?? 0), 2) ?></div>
                        <div class="small text-muted">Unique Products: <?= (int)($bizLife['unique_products'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Avg Order Value</div>
                        <div class="h4 mb-1"><?= number_format((float)($bizLife['avg_order_value'] ?? 0), 2) ?></div>
                        <div class="small text-muted">Owner KPI</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Outstanding Balance</div>
                        <div class="h4 mb-1 text-danger"><?= number_format((float)($receivableSummary['total_receivable'] ?? 0), 2) ?></div>
                        <div class="small text-muted">Advance: <?= number_format((float)($receivableSummary['advance_balance'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="border rounded p-2 h-100 bg-light">
                        <div class="text-muted small">Relationship Span</div>
                        <div class="small mb-1">First Order: <?= esc($bizLife['first_order_date'] ?? '-') ?></div>
                        <div class="small">Last Order: <?= esc($bizLife['last_order_date'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>Name:</strong> <?= esc($customer['name']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Code:</strong> <?= esc($customer['customer_code']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Type:</strong> <?= esc($customer['type']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <span class="badge bg-<?= ($customer['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                <?= esc($customer['status']) ?>
                            </span>
                        </div>
                        <?php $metadata = json_decode($customer['metadata'] ?? '{}', true); ?>
                        <?php if (!empty($customer['company_name']) || !empty($metadata['company_name'])): ?>
                            <div class="col-md-6">
                                <strong>Company Name:</strong> <?= esc($customer['company_name'] ?? $metadata['company_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($metadata['legacy_number'])): ?>
                            <div class="col-md-6">
                                <strong>Legacy Number:</strong> <?= esc($metadata['legacy_number']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($customer['email']) || !empty($metadata['primary_email'])): ?>
                            <div class="col-md-6">
                                <strong>Primary Email:</strong> <?= esc($customer['email'] ?? $metadata['primary_email']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($customer['phone']) || !empty($metadata['phone'])): ?>
                            <div class="col-md-6">
                                <strong>Phone:</strong> <?= esc($customer['phone'] ?? $metadata['phone']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($customer['mobile']) || !empty($metadata['mobile'])): ?>
                            <div class="col-md-6">
                                <strong>Mobile:</strong> <?= esc($customer['mobile'] ?? $metadata['mobile']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($customer['website']) || !empty($metadata['website'])): ?>
                            <div class="col-md-6">
                                <strong>Website:</strong> <?= esc($customer['website'] ?? $metadata['website']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Avatar</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($customer['avatar_path'])): ?>
                        <img src="<?= base_url($customer['avatar_path']) ?>" alt="Avatar" class="img-fluid rounded-circle" style="max-width: 150px;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                            <span class="text-muted">No Avatar</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Contacts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($contacts)): ?>
                        <p class="text-muted">No contacts found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($contacts as $contact): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= esc($contact['name']) ?></h6>
                                            <?php if (!empty($contact['email'])): ?>
                                                <p class="mb-1"><small>Email: <?= esc($contact['email']) ?></small></p>
                                            <?php endif; ?>
                                            <?php if (!empty($contact['phone'])): ?>
                                                <p class="mb-1"><small>Phone: <?= esc($contact['phone']) ?></small></p>
                                            <?php endif; ?>
                                            <?php if (!empty($contact['role'])): ?>
                                                <p class="mb-0"><small>Role: <?= esc($contact['role']) ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Addresses</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($addresses)): ?>
                        <p class="text-muted">No addresses found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($addresses as $address): ?>
                                <?php
                                    // normalize address fields (support both customer_addresses schema and legacy inline fields)
                                    $atype = $address['type'] ?? (
                                        !empty($address['is_shipping']) ? 'Shipping Address' : (
                                            !empty($address['is_billing']) ? 'Billing Address' : 'Address'
                                        )
                                    );
                                    $line1 = $address['line1'] ?? ($address['line_1'] ?? '');
                                    $line2 = $address['line2'] ?? ($address['line_2'] ?? '');
                                    $city = $address['city_name'] ?? ($address['city'] ?? '');
                                    $state = $address['state_name'] ?? ($address['state'] ?? '');
                                    $postal = $address['postal_code'] ?? ($address['postal'] ?? '');
                                    $country = $address['country'] ?? ($address['country_name'] ?? '');
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= esc($atype) ?>
                                                <?php if (!empty($address['is_shipping'])): ?>
                                                    <span class="badge bg-info">Shipping</span>
                                                <?php endif; ?>
                                                <?php if (!empty($address['is_billing'])): ?>
                                                    <span class="badge bg-warning">Billing</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1">
                                                <?= esc($line1) ?>
                                                <?php if (!empty($line2)): ?>, <?= esc($line2) ?><?php endif; ?>
                                            </p>
                                            <p class="mb-0">
                                                <small>
                                                    <?= esc($city) ?><?= (!empty($city) && !empty($state)) ? ', ' : '' ?><?= esc($state) ?> <?= esc($postal) ?>
                                                    <?php if (!empty($country)): ?>, <?= esc($country) ?><?php endif; ?>
                                                </small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Purchased Products</h5>
                </div>
                <div class="card-body p-0">
                    <?php $topProducts = $biz['top_products'] ?? []; ?>
                    <?php if (empty($topProducts)): ?>
                        <div class="p-3 text-muted">No product purchasing data yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $tp): ?>
                                        <tr>
                                            <td><?= esc($tp['product_name'] ?? '-') ?></td>
                                            <td class="text-end"><?= number_format((float)($tp['total_qty'] ?? 0), 2) ?></td>
                                            <td class="text-end"><?= (int)($tp['order_count'] ?? 0) ?></td>
                                            <td class="text-end"><?= number_format((float)($tp['total_sales'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Yearly Performance</h5>
                </div>
                <div class="card-body p-0">
                    <?php $yearTrend = $biz['yearly_trend'] ?? []; ?>
                    <?php if (empty($yearTrend)): ?>
                        <div class="p-3 text-muted">No yearly trend data available.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($yearTrend as $yt): ?>
                                        <tr>
                                            <td><?= esc($yt['period'] ?? '-') ?></td>
                                            <td class="text-end"><?= (int)($yt['order_count'] ?? 0) ?></td>
                                            <td class="text-end"><?= number_format((float)($yt['revenue'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Trend (Last 12 Months)</h5>
                </div>
                <div class="card-body p-0">
                    <?php $monthTrend = $biz['monthly_trend'] ?? []; ?>
                    <?php if (empty($monthTrend)): ?>
                        <div class="p-3 text-muted">No monthly trend data available.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthTrend as $mt): ?>
                                        <tr>
                                            <td><?= esc($mt['period'] ?? '-') ?></td>
                                            <td class="text-end"><?= (int)($mt['order_count'] ?? 0) ?></td>
                                            <td class="text-end"><?= number_format((float)($mt['revenue'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Receivables Snapshot</h5>
                    <a href="<?= site_url('accounting/customer-payments/pay?customer_id=' . (int)$customer['id']) ?>" class="btn btn-sm btn-success">Receive Payment</a>
                </div>
                <div class="card-body">
                    <?php $sum = $receivableSummary ?? []; ?>
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small">Open Invoices</div>
                                <div class="h5 mb-0"><?= (int)($sum['open_invoice_count'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small">Total Pending</div>
                                <div class="h5 mb-0 text-danger"><?= number_format((float)($sum['total_receivable'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small">Posted Payments</div>
                                <div class="h5 mb-0 text-success"><?= number_format((float)($sum['posted_payments_total'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small">Draft Payments</div>
                                <div class="h5 mb-0 text-warning"><?= number_format((float)($sum['draft_payments_total'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-2 col-12">
                            <div class="border rounded p-2 bg-light">
                                <div class="text-muted small">Advance Balance</div>
                                <div class="h5 mb-0 text-primary"><?= number_format((float)($sum['advance_balance'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Unpaid Invoices</h5>
                </div>
                <div class="card-body p-0">
                    <?php $unpaid = $unpaidInvoices ?? []; ?>
                    <?php if (empty($unpaid)): ?>
                        <div class="p-3 text-muted">No unpaid invoices.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Sales Order</th>
                                        <th>Date</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th class="text-end">Outstanding</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($unpaid as $inv): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= site_url('customer-invoices/view/' . (int)$inv['id']) ?>" target="_blank">
                                                <?= esc($inv['invoice_number'] ?? ('INV-' . (int)$inv['id'])) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($inv['sales_order_id'])): ?>
                                                <a href="<?= site_url('sales-orders/view/' . (int)$inv['sales_order_id']) ?>" target="_blank">
                                                    <?= esc($inv['sales_order_number'] ?? ('SO-' . (int)$inv['sales_order_id'])) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= esc($inv['issue_date'] ?? '-') ?></td>
                                        <td><?= esc($inv['due_date'] ?? '-') ?></td>
                                        <td>
                                            <?php $st = strtolower((string)($inv['status'] ?? '')); ?>
                                            <span class="badge bg-<?= $st === 'overdue' ? 'danger' : ($st === 'partially_paid' ? 'warning' : 'secondary') ?>">
                                                <?= esc($inv['status'] ?? 'open') ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-danger fw-semibold"><?= number_format((float)($inv['outstanding'] ?? 0), 2) ?></td>
                                        <td>
                                            <a href="<?= site_url('accounting/customer-payments/pay?customer_id=' . (int)$customer['id'] . '&invoice_id=' . (int)$inv['id'] . '&amount=' . (float)($inv['outstanding'] ?? 0)) ?>" class="btn btn-sm btn-outline-success">Pay</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Pending by Sales Order</h5>
                </div>
                <div class="card-body">
                    <?php $soPending = $orderReceivables ?? []; ?>
                    <?php if (empty($soPending)): ?>
                        <p class="text-muted mb-0">No pending receivables linked to sales orders.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($soPending as $so): ?>
                                <a href="<?= site_url('sales-orders/view/' . (int)$so['sales_order_id']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>
                                        <?= esc($so['sales_order_number'] ?? ('SO-' . (int)$so['sales_order_id'])) ?>
                                        <small class="text-muted d-block"><?= (int)($so['invoice_count'] ?? 0) ?> pending invoice(s)</small>
                                    </span>
                                    <span class="badge bg-danger"><?= number_format((float)($so['pending_amount'] ?? 0), 2) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment History</h5>
                </div>
                <div class="card-body p-0">
                    <?php $history = $paymentHistory ?? []; ?>
                    <?php if (empty($history)): ?>
                        <div class="p-3 text-muted">No payment history yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Allocated</th>
                                        <th>Reference</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($history as $p): ?>
                                    <?php $pst = strtolower((string)($p['status'] ?? 'draft')); ?>
                                    <tr>
                                        <td><?= (int)($p['id'] ?? 0) ?></td>
                                        <td><?= esc($p['payment_date'] ?? '-') ?></td>
                                        <td><?= esc($p['payment_method'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pst === 'posted' ? 'success' : ($pst === 'void' ? 'danger' : 'warning') ?>">
                                                <?= esc($p['status'] ?? 'draft') ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
                                        <td class="text-end"><?= number_format((float)($p['allocated_amount'] ?? 0), 2) ?></td>
                                        <td><?= esc($p['memo'] ?? $p['notes'] ?? '-') ?></td>
                                        <td>
                                            <a href="<?= site_url('accounting/customer-payments/view/' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
