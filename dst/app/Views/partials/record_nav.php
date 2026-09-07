<?php
/**
 * Prev / next record navigation for detail pages.
 *
 * Usage from any view:
 *   <?= view('partials/record_nav', [
 *       'table'   => 'sales_orders',
 *       'id'      => $order['id'],                 // numeric id or public_id
 *       'pattern' => 'sales-orders/view/{id}',
 *       'list'    => 'sales-orders',
 *   ]) ?>
 *
 * Records are ordered newest first (id DESC), so "previous" is the newer
 * document and "next" the older one, matching the list pages.
 */

$navTable   = (string)($table ?? '');
$navPattern = (string)($pattern ?? '');
$navList    = (string)($list ?? '');
$navId      = (string)($id ?? '');

if ($navTable === '' || $navPattern === '' || $navId === '') {
    return;
}

$navDb     = \Config\Database::connect();
$navFields = $navDb->getFieldNames($navTable);
$navSoft   = in_array('deleted_at', $navFields, true);
$navPublic = in_array('public_id', $navFields, true);

/** Base builder with the soft-delete filter applied. */
$navScope = static function () use ($navDb, $navTable, $navSoft) {
    $b = $navDb->table($navTable);
    if ($navSoft) {
        $b->where('deleted_at', null);
    }
    return $b;
};

// Resolve whatever is in the URL to the numeric row.
$navRow = null;
if (ctype_digit($navId)) {
    $navRow = $navScope()->select('id')->where('id', (int)$navId)->get(1)->getRowArray();
}
if (!$navRow && $navPublic) {
    $navRow = $navScope()->select('id')->where('public_id', $navId)->get(1)->getRowArray();
}
if (!$navRow) {
    return;
}
$navCurrent = (int)$navRow['id'];

$navSelect = $navPublic ? 'id, public_id' : 'id';

$navPrevRow = $navScope()->select($navSelect)->where('id >', $navCurrent)->orderBy('id', 'ASC')->get(1)->getRowArray();
$navNextRow = $navScope()->select($navSelect)->where('id <', $navCurrent)->orderBy('id', 'DESC')->get(1)->getRowArray();

$navTotal    = (int)$navScope()->countAllResults();
$navPosition = (int)$navScope()->where('id >=', $navCurrent)->countAllResults();

/** Same identifier style the current URL uses. */
$navUrl = static function (?array $row) use ($navPattern, $navPublic, $navId) {
    if (!$row) {
        return null;
    }
    $ident = ($navPublic && !ctype_digit($navId) && !empty($row['public_id']))
        ? $row['public_id']
        : $row['id'];

    return site_url(str_replace('{id}', (string)$ident, $navPattern));
};

$navPrevUrl = $navUrl($navPrevRow);
$navNextUrl = $navUrl($navNextRow);
?>
<div class="btn-group btn-group-sm record-nav" role="group" aria-label="Record navigation">
    <?php if ($navList !== ''): ?>
        <a class="btn btn-outline-secondary" href="<?= site_url($navList) ?>" title="Back to list" aria-label="Back to list">
            <i class="bi bi-list-ul"></i>
        </a>
    <?php endif; ?>
    <?php if ($navPrevUrl): ?>
        <a class="btn btn-outline-secondary" href="<?= $navPrevUrl ?>" title="Newer record" aria-label="Newer record">
            <i class="bi bi-chevron-left"></i>
        </a>
    <?php else: ?>
        <span class="btn btn-outline-secondary disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
    <?php endif; ?>
    <span class="btn btn-outline-secondary disabled record-nav-count" aria-live="polite">
        <?= $navPosition ?> / <?= $navTotal ?>
    </span>
    <?php if ($navNextUrl): ?>
        <a class="btn btn-outline-secondary" href="<?= $navNextUrl ?>" title="Older record" aria-label="Older record">
            <i class="bi bi-chevron-right"></i>
        </a>
    <?php else: ?>
        <span class="btn btn-outline-secondary disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
    <?php endif; ?>
</div>
