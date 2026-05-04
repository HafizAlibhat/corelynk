<?php
/**
 * Order Progress Partial — loaded into a modal via AJAX
 * @var array       $timeline
 * @var array       $sales_order
 * @var array|null  $delivery_order
 */
$do          = $delivery_order ?? [];
$isConfirmed = in_array($do['status'] ?? '', ['confirmed', 'delivered'], true);
?>
<style>
.op-tl-wrap{background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);border-radius:8px;padding:1.5rem 1.25rem 1.25rem}
.op-tl-heading{font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:1.75rem;display:flex;align-items:center;gap:.4rem}
.op-tl-bar{position:relative;display:flex;justify-content:space-between;align-items:flex-start}
.op-tl-bar::before{content:'';position:absolute;top:11px;left:0;right:0;height:2px;background:#334155;border-radius:1px}
.op-tl-fill{position:absolute;top:11px;left:0;height:2px;background:linear-gradient(90deg,#10b981,#34d399);border-radius:1px;transition:width .6s ease}
.op-tl-step{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;z-index:2}
.op-tl-dot{width:22px;height:22px;border-radius:50%;border:2.5px solid #475569;background:#0f172a;margin-bottom:.6rem;flex-shrink:0}
.op-tl-dot.done{background:#10b981;border-color:#10b981;box-shadow:0 0 7px rgba(16,185,129,.35)}
.op-tl-dot.cur{border-color:#fbbf24;background:#0f172a;box-shadow:0 0 7px rgba(251,191,36,.3)}
.op-tl-info{text-align:center;max-width:110px}
.op-tl-label{font-size:.7rem;font-weight:600;color:#e2e8f0;line-height:1.3;margin-bottom:3px}
.op-tl-date{font-size:.65rem;color:#94a3b8}
.op-tl-time{font-size:.62rem;color:#64748b}
.op-tl-chip{display:inline-block;font-size:.58rem;font-weight:700;padding:2px 7px;border-radius:3px;text-transform:uppercase;margin-top:4px;letter-spacing:.03em}
.op-tl-chip.done{background:rgba(16,185,129,.15);color:#6ee7b7}
.op-tl-chip.pend{background:rgba(251,191,36,.12);color:#fcd34d}
.op-tl-dur{position:absolute;top:-17px;left:50%;transform:translateX(-50%);font-size:.57rem;color:#64748b;white-space:nowrap;background:#1e293b;padding:1px 5px;border-radius:3px;border:1px solid #334155}
.op-metric-card{flex:1;min-width:130px;border-radius:8px;padding:.55rem .75rem}
</style>
<?php if (empty($timeline)): ?>
    <div class="text-center text-muted py-3"><i class="bi bi-clock-history me-2"></i>No timeline data available yet.</div>
<?php else:
    $tlMeta  = $timeline['_meta'] ?? ['source' => 'unknown'];
    $tlSrc   = $tlMeta['source'] ?? 'unknown';

    $eventMap = [];
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        $eventMap[$ev['label']] = $ev;
    }

    if ($tlSrc === 'procurement') {
        $milestones = [
            ['label' => 'Sales Order',       'event' => $eventMap['Sales Order Created'] ?? null],
            ['label' => 'RFQ Created',        'event' => $eventMap['RFQ Created'] ?? null],
            ['label' => 'Purchase Order',     'event' => $eventMap['Purchase Order Created'] ?? null],
            ['label' => 'Warehouse Receipt',  'event' => $eventMap['Received at Warehouse'] ?? null],
            ['label' => 'Shipped',            'event' => $isConfirmed ? ['time' => $do['shipped_at'] ?? $do['updated_at'], 'label' => 'Shipped'] : null],
        ];
    } else {
        $milestones = [
            ['label' => 'Sales Order',   'event' => $eventMap['Sales Order Created'] ?? null],
            ['label' => 'Stock Available','event' => $eventMap['Stock Available'] ?? null],
            ['label' => 'DO Created',    'event' => $eventMap['Delivery Order Created'] ?? null],
            ['label' => 'Shipped',       'event' => $isConfirmed ? ['time' => $do['shipped_at'] ?? $do['updated_at'], 'label' => 'Shipped'] : null],
        ];
    }

    $lastDoneIdx = -1;
    foreach ($milestones as $idx => $m) {
        if (!empty($m['event']['time'])) $lastDoneIdx = $idx;
    }
    $totalSteps  = count($milestones);
    $doneCount   = $lastDoneIdx + 1;
    $progressPct = $lastDoneIdx >= 0 ? ($lastDoneIdx / max($totalSteps - 1, 1)) * 100 : 0;

    $durationMap = [];
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        if (!empty($ev['duration_from_prev'])) $durationMap[$ev['label']] = $ev['duration_from_prev'];
    }

    $firstTime = $lastTime = null;
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        if (!empty($ev['time'])) { if (!$firstTime) $firstTime = $ev['time']; $lastTime = $ev['time']; }
    }
    $totalSec = ($firstTime && $lastTime) ? max(0, strtotime($lastTime) - strtotime($firstTime)) : 0;

    $stepDurations = [];
    $prevT = null;
    foreach ($milestones as $idx => $m) {
        if (!empty($m['event']['time'])) {
            if ($prevT !== null) {
                $stepDurations[] = [
                    'from'    => $milestones[$idx - 1]['label'] ?? '',
                    'to'      => $m['label'],
                    'seconds' => max(0, strtotime($m['event']['time']) - strtotime($prevT)),
                ];
            }
            $prevT = $m['event']['time'];
        }
    }
    $slowestSec = 0; $slowestStep = null;
    foreach ($stepDurations as $sd) {
        if ($sd['seconds'] > $slowestSec) { $slowestSec = $sd['seconds']; $slowestStep = $sd; }
    }

    $fmtSecs = function($secs) {
        if ($secs <= 0) return '—';
        $d = intdiv($secs, 86400); $h = intdiv($secs % 86400, 3600); $m = intdiv($secs % 3600, 60);
        $p = [];
        if ($d > 0) $p[] = $d.'d'; if ($h > 0) $p[] = $h.'h'; if ($m > 0 && $d === 0) $p[] = $m.'m';
        return $p ? implode(' ', $p) : '< 1m';
    };
?>

<!-- Header -->
<div style="padding:.5rem 0 .75rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
    <div>
        <strong style="color:#f1f5f9;font-size:.92rem;"><?= esc($sales_order['order_number'] ?? '') ?></strong>
        <span style="color:#94a3b8;font-size:.78rem;margin-left:.5rem;">Order Progress</span>
    </div>
    <?php if ($tlSrc === 'procurement'): ?>
        <span style="background:rgba(99,102,241,.15);color:#818cf8;padding:3px 10px;border-radius:10px;font-size:.72rem;font-weight:600;">
            <i class="bi bi-cart3 me-1"></i>Procurement
        </span>
    <?php else: ?>
        <span style="background:rgba(16,185,129,.15);color:#34d399;padding:3px 10px;border-radius:10px;font-size:.72rem;font-weight:600;">
            <i class="bi bi-box-seam me-1"></i>In Stock
        </span>
    <?php endif; ?>
</div>

<!-- Timeline bar -->
<div class="op-tl-wrap">
    <div class="op-tl-heading"><i class="bi bi-clock-history"></i> Progress</div>
    <div class="op-tl-bar">
        <div class="op-tl-fill" style="width:<?= $progressPct ?>%"></div>
        <?php foreach ($milestones as $idx => $m):
            $has  = !empty($m['event']['time']);
            $isCur = !$has && ($idx === 0 || !empty($milestones[$idx-1]['event']['time']));
            $label = $m['event']['label'] ?? $m['label'];
            $dur   = $durationMap[$label] ?? null;
        ?>
        <div class="op-tl-step">
            <?php if ($dur && $idx > 0): ?><div class="op-tl-dur"><?= esc($dur) ?></div><?php endif; ?>
            <div class="op-tl-dot <?= $has ? 'done' : ($isCur ? 'cur' : '') ?>"></div>
            <div class="op-tl-info">
                <div class="op-tl-label"><?= esc($m['label']) ?></div>
                <?php if ($has): ?>
                    <div class="op-tl-date"><?= date('d M Y', strtotime($m['event']['time'])) ?></div>
                    <div class="op-tl-time"><?= date('H:i', strtotime($m['event']['time'])) ?></div>
                <?php endif; ?>
                <span class="op-tl-chip <?= $has ? 'done' : 'pend' ?>"><?= $has ? 'Done' : 'Pending' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Performance metrics -->
<div style="margin-top:.9rem;">
    <div style="font-size:.72rem;font-weight:700;color:#94a3b8;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.04em;">
        <i class="bi bi-speedometer2 me-1"></i>Performance
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;">
        <div class="op-metric-card" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);">
            <div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">Total Time</div>
            <div style="font-size:1.05rem;font-weight:700;color:#818cf8;"><?= $fmtSecs($totalSec) ?></div>
        </div>
        <div class="op-metric-card" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);">
            <div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">Steps Done</div>
            <div style="font-size:1.05rem;font-weight:700;color:#34d399;"><?= $doneCount ?> / <?= $totalSteps ?></div>
        </div>
        <?php if ($slowestStep): ?>
        <div class="op-metric-card" style="flex:2;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);">
            <div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;">Slowest Step</div>
            <div style="font-size:1rem;font-weight:700;color:#fbbf24;"><?= $fmtSecs($slowestSec) ?> <small style="font-size:.65rem;font-weight:400;color:#94a3b8;"><?= esc($slowestStep['from']) ?> → <?= esc($slowestStep['to']) ?></small></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($stepDurations)): ?>
    <div style="margin-top:.6rem;">
        <?php foreach ($stepDurations as $sd):
            $pct = $totalSec > 0 ? round(($sd['seconds'] / $totalSec) * 100) : 0;
            $isSlow = ($slowestStep && $sd['from'] === $slowestStep['from'] && $sd['to'] === $slowestStep['to']);
        ?>
        <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:3px;font-size:.7rem;">
            <div style="width:130px;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= esc($sd['from'].' → '.$sd['to']) ?>">
                <?= esc($sd['from']) ?> → <?= esc($sd['to']) ?>
            </div>
            <div style="flex:1;height:5px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $isSlow ? '#fbbf24' : '#818cf8' ?>;border-radius:3px;"></div>
            </div>
            <div style="width:45px;text-align:right;color:<?= $isSlow ? '#fbbf24' : '#94a3b8' ?>;font-weight:<?= $isSlow ? '700' : '400' ?>;"><?= $fmtSecs($sd['seconds']) ?></div>
            <div style="width:32px;text-align:right;color:#64748b;"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
