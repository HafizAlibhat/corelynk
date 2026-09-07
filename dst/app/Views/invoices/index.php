<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Customer Invoices<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page cl-list-page--dashboard">

  <div class="cl-list-header">
    <div>
      <h2 class="mb-0">Customer Invoices</h2>
      <small class="text-muted">All issued invoices</small>
    </div>
    <a href="<?= base_url('customer-invoices/system/new') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>New Invoice
    </a>
  </div>

  <?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success py-2">
      <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
    </div>
  <?php endif; ?>
  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger py-2">
      <i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
    </div>
  <?php endif; ?>

  <!-- Filters toolbar -->
  <div class="card cl-list-filters mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
      <input type="search" id="invoiceSearch" class="form-control form-control-sm" style="max-width:260px" placeholder="Search invoice #, customer…">
      <select id="statusFilter" class="form-select form-select-sm" style="max-width:150px">
        <option value="">All Statuses</option>
        <option value="draft">Draft</option>
        <option value="confirmed">Confirmed</option>
        <option value="posted">Posted</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary" id="resetFiltersBtn" title="Reset filters">
        <i class="bi bi-arrow-counterclockwise"></i>
      </button>
      <div class="ms-auto text-muted small" id="rowCount"></div>
    </div>
  </div>

  <div class="card cl-list-table-card">
    <div class="card-body p-0">

    <?php if (empty($invoices)): ?>
      <div class="cl-list-empty">
        <span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-file-earmark-x"></i></span>
        <h6>No Invoices Yet</h6>
        <p>Create your first invoice from a Sales Order or manually.</p>
        <a href="<?= base_url('customer-invoices/system/new') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>New Invoice
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="cl-table table table-sm table-hover mb-0" id="invoicesTable">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Customer</th>
              <th>Sales Order #</th>
              <th>Type</th>
              <th>Issue Date</th>
              <th>Due Date</th>
              <th class="text-end">Total</th>
              <th>Status</th>
              <th class="text-end actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $inv):
              $statusRaw  = strtolower(trim($inv['status'] ?? 'draft'));
              $statusLabel = ucfirst($statusRaw ?: 'draft');
              $statusTone = match($statusRaw) {
                'posted', 'paid' => 'success',
                'confirmed' => 'info',
                'cancelled' => 'danger',
                'draft' => 'warning',
                default => 'neutral',
              };
              $issueDate = $inv['issue_date'] ? date('d M Y', strtotime($inv['issue_date'])) : '—';
              $dueDate   = $inv['due_date']   ? date('d M Y', strtotime($inv['due_date']))   : '—';
              $isOverdue = ($statusRaw !== 'posted' && $statusRaw !== 'cancelled'
                            && !empty($inv['due_date']) && strtotime($inv['due_date']) < strtotime('today'));
            ?>
            <tr data-inv="<?= esc(strtolower($inv['invoice_number'] ?? '')) ?>"
                data-customer="<?= esc(strtolower($inv['customer_name'] ?? '')) ?>"
                data-status="<?= esc($statusRaw) ?>">
              <td class="fw-semibold">
                <a href="<?= base_url('customer-invoices/view/' . (int)$inv['id']) ?>" class="text-primary">
                  <?= esc($inv['invoice_number'] ?? ('INV-' . $inv['id'])) ?>
                </a>
              </td>
              <td><?= esc($inv['customer_name'] ?? '—') ?></td>
              <td>
                <?php if (!empty($inv['so_number'])): ?>
                  <span class="fw-semibold text-primary"><?= esc($inv['so_number']) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="cl-status-badge cl-status-badge--neutral"><span class="cl-status-badge__dot"></span><?= esc(ucfirst($inv['invoice_type'] ?? 'system')) ?></span>
              </td>
              <td><?= $issueDate ?></td>
              <td>
                <?php if ($isOverdue): ?>
                  <span class="text-danger fw-semibold"><?= $dueDate ?> <i class="bi bi-exclamation-circle ms-1" title="Overdue"></i></span>
                <?php else: ?>
                  <?= $dueDate ?>
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold cl-data-number">
                <?= esc($inv['currency_code'] ?? 'PKR') ?> <?= number_format((float)($inv['total_amount'] ?? 0), 2) ?>
              </td>
              <td>
                <span class="cl-status-badge cl-status-badge--<?= $statusTone ?>"><span class="cl-status-badge__dot"></span><?= esc($statusLabel) ?></span>
              </td>
              <td class="text-end actions-col">
                <a href="<?= base_url('customer-invoices/view/' . (int)$inv['id']) ?>"
                   class="btn btn-sm cl-icon-action" title="View"><i class="bi bi-eye"></i></a>
                <?php if ($statusRaw === 'draft' || $statusRaw === ''): ?>
                  <a href="<?= base_url('customer-invoices/edit/' . (int)$inv['id']) ?>"
                     class="btn btn-sm cl-icon-action" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php endif; ?>
                <a href="<?= base_url('customer-invoices/pdf/' . (int)$inv['id']) ?>"
                   class="btn btn-sm cl-icon-action" title="PDF" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="px-3 py-2 text-muted small border-top" id="showingCount">
        Showing <?= count($invoices) ?> invoice<?= count($invoices) !== 1 ? 's' : '' ?>
      </div>
    <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  const searchEl  = document.getElementById('invoiceSearch');
  const statusEl  = document.getElementById('statusFilter');
  const resetBtn  = document.getElementById('resetFiltersBtn');
  const countEl   = document.getElementById('rowCount');
  const showingEl = document.getElementById('showingCount');

  function applyFilters(){
    const q   = (searchEl  && searchEl.value  || '').trim().toLowerCase();
    const st  = (statusEl  && statusEl.value  || '');
    let vis = 0, total = 0;
    document.querySelectorAll('#invoicesTable tbody tr').forEach(function(tr){
      total++;
      const inv      = tr.dataset.inv      || '';
      const customer = tr.dataset.customer || '';
      const status   = tr.dataset.status   || '';
      let show = true;
      if (q  && !inv.includes(q) && !customer.includes(q)) show = false;
      if (st && status !== st) show = false;
      tr.style.display = show ? '' : 'none';
      if (show) vis++;
    });
    if (countEl)   countEl.textContent   = vis + ' / ' + total;
    if (showingEl) showingEl.textContent = 'Showing ' + vis + ' of ' + total + ' invoice' + (total !== 1 ? 's' : '');
  }

  if (searchEl) searchEl.addEventListener('input',  applyFilters);
  if (statusEl) statusEl.addEventListener('change', applyFilters);
  if (resetBtn) resetBtn.addEventListener('click',  function(){
    if (searchEl) searchEl.value = '';
    if (statusEl) statusEl.value = '';
    applyFilters();
  });

  applyFilters();
})();
</script>
<?= $this->endSection() ?>
