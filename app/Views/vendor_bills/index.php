<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Vendor Bills<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $bills          = $bills          ?? [];
  $allBills       = $allBills       ?? $bills;
  $vendors        = $vendors        ?? [];
  $activeStatus   = $activeStatus   ?? '';
  $activePayStatus= $activePayStatus ?? '';
  $activeVendor   = $activeVendor   ?? '';
  $searchTerm     = $searchTerm     ?? '';
  $sortKey        = $sortKey        ?? 'bill_date';
  $sortDir        = $sortDir        ?? 'desc';
  $perPage        = $perPage        ?? 25;
  $page           = $page           ?? 1;
  $totalPages     = $totalPages     ?? 1;
  $totalRecords   = $totalRecords   ?? count($bills);

  // Build a sort URL helper
  function vbSortUrl(string $col, string $currentKey, string $currentDir): string {
      $params = $_GET;
      $params['sort'] = $col;
      $params['dir']  = ($currentKey === $col && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';
      $params['page'] = 1;
      return '?' . http_build_query($params);
  }
  function vbSortIcon(string $col, string $currentKey, string $currentDir): string {
      if ($currentKey !== $col) return '<i class="bi bi-arrow-down-up" style="opacity:.3;font-size:.6rem"></i>';
      return strtolower($currentDir) === 'asc'
          ? '<i class="bi bi-sort-up" style="color:var(--cl-primary);font-size:.65rem"></i>'
          : '<i class="bi bi-sort-down" style="color:var(--cl-primary);font-size:.65rem"></i>';
  }
  // Build pagination URL helper
  function vbPageUrl(int $p): string {
      $params = $_GET; $params['page'] = $p;
      return '?' . http_build_query($params);
  }

  function vbPayStatus(array $row): string {
      $total   = (float)($row['total_amount'] ?? 0);
      $paid    = (float)($row['paid_total']   ?? 0);
      $balance = max(0, $total - $paid);
      // Respect explicit 'paid' or 'cancelled' DB status too
      if (($row['status'] ?? '') === 'cancelled') return 'cancelled';
      if (($row['status'] ?? '') === 'paid' || ($balance <= 0.001 && $total > 0)) return 'paid';
      if ($paid > 0) return 'partial';
      return 'unpaid';
  }
  function vbFmtDate(?string $d): string {
      if (!$d) return '—';
      $ts = strtotime($d);
      return $ts ? date('d/m/Y', $ts) : esc($d);
  }
?>
<style>
    .vbl-wrap   { padding: .6rem .9rem; }
    .vbl-card   { background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: .5rem; }
    .vbl-header { padding: .5rem .85rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--cl-border); }
    .vbl-header-title { font-size: .92rem; font-weight: 700; color: var(--cl-text-primary); }
    .vbl-header-sub   { font-size: .72rem; color: var(--cl-text-muted); }

    /* Filter bar */
    .vbl-filter { display: flex; align-items: flex-end; flex-wrap: wrap; gap: .35rem; padding: .5rem .85rem; border-bottom: 1px solid var(--cl-border); background: var(--cl-surface-alt, var(--cl-surface)); }
    .vbl-filter .form-control, .vbl-filter .form-select {
        font-size: .74rem; padding: .18rem .45rem; height: 26px; border-radius: 4px;
        background: var(--cl-surface); border-color: var(--cl-border); color: var(--cl-text-primary);
    }
    .vbl-filter .form-select { padding-right: 1.6rem; }
    .vbl-filter select.f-vendor { width: 160px; }
    .vbl-filter select.f-status { width: 110px; }
    .vbl-filter input.f-search { width: 170px; }
    .vbl-filter label { font-size: .68rem; color: var(--cl-text-muted); display: block; margin-bottom: .1rem; }
    /* Sortable column headers */
    .vbl-table th a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: .25rem; }
    .vbl-table th a:hover { color: var(--cl-primary); }
    .vbl-btn { display: inline-flex; align-items: center; gap: .3rem; padding: .18rem .6rem; height: 26px; font-size: .73rem; font-weight: 500; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); cursor: pointer; text-decoration: none; transition: all .12s; white-space: nowrap; }
    .vbl-btn:hover { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .vbl-btn-primary { background: var(--cl-primary); color: #fff; border-color: var(--cl-primary); }
    .vbl-btn-primary:hover { background: var(--cl-primary-dark, #1d4ed8); color: #fff; }

    /* Table */
    .vbl-table-wrap { overflow: hidden; border-radius: 0 0 .5rem .5rem; }
    .vbl-table { width: 100%; border-collapse: collapse; font-size: .76rem; }
    .vbl-table th { font-size: .67rem; text-transform: uppercase; letter-spacing: .4px; padding: .45rem .55rem; background: var(--cl-surface-alt, var(--cl-surface)); color: var(--cl-text-muted); border-bottom: 2px solid var(--cl-border); white-space: nowrap; }
    .vbl-table td { padding: .4rem .55rem; border-bottom: 1px solid var(--cl-border); vertical-align: middle; color: var(--cl-text-primary); }
    .vbl-table tbody tr:last-child td { border-bottom: none; }
    .vbl-table tbody tr:hover td { background: rgba(59,130,246,.04); }
    .vbl-table .money { font-variant-numeric: tabular-nums; white-space: nowrap; }
    body.theme-dark .vbl-table tbody tr:nth-child(even) td { background: #0f1a2b; }
    body.theme-dark .vbl-table tbody tr:nth-child(odd)  td { background: #1a2740; }
    body.theme-dark .vbl-table tbody tr:hover td { background: rgba(37,99,235,.1) !important; }

    /* Payment status badges */
    .ps-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .12rem .4rem; border-radius: .25rem; font-size: .64rem; font-weight: 600; white-space: nowrap; }
    .ps-paid      { background: rgba(34,197,94,.12);  color: #16a34a; }
    .ps-partial   { background: rgba(245,158,11,.12); color: #b45309; }
    .ps-unpaid    { background: rgba(239,68,68,.12);  color: #dc2626; }
    .ps-cancelled { background: rgba(148,163,184,.12);color: #64748b; }
    .ps-draft     { background: rgba(148,163,184,.12);color: #94a3b8; }
    body.theme-dark .ps-paid    { color: #4ade80; }
    body.theme-dark .ps-partial { color: #fbbf24; }
    body.theme-dark .ps-unpaid  { color: #f87171; }

    /* Payment count pill */
    .pay-cnt { display: inline-flex; align-items: center; gap: .2rem; font-size: .64rem; color: var(--cl-text-muted); }
    .pay-cnt .dot { width: 5px; height: 5px; border-radius: 50%; background: var(--cl-primary); display: inline-block; }

    /* Footer */
    .vbl-footer { display: flex; align-items: center; justify-content: space-between; padding: .35rem .75rem; border-top: 1px solid var(--cl-border); font-size: .72rem; color: var(--cl-text-muted); }

    /* Advance badge */
    .adv-tag { display:inline-flex; align-items:center; gap:.3rem; font-size:.73rem; padding:.18rem .55rem; border-radius:.25rem; background:rgba(34,197,94,.1); color:#22c55e; font-weight:600; }
    .adv-tag.zero { background:rgba(148,163,184,.1); color:var(--cl-text-muted); }

    /* Pay bar */
    .vbl-pay-bar { background:var(--cl-surface-alt,var(--cl-surface)); border:1px solid var(--cl-border); border-radius:.45rem; padding:.6rem 1rem; margin:.6rem .9rem 0; }
    .vbl-pay-bar .pay-stat { font-size:.78rem; color:var(--cl-text-muted); }
    .vbl-pay-bar .pay-stat strong { color:var(--cl-text-primary); }
    .pay-input { width:95px; text-align:right; font-size:.76rem; padding:.22rem .35rem; border-radius:.25rem; background:var(--cl-surface); border:1px solid var(--cl-border); color:var(--cl-text-primary); }
    .pay-input:focus { border-color:var(--cl-primary); outline:none; box-shadow:0 0 0 2px rgba(59,130,246,.1); }
    .pay-input:disabled { opacity:.35; cursor:not-allowed; }
</style>

<div class="vbl-wrap">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.5rem;">
        <div>
            <h5 class="mb-0 fw-bold" style="color:var(--cl-text-primary)">Vendor Bills</h5>
            <small style="color:var(--cl-text-muted)">Manage and pay outstanding vendor bills</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('accounting/vendor-payments/pay') ?>" class="vbl-btn vbl-btn-primary"><i class="bi bi-cash-coin"></i> Vendor Payments</a>
            <a href="<?= base_url('purchases') ?>" class="vbl-btn"><i class="bi bi-cart3"></i> Purchases</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="vbl-card mb-2">
        <form class="vbl-filter" method="get" id="filterForm">
            <div>
                <label>Vendor</label>
                <select name="vendor_id" id="filter_vendor" class="form-select f-vendor">
                    <option value="">All vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= ((string)$activeVendor === (string)$v['id']) ? 'selected' : '' ?>>
                            <?= esc($v['name'] ?? '—') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>DB Status</label>
                <select name="status" class="form-select f-status">
                    <option value="">All DB</option>
                    <?php foreach (['draft','confirmed','cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($activeStatus === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Pay Status</label>
                <select name="pay_status" class="form-select" style="width:110px">
                    <option value="">All</option>
                    <option value="unpaid"  <?= ($activePayStatus === 'unpaid')  ? 'selected' : '' ?>>Unpaid</option>
                    <option value="partial" <?= ($activePayStatus === 'partial') ? 'selected' : '' ?>>Partial</option>
                    <option value="paid"    <?= ($activePayStatus === 'paid')    ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div>
                <label>Search</label>
                <input type="text" name="search" class="form-control f-search" placeholder="Bill #, vendor, notes" value="<?= esc($searchTerm) ?>">
            </div>
            <div>
                <label>Per page</label>
                <select name="per_page" class="form-select" style="width:68px">
                    <?php foreach ([10,25,50,100] as $n): ?>
                        <option value="<?= $n ?>" <?= $perPage == $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="sort" value="<?= esc($sortKey) ?>">
            <input type="hidden" name="dir"  value="<?= esc($sortDir) ?>">
            <div style="padding-bottom:.05rem">
                <label>&nbsp;</label>
                <div class="d-flex gap-1">
                    <button type="submit" class="vbl-btn vbl-btn-primary"><i class="bi bi-search"></i> Filter</button>
                    <a href="<?= base_url('vendor-bills') ?>" class="vbl-btn"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                    <span class="adv-tag zero ms-2" id="adv_tag" style="display:none">
                        Advance: <strong id="adv_amount">0.00</strong>
                    </span>
                </div>
            </div>
        </form>
    </div>

    <!-- Bills Table -->
    <div class="vbl-card">
        <div style="padding:.4rem .75rem; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--cl-border);">
            <span style="font-size:.8rem; font-weight:600; color:var(--cl-text-primary)">
                Bills <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= $totalRecords ?></span>
                <?php if ($totalRecords !== count($bills)): ?>
                    <span style="font-size:.68rem;color:var(--cl-text-muted);font-weight:400">showing <?= count($bills) ?> of <?= $totalRecords ?></span>
                <?php endif; ?>
            </span>
            <div id="pay_header_actions" style="display:none">
                <button type="button" class="vbl-btn" id="btn_auto_apply"><i class="bi bi-magic"></i> Auto Apply Advance</button>
            </div>
        </div>
        <div class="vbl-table-wrap">
            <div style="overflow-x:auto">
                <table class="vbl-table">
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" class="form-check-input" id="chk_all"></th>
                            <th style="width:52px">
                                <a href="<?= vbSortUrl('id',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Bill # <?= vbSortIcon('id',$sortKey,$sortDir) ?></a>
                            </th>
                            <th style="width:90px">
                                <a href="<?= vbSortUrl('bill_date',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Date <?= vbSortIcon('bill_date',$sortKey,$sortDir) ?></a>
                            </th>
                            <th>
                                <a href="<?= vbSortUrl('vendor',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Vendor <?= vbSortIcon('vendor',$sortKey,$sortDir) ?></a>
                            </th>
                            <th style="width:52px">PO</th>
                            <th style="width:100px">Pay Status</th>
                            <th style="width:90px">
                                <a href="<?= vbSortUrl('last_paid',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Last Paid <?= vbSortIcon('last_paid',$sortKey,$sortDir) ?></a>
                            </th>
                            <th style="width:55px; text-align:center">
                                <a href="<?= vbSortUrl('payment_count',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit" title="Number of payments">Pmts <?= vbSortIcon('payment_count',$sortKey,$sortDir) ?></a>
                            </th>
                            <th class="text-end" style="width:105px">
                                <a href="<?= vbSortUrl('total',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Total <?= vbSortIcon('total',$sortKey,$sortDir) ?></a>
                            </th>
                            <th class="text-end" style="width:105px">
                                <a href="<?= vbSortUrl('paid',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Paid <?= vbSortIcon('paid',$sortKey,$sortDir) ?></a>
                            </th>
                            <th class="text-end" style="width:105px">
                                <a href="<?= vbSortUrl('balance',$sortKey,$sortDir) ?>" class="text-decoration-none" style="color:inherit">Balance <?= vbSortIcon('balance',$sortKey,$sortDir) ?></a>
                            </th>
                            <th class="text-end" style="width:105px" title="Pay from advance">Advance</th>
                            <th class="text-end" style="width:105px" title="Pay via cash/bank">Cash/Bank</th>
                            <th style="width:60px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="bills_tbody">
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="14" class="text-center" style="padding:2.5rem; color:var(--cl-text-muted)">
                            <i class="bi bi-receipt fs-3 d-block mb-2 opacity-50"></i>No vendor bills found.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $row):
                            $total      = (float)($row['total_amount']    ?? 0);
                            $paidAmt    = (float)($row['paid_total']      ?? 0);
                            $payCount   = (int)  ($row['payment_count']   ?? 0);
                            $lastPaid   = $row['last_payment_date']       ?? null;
                            $bal        = max(0, $total - $paidAmt);
                            $payStatus  = vbPayStatus($row);
                            $isPayable  = $payStatus !== 'paid' && $payStatus !== 'cancelled' && $payStatus !== 'draft' && $bal > 0;
                            $psBadgeClass = match($payStatus) {
                                'paid'      => 'ps-paid',
                                'partial'   => 'ps-partial',
                                'cancelled' => 'ps-cancelled',
                                'draft'     => 'ps-draft',
                                default     => 'ps-unpaid',
                            };
                            $psLabel = match($payStatus) {
                                'paid'      => '<i class="bi bi-check-circle-fill"></i> Paid',
                                'partial'   => '<i class="bi bi-half"></i> Partial',
                                'cancelled' => '<i class="bi bi-x-circle"></i> Cancelled',
                                'draft'     => '<i class="bi bi-pencil"></i> Draft',
                                default     => '<i class="bi bi-exclamation-circle"></i> Unpaid',
                            };
                        ?>
                        <tr class="<?= $isPayable ? 'bill-payable' : '' ?>"
                            data-bill-id="<?= (int)($row['id'] ?? 0) ?>"
                            data-vendor-id="<?= (int)($row['vendor_id'] ?? 0) ?>"
                            data-balance="<?= $bal ?>"
                            data-total="<?= $total ?>"
                            data-status="<?= esc($row['status'] ?? 'draft') ?>">
                            <td>
                                <?php if ($isPayable): ?>
                                    <input type="checkbox" class="form-check-input bill-chk">
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-semibold" style="font-size:.75rem">#<?= (int)($row['id'] ?? 0) ?></span></td>
                            <td style="font-size:.73rem; color:var(--cl-text-muted)"><?= vbFmtDate($row['bill_date'] ?? null) ?></td>
                            <td style="font-size:.75rem; font-weight:500"><?= esc($row['vendor_name'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($row['po_id'])): ?>
                                    <a href="<?= base_url('purchases/po/' . (int)$row['po_id']) ?>" class="text-decoration-none" style="font-size:.73rem">#<?= (int)$row['po_id'] ?></a>
                                <?php else: ?><span style="color:var(--cl-text-muted)">—</span><?php endif; ?>
                            </td>
                            <td><span class="ps-badge <?= $psBadgeClass ?>"><?= $psLabel ?></span></td>
                            <td style="font-size:.72rem; color:var(--cl-text-muted)"><?= vbFmtDate($lastPaid) ?></td>
                            <td class="text-center">
                                <?php if ($payCount > 0): ?>
                                    <span class="pay-cnt"><span class="dot"></span><?= $payCount ?></span>
                                <?php else: ?><span style="color:var(--cl-text-muted);font-size:.7rem">—</span><?php endif; ?>
                            </td>
                            <td class="text-end money"><?= number_format($total, 2) ?></td>
                            <td class="text-end money" style="color:<?= $paidAmt > 0 ? '#22c55e' : 'var(--cl-text-muted)' ?>"><?= number_format($paidAmt, 2) ?></td>
                            <td class="text-end money fw-semibold" style="color:<?= $bal > 0 ? '#ef4444' : '#22c55e' ?>"><?= number_format($bal, 2) ?></td>
                            <td class="text-end">
                                <?php if ($isPayable): ?>
                                    <input type="number" step="0.01" min="0" max="<?= $bal ?>" class="pay-input adv-pay" value="0.00" disabled>
                                <?php else: ?><span style="color:var(--cl-text-muted)">—</span><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($isPayable): ?>
                                    <input type="number" step="0.01" min="0" max="<?= $bal ?>" class="pay-input cash-pay" value="0.00" disabled>
                                <?php else: ?><span style="color:var(--cl-text-muted)">—</span><?php endif; ?>
                            </td>
                            <td>
                                <a class="vbl-btn" style="font-size:.68rem; padding:.1rem .35rem" href="<?= base_url('vendor-bills/' . (int)($row['id'] ?? 0)) ?>"><i class="bi bi-eye"></i> View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($allBills)): ?>
        <div class="vbl-footer">
            <?php
                $totSum  = array_sum(array_column($allBills, 'total_amount'));
                $paidSum = array_sum(array_column($allBills, 'paid_total'));
                $balSum  = max(0, $totSum - $paidSum);
            ?>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex align-items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= vbPageUrl(1) ?>" class="vbl-btn" style="padding:.1rem .3rem;font-size:.65rem"><i class="bi bi-chevron-double-left"></i></a>
                    <a href="<?= vbPageUrl($page - 1) ?>" class="vbl-btn" style="padding:.1rem .3rem;font-size:.65rem"><i class="bi bi-chevron-left"></i></a>
                <?php endif; ?>
                <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="<?= vbPageUrl($p) ?>" class="vbl-btn<?= $p === $page ? ' vbl-btn-primary' : '' ?>" style="padding:.1rem .4rem;font-size:.68rem;min-width:26px;justify-content:center"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= vbPageUrl($page + 1) ?>" class="vbl-btn" style="padding:.1rem .3rem;font-size:.65rem"><i class="bi bi-chevron-right"></i></a>
                    <a href="<?= vbPageUrl($totalPages) ?>" class="vbl-btn" style="padding:.1rem .3rem;font-size:.65rem"><i class="bi bi-chevron-double-right"></i></a>
                <?php endif; ?>
                <span style="font-size:.68rem;color:var(--cl-text-muted);margin-left:.3rem">Page <?= $page ?> of <?= $totalPages ?></span>
            </div>
            <?php else: ?>
            <span><?= $totalRecords ?> bill(s)</span>
            <?php endif; ?>
            <span style="font-size:.71rem">
                Total: <strong class="ms-1"><?= number_format($totSum, 2) ?></strong>
                &nbsp;·&nbsp; Paid: <strong class="ms-1" style="color:#22c55e"><?= number_format($paidSum, 2) ?></strong>
                &nbsp;·&nbsp; Outstanding: <strong class="ms-1" style="color:#ef4444"><?= number_format($balSum, 2) ?></strong>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Bar (shown when bills are checked) -->
    <div class="vbl-pay-bar mt-2" id="pay_bar" style="display:none">
        <div class="row align-items-center g-2">
            <div class="col-auto"><div class="pay-stat">Selected: <strong id="ps_count">0</strong> bills</div></div>
            <div class="col-auto"><div class="pay-stat">Outstanding: <strong id="ps_outstanding" style="color:#ef4444">0.00</strong></div></div>
            <div class="col-auto"><div class="pay-stat">Advance: <strong id="ps_advance" style="color:#22c55e">0.00</strong></div></div>
            <div class="col-auto"><div class="pay-stat">Cash/Bank: <strong id="ps_cash" style="color:#3b82f6">0.00</strong></div></div>
            <div class="col-auto"><div class="pay-stat">Total: <strong id="ps_total" style="font-size:.9rem">0.00</strong></div></div>
            <div class="col-auto ms-auto d-flex gap-2 align-items-center">
                <select class="form-select form-select-sm" id="pay_method" style="width:auto; font-size:.76rem; height:26px; padding:.18rem .45rem">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="online_transfer">Online</option>
                </select>
                <select class="form-select form-select-sm" id="pay_source" style="width:auto; font-size:.76rem; height:26px; padding:.18rem .45rem">
                    <option value="">Source Account</option>
                </select>
                <button type="button" class="vbl-btn" id="btn_pay">Save Draft</button>
                <button type="button" class="vbl-btn vbl-btn-primary" id="btn_pay_post" disabled>Confirm &amp; Post</button>
            </div>
            <div class="col-12"><small id="pay_feedback" class="mt-1 d-block"></small></div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(() => {
  const URLS = {
    balance: '<?= base_url("accounting/cheques/balanceData") ?>',
    draft:   '<?= base_url("accounting/vendor-payments/draft") ?>',
    confirm: '<?= base_url("accounting/vendor-payments/confirm") ?>',
    accounts:'<?= base_url("accounting/journal-lite/accounts") ?>',
  };
  const CSRF = { name: '<?= csrf_token() ?>', value: '<?= csrf_hash() ?>' };

  const q  = s => document.querySelector(s);
  const qa = s => document.querySelectorAll(s);

  const el = {
    vendor: q('#filter_vendor'), advTag: q('#adv_tag'), advAmt: q('#adv_amount'),
    chkAll: q('#chk_all'), payBar: q('#pay_bar'), hdrActions: q('#pay_header_actions'),
    btnAuto: q('#btn_auto_apply'), btnPay: q('#btn_pay'), btnPost: q('#btn_pay_post'),
    method: q('#pay_method'), source: q('#pay_source'), fb: q('#pay_feedback'),
    psCount: q('#ps_count'), psOut: q('#ps_outstanding'),
    psAdv: q('#ps_advance'), psCash: q('#ps_cash'), psTotal: q('#ps_total'),
  };

  let advance = 0, draftId = null;
  const activeVendor = '<?= esc($activeVendor) ?>';

  const fmt = v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v||0);
  const num = v => { const n=parseFloat(String(v||'').replace(/,/g,'')); return Number.isFinite(n)?n:0; };
  const msg = (t,txt) => {
    if(!txt){el.fb.innerHTML='';return;}
    const c={success:'#22c55e',warning:'#f59e0b',danger:'#ef4444'};
    el.fb.innerHTML='<span style="color:'+(c[t]||'inherit')+'">'+txt+'</span>';
  };

  const getVid = () => {
    const r = el.vendor ? el.vendor.value : '';
    if(!r) return null;
    const v=parseInt(r,10);
    return Number.isFinite(v)&&v>=0?v:null;
  };

  const loadAdvance = async (vid) => {
    if(vid===null){advance=0;if(el.advTag)el.advTag.style.display='none';return;}
    try{
      const r=await fetch(URLS.balance+'?type=vendor&id='+vid,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d=await r.json();
      if(d&&d.success){
        advance=num(d.advance);
        if(el.advAmt) el.advAmt.textContent=fmt(advance);
        if(el.advTag){el.advTag.style.display='';el.advTag.className=advance>0?'adv-tag ms-2':'adv-tag zero ms-2';}
      } else { advance=0; if(el.advTag)el.advTag.style.display='none'; }
    }catch(e){advance=0;if(el.advTag)el.advTag.style.display='none';}
    refreshPaybar();
  };

  const loadAccounts = async () => {
    try{
      const r=await fetch(URLS.accounts,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d=await r.json();
      if(d&&Array.isArray(d.data)&&el.source){
        let html='<option value="">Source Account</option>';
        d.data.forEach(a=>{const code=a.code||a.account_number||'';html+='<option value="'+a.id+'">'+a.name+(code?' ('+code+')':'')+'</option>';});
        el.source.innerHTML=html;
      }
    }catch(e){}
  };

  const advUsed = (skip) => { let t=0; qa('.adv-pay').forEach(i=>{if(i!==skip)t+=num(i.value);}); return t; };

  const refreshPaybar = () => {
    let cnt=0,selOut=0,selAdv=0,selCash=0;
    qa('tr.bill-payable').forEach(row=>{
      const cb=row.querySelector('.bill-chk');
      const checked=cb&&cb.checked;
      row.classList.toggle('pay-selected',!!checked);
      const advI=row.querySelector('.adv-pay');
      const cashI=row.querySelector('.cash-pay');
      if(advI) advI.disabled=!checked;
      if(cashI) cashI.disabled=!checked;
      if(!checked){if(advI)advI.value='0.00';if(cashI)cashI.value='0.00';}
      if(checked){cnt++;selOut+=num(row.dataset.balance);selAdv+=num(advI?advI.value:0);selCash+=num(cashI?cashI.value:0);}
    });
    if(el.psCount)  el.psCount.textContent=cnt;
    if(el.psOut)    el.psOut.textContent=fmt(selOut);
    if(el.psAdv)    el.psAdv.textContent=fmt(selAdv);
    if(el.psCash)   el.psCash.textContent=fmt(selCash);
    if(el.psTotal)  el.psTotal.textContent=fmt(selAdv+selCash);
    if(el.payBar)   el.payBar.style.display=cnt>0?'':'none';
    if(el.hdrActions) el.hdrActions.style.display=advance>0&&cnt>0?'block':'none';
    if(selAdv>advance&&advance>0) msg('warning','Advance applied ('+fmt(selAdv)+') exceeds available ('+fmt(advance)+')');
    else msg('','');
  };

  qa('.adv-pay').forEach(inp=>{
    inp.addEventListener('input',()=>{
      const row=inp.closest('tr'),bal=num(row.dataset.balance);
      const cashI=row.querySelector('.cash-pay'),cashV=num(cashI?cashI.value:0);
      const other=advUsed(inp),maxAdv=advance>0?Math.min(bal-cashV,Math.max(0,advance-other)):0;
      if(num(inp.value)>maxAdv&&inp.value.trim()!=='') inp.value=maxAdv.toFixed(2);
      refreshPaybar();
    });
    inp.addEventListener('blur',()=>{inp.value=num(inp.value)>0?num(inp.value).toFixed(2):'0.00';refreshPaybar();});
    inp.addEventListener('focus',()=>{if(num(inp.value)===0)inp.value='';});
  });

  qa('.cash-pay').forEach(inp=>{
    inp.addEventListener('input',()=>{
      const row=inp.closest('tr'),bal=num(row.dataset.balance);
      const advI=row.querySelector('.adv-pay'),advV=num(advI?advI.value:0);
      const maxCash=Math.max(0,bal-advV);
      if(num(inp.value)>maxCash&&inp.value.trim()!=='') inp.value=maxCash.toFixed(2);
      refreshPaybar();
    });
    inp.addEventListener('blur',()=>{inp.value=num(inp.value)>0?num(inp.value).toFixed(2):'0.00';refreshPaybar();});
    inp.addEventListener('focus',()=>{if(num(inp.value)===0)inp.value='';});
  });

  qa('.bill-chk').forEach(cb=>cb.addEventListener('change',()=>refreshPaybar()));
  if(el.chkAll) el.chkAll.addEventListener('change',()=>{qa('.bill-chk').forEach(c=>{c.checked=el.chkAll.checked;});refreshPaybar();});

  if(el.btnAuto) el.btnAuto.addEventListener('click',()=>{
    if(advance<=0) return;
    const rows=Array.from(qa('tr.bill-payable'));
    const checked=rows.filter(r=>r.querySelector('.bill-chk')&&r.querySelector('.bill-chk').checked);
    const targets=checked.length>0?checked:rows;
    if(checked.length===0) targets.forEach(r=>{const c=r.querySelector('.bill-chk');if(c)c.checked=true;});
    let rem=advance;
    targets.forEach(r=>{
      const bal=num(r.dataset.balance),ai=r.querySelector('.adv-pay'),ci=r.querySelector('.cash-pay');
      if(!ai||bal<=0) return;
      const cashV=num(ci?ci.value:0),a=Math.min(bal-cashV,rem);
      ai.value=a>0?a.toFixed(2):'0.00'; rem-=a;
    });
    refreshPaybar();
  });

  const spin=(btn,on)=>{if(!btn._t)btn._t=btn.innerHTML;btn.disabled=on;btn.innerHTML=on?'<span class="spinner-border spinner-border-sm me-1"></span>Wait...':btn._t;};

  if(el.btnPay) el.btnPay.addEventListener('click',async()=>{
    const allocs=[]; let totalAdv=0,totalCash=0;
    qa('tr.bill-payable').forEach(row=>{
      const cb=row.querySelector('.bill-chk');
      if(!cb||!cb.checked) return;
      const ai=row.querySelector('.adv-pay'),ci=row.querySelector('.cash-pay');
      const advV=num(ai?ai.value:0),cashV=num(ci?ci.value:0),amt=advV+cashV;
      if(amt<=0) return;
      allocs.push({vendor_bill_id:parseInt(row.dataset.billId,10),amount:amt,cash_amount:cashV,advance_amount:advV});
      totalAdv+=advV; totalCash+=cashV;
    });
    if(allocs.length===0){msg('warning','Select bills and enter amounts.');return;}
    const vendorIds=[...new Set(Array.from(qa('tr.bill-payable')).filter(r=>r.querySelector('.bill-chk')&&r.querySelector('.bill-chk').checked).map(r=>r.dataset.vendorId).filter(Boolean))];
    if(vendorIds.length>1){msg('danger','Cannot pay bills from different vendors in one payment. Filter by vendor first.');return;}
    const vid=vendorIds[0]||getVid();
    if(!vid&&vid!==0&&vid!=='0'){msg('warning','Select a vendor.');return;}
    if(totalCash>0&&el.source&&!el.source.value){msg('warning','Select a source account for cash/bank payment.');return;}
    const payType=totalAdv>0&&totalCash===0?'advance':'settlement';
    const fd=new FormData();
    fd.append(CSRF.name,CSRF.value);
    fd.append('vendor_id',vid);
    fd.append('payment_method',el.method?el.method.value:'cash');
    fd.append('payment_type',payType);
    fd.append('payment_date',new Date().toISOString().slice(0,10));
    fd.append('source_account_id',el.source?el.source.value||'0':'0');
    fd.append('allocations',JSON.stringify(allocs));
    fd.append('advance_amount',totalAdv.toFixed(2));
    spin(el.btnPay,true);msg('','');
    try{
      const r=await fetch(URLS.draft,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d&&d.success){
        draftId=d.payment_id;
        if(el.btnPost) el.btnPost.disabled=false;
        msg('success','Draft #'+d.payment_id+' saved. Click Confirm & Post to finalize.');
        if(getVid()!==null) loadAdvance(getVid());
      } else msg('danger',(d&&d.message)||'Failed to save.');
    }catch(e){msg('danger','Network error.');console.error(e);}
    finally{spin(el.btnPay,false);}
  });

  if(el.btnPost) el.btnPost.addEventListener('click',async()=>{
    if(!draftId){msg('warning','Save a draft first.');return;}
    const fd=new FormData();
    fd.append(CSRF.name,CSRF.value);
    fd.append('payment_id',draftId);
    spin(el.btnPost,true);msg('','');
    try{
      const r=await fetch(URLS.confirm,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d&&d.success){msg('success','Payment posted! Reloading...');setTimeout(()=>location.reload(),1200);}
      else msg('danger',(d&&d.message)||'Failed.');
    }catch(e){msg('danger','Network error.');console.error(e);}
    finally{spin(el.btnPost,false);}
  });

  if(activeVendor!==''){const vid=parseInt(activeVendor,10);if(Number.isFinite(vid)&&vid>=0)loadAdvance(vid);}
  loadAccounts();
  refreshPaybar();
})();
</script>
<?= $this->endSection() ?>
