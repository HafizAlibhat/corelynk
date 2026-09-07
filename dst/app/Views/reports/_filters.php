<?php
/**
 * Period + currency picker. Shared by the hub and every report so the filter
 * survives navigation between them.
 *
 * @var array $meta
 * @var array $currencies
 */
$action = current_url();
$presets = [
    'This month'      => [date('Y-m-01'), date('Y-m-t')],
    'Last month'      => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Last 3 months'   => [date('Y-m-01', strtotime('-2 months')), date('Y-m-d')],
    'This year'       => [date('Y-01-01'), date('Y-m-d')],
    'Last 12 months'  => [date('Y-m-01', strtotime('-11 months')), date('Y-m-d')],
];
?>
<form class="rp-filters" method="get" action="<?= esc($action) ?>">
    <div class="rp-filters-presets">
        <?php foreach ($presets as $label => [$pFrom, $pTo]): ?>
            <?php $active = $meta['from'] === $pFrom && $meta['to'] === $pTo; ?>
            <a class="rp-preset<?= $active ? ' is-active' : '' ?>"
               href="<?= esc($action) ?>?from=<?= $pFrom ?>&to=<?= $pTo ?>&currency=<?= esc($meta['display']) ?>"><?= esc($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="rp-filters-fields">
        <label class="rp-field">
            <span>From</span>
            <input type="date" name="from" value="<?= esc($meta['from']) ?>">
        </label>
        <label class="rp-field">
            <span>To</span>
            <input type="date" name="to" value="<?= esc($meta['to']) ?>">
        </label>
        <label class="rp-field">
            <span>Show amounts in</span>
            <select name="currency">
                <?php foreach ($currencies as $code => $info): ?>
                    <option value="<?= esc($code) ?>" <?= $meta['display'] === $code ? 'selected' : '' ?>>
                        <?= esc($code) ?><?= !empty($info['symbol']) ? ' (' . esc($info['symbol']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rp-apply"><i class="bi bi-arrow-repeat"></i> Apply</button>
    </div>
</form>
