<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($report['title']) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/reports.css') ?>?v=1">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * The one renderer every report uses. It knows how to draw four things —
 * KPIs, callouts, charts and tables — and nothing about any single report.
 *
 * @var array $report
 * @var array $catalogue
 * @var array $meta
 * @var array $currencies
 */
$qs = '?from=' . urlencode($meta['from']) . '&to=' . urlencode($meta['to']) . '&currency=' . urlencode($meta['display']);
$charts = [];
?>
<div class="rp-page rp-page--report">

    <nav class="rp-crumb">
        <a href="<?= base_url('reports') . $qs ?>"><i class="bi bi-arrow-left"></i> All reports</a>
    </nav>

    <header class="rp-header">
        <div>
            <h1><i class="bi <?= esc($report['icon']) ?>"></i> <?= esc($report['title']) ?></h1>
            <p><?= esc($report['lede']) ?></p>
            <span class="rp-header-meta"><?= esc($report['range']) ?> · amounts in <?= esc($report['currency']) ?></span>
        </div>
        <div class="rp-header-actions">
            <a class="rp-btn" href="<?= base_url('reports/export/' . $report['key']) . $qs ?>"><i class="bi bi-download"></i> Export CSV</a>
            <button type="button" class="rp-btn" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </header>

    <?= $this->include('reports/_filters') ?>

    <div class="rp-switcher">
        <?php foreach ($catalogue as $key => $item): ?>
            <a class="rp-switch<?= $key === $report['key'] ? ' is-active' : '' ?>" href="<?= base_url('reports/' . $key) . $qs ?>">
                <i class="bi <?= esc($item['icon']) ?>"></i> <?= esc($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($report['kpis'])): ?>
        <div class="rp-kpis">
            <?php foreach ($report['kpis'] as $kpi): ?>
                <article class="rp-kpi rp-tone-<?= esc($kpi['tone']) ?>">
                    <span class="rp-kpi-label"><?= esc($kpi['label']) ?></span>
                    <strong class="rp-kpi-value"><?= esc($kpi['value']) ?></strong>
                    <span class="rp-kpi-sub"><?= esc($kpi['sub']) ?></span>
                    <span class="rp-kpi-hint"><?= esc($kpi['hint']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['callouts'])): ?>
        <div class="rp-callouts">
            <?php foreach ($report['callouts'] as $c): ?>
                <div class="rp-callout rp-callout-<?= esc($c['tone']) ?>">
                    <strong><?= esc($c['title']) ?></strong>
                    <p><?= $c['text'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($report['sections'] as $i => $section): ?>
        <?php if ($section['type'] === 'chart'): ?>
            <?php $charts['rpChart' . $i] = $section['chart']; ?>
            <section class="rp-panel">
                <div class="rp-panel-head">
                    <h2><?= esc($section['title']) ?></h2>
                    <?php if (!empty($section['note'])): ?><p><?= esc($section['note']) ?></p><?php endif; ?>
                </div>
                <div class="rp-chart-wrap<?= !empty($section['chart']['horizontal']) ? ' is-tall' : '' ?>">
                    <canvas id="rpChart<?= $i ?>"></canvas>
                </div>
            </section>

        <?php elseif ($section['type'] === 'table'): ?>
            <section class="rp-panel">
                <div class="rp-panel-head">
                    <h2><?= esc($section['title']) ?></h2>
                    <?php if (!empty($section['note'])): ?><p><?= esc($section['note']) ?></p><?php endif; ?>
                    <?php if (!empty($section['rows'])): ?>
                        <span class="rp-count"><?= count($section['rows']) ?> row<?= count($section['rows']) === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($section['rows'])): ?>
                    <p class="rp-empty"><i class="bi bi-check2-circle"></i> <?= esc($section['empty'] ?? 'Nothing to show.') ?></p>
                <?php else: ?>
                    <div class="rp-table-wrap">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <?php foreach ($section['columns'] as $col): ?>
                                        <th class="rp-al-<?= esc($col['align'] ?? 'left') ?>"><?= esc($col['label']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($section['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($section['columns'] as $col):
                                            $value = $row[$col['key']] ?? '';
                                            $type  = $col['type'] ?? 'text';
                                            $class = 'rp-al-' . ($col['align'] ?? 'left') . (!empty($col['strong']) ? ' rp-strong' : '');
                                            ?>
                                            <td class="<?= $class ?>">
                                                <?php if ($type === 'bar'): ?>
                                                    <span class="rp-bar" style="--rp-bar:<?= min(100, (float) $value) ?>%">
                                                        <span class="rp-bar-fill"></span>
                                                        <span class="rp-bar-text"><?= esc($value) ?>%</span>
                                                    </span>
                                                <?php elseif ($type === 'badge'):
                                                    $tone = !empty($col['tone']) ? ($row[$col['tone']] ?? 'neutral') : 'neutral'; ?>
                                                    <span class="rp-badge rp-tone-<?= esc($tone) ?>"><?= esc($value) ?></span>
                                                <?php elseif ($type === 'code'): ?>
                                                    <?php if ($value !== ''): ?><code class="rp-code"><?= esc($value) ?></code><?php else: ?><span class="rp-muted">—</span><?php endif; ?>
                                                <?php else:
                                                    // A numeric sibling column named in 'tone' colours the figure by sign.
                                                    $signed = !empty($col['tone']) && isset($row[$col['tone']]) && is_numeric($row[$col['tone']])
                                                        ? ((float) $row[$col['tone']] < 0 ? ' rp-neg' : ' rp-pos') : ''; ?>
                                                    <span class="rp-val<?= $signed ?>"><?= esc($value) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>

</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>window.rpCharts = <?= json_encode($charts, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= base_url('assets/js/reports.js') ?>?v=1" defer></script>
<?= $this->endSection() ?>
