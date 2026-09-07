<?php
/**
 * Shared compact multi-value renderer.
 * Required: $items. Optional: $visible (default 2), $moreClass, $dataAttribute.
 */
$chipItems = array_values(array_filter((array) ($items ?? []), static fn($item): bool => trim((string) $item) !== ''));
$visibleCount = max(1, (int) ($visible ?? 2));
$visibleItems = array_slice($chipItems, 0, $visibleCount);
$remainingCount = max(0, count($chipItems) - count($visibleItems));
$moreClass = trim((string) ($moreClass ?? ''));
$dataAttribute = trim((string) ($dataAttribute ?? 'data-cl-chip-values'));
if (preg_match('/^data-[a-z0-9_-]+$/', $dataAttribute) !== 1) {
    $dataAttribute = 'data-cl-chip-values';
}
?>
<span class="cl-tag-list">
    <?php foreach ($visibleItems as $chipItem): ?>
        <span class="cl-tag-chip" title="<?= esc((string) $chipItem) ?>"><?= esc((string) $chipItem) ?></span>
    <?php endforeach; ?>
    <?php if ($remainingCount > 0): ?>
        <button type="button" class="cl-tag-chip cl-tag-chip--more<?= $moreClass !== '' ? ' ' . esc($moreClass) : '' ?>" <?= esc($dataAttribute) ?>='<?= esc(json_encode($chipItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' title="Show <?= $remainingCount ?> more item<?= $remainingCount === 1 ? '' : 's' ?>" aria-label="Show <?= $remainingCount ?> more item<?= $remainingCount === 1 ? '' : 's' ?>">+<?= $remainingCount ?></button>
    <?php endif; ?>
</span>
