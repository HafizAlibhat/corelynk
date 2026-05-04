<?php
// Simple form to issue products to a subcontractor/vendor
?>
<div class="container">
  <h2>Issue to Subcontractor / Vendor</h2>
  <form method="post" action="<?= base_url('accounting/purchase-orders/issue-submit') ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label>Vendor</label>
      <select name="vendor_id" class="form-control">
        <?php foreach($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>"><?= esc($v['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label>Process/Batch ID (optional)</label>
      <input type="number" name="process_run_id" class="form-control" />
    </div>
    <table class="table table-striped">
      <thead><tr><th>Product</th><th>Qty</th><th>Issue Qty</th></tr></thead>
      <tbody>
      <?php foreach($products as $p): ?>
        <tr>
          <td><?= esc($p['name']) ?> (PID <?= (int)$p['id'] ?>)</td>
          <td><?= (float)($p['current_stock']??0) ?></td>
          <td><input type="number" name="lines[<?= (int)$p['id'] ?>]" step="0.001" class="form-control" /></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-primary">Create Issue</button>
    <a class="btn btn-secondary" href="<?= base_url('accounting/purchase-orders') ?>">Cancel</a>
  </form>
</div>
