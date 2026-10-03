<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Reports<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/reports.css') ?>?v=1">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * @var array $catalogue
 * @var array $meta
 * @var array $currencies
 * @var array $headline  health() spec — the hub shows its KPIs and callouts
 */
$groups = [];
foreach ($catalogue as $key => $item) {
    $groups[$item['group']][$key] = $item;
}
?>
<div class="rp-page">

    <header class="rp-header">
        <div>
            <h1>Reports</h1>
            <p>Ten views of the business, written to be read in a minute. Pick a period once — it follows you between reports.</p>
        </div>
        <a class="rp-header-cta" href="<?= base_url('reports/health') ?>?from=<?= esc($meta['from']) ?>&to=<?= esc($meta['to']) ?>&currency=<?= esc($meta['display']) ?>">
            <i class="bi bi-heart-pulse"></i> Open the scorecard
        </a>
    </header>

    <?= $this->include('reports/_filters') ?>

    <section class="rp-snapshot">
        <div class="rp-snapshot-head">
            <h2>Right now</h2>
            <span><?= esc($headline['range']) ?> · amounts in <?= esc($headline['currency']) ?></span>
        </div>
        <div class="rp-kpis">
            <?php foreach ($headline['kpis'] as $kpi): ?>
                <article class="rp-kpi rp-tone-<?= esc($kpi['tone']) ?>" title="<?= esc($kpi['hint']) ?>">
                    <span class="rp-kpi-label"><?= esc($kpi['label']) ?></span>
                    <strong class="rp-kpi-value"><?= esc($kpi['value']) ?></strong>
                    <span class="rp-kpi-sub"><?= esc($kpi['sub']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($headline['callouts'])): ?>
            <div class="rp-callouts">
                <?php foreach (array_slice($headline['callouts'], 0, 2) as $c): ?>
                    <div class="rp-callout rp-callout-<?= esc($c['tone']) ?>">
                        <strong><?= esc($c['title']) ?></strong>
                        <p><?= $c['text'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php foreach ($groups as $groupName => $items): ?>
        <section class="rp-group">
            <h2 class="rp-group-title"><?= esc($groupName) ?></h2>
            <div class="rp-cards">
                <?php foreach ($items as $key => $item): ?>
                    <a class="rp-card" href="<?= base_url('reports/' . $key) ?>?from=<?= esc($meta['from']) ?>&to=<?= esc($meta['to']) ?>&currency=<?= esc($meta['display']) ?>">
                        <span class="rp-card-icon"><i class="bi <?= esc($item['icon']) ?>"></i></span>
                        <span class="rp-card-body">
                            <strong><?= esc($item['title']) ?></strong>
                            <span class="rp-card-lede"><?= esc($item['lede']) ?></span>
                            <span class="rp-card-for"><i class="bi bi-lightbulb"></i> <?= esc($item['for']) ?></span>
                        </span>
                        <i class="bi bi-arrow-right rp-card-go"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

</div>
<?= $this->endSection() ?>
