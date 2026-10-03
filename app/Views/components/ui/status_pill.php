<?php
$status = trim((string) ($status ?? ''));
$normalized = strtolower(str_replace([' ', '_'], '-', $status));
?>
<span class="cl-badge" data-status="<?= esc($normalized !== '' ? $normalized : 'unknown') ?>"><?= esc($status !== '' ? $status : 'Unknown') ?></span>
