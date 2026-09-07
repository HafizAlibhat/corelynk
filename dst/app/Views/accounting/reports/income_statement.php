<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Income Statement<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <div class="section-icon section-accent-green"><i class="bi bi-graph-up"></i></div>
            <div>
              <h5 class="mb-0 section-title">Income Statement</h5>
              <small class="section-sub">Period: <?= esc($from) ?> to <?= esc($to) ?></small>
            </div>
          </div>
          <form class="d-flex gap-2" method="get" action="">
            <input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm" />
            <input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm" />
            <button class="btn btn-sm btn-primary" type="submit">Apply</button>
          </form>
        </div>
        <div class="card-body">
          <?php
            $revenueTotal = 0; $expenseTotal = 0;
          ?>
          <h6 class="mb-2">Revenue</h6>
          <div class="table-responsive mb-3">
            <table class="table table-sm table-striped">
              <tbody>
                <?php foreach ($rows as $r): if ($r['type'] !== 'Revenue') continue; $amt = (float)$r['credits'] - (float)$r['debits']; $revenueTotal += $amt; ?>
                  <tr>
                    <td style="width:120px;"><?= esc($r['code']) ?></td>
                    <td><?= esc($r['name']) ?></td>
                    <td class="text-end" style="width:160px;"><?= number_format($amt, 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="fw-semibold">
                  <td colspan="2" class="text-end">Total Revenue</td>
                  <td class="text-end"><?= number_format($revenueTotal, 2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <h6 class="mb-2">Expenses</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <tbody>
                <?php foreach ($rows as $r): if ($r['type'] !== 'Expense') continue; $amt = (float)$r['debits'] - (float)$r['credits']; $expenseTotal += $amt; ?>
                  <tr>
                    <td style="width:120px;"><?= esc($r['code']) ?></td>
                    <td><?= esc($r['name']) ?></td>
                    <td class="text-end" style="width:160px;"><?= number_format($amt, 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="fw-semibold">
                  <td colspan="2" class="text-end">Total Expenses</td>
                  <td class="text-end"><?= number_format($expenseTotal, 2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-3 d-flex justify-content-end">
            <?php $net = $revenueTotal - $expenseTotal; ?>
            <div class="fs-6">Net Income: <span class="badge <?= $net>=0?'bg-success':'bg-danger' ?>"><?= number_format($net,2) ?></span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
