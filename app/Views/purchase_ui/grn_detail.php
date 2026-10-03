<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>GRN <?= esc($grn['grn_number'] ?? ('#'.($grn['id'] ?? ''))) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $grnId       = (int)($grn['id'] ?? 0);
  $vendorId    = (int)($grn['vendor_id'] ?? 0);
  $poId        = (int)($grn['po_id'] ?? 0);
  $warehouseId = (int)($grn['movement_warehouse_id'] ?? 0);
  $locationId  = (int)($grn['movement_location_id'] ?? 0);

  $receivedAtRaw   = !empty($grn['received_at']) ? $grn['received_at'] : ($grn['created_at'] ?? null);
  $receivedAtLabel = $receivedAtRaw ? date('d M Y, H:i', strtotime((string)$receivedAtRaw)) : 'N/A';
  $createdAtLabel  = !empty($grn['created_at'])  ? date('d M Y, H:i', strtotime((string)$grn['created_at']))  : 'N/A';

  $createdByLabel = trim((string)($grn['created_by_username'] ?? ''));
  if ($createdByLabel === '') $createdByLabel = trim((string)($grn['created_by_name'] ?? ''));
  if ($createdByLabel === '') $createdByLabel = !empty($grn['created_by']) ? ('User #'.(int)$grn['created_by']) : 'System';

  $locationLabel = trim((string)($grn['location_path'] ?? ''));
  if ($locationLabel === '') $locationLabel = (string)($grn['location_name'] ?? 'N/A');

  $poPublicId = trim((string)($grn['po_public_id'] ?? ''));
  $poUrl = $poPublicId !== '' ? site_url('new-purchase-orders/'.$poPublicId)
         : ($poId > 0 ? site_url('new-purchase-orders/'.$poId) : '');

  $vendorPublicId = trim((string)($grn['vendor_public_id'] ?? ''));
  $vendorUrl = $vendorPublicId !== '' ? site_url('vendors/'.$vendorPublicId)
             : ($vendorId > 0 ? site_url('vendors/'.$vendorId) : '');
    $allBillsUrl = site_url('vendor-bills' . (!empty($grn['po_number']) ? ('?search=' . urlencode((string)$grn['po_number'])) : ''));
    $relatedBills = $relatedBills ?? [];

  $summaryReceivedQty = 0.0; $summaryPayableQty = 0.0; $summaryFreeQty = 0.0;
    $summaryGrossValue  = 0.0; $summaryPayableValue = 0.0;
    $summaryTotalWeightKg = 0.0; $hasWeightData = false;
    $summaryOrderedQty = 0.0; $summaryExtraQty = 0.0;
    $summaryBillableExtraQty = 0.0; $summaryBillableExtraValue = 0.0;
    $summaryNonBillableExtraQty = 0.0; $summaryNonBillableExtraValue = 0.0;
    $extraReasonBreakdown = [];
    $extraLineBreakdown = [];
    $splitOverQty = static function(array $ln, float $overQty, string $reasonType): array {
      $overQty = max(0.0, (float)$overQty);
      $payableExtra = 0.0;
      $freeExtra = 0.0;
      $payableReasonType = in_array($reasonType, ['vendor_extra','extra_ordered'], true) ? $reasonType : '';

      $rawSplit = trim((string)($ln['over_receipt_split_json'] ?? ''));
      if ($rawSplit !== '') {
        $split = json_decode($rawSplit, true);
        if (is_array($split)) {
          $payableExtra = max(0.0, (float)($split['payable_qty'] ?? 0));
          $freeExtra = max(0.0, (float)($split['free_qty'] ?? 0));
          $payableReasonType = strtolower(trim((string)($split['payable_reason_type'] ?? $payableReasonType)));
          if (!in_array($payableReasonType, ['vendor_extra','extra_ordered'], true)) {
            $payableReasonType = $payableExtra > 0.0001 ? ($payableReasonType !== '' ? 'vendor_extra' : '') : '';
          }
        }
      }

      if ($payableExtra <= 0.0001 && $freeExtra <= 0.0001) {
        if (in_array($reasonType, ['vendor_extra','extra_ordered'], true)) {
          $payableExtra = $overQty;
          $payableReasonType = $reasonType;
        } elseif ($reasonType === 'replacement_free') {
          $freeExtra = $overQty;
        }
      }

      if ($payableExtra > $overQty) $payableExtra = $overQty;
      if ($freeExtra > $overQty) $freeExtra = $overQty;
      $sum = $payableExtra + $freeExtra;
      if (abs($overQty - $sum) > 0.02) {
        if ($sum > 0.0001) {
          $ratio = $overQty / $sum;
          $payableExtra = $payableExtra * $ratio;
          $freeExtra = max(0.0, $overQty - $payableExtra);
        } else {
          $payableExtra = in_array($reasonType, ['vendor_extra','extra_ordered'], true) ? $overQty : 0.0;
          $freeExtra = $payableExtra > 0.0001 ? 0.0 : $overQty;
        }
      }

      if ($payableExtra <= 0.0001) {
        $payableReasonType = '';
      } elseif (!in_array($payableReasonType, ['vendor_extra','extra_ordered'], true)) {
        $payableReasonType = 'vendor_extra';
      }

      return [
        'payable_extra' => (float)$payableExtra,
        'free_extra' => (float)$freeExtra,
        'payable_reason_type' => $payableReasonType,
      ];
    };
  foreach (($lines ?? []) as $sln) {
      $sQty     = (float)($sln['qty_received'] ?? 0);
      $sOverQty = max(0.0, (float)($sln['over_received_qty'] ?? 0));
      $sUnit    = (float)($sln['unit_price'] ?? ($sln['unit_cost'] ?? 0));
      $sReason  = strtolower(trim((string)($sln['over_receipt_reason_type'] ?? '')));
      $sUnitWeight = (float)($sln['product_unit_weight'] ?? 0);
      $sWeight  = $sUnitWeight > 0 ? $sUnitWeight : (float)($sln['product_weight'] ?? 0);
      $sWeightUnit = strtolower(trim((string)($sln['product_weight_unit'] ?? 'kg')));
      $splitQty = $splitOverQty($sln, $sOverQty, $sReason);
      $sPayableExtra = (float)($splitQty['payable_extra'] ?? 0.0);
      $sFreeExtra = (float)($splitQty['free_extra'] ?? 0.0);
      $sPayableReason = (string)($splitQty['payable_reason_type'] ?? '');
      $sBaseQty = max(0.0, $sQty - $sOverQty);
      $sPayQty  = $sBaseQty + $sPayableExtra;
      $sFreeQty = $sFreeExtra;
      $sWeightKg = 0.0;
      if ($sWeight > 0) {
        $hasWeightData = true;
        if ($sWeightUnit === 'g') {
          $sWeightKg = $sWeight / 1000;
        } elseif ($sWeightUnit === 'lbs' || $sWeightUnit === 'lb') {
          $sWeightKg = $sWeight * 0.45359237;
        } elseif ($sWeightUnit === 'oz') {
          $sWeightKg = $sWeight * 0.0283495231;
        } else {
          $sWeightKg = $sWeight;
        }
      }
      $summaryReceivedQty  += $sQty;
      $summaryPayableQty   += $sPayQty;
      $summaryFreeQty      += $sFreeQty;
      $summaryOrderedQty   += ((isset($sln['ordered_qty']) && (float)$sln['ordered_qty'] > 0)
        ? (float)$sln['ordered_qty']
        : max(0.0, $sQty - $sOverQty));
      if ($sOverQty > 0.0001) {
        $summaryExtraQty += $sOverQty;

        if ($sPayableExtra > 0.0001) {
          $reasonKey = $sPayableReason !== '' ? $sPayableReason : 'vendor_extra';
          if (!isset($extraReasonBreakdown[$reasonKey])) {
            $extraReasonBreakdown[$reasonKey] = 0.0;
          }
          $extraReasonBreakdown[$reasonKey] += $sPayableExtra;
          $summaryBillableExtraQty += $sPayableExtra;
          $summaryBillableExtraValue += ($sPayableExtra * $sUnit);
        }

        if ($sFreeExtra > 0.0001) {
          if (!isset($extraReasonBreakdown['replacement_free'])) {
            $extraReasonBreakdown['replacement_free'] = 0.0;
          }
          $extraReasonBreakdown['replacement_free'] += $sFreeExtra;
          $summaryNonBillableExtraQty += $sFreeExtra;
          $summaryNonBillableExtraValue += ($sFreeExtra * $sUnit);
        }

        $sOrderedQty = ((isset($sln['ordered_qty']) && (float)$sln['ordered_qty'] > 0)
          ? (float)$sln['ordered_qty']
          : max(0.0, $sQty - $sOverQty));
        $extraLineBreakdown[] = [
          'product' => (string)($sln['product_name'] ?? ($sln['description'] ?? 'Product')),
          'ordered' => $sOrderedQty,
          'received' => $sQty,
          'extra' => $sOverQty,
          'reason' => ($sPayableExtra > 0.0001 && $sFreeExtra > 0.0001) ? 'mixed' : ($sPayableExtra > 0.0001 ? ($sPayableReason ?: 'vendor_extra') : 'replacement_free'),
          'unit_price' => $sUnit,
        ];
      }
      $summaryGrossValue   += ($sQty * $sUnit);
      $summaryPayableValue += ($sPayQty * $sUnit);
      $summaryTotalWeightKg += ($sWeightKg * $sQty);
  }
  $lineCount = count($lines ?? []);
  $expectedBaseValue = max(0.0, $summaryGrossValue - ($summaryExtraQty > 0 ? ($summaryExtraQty * (($summaryReceivedQty > 0) ? ($summaryGrossValue / $summaryReceivedQty) : 0)) : 0));
  $expectedExtraValue = max(0.0, $summaryExtraQty > 0 ? ($summaryGrossValue - max(0.0, $summaryGrossValue - ($summaryExtraQty * (($summaryReceivedQty > 0) ? ($summaryGrossValue / $summaryReceivedQty) : 0)))) : 0.0);
  $baseBills = [];
  $extraBills = [];
  $unknownBills = [];
  foreach (($relatedBills ?? []) as $rbx) {
    $bo = strtolower(trim((string)($rbx['based_on'] ?? '')));
    if ($bo === 'po_over_receipt') {
      $extraBills[] = $rbx;
    } elseif ($bo === 'po_qty') {
      $baseBills[] = $rbx;
    } else {
      $unknownBills[] = $rbx;
    }
  }
  if (!empty($unknownBills)) {
    foreach ($unknownBills as $ub) {
      $amt = (float)($ub['total_amount'] ?? 0);
      // Legacy records may not have based_on; infer using amount against extra and base expected values.
      if ($summaryExtraQty > 0.0001 && $amt > 0.0001 && $amt <= ($summaryBillableExtraValue > 0 ? ($summaryBillableExtraValue + 0.01) : (($summaryGrossValue - $summaryPayableValue) + 0.01))) {
        $extraBills[] = $ub;
      } else {
        $baseBills[] = $ub;
      }
    }
  }

  $usedInferredBillableExtra = false;
  $displayPayableQty = $summaryPayableQty;
  $displayPayableValue = $summaryPayableValue;
  if ($summaryBillableExtraQty <= 0.0001 && $summaryExtraQty > 0.0001 && !empty($extraBills)) {
    // Legacy case: extra qty exists and extra bill exists, but reason type was not captured.
    $usedInferredBillableExtra = true;
    $displayPayableQty = $summaryReceivedQty;
    $displayPayableValue = $summaryGrossValue;
  }
  $allRelatedBillsCleared = !empty($relatedBills);
  foreach (($relatedBills ?? []) as $rbx) {
    if ((float)($rbx['balance'] ?? 0) > 0.0001) {
      $allRelatedBillsCleared = false;
      break;
    }
  }
  $payableGapValue = max(0.0, $summaryGrossValue - $displayPayableValue);
?>
<style>
.grn-doc{width:100%;max-width:none;margin:0;border:1px solid var(--cl-color-border);border-radius:var(--cl-radius-xl);background:var(--cl-color-surface-1);box-shadow:var(--cl-shadow-1);overflow:hidden;}
/* Hero */
.grn-hero{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.95rem 1rem .85rem;background:radial-gradient(90% 180% at 100% -20%,rgba(79,70,229,.2),transparent 58%),linear-gradient(118deg,rgba(37,99,235,.11),rgba(79,70,229,.055) 45%,var(--cl-color-surface-1) 78%);color:var(--cl-color-text-primary);position:relative;overflow:hidden;border-bottom:1px solid rgba(79,70,229,.18);}
.grn-hero::before{content:'';position:absolute;inset:0 auto 0 0;width:4px;background:linear-gradient(180deg,#2563eb,#4f46e5 55%,#06b6d4);}
.grn-hero::after{content:'GRN';position:absolute;right:.75rem;top:50%;transform:translateY(-50%);font-size:5.5rem;font-weight:900;color:var(--cl-color-brand-600);opacity:.035;pointer-events:none;user-select:none;line-height:1;}
.grn-hero-main{position:relative;z-index:1;min-width:0;}
.grn-doc-type{display:inline-flex;align-items:center;gap:.35rem;margin-bottom:.35rem;padding:.2rem .58rem;border:1px solid rgba(79,70,229,.2);border-radius:999px;background:rgba(79,70,229,.08);color:var(--cl-color-brand-700);font-size:.62rem;font-weight:800;letter-spacing:.075em;text-transform:uppercase;}
.grn-hero-num{margin-bottom:.16rem;color:var(--cl-color-text-primary);font-size:clamp(1.35rem,2vw,1.65rem);font-weight:800;letter-spacing:-.025em;line-height:1.1;}
.grn-hero-sub{font-size:.72rem;color:var(--cl-color-text-muted);}
.grn-hero-sub a{color:var(--cl-color-brand-700);font-weight:700;text-decoration:none;}
.grn-hero-sub a:hover{text-decoration:underline;}
.grn-hero-actions{position:relative;z-index:1;display:flex;flex:0 0 auto;gap:.35rem;flex-wrap:wrap;justify-content:flex-end;}
.grn-hero-btn{display:inline-flex;align-items:center;justify-content:center;gap:.3rem;min-height:34px;padding:.32rem .65rem;border:1px solid var(--cl-color-border);border-radius:8px;background:var(--cl-color-surface-1);color:var(--cl-color-text-secondary);box-shadow:0 1px 2px rgba(15,23,42,.04);font:inherit;font-size:.7rem;font-weight:750;text-decoration:none;transition:background-color .15s,border-color .15s,color .15s;}
.grn-hero-btn:hover{border-color:var(--cl-color-brand-400);background:var(--cl-color-surface-2);color:var(--cl-color-brand-700);}
.grn-hero-btn:focus-visible{outline:3px solid var(--cl-color-focus);outline-offset:2px;}
.grn-hero-actions .grn-hero-btn:first-child{border-color:rgba(79,70,229,.26);background:linear-gradient(135deg,rgba(37,99,235,.13),rgba(79,70,229,.12));color:var(--cl-color-brand-700);}
.grn-hero-actions .grn-hero-btn:first-child:hover{border-color:var(--cl-color-brand-500);background:linear-gradient(135deg,rgba(37,99,235,.2),rgba(79,70,229,.18));}
/* Facts strip */
.grn-facts{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));background:var(--cl-color-surface-1);}
.grn-fact{min-width:0;padding:.62rem .75rem;border-right:1px solid var(--cl-color-border);border-bottom:1px solid var(--cl-color-border);background:var(--cl-color-surface-1);}
.grn-fact:nth-child(1){background:linear-gradient(135deg,rgba(34,197,94,.09),transparent 72%);}
.grn-fact:nth-child(2),.grn-fact:nth-child(3),.grn-fact:nth-child(4){background:linear-gradient(135deg,rgba(79,70,229,.065),transparent 72%);}
.grn-fact:nth-child(10){background:linear-gradient(135deg,rgba(34,197,94,.1),transparent 72%);}
.grn-fact:nth-child(5n){border-right:none!important;}
.grn-fact:nth-last-child(-n+5){border-bottom:none;}
.grn-fact-lbl{display:flex;align-items:center;gap:.28rem;margin-bottom:.16rem;color:var(--cl-color-text-muted);font-size:.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;line-height:1.1;}
.grn-fact-lbl i{display:inline-grid;place-items:center;width:18px;height:18px;border-radius:5px;background:rgba(79,70,229,.1);color:var(--cl-color-brand-600);font-size:.62rem;}
.grn-fact:nth-child(1) .grn-fact-lbl i,.grn-fact:nth-child(10) .grn-fact-lbl i{background:rgba(34,197,94,.12);color:#168447;}
.grn-fact-val{overflow-wrap:anywhere;color:var(--cl-color-text-primary);font-size:.8rem;font-weight:700;line-height:1.22;}
.grn-fact-val a{color:var(--cl-color-brand-700);text-decoration:none;}
.grn-fact-val a:hover{text-decoration:underline;}
.grn-fact-val.stamp{color:#168447;font-size:.84rem;font-weight:800;}
.c-green{color:#168447!important;} .c-blue{color:var(--cl-color-brand-700)!important;} .c-amber{color:#b96805!important;}
/* Section */
.grn-sec{background:var(--cl-color-surface-1);border:0;border-top:1px solid var(--cl-color-border);}
.grn-sec-hd{display:flex;align-items:center;gap:.5rem;min-height:39px;padding:.48rem .75rem;border-bottom:1px solid var(--cl-color-border);background:var(--cl-color-surface-2);color:var(--cl-color-text-secondary);font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.065em;}
.grn-sec-hd i{color:var(--cl-color-brand-600);font-size:.82rem;}
.grn-sec-badge{display:inline-grid;place-items:center;min-width:22px;height:22px;margin-left:auto;padding:0 .42rem;border-radius:999px;background:rgba(79,70,229,.1);color:var(--cl-color-brand-700);font-size:.62rem;font-weight:800;}
.grn-sec-note{color:var(--cl-color-text-muted);font-size:.66rem;font-weight:500;letter-spacing:0;text-transform:none;}
.grn-sec-bills .grn-sec-hd{background:linear-gradient(90deg,rgba(79,70,229,.11),var(--cl-color-surface-2) 42%);box-shadow:inset 3px 0 0 #6366f1;}
.grn-sec-lines .grn-sec-hd{background:linear-gradient(90deg,rgba(37,99,235,.12),var(--cl-color-surface-2) 42%);box-shadow:inset 3px 0 0 #2563eb;}
.grn-sec-lines .grn-sec-hd i{color:#2563eb;}
.grn-sec-lines .grn-sec-badge{background:rgba(37,99,235,.12);color:#1d4ed8;}
.grn-sec-history .grn-sec-hd{background:linear-gradient(90deg,rgba(245,158,11,.12),var(--cl-color-surface-2) 42%);box-shadow:inset 3px 0 0 #f59e0b;}
.grn-sec-history .grn-sec-hd i{color:#c66a03;}
.grn-sec-history .grn-sec-badge{background:rgba(245,158,11,.14);color:#9a5705;}
.grn-sec-flow{background:linear-gradient(135deg,rgba(20,184,166,.055),var(--cl-color-surface-1) 45%);}
/* Lines table */
.grn-tbl{width:100%;border-collapse:separate;border-spacing:0;color:var(--cl-color-text-secondary);font-size:.76rem;}
.grn-tbl thead th{padding:.46rem .62rem;background:linear-gradient(180deg,rgba(37,99,235,.075),var(--cl-color-surface-2));color:var(--cl-color-text-secondary);font-size:.61rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid rgba(37,99,235,.18);box-shadow:inset 0 1px 0 rgba(37,99,235,.12);white-space:nowrap;}
.grn-tbl tbody tr{border-bottom:1px solid var(--cl-color-border);}
.grn-tbl tbody tr:last-child{border-bottom:none;}
.grn-tbl tbody tr:hover{background:rgba(59,130,246,.07);}
.grn-tbl td{padding:.48rem .62rem;border-bottom:1px solid var(--cl-color-border);vertical-align:middle;}
.line-sr{color:var(--cl-color-text-muted);font-size:.72rem;font-weight:800;text-align:center;}
.prod-thumb-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;padding:0;border:none;background:transparent;cursor:zoom-in;}
.prod-thumb{width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--cl-color-border);background:var(--cl-color-surface-2);box-shadow:0 1px 2px rgba(15,23,42,.08);transition:transform .14s ease, box-shadow .14s ease;}
.prod-thumb-btn:hover .prod-thumb{transform:scale(1.08);box-shadow:0 10px 26px rgba(15,23,42,.16);}
.prod-meta{display:flex;flex-direction:column;gap:.14rem;min-width:0;}
.prod-code{display:inline-flex;align-items:center;gap:.28rem;width:fit-content;max-width:100%;padding:.09rem .45rem .09rem .4rem;border:1px solid var(--cl-color-border);border-radius:6px;background:var(--cl-color-surface-2);color:var(--cl-color-text-secondary);font-size:.68rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-weight:800;line-height:1.2;white-space:nowrap;}
.prod-code small{color:var(--cl-color-text-muted);font-size:.55rem;letter-spacing:.07em;text-transform:uppercase;font-weight:800;}
.prod-name{line-height:1.22;}
.prod-name a{color:var(--cl-color-text-primary);font-weight:750;text-decoration:none;}
.prod-name a:hover{color:var(--cl-color-brand-700);text-decoration:underline;}
.prod-sub{display:-webkit-box;overflow:hidden;color:var(--cl-color-text-muted);font-size:.68rem;line-height:1.22;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.over-chip{display:inline-flex;align-items:center;gap:.2rem;margin-top:.18rem;padding:.1rem .4rem;border:1px solid rgba(37,99,235,.2);border-radius:6px;background:rgba(37,99,235,.08);color:var(--cl-color-brand-700);font-size:.65rem;font-weight:700;}
.over-chip.free{background:rgba(245,158,11,.1);color:#9a5705;border-color:rgba(245,158,11,.25);}
.avail-grid{display:inline-grid;grid-template-columns:auto auto;gap:.1rem .45rem;font-size:.72rem;}
.avail-grid .v{font-weight:700;} .avail-grid .l{color:var(--cl-color-text-muted);}
.metric-main{font-size:.96rem;font-weight:700;line-height:1.05;}
.metric-sub{margin-top:.1rem;color:var(--cl-color-text-muted);font-size:.68rem;line-height:1.15;}
.metric-sub.positive{color:#168447;font-weight:700;}
.metric-sub.warn{color:#b96805;font-weight:700;}
.line-actions{display:flex;gap:.38rem;flex-wrap:wrap;justify-content:flex-end;}
.line-actions .btn{font-size:.72rem;padding:.26rem .6rem;white-space:nowrap;}
.issue-trigger{border-color:rgba(79,70,229,.22);color:var(--cl-color-brand-700);background:rgba(79,70,229,.08);}
.issue-trigger:hover{background:rgba(79,70,229,.14);color:var(--cl-color-brand-800);border-color:var(--cl-color-brand-400);}
.grn-bills{display:flex;gap:.45rem;flex-wrap:wrap;padding:.75rem 1.1rem;}
.grn-bill-chip{position:relative;display:flex;flex-direction:column;gap:.12rem;min-width:165px;padding:.5rem .65rem;border:1px solid var(--cl-color-border);border-radius:8px;background:var(--cl-color-surface-2);overflow:hidden;}
.grn-bill-chip a{color:var(--cl-color-brand-700);font-weight:750;text-decoration:none;}
.grn-bill-chip a:hover{text-decoration:underline;}
.grn-bill-meta{font-size:.67rem;color:var(--cl-color-text-muted);}
.grn-bill-paid-ribbon{position:absolute;top:0;right:0;background:#16a34a;color:#fff;font-size:.58rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:.16rem .38rem;border-bottom-left-radius:.35rem;}
.grn-flow-card{margin:.72rem 1.1rem;padding:.72rem .85rem;border:1px solid rgba(37,99,235,.2);border-radius:9px;background:rgba(37,99,235,.06);}
.grn-flow-title{display:flex;align-items:center;gap:.35rem;margin-bottom:.32rem;color:var(--cl-color-brand-700);font-size:.74rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;}
.grn-flow-txt{color:var(--cl-color-text-secondary);font-size:.78rem;line-height:1.4;}
.grn-flow-list{margin:.45rem 0 0;padding-left:1rem;color:var(--cl-color-text-secondary);font-size:.76rem;}
.grn-flow-list li{margin:.16rem 0;}
.grn-flow-ok{margin-top:.46rem;font-size:.78rem;color:#0f766e;font-weight:700;}
/* Over-receipt notice */
.over-notice{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem 1.2rem;padding:.58rem .8rem;border-bottom:1px solid rgba(37,99,235,.2);background:rgba(37,99,235,.06);color:var(--cl-color-text-secondary);font-size:.76rem;}
.over-notice .chip{display:inline-flex;align-items:center;gap:.25rem;padding:.12rem .55rem;border-radius:999px;background:rgba(37,99,235,.1);color:var(--cl-color-brand-700);font-size:.64rem;font-weight:800;text-transform:uppercase;}
/* Footer */
.grn-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.62rem .8rem;border-top:1px solid var(--cl-color-border);background:var(--cl-color-surface-2);color:var(--cl-color-text-muted);font-size:.7rem;}
.grn-footer a{color:var(--cl-color-brand-700);font-weight:700;text-decoration:none;}
.grn-footer a:hover{text-decoration:underline;}
.grn-lightbox .modal-content{background:rgba(15,23,42,.96);border:1px solid rgba(148,163,184,.25);}
.grn-lightbox .modal-body{padding:1rem;display:flex;align-items:center;justify-content:center;min-height:65vh;}
.grn-lightbox img{max-width:100%;max-height:78vh;object-fit:contain;border-radius:.55rem;background:#0f172a;}
.grn-lightbox .btn-close{filter:invert(1) grayscale(1);}
.issue-modal-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem 1rem;margin-bottom:.8rem;padding:.7rem .85rem;border:1px solid var(--cl-color-border);border-radius:9px;background:var(--cl-color-surface-2);}
.issue-modal-summary .k{color:var(--cl-color-text-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;font-weight:800;}
.issue-modal-summary .v{color:var(--cl-color-text-primary);font-size:.84rem;font-weight:750;}
/* Dark mode */
html[data-bs-theme="dark"] .grn-doc-type,body.theme-dark .grn-doc-type{border-color:rgba(129,140,248,.28);background:rgba(99,102,241,.14);color:#a5b4fc;}
html[data-bs-theme="dark"] .grn-hero,body.theme-dark .grn-hero{background:radial-gradient(90% 180% at 100% -20%,rgba(99,102,241,.22),transparent 58%),linear-gradient(118deg,rgba(37,99,235,.13),rgba(79,70,229,.08) 45%,var(--cl-color-surface-1) 78%);}
html[data-bs-theme="dark"] .grn-fact-val.stamp,body.theme-dark .grn-fact-val.stamp{color:#86efac;}
html[data-bs-theme="dark"] .grn-fact:nth-child(1),html[data-bs-theme="dark"] .grn-fact:nth-child(10),body.theme-dark .grn-fact:nth-child(1),body.theme-dark .grn-fact:nth-child(10){background:linear-gradient(135deg,rgba(34,197,94,.12),transparent 72%);}
html[data-bs-theme="dark"] .grn-fact:nth-child(2),html[data-bs-theme="dark"] .grn-fact:nth-child(3),html[data-bs-theme="dark"] .grn-fact:nth-child(4),body.theme-dark .grn-fact:nth-child(2),body.theme-dark .grn-fact:nth-child(3),body.theme-dark .grn-fact:nth-child(4){background:linear-gradient(135deg,rgba(99,102,241,.11),transparent 72%);}
html[data-bs-theme="dark"] .grn-sec-lines .grn-sec-badge,body.theme-dark .grn-sec-lines .grn-sec-badge{color:#93c5fd;}
html[data-bs-theme="dark"] .grn-sec-history .grn-sec-badge,body.theme-dark .grn-sec-history .grn-sec-badge{color:#fcd34d;}
html[data-bs-theme="dark"] .c-green,body.theme-dark .c-green{color:#86efac!important;}
html[data-bs-theme="dark"] .c-amber,body.theme-dark .c-amber{color:#fcd34d!important;}
html[data-bs-theme="dark"] .over-chip.free,body.theme-dark .over-chip.free{background:rgba(245,158,11,.12);color:#fcd34d;border-color:rgba(245,158,11,.3);}
html[data-bs-theme="dark"] .grn-flow-ok,body.theme-dark .grn-flow-ok{color:#5eead4;}
@media(max-width:1199.98px){.grn-facts{grid-template-columns:repeat(4,minmax(0,1fr));}.grn-fact:nth-child(5n){border-right:1px solid var(--cl-color-border)!important;}.grn-fact:nth-child(4n){border-right:none!important;}.grn-fact:nth-last-child(-n+5){border-bottom:1px solid var(--cl-color-border);}.grn-fact:nth-last-child(-n+2){border-bottom:none;}}
@media(max-width:991.98px){.grn-facts{grid-template-columns:repeat(3,minmax(0,1fr));}.grn-fact:nth-child(4n){border-right:1px solid var(--cl-color-border)!important;}.grn-fact:nth-child(3n){border-right:none!important;}.grn-fact:nth-last-child(-n+2){border-bottom:1px solid var(--cl-color-border);}.grn-fact:last-child{border-bottom:none;}}
@media(max-width:767.98px){.grn-hero{align-items:flex-start;flex-direction:column;padding:.75rem;}.grn-hero-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));width:100%;}.grn-hero-btn{padding-inline:.4rem;}.grn-facts{grid-template-columns:repeat(2,minmax(0,1fr));}.grn-fact:nth-child(3n){border-right:1px solid var(--cl-color-border)!important;}.grn-fact:nth-child(2n){border-right:none!important;}.grn-fact:nth-last-child(-n+2){border-bottom:none;}.grn-sec-hd{align-items:flex-start;flex-wrap:wrap;}.grn-sec-note{flex-basis:100%;order:3;}.grn-tbl{min-width:820px;}.line-actions{justify-content:flex-start;}.issue-modal-summary{grid-template-columns:1fr;}}
@media(max-width:419.98px){.grn-hero-actions{grid-template-columns:repeat(2,minmax(0,1fr));}.grn-facts{grid-template-columns:1fr;}.grn-fact{border-right:none!important;border-bottom:1px solid var(--cl-color-border)!important;}.grn-fact:last-child{border-bottom:none!important;}.grn-footer{align-items:flex-start;flex-direction:column;}}
@media(prefers-reduced-motion:reduce){.grn-hero-btn,.prod-thumb{transition:none;}.prod-thumb-btn:hover .prod-thumb{transform:none;}}
@media print{.grn-hero-actions,.line-actions,.grn-footer a,.prod-thumb-btn{display:none!important;}.grn-doc{max-width:100%;border:0;border-radius:0;box-shadow:none;}.grn-hero{border-radius:0;}.grn-facts,.grn-sec,.grn-footer{border-radius:0;}}
</style>

<div class="grn-doc cl-detail-page">

  <!-- ① HERO -->
  <div class="grn-hero">
    <div class="grn-hero-main">
      <div class="grn-doc-type"><i class="bi bi-box-arrow-in-down"></i>Goods Receipt Note</div>
      <div class="grn-hero-num"><?= esc($grn['grn_number'] ?? ('#'.$grnId)) ?></div>
      <div class="grn-hero-sub">
        <?php if ($poUrl !== ''): ?>
          PO: <a href="<?= esc($poUrl) ?>"><?= esc($grn['po_number'] ?? ('PO #'.$poId)) ?></a> &nbsp;&middot;&nbsp;
        <?php endif; ?>
        Document ID: <span>#<?= $grnId ?></span>
      </div>
    </div>
    <div class="grn-hero-actions">
      <?= view('partials/record_nav', [
          'table'   => 'purchase_grns',
          'id'      => $grn['public_id'] ?? $grnId,
          'pattern' => 'new-purchase-grns/detail/{id}',
          'list'    => 'new-purchase-grns/list',
      ]) ?>
      <a href="<?= esc($allBillsUrl) ?>" class="grn-hero-btn"><i class="bi bi-receipt"></i>Bills</a>
      <button type="button" class="grn-hero-btn" id="grnPrintBtn"><i class="bi bi-printer"></i>Print</button>
      <button type="button" class="grn-hero-btn" id="grnPdfBtn"><i class="bi bi-filetype-pdf"></i>PDF</button>
      <a href="<?= site_url('new-purchase-grns/list') ?>" class="grn-hero-btn"><i class="bi bi-arrow-left"></i>Back</a>
    </div>
  </div>

  <!-- ② KEY FACTS -->
  <div class="grn-facts">
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-calendar-check"></i>Received</div>
      <div class="grn-fact-val stamp"><?= esc($receivedAtLabel) ?></div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-building"></i>Vendor</div>
      <div class="grn-fact-val">
        <?php if ($vendorUrl !== ''): ?>
          <a href="<?= esc($vendorUrl) ?>"><?= esc($grn['vendor_name'] ?? 'Vendor #'.$vendorId) ?></a>
        <?php else: ?>
          <?= esc($grn['vendor_name'] ?? 'N/A') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-geo-alt"></i>Warehouse</div>
      <div class="grn-fact-val">
        <?php if ($warehouseId > 0): ?>
          <a href="<?= site_url('inventory/stock?warehouse='.$warehouseId) ?>"><?= esc($grn['warehouse_name'] ?? 'WH #'.$warehouseId) ?></a>
        <?php else: ?>
          <?= esc($grn['warehouse_name'] ?? 'N/A') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-pin-map"></i>Location</div>
      <div class="grn-fact-val">
        <?php if ($locationId > 0): ?>
          <a href="<?= site_url('inventory/locations') ?>"><?= esc($locationLabel) ?></a>
        <?php else: ?>
          <?= esc($locationLabel) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-person"></i>Created By</div>
      <div class="grn-fact-val"><?= esc($createdByLabel) ?></div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-clock"></i>Created At</div>
      <div class="grn-fact-val"><?= esc($createdAtLabel) ?></div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-list-ol"></i>Lines</div>
      <div class="grn-fact-val"><?= number_format((float)$lineCount, 0) ?></div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-box-seam"></i>Received Qty</div>
      <div class="grn-fact-val"><?= number_format($summaryReceivedQty,2) ?></div>
    </div>
    <div class="grn-fact">
      <div class="grn-fact-lbl"><i class="bi bi-speedometer2"></i>Total Weight</div>
      <div class="grn-fact-val <?= $hasWeightData ? 'c-blue' : '' ?>">
        <?= $hasWeightData ? number_format($summaryTotalWeightKg, 3).' kg' : 'Weight not set' ?>
      </div>
    </div>
    <div class="grn-fact" style="border-right:none;">
      <div class="grn-fact-lbl"><i class="bi bi-cash-stack"></i>Payable Value</div>
      <div class="grn-fact-val c-green"><?= number_format($displayPayableValue,2) ?></div>
    </div>
  </div>

  <?php if ($summaryFreeQty > 0.0001): ?>
  <div class="over-notice">
    <span class="chip"><i class="bi bi-info-circle"></i>Over-Receipt Breakdown</span>
    Received <strong><?= number_format($summaryReceivedQty,2) ?></strong> &mdash;
    Payable <strong><?= number_format($displayPayableQty,2) ?></strong> &mdash;
    Free replacement <strong><?= number_format($summaryFreeQty,2) ?></strong> &mdash;
    Payable value <strong><?= number_format($displayPayableValue,2) ?></strong>
  </div>
  <?php endif; ?>

  <?php if (!empty($relatedBills)): ?>
  <div class="grn-sec grn-sec-bills">
    <div class="grn-sec-hd"><i class="bi bi-receipt"></i>Related Bills<span class="grn-sec-note">Linked with this PO</span><span class="grn-sec-badge"><?= count($relatedBills) ?></span></div>
    <div class="grn-bills">
      <?php foreach ($relatedBills as $rb): ?>
        <?php
          $billIdRaw = trim((string)($rb['public_id'] ?? ''));
          if ($billIdRaw === '') {
              $billIdRaw = (string)((int)($rb['id'] ?? 0));
          }
          $billUrl = site_url('vendor-bills/'.$billIdRaw);
          $billStatus = trim((string)($rb['status'] ?? 'draft'));
          $billBal = (float)($rb['balance'] ?? 0);
          $billPaid = !empty($rb['is_paid']) || $billBal <= 0.0001;
        ?>
        <div class="grn-bill-chip">
          <?php if ($billPaid): ?><span class="grn-bill-paid-ribbon">Paid</span><?php endif; ?>
          <a href="<?= esc($billUrl) ?>"><?= esc($rb['bill_ref'] ?? ('VB-'.(int)($rb['id'] ?? 0))) ?></a>
          <div class="grn-bill-meta"><?= esc($rb['bill_date'] ? date('d M Y', strtotime((string)$rb['bill_date'])) : 'No date') ?> | <?= $billPaid ? 'Paid' : esc(ucfirst($billStatus)) ?></div>
          <div class="grn-bill-meta">Total <?= number_format((float)($rb['total_amount'] ?? 0),2) ?> | <?= $billPaid ? '<span class="c-green">Bal 0.00</span>' : ('Bal '.number_format($billBal,2)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ④ LINES -->
  <div class="grn-sec grn-sec-lines">
    <div class="grn-sec-hd"><i class="bi bi-list-ul"></i>Received Lines<span class="grn-sec-note">Ordered quantity is shown only when it differs from received.</span><span class="grn-sec-badge"><?= $lineCount ?></span></div>
    <div>
      <table class="grn-tbl" style="table-layout:fixed;">
        <thead><tr>
          <th style="width:4%">Sr.</th>
          <th style="width:6%">IMG</th>
          <th style="width:28%">Product</th>
          <th style="width:5%">Unit</th>
          <th style="width:9%" class="text-end">Qty</th>
          <th style="width:9%" class="text-end">Unit Price</th>
          <th style="width:12%" class="text-end">Value</th>
          <th style="width:14%" class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($lines)): ?>
          <tr><td colspan="8" class="text-muted" style="padding:1.5rem 1rem;">No lines recorded.</td></tr>
        <?php else: ?>
          <?php foreach ($lines as $idx => $ln): ?>
          <?php
            $img = base_url('assets/images/no-image.png');
            if (!empty($ln['variant_image'])) {
                $img = base_url('/uploads/variants/'.ltrim($ln['variant_image'],'/'));
            } elseif (!empty($ln['product_images'])) {
                $imgs = is_string($ln['product_images']) ? json_decode($ln['product_images'],true) : $ln['product_images'];
                if (is_array($imgs) && !empty($imgs[0])) $img = base_url('/uploads/products/'.ltrim($imgs[0],'/'));
            }
            $code       = $ln['variant_art_number'] ?? ($ln['product_code'] ?? ($ln['product_sku'] ?? ''));
            $variantTxt = trim(($ln['variant_art_number'] ?? '').' '.($ln['variant_name'] ?? ''));
            $lineName   = $ln['product_name'] ?? 'Product';
            $lineDesc   = $ln['description'] ?? '';
            if ($variantTxt !== '') $lineDesc = $lineDesc !== '' ? ($variantTxt.' | '.$lineDesc) : $variantTxt;
            $qty       = (float)($ln['qty_received'] ?? 0);
            $unitPrice = (float)($ln['unit_price'] ?? ($ln['unit_cost'] ?? 0));
            $total     = $qty * $unitPrice;
            $ordQty    = isset($ln['ordered_qty']) ? (float)$ln['ordered_qty'] : 0.0;
            $overQty   = max(0.0, (float)($ln['over_received_qty'] ?? 0));
            $rType     = strtolower(trim((string)($ln['over_receipt_reason_type'] ?? '')));
            $splitQty  = $splitOverQty($ln, $overQty, $rType);
            $linePayableExtra = (float)($splitQty['payable_extra'] ?? 0.0);
            $lineFreeExtra = (float)($splitQty['free_extra'] ?? 0.0);
            $linePayableReason = (string)($splitQty['payable_reason_type'] ?? '');
            $baseQty   = max(0.0, $qty - $overQty);
            $isLegacyBillableExtra = $usedInferredBillableExtra && $overQty > 0.0001 && $rType === '' && $linePayableExtra <= 0.0001;
            if ($isLegacyBillableExtra) {
              $linePayableExtra = $overQty;
              $lineFreeExtra = 0.0;
              $linePayableReason = $linePayableReason !== '' ? $linePayableReason : 'vendor_extra';
            }
            $payQty    = $baseQty + $linePayableExtra;
            $freeQty   = $lineFreeExtra;
            $payVal    = $payQty * $unitPrice;
            $freeVal   = $freeQty * $unitPrice;
            $lineId    = (int)($ln['id'] ?? 0);
            $lineProductId = (int)($ln['product_id'] ?? 0);
            $lineVariantId = (int)($ln['variant_id'] ?? 0);
            // Product URL uses public_id only — never plain numeric id
            $prodPublicId = trim((string)($ln['product_public_id'] ?? ''));
            $prodUrl = $prodPublicId !== '' ? site_url('products/'.$prodPublicId) : '';
            $stockUrl = $lineProductId > 0
              ? site_url('inventory/stock/product/'.$lineProductId.($lineVariantId > 0 ? '?variant_id='.$lineVariantId : ''))
              : '';
            $sum = $issueSummary[$lineId] ?? ['scrap'=>0,'return_to_vendor'=>0,'send_for_repair'=>0,'receive_repaired_back'=>0];
            $available = max(0.0, $qty - (float)$sum['scrap'] - (float)$sum['return_to_vendor'] - (float)$sum['send_for_repair'] + (float)$sum['receive_repaired_back']);
            $repairOut = max(0.0, (float)$sum['send_for_repair'] - (float)$sum['receive_repaired_back']);
            $showOrderedQty = $ordQty > 0.0 && abs($ordQty - $qty) > 0.0001;
            $productLabel = trim($lineName.($code !== '' ? ' - '.$code : ''));
            $codeLabel = !empty($ln['variant_art_number']) ? 'ART' : 'Code';
            $lineWeightUnit = strtolower(trim((string)($ln['product_weight_unit'] ?? 'kg')));
            $lineUnitWt = (float)($ln['product_unit_weight'] ?? 0);
            $lineUnitWeight = $lineUnitWt > 0 ? $lineUnitWt : (float)($ln['product_weight'] ?? 0);
            $lineWeightKg = 0.0;
            if ($lineUnitWeight > 0) {
              if ($lineWeightUnit === 'g') {
                $lineWeightKg = ($lineUnitWeight / 1000) * $qty;
              } elseif ($lineWeightUnit === 'lbs' || $lineWeightUnit === 'lb') {
                $lineWeightKg = ($lineUnitWeight * 0.45359237) * $qty;
              } elseif ($lineWeightUnit === 'oz') {
                $lineWeightKg = ($lineUnitWeight * 0.0283495231) * $qty;
              } else {
                $lineWeightKg = $lineUnitWeight * $qty;
              }
            }
          ?>
          <tr>
            <td class="line-sr"><?= (int)$idx + 1 ?></td>
            <td>
              <button type="button" class="prod-thumb-btn line-image-open" data-image-src="<?= esc($img, 'attr') ?>" data-image-alt="<?= esc($productLabel, 'attr') ?>" aria-label="Preview <?= esc($productLabel, 'attr') ?> image">
                <img src="<?= esc($img) ?>" alt="<?= esc($productLabel, 'attr') ?>" class="prod-thumb js-product-hover-thumb" data-preview-src="<?= esc($img, 'attr') ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>');">
              </button>
            </td>
            <td>
              <div class="prod-meta">
                <?php if ($code !== ''): ?><span class="prod-code"><small><?= esc($codeLabel) ?></small>&#8203;<?= esc(ltrim($code)) ?></span><?php endif; ?>
                <div class="prod-name">
                  <?php if ($prodUrl !== ''): ?>
                    <a href="<?= esc($prodUrl) ?>" target="_blank" rel="noopener noreferrer"><?= esc($lineName) ?></a>
                  <?php else: ?>
                    <?= esc($lineName) ?>
                  <?php endif; ?>
                </div>
                <?php if (trim($lineDesc) !== '' && $lineDesc !== $lineName): ?>
                  <div class="prod-sub"><?= esc($lineDesc) ?></div>
                <?php endif; ?>
                <?php if ($overQty > 0.0001): ?>
                  <?php if ($linePayableExtra > 0.0001 && $lineFreeExtra > 0.0001): ?>
                    <div class="over-chip">
                      <i class="bi bi-diagram-3"></i>+<?= number_format($overQty,2) ?> extra &middot; Pay <?= number_format($linePayableExtra,2) ?> + Free <?= number_format($lineFreeExtra,2) ?>
                    </div>
                  <?php else: ?>
                    <div class="over-chip <?= $lineFreeExtra > 0.0001 ? 'free' : '' ?>">
                      <i class="bi bi-plus-circle"></i>+<?= number_format($overQty,2) ?> extra
                      <?php if ($linePayableExtra > 0.0001): ?>&middot; <?= esc(str_replace('_',' ', $linePayableReason !== '' ? $linePayableReason : $rType)) ?><?php else: ?>&middot; free replacement<?php endif; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($ln['over_receipt_reason_details'])): ?>
                  <div class="prod-sub">Note: <?= esc($ln['over_receipt_reason_details']) ?></div>
                <?php endif; ?>
              </div>
            </td>
            <td style="color:var(--bs-secondary-color,#6c757d);font-size:.8rem;"><?= esc($ln['product_unit'] ?? 'pcs') ?></td>
            <td class="text-end">
              <div class="metric-main"><?= number_format($qty,2) ?></div>
              <?php if ($showOrderedQty): ?><div class="metric-sub" title="Ordered quantity from purchase order">Ordered <?= number_format($ordQty,2) ?></div><?php endif; ?>
              <?php if ($lineWeightKg > 0.0001): ?><div class="metric-sub">Wt <?= number_format($lineWeightKg,3) ?> kg</div><?php endif; ?>
            </td>
            <td class="text-end"><div class="metric-main" style="font-size:.9rem;"><?= number_format($unitPrice,2) ?></div></td>
            <td class="text-end">
              <div class="metric-main" style="font-size:.9rem;"><?= number_format($total,2) ?></div>
              <?php if (abs($payVal-$total) > 0.0001): ?><div class="metric-sub positive">Payable <?= number_format($payVal,2) ?></div><?php endif; ?>
              <?php if ($freeQty > 0.0001): ?><div class="metric-sub warn">Free <?= number_format($freeVal,2) ?></div><?php endif; ?>
            </td>
            <td class="text-end">
              <div class="line-actions">
                <button
                  type="button"
                  class="btn btn-sm issue-trigger issue-open"
                  data-line-id="<?= $lineId ?>"
                  data-line-name="<?= esc($productLabel, 'attr') ?>"
                  data-available="<?= esc((string)$available, 'attr') ?>"
                  data-repair-outstanding="<?= esc((string)$repairOut, 'attr') ?>"
                >
                  <i class="bi bi-tools"></i> Issue
                </button>
                <?php if ($stockUrl !== ''): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= esc($stockUrl) ?>">
                    <i class="bi bi-bar-chart"></i> Stock
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ⑤ ISSUE HISTORY -->
  <?php $issueHistory = $issueHistory ?? []; ?>
  <div class="grn-sec grn-sec-history">
    <div class="grn-sec-hd"><i class="bi bi-clock-history"></i>Issue History<span class="grn-sec-badge"><?= count($issueHistory) ?></span></div>
    <?php if (empty($issueHistory)): ?>
      <div style="padding:1rem 1.3rem;font-size:.83rem;color:var(--bs-secondary-color,#6c757d);">No issue actions recorded.</div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table table-sm mb-0" style="font-size:.82rem;">
          <thead><tr>
            <th style="padding:.45rem .85rem;font-size:.66rem;text-transform:uppercase;letter-spacing:.06em;">Date</th>
            <th>Line #</th><th>Action</th><th class="text-end">Qty</th><th>Reason</th>
          </tr></thead>
          <tbody>
            <?php foreach ($issueHistory as $h): ?>
            <tr>
              <td style="padding:.42rem .85rem;"><?= esc($h['action_date'] ?? '—') ?></td>
              <td>#<?= (int)($h['grn_line_id'] ?? 0) ?></td>
              <td><span class="badge bg-secondary"><?= esc(strtoupper(str_replace('_',' ',(string)($h['action_type'] ?? '')))) ?></span></td>
              <td class="text-end fw-bold"><?= number_format((float)($h['qty'] ?? 0),2) ?></td>
              <td class="text-muted"><?= esc($h['reason'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($summaryExtraQty > 0.0001): ?>
  <div class="grn-sec grn-sec-flow" style="border-top:none;">
    <div class="grn-flow-card" style="margin:.55rem 1.1rem;">
      <div class="grn-flow-title"><i class="bi bi-info-circle"></i>Extra Qty Flow</div>
      <div class="grn-flow-txt">
        This GRN line went through extra-receipt flow: ordered <strong><?= number_format($summaryOrderedQty,2) ?></strong>, received <strong><?= number_format($summaryReceivedQty,2) ?></strong>, extra <strong><?= number_format($summaryExtraQty,2) ?></strong>.
        Gross value is <strong><?= number_format($summaryGrossValue,2) ?></strong>, payable value is <strong><?= number_format($displayPayableValue,2) ?></strong>.
      </div>
      <?php if (!empty($extraLineBreakdown)): ?>
        <ul class="grn-flow-list">
          <?php foreach ($extraLineBreakdown as $xb): ?>
            <?php
              $reasonRaw = strtolower(trim((string)($xb['reason'] ?? '')));
              $reasonLabel = $reasonRaw !== '' && $reasonRaw !== 'unspecified'
                ? str_replace('_', ' ', $reasonRaw)
                : ($usedInferredBillableExtra ? 'vendor sent extra pcs (payable)' : 'free replacement');
            ?>
            <li>
              <strong><?= esc($xb['product']) ?></strong>: ordered <?= number_format((float)$xb['ordered'],2) ?>,
              received <?= number_format((float)$xb['received'],2) ?>,
              extra <?= number_format((float)$xb['extra'],2) ?>,
              reason <strong><?= esc($reasonLabel) ?></strong>.
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="grn-flow-txt" style="margin-top:.3rem;">
        Bill split: <strong><?= count($baseBills) ?></strong> base bill(s) and <strong><?= count($extraBills) ?></strong> extra bill(s).
      </div>
      <?php if ($allRelatedBillsCleared): ?>
        <div class="grn-flow-ok"><i class="bi bi-check-circle"></i>All related bills are paid (green cards show Paid and balance 0.00).</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ⑥ FOOTER -->
  <div class="grn-footer">
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center;">
      <span>Internal receipt record ID: #<?= $grnId ?></span>
      <span style="font-weight:700;color:var(--bs-body-color,#212529);"><?= $lineCount ?> product<?= $lineCount !== 1 ? 's' : '' ?> in this GRN</span>
      <?php if ($summaryTotalWeightKg > 0.0001): ?>
        <span class="c-blue" style="font-weight:700;">Total weight: <?= number_format($summaryTotalWeightKg, 3) ?> kg</span>
      <?php endif; ?>
    </div>
    <a href="<?= site_url('new-purchase-grns/list') ?>"><i class="bi bi-arrow-left me-1"></i>Back to GRN list</a>
  </div>

</div>

<div class="modal fade" id="grnIssueModal" tabindex="-1" aria-labelledby="grnIssueModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.08em;font-weight:700;">Inventory Issue Action</div>
          <h5 class="modal-title mb-0" id="grnIssueModalLabel">Line Action</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="issue-modal-summary">
          <div>
            <div class="k">Product</div>
            <div class="v" id="grnIssueProduct">-</div>
          </div>
          <div>
            <div class="k">Available Qty</div>
            <div class="v" id="grnIssueAvailable">0.00</div>
          </div>
          <div>
            <div class="k">Repair Outstanding</div>
            <div class="v" id="grnIssueRepair">0.00</div>
          </div>
          <div>
            <div class="k">Action Date</div>
            <div class="v" id="grnIssueActionDateLabel"></div>
          </div>
        </div>
        <input type="hidden" id="grnIssueLineId" value="">
        <div class="mb-3">
          <label for="grnIssueAction" class="form-label">Action</label>
          <select class="form-select" id="grnIssueAction">
            <option value="">Select action</option>
            <option value="scrap">Mark Scrap</option>
            <option value="return_to_vendor">Return to Vendor</option>
            <option value="send_for_repair">Send for Repair</option>
            <option value="receive_repaired_back">Receive Repaired Back</option>
          </select>
        </div>
        <div class="row g-3">
          <div class="col-sm-4">
            <label for="grnIssueQty" class="form-label">Qty</label>
            <input type="number" step="0.01" min="0" class="form-control" id="grnIssueQty" placeholder="0.00">
          </div>
          <div class="col-sm-8">
            <label for="grnIssueReason" class="form-label">Reason / details</label>
            <input type="text" class="form-control" id="grnIssueReason" maxlength="255" placeholder="Optional details">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="grnIssueSave">Save action</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade grn-lightbox" id="grnImageModal" tabindex="-1" aria-labelledby="grnImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-white" id="grnImageModalLabel">Product image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img src="<?= esc(base_url('assets/images/no-image.png')) ?>" alt="" id="grnImageModalImg">
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const grnRef = '<?= esc((string)$grnId) ?>';
  const base = '<?= site_url('new-purchase-grns/'.$grnId.'/lines/') ?>';
  const issueModalEl = document.getElementById('grnIssueModal');
  const imageModalEl = document.getElementById('grnImageModal');
  const issueModal = issueModalEl && window.bootstrap ? new bootstrap.Modal(issueModalEl) : null;
  const imageModal = imageModalEl && window.bootstrap ? new bootstrap.Modal(imageModalEl) : null;
  const issueLineId = document.getElementById('grnIssueLineId');
  const issueProduct = document.getElementById('grnIssueProduct');
  const issueAvailable = document.getElementById('grnIssueAvailable');
  const issueRepair = document.getElementById('grnIssueRepair');
  const issueAction = document.getElementById('grnIssueAction');
  const issueQty = document.getElementById('grnIssueQty');
  const issueReason = document.getElementById('grnIssueReason');
  const issueSave = document.getElementById('grnIssueSave');
  const issueActionDateLabel = document.getElementById('grnIssueActionDateLabel');
  const imageModalImg = document.getElementById('grnImageModalImg');
  const imageModalTitle = document.getElementById('grnImageModalLabel');
  const printBtn = document.getElementById('grnPrintBtn');
  const pdfBtn = document.getElementById('grnPdfBtn');
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = (window.csrfHash || (csrfMeta ? csrfMeta.getAttribute('content') : '') || '').trim();
  const today = new Date().toISOString().slice(0,10);

  if (printBtn) {
    printBtn.addEventListener('click', () => window.open('<?= site_url('new-purchase-grns/') ?>' + encodeURIComponent(grnRef) + '/print', '_blank'));
  }
  if (pdfBtn) {
    pdfBtn.addEventListener('click', () => window.print());
  }

  if (issueActionDateLabel) {
    issueActionDateLabel.textContent = today;
  }

  document.querySelectorAll('.issue-open').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!issueModal) return;
      const available = btn.getAttribute('data-available') || '0';
      const repairOutstanding = btn.getAttribute('data-repair-outstanding') || '0';
      issueLineId.value = btn.getAttribute('data-line-id') || '';
      issueProduct.textContent = btn.getAttribute('data-line-name') || 'Line item';
      issueAvailable.textContent = Number.parseFloat(available || '0').toFixed(2);
      issueRepair.textContent = Number.parseFloat(repairOutstanding || '0').toFixed(2);
      issueAction.value = '';
      issueQty.value = '';
      issueReason.value = '';
      issueSave.disabled = false;
      issueSave.textContent = 'Save action';
      issueSave.setAttribute('data-available', available);
      issueSave.setAttribute('data-repair-outstanding', repairOutstanding);
      issueModal.show();
    });
  });

  // Lightbox: open on both button click and direct img click
  function openLightbox(src, alt) {
    if (!imageModal || !imageModalImg || !imageModalTitle) return;
    imageModalImg.src = src || '';
    imageModalImg.alt = alt || 'Product image';
    imageModalTitle.textContent = alt || 'Product image';
    imageModal.show();
  }
  document.querySelectorAll('.line-image-open').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      openLightbox(btn.getAttribute('data-image-src'), btn.getAttribute('data-image-alt'));
    });
  });
  document.querySelectorAll('.prod-thumb').forEach(img => {
    img.style.cursor = 'zoom-in';
    img.addEventListener('click', e => {
      e.stopPropagation();
      const btn = img.closest('.line-image-open');
      if (btn) openLightbox(btn.getAttribute('data-image-src'), btn.getAttribute('data-image-alt'));
      else openLightbox(img.src, img.alt);
    });
  });

  if (issueSave) {
    issueSave.addEventListener('click', async () => {
      const id = issueLineId ? issueLineId.value : '';
      const actionType = issueAction ? issueAction.value : '';
      const qty = Number.parseFloat(issueQty ? issueQty.value : '0');
      const avail = Number.parseFloat(issueSave.getAttribute('data-available') || '0');
      const repairOuts = Number.parseFloat(issueSave.getAttribute('data-repair-outstanding') || '0');
      if (!id) { alert('Line not found'); return; }
      if (!actionType) { alert('Select an action'); return; }
      if (!Number.isFinite(qty) || qty <= 0) { alert('Enter qty > 0'); return; }
      if (['scrap','return_to_vendor','send_for_repair'].includes(actionType) && qty > avail + 0.0001) { alert('Qty exceeds available quantity'); return; }
      if (actionType === 'receive_repaired_back' && qty > repairOuts + 0.0001) { alert('Qty exceeds repair outstanding'); return; }
      const payload = { action_type: actionType, qty, reason: issueReason ? issueReason.value.trim() : '', action_date: today };
      issueSave.disabled = true;
      const originalText = issueSave.textContent;
      issueSave.textContent = 'Saving...';
      try {
        const resp = await fetch(base + id + '/issue', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
          },
          body: JSON.stringify(payload)
        });
        const raw = await resp.text();
        let data = null;
        try {
          data = raw ? JSON.parse(raw) : null;
        } catch (_) {
          data = null;
        }
        if (resp.ok && data && data.success) {
          window.location.reload();
          return;
        }
        const fallback = raw && raw.length < 180 ? raw : 'Failed to save action';
        alert((data && data.error) ? data.error : fallback);
      } catch (e) {
        alert('Network error: ' + e.message);
      }
      issueSave.disabled = false;
      issueSave.textContent = originalText;
    });
  }
}); // end DOMContentLoaded
</script>

<?= $this->endSection() ?>
