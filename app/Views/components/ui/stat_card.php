<?php
$label = (string) ($label ?? '');
$value = (string) ($value ?? '');
$subtext = (string) ($subtext ?? '');
$icon = (string) ($icon ?? 'bi-graph-up');
$href = (string) ($href ?? '');
$content = static function () use ($label, $value, $subtext, $icon): void {
?>
    <span class="stat-card__label"><?= esc($label) ?></span>
    <strong class="stat-card__value"><?= esc($value) ?></strong>
    <?php if ($subtext !== ''): ?><small class="stat-card__subtext"><?= esc($subtext) ?></small><?php endif; ?>
    <span class="stat-card__icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span>
<?php
};
?>
<?php if ($href !== ''): ?>
<a class="stat-card d-block" href="<?= esc($href) ?>"><?php $content(); ?></a>
<?php else: ?>
<div class="stat-card"><?php $content(); ?></div>
<?php endif; ?>
