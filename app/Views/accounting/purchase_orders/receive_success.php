<?php
// Simple success page after receiving
?>
<div class="container">
  <h3>Receive processed</h3>
  <p><?= esc($message ?? 'Receive completed successfully') ?></p>
  <a class="btn btn-primary" href="<?= base_url('accounting/purchase-orders') ?>">Back to POs</a>
</div>
