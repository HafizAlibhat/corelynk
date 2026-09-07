<?php
/**
 * Shared primary list action.
 * Required: $url. Optional: $label and $icon.
 * Keep secondary actions in the adjacent compact overflow menu.
 */
$actionUrl = (string) ($url ?? '#');
$actionLabel = trim((string) ($label ?? 'View')) ?: 'View';
$actionIcon = trim((string) ($icon ?? 'bi-eye')) ?: 'bi-eye';
$actionClass = trim((string) ($class ?? ''));
?>
<a class="cl-action-icon cl-action-icon--primary<?= $actionClass !== '' ? ' ' . esc($actionClass) : '' ?>" href="<?= esc($actionUrl) ?>" title="<?= esc($actionLabel) ?>" aria-label="<?= esc($actionLabel) ?>">
    <i class="bi <?= esc($actionIcon) ?>" aria-hidden="true"></i>
</a>
