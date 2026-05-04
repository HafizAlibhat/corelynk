<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>New Purchase UI</title>
    <style>
      body{font-family:Arial, Helvetica, sans-serif;margin:16px}
      nav a{margin-right:12px}
      table{border-collapse:collapse;width:100%;margin-top:8px}
      table,th,td{border:1px solid #ccc;padding:6px}
      .error{color:#a00}
      .success{color:#080}
      .small{font-size:0.9em;color:#444}
    </style>
  </head>
  <body>
    <nav>
      <a href="<?= site_url('newpurchaseui/rfqs') ?>">RFQs</a>
      <a href="<?= site_url('newpurchaseui/pos') ?>">Purchase Orders</a>
      <a href="<?= site_url('newpurchaseui/grn') ?>">GRN</a>
    </nav>
    <hr/>
    <?= $this->renderSection('content') ?>
  </body>
</html>
