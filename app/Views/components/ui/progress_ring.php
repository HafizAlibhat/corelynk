<?php
$value = max(0, min(100, (int) ($value ?? 0)));
$label = (string) ($label ?? 'Progress');
$variant = in_array(($variant ?? 'purple'), ['purple', 'blue', 'cyan'], true) ? $variant : 'purple';
?>
<div class="text-center">
    <span class="cl-progress-ring cl-ring-<?= esc($variant) ?>" style="--value: <?= $value ?>" role="img" aria-label="<?= esc($label) ?>: <?= $value ?> percent">
        <strong><?= $value ?>%</strong>
    </span>
    <small class="d-block mt-2 text-muted"><?= esc($label) ?></small>
</div>
