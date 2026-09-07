<?php /** @var array $report */ ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container">
  <h4>Cheque schema ensure report</h4>
  <table class="table table-sm table-bordered">
    <tr><th>Employee column</th><td><?= $report['employee_column'] ? esc(json_encode($report['employee_column'])) : '<em>missing</em>' ?></td></tr>
    <tr><th>Index exists</th><td><?= $report['index_exists'] ? 'Yes' : 'No' ?></td></tr>
    <tr><th>Foreign key fk_cheques_employee</th><td><?= $report['fk_exists'] ? 'Yes' : 'No' ?></td></tr>
    <tr><th>cheques Engine</th><td><?= esc($report['cheques_engine'] ?? 'unknown') ?></td></tr>
    <tr><th>employees Engine</th><td><?= esc($report['employees_engine'] ?? 'unknown') ?></td></tr>
    <tr><th>Orphan employee_id count</th><td><?= (int)($report['orphan_count'] ?? 0) ?></td></tr>
  </table>
  <div class="mt-3">
    <a href="<?= base_url('/accounting/cheques/create') ?>" class="btn btn-secondary btn-sm">Back to cheque form</a>
  </div>
</div>
<?= $this->endSection() ?>
