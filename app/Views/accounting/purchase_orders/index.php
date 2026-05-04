<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Purchase Orders<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <div class="section-icon section-accent-orange"><i class="bi bi-cart"></i></div>
            <div>
              <h5 class="mb-0">Purchase Orders</h5>
              <small class="text-muted">Procurement</small>
            </div>
          </div>
          <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#poCreate">New PO</button>
        </div>
        <div class="collapse" id="poCreate">
          <div class="card-body border-bottom">
            <form method="post" action="<?= site_url('accounting/purchase-orders/create') ?>" class="row g-2">
              <div class="col-md-3">
                <select name="vendor_id" class="form-select form-select-sm" required>
                  <option value="">Vendor</option>
                  <?php foreach ($vendors as $v): ?>
                    <option value="<?= esc($v['id']) ?>"><?= esc($v['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" />
              </div>
              <div class="col-md-2">
                <input type="text" name="currency_code" value="PKR" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <div class="border rounded p-2 bg-light">
                  <small class="text-muted">Lines (enter at least one):</small>
                  <div id="po-lines" class="d-flex flex-column gap-2 mt-2">
                    <div class="line row g-1">
                      <div class="col-md-4"><input type="text" name="lines[0][description]" placeholder="Description" class="form-control form-control-sm" /></div>
                      <div class="col-md-2"><input type="number" step="0.001" min="0" name="lines[0][qty]" placeholder="Qty" class="form-control form-control-sm" /></div>
                      <div class="col-md-2"><input type="number" step="0.01" min="0" name="lines[0][unit_price]" placeholder="Unit Price" class="form-control form-control-sm" /></div>
                      <div class="col-md-2 pt-1"><button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="addLine()">+</button></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12 text-end">
                <button class="btn btn-sm btn-success">Create PO</button>
              </div>
            </form>
            <script>
              let lineIndex = 1;
              function addLine(){
                const container = document.getElementById('po-lines');
                const div = document.createElement('div');
                div.className='line row g-1';
                div.innerHTML = `
                  <div class="col-md-4"><input type="text" name="lines[${lineIndex}][description]" placeholder="Description" class="form-control form-control-sm" /></div>
                  <div class="col-md-2"><input type="number" step="0.001" min="0" name="lines[${lineIndex}][qty]" placeholder="Qty" class="form-control form-control-sm" /></div>
                  <div class="col-md-2"><input type="number" step="0.01" min="0" name="lines[${lineIndex}][unit_price]" placeholder="Unit Price" class="form-control form-control-sm" /></div>
                  <div class="col-md-2 pt-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.line').remove()">-</button></div>
                `;
                container.appendChild(div);
                lineIndex++;
              }
            </script>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Vendor</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th class="text-end">Subtotal</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td><?= esc($o['id']) ?></td>
                    <td><?= esc($o['vendor_id']) ?></td>
                    <td><?= esc($o['order_date']) ?></td>
                    <td><span class="badge bg-secondary"><?= esc($o['status']) ?></span></td>
                    <td class="text-end"><?= number_format($o['subtotal'],2) ?></td>
                    <td class="text-end"><?= number_format($o['total'],2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
