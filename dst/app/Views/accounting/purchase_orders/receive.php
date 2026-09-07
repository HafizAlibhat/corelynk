<?php
// Minimal receive view for Purchase Orders
// Expects $po, $lines
?>
<div class="container">
  <h2>Receive Purchase Order #<?= esc($po['id']) ?> - Vendor ID <?= esc($po['vendor_id']) ?></h2>
  <form method="post" action="<?= base_url('accounting/purchase-orders/receive') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="po_id" value="<?= esc($po['id']) ?>" />
    <table class="table table-striped">
      <thead><tr><th>Product</th><th>Ordered</th><th>Already Received</th><th>Receive Qty</th></tr></thead>
      <tbody>
      <?php foreach($lines as $ln): ?>
        <tr>
          <td><?= esc($ln['description']?:'') ?> <?php if($ln['product_id']): ?>(PID <?= esc($ln['product_id']) ?>)<?php endif ?></td>
          <td><?= (float)$ln['qty'] ?></td>
          <td><?= (float)$ln['qty_received'] ?></td>
          <td><input type="number" step="0.001" class="form-control" name="lines[<?= (int)$ln['id'] ?>][received]" value="" /></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button class="btn btn-primary">Confirm Receive</button>
    <a class="btn btn-secondary" href="<?= base_url('accounting/purchase-orders') ?>">Cancel</a>
  </form>
</div>
