<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Balance Sheet<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <div class="section-icon section-accent-purple"><i class="bi bi-columns-gap"></i></div>
            <div>
              <h5 class="mb-0 section-title">Balance Sheet</h5>
              <small class="section-sub">As of <?= esc($to) ?></small>
            </div>
          </div>
          <form class="d-flex gap-2" method="get" action="">
            <input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm" />
            <button class="btn btn-sm btn-primary" type="submit">Apply</button>
          </form>
        </div>
        <div class="card-body">
          <style>.tb-sticky thead th{position:sticky;top:0;background:#f8f9fa;z-index:2}.tb-condensed.table-sm td,.tb-condensed.table-sm th{padding:.35rem .5rem}</style>
          <?php
            $assets = []; $liabilities = []; $equity = [];
            foreach ($rows as $r) {
              if ($r['type'] === 'Asset') $assets[] = $r; elseif ($r['type'] === 'Liability') $liabilities[] = $r; elseif ($r['type'] === 'Equity') $equity[] = $r;
            }
            // Compute balances: Assets as debit-credit; Liabilities/Equity as credit-debit to show natural-side positive
            $assetTotal = 0; foreach ($assets as $a) { $assetTotal += (float)$a['debits'] - (float)$a['credits']; }
            $liabilityTotal = 0; foreach ($liabilities as $l) { $liabilityTotal += (float)$l['credits'] - (float)$l['debits']; }
            $equityTotal = 0; foreach ($equity as $e) { $equityTotal += (float)$e['credits'] - (float)$e['debits']; }
          ?>
          <div class="row">
            <div class="col-md-6">
              <h6 class="mb-2">Assets</h6>
              <table class="table table-sm tb-sticky tb-condensed">
                <tbody>
                  <?php foreach ($assets as $a): $bal = (float)$a['debits'] - (float)$a['credits']; ?>
                  <tr>
                    <td style="width:120px;"><?= esc($a['code']) ?></td>
                    <td><?= esc($a['name']) ?></td>
                    <td class="text-end" style="width:160px;"><?= number_format($bal,2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="fw-semibold">
                    <td colspan="2" class="text-end">Total Assets</td>
                    <td class="text-end"><?= number_format($assetTotal,2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="col-md-6">
              <h6 class="mb-2">Liabilities</h6>
              <table class="table table-sm tb-sticky tb-condensed">
                <tbody>
                  <?php foreach ($liabilities as $l): $bal = (float)$l['credits'] - (float)$l['debits']; ?>
                  <tr>
                    <td style="width:120px;"><?= esc($l['code']) ?></td>
                    <td><?= esc($l['name']) ?></td>
                    <td class="text-end" style="width:160px;"><?= number_format($bal,2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="fw-semibold">
                    <td colspan="2" class="text-end">Total Liabilities</td>
                    <td class="text-end"><?= number_format($liabilityTotal,2) ?></td>
                  </tr>
                </tfoot>
              </table>
              <h6 class="mb-2 mt-4">Equity</h6>
              <table class="table table-sm tb-sticky tb-condensed">
                <tbody>
                  <?php foreach ($equity as $e): $bal = (float)$e['credits'] - (float)$e['debits']; ?>
                  <tr>
                    <td style="width:120px;"><?= esc($e['code']) ?></td>
                    <td><?= esc($e['name']) ?></td>
                    <td class="text-end" style="width:160px;"><?= number_format($bal,2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="fw-semibold">
                    <td colspan="2" class="text-end">Total Equity</td>
                    <td class="text-end"><?= number_format($equityTotal,2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="mt-3 d-flex justify-content-end">
            <?php $balanceOk = abs(($liabilityTotal + $equityTotal) - $assetTotal) < 0.01; ?>
            <div class="fs-6">Equation Check: <span class="badge <?= $balanceOk ? 'bg-success':'bg-danger' ?>">Assets <?= number_format($assetTotal,2) ?> = Liabilities + Equity <?= number_format($liabilityTotal + $equityTotal,2) ?></span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
