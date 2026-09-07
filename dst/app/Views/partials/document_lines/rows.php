<?php
/** @var string $docType */
/** @var array $lines */
/** @var array $sectionSubtotals */

$fmt = static function ($v, $d = 2) {
    return number_format((float)$v, $d);
};

$lineType = static function ($ln) {
    $t = strtolower((string)($ln['display_type'] ?? 'line'));
    return $t === 'section' ? 'section' : 'line';
};

$defaultImg = base_url('assets/images/no-image.png');
$normalizeImg = static function ($img) use ($defaultImg) {
    $img = trim((string)$img);
    if ($img === '') {
        return $defaultImg;
    }
    if (preg_match('#^(https?:)?//#i', $img) || stripos($img, 'data:') === 0) {
        return $img;
    }
    if (stripos($img, 'file:') === 0) {
        return $defaultImg;
    }
    return base_url(ltrim($img, '/'));
};

$columnCount = 12;
if ($docType === 'sales_order') {
    $columnCount = 18;
} elseif ($docType === 'quotation') {
    $columnCount = 13;
} elseif ($docType === 'customer_invoice') {
    $columnCount = 12;
} elseif ($docType === 'purchase_order') {
    $columnCount = 10;
} elseif ($docType === 'purchase_rfq') {
    $columnCount = 8;
}

if (empty($lines)): ?>
<tr><td colspan="<?= $columnCount ?>" class="text-muted">No lines</td></tr>
<?php return; endif;

$currentSectionId = 0;
$lineNo = 0;
foreach ($lines as $idx => $ln):
    $id = (int)($ln['id'] ?? 0);
    $isSection = $lineType($ln) === 'section';
    $next = $lines[$idx + 1] ?? null;
    $nextIsSectionOrEnd = (!$next) || ($lineType($next) === 'section');
    if ($isSection):
        // Section feature removed.
        continue;
    endif;

    if ($docType === 'quotation'):
        $discType = strtolower((string)($ln['discount_type'] ?? 'percent'));
        if (!in_array($discType, ['percent', 'fixed'], true)) $discType = 'percent';
        $discSuffix = $discType === 'fixed' ? 'Fix' : '%';
        
        // Build product browse URL (variant takes priority over product)
        $productBrowseUrl = '';
        $productId = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
        $variantId = !empty($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0;
        if ($variantId > 0) {
            $productBrowseUrl = site_url('product-variants/' . $variantId . '/edit');
        } elseif ($productId > 0) {
            $productBrowseUrl = site_url('products/' . $productId);
        }
?>
<?php $lineNo++; ?>
<tr data-line-id="<?= $id ?>" data-display-type="line" data-line-updated-at="<?= esc((string)($ln['updated_at'] ?? '')) ?>" data-discount-type="<?= esc($discType) ?>">
    <td class="text-center text-muted"><?= $lineNo ?></td>
    <td class="line-code">
        <span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span>
        <?php if ($productBrowseUrl): ?>
            <a href="<?= esc($productBrowseUrl) ?>" target="_blank" title="View product" style="text-decoration:none; color:inherit; border-bottom:1px dotted #666;">
                <?= esc((string)($ln['product_code'] ?? '')) ?>
            </a>
        <?php else: ?>
            <?= esc((string)($ln['product_code'] ?? '')) ?>
        <?php endif; ?>
    </td>
    <td>
        <?php $img = $normalizeImg($ln['product_image_url'] ?? ''); ?>
        <?php if ($productBrowseUrl): ?>
            <a href="<?= esc($productBrowseUrl) ?>" target="_blank" title="View product">
                <img src="<?= esc($img) ?>" alt="" class="quote-line-image quote-image-thumb js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');">
            </a>
        <?php else: ?>
            <img src="<?= esc($img) ?>" alt="" class="quote-line-image quote-image-thumb js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');">
        <?php endif; ?>
    </td>
    <td class="line-desc">
        <?php if ($productBrowseUrl): ?>
            <a href="<?= esc($productBrowseUrl) ?>" target="_blank" title="View product" style="text-decoration:none; color:inherit;">
                <div class="fw-semibold" style="line-height:1.2; color:#0066cc;">
                    <?= esc((string)($ln['product_name'] ?? ($ln['description'] ?? '—'))) ?>
                </div>
            </a>
        <?php else: ?>
            <div class="fw-semibold" style="line-height:1.2;">
                <?= esc((string)($ln['product_name'] ?? ($ln['description'] ?? '—'))) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($ln['variant_attrs']) && is_array($ln['variant_attrs'])): ?>
            <div class="text-muted" style="font-size:0.76rem;line-height:1.25;">
                <?php
                    $pairs = [];
                    foreach ($ln['variant_attrs'] as $attrKey => $attrValue) {
                        $k = trim((string)$attrKey);
                        $v = trim((string)$attrValue);
                        if ($k === '' || $v === '') continue;
                        $pairs[] = $k . ': ' . $v;
                    }
                ?>
                <?= esc(implode(' | ', $pairs)) ?>
            </div>
        <?php endif; ?>
    </td>
    <td class="line-unit"><?= esc((string)($ln['unit'] ?? 'pcs')) ?></td>
    <td class="text-end line-qty"><?= $fmt($ln['quantity'] ?? 0, 2) ?></td>
    <td class="text-end line-unit-price"><?= $fmt($ln['unit_price'] ?? 0, 2) ?></td>
    <td class="text-end line-disc-percent col-disc" data-discount-type="<?= esc($discType) ?>"><span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;"><?= esc($discSuffix) ?></span><span><?= esc((string)($ln['discount_value'] ?? '0')) ?></span></td>
    <td class="text-end line-disc-amt col-disc"><?= $fmt($ln['discount_amount'] ?? 0, 2) ?></td>
    <td class="text-end line-tax-percent col-tax"><?= esc((string)($ln['tax_rate'] ?? '')) ?></td>
    <td class="text-end line-tax-amt col-tax"><?= $fmt($ln['tax_amount'] ?? 0, 2) ?></td>
    <td class="text-end line-total"><?= $fmt($ln['line_total'] ?? 0, 2) ?></td>
    <td>
        <div class="d-flex align-items-center gap-1">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-line">Edit</button>
                <button type="button" class="btn btn-sm btn-success btn-save-line" style="display:none">Save</button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-line" style="display:none">Cancel</button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-line" title="Delete this line"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    </td>
</tr>
<?php if ($currentSectionId > 0 && $nextIsSectionOrEnd): ?>
<tr class="doc-section-subtotal-row" data-section-id="<?= $currentSectionId ?>">
    <td colspan="11" class="text-end text-muted small fw-semibold">Section Subtotal</td>
    <td class="text-end fw-semibold"><?= $fmt($sectionSubtotals[$currentSectionId] ?? 0, 2) ?></td>
    <td></td>
</tr>
<?php endif; ?>
<?php
    elseif ($docType === 'sales_order'):
?>
<?php $lineNo++; ?>
<tr data-line-id="<?= $id ?>" data-display-type="line" data-line-updated-at="<?= esc((string)($ln['updated_at'] ?? '')) ?>">
    <td class="text-center text-muted"><?= $lineNo ?></td>
    <td><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><?= esc((string)($ln['product_code'] ?? '')) ?></td>
    <td><?php $img = $normalizeImg($ln['product_image_url'] ?? ''); ?><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');"></td>
    <td><div class="fw-semibold" style="line-height:1.2;"><?= esc((string)($ln['product_name'] ?? ($ln['description'] ?? '—'))) ?></div></td>
    <td><?= esc((string)($ln['unit'] ?? 'pcs')) ?></td>
    <td class="text-end"><?= $fmt($ln['quantity'] ?? 0, 2) ?></td>
    <td class="text-end"><?= $fmt($ln['unit_price'] ?? 0, 2) ?></td>
    <td class="text-end col-disc"><?= esc((string)($ln['discount_value'] ?? '0')) ?></td>
    <td class="text-end col-disc"><?= $fmt($ln['discount_amount'] ?? 0, 2) ?></td>
    <td class="text-end col-tax"><?= esc((string)($ln['tax_rate'] ?? '')) ?></td>
    <td class="text-end col-tax"><?= $fmt($ln['tax_amount'] ?? 0, 2) ?></td>
    <td class="text-end">—</td>
    <td class="text-end">—</td>
    <td class="text-end">—</td>
    <td class="text-end">—</td>
    <td class="text-end">—</td>
    <td class="text-end">—</td>
    <td class="text-end fw-semibold"><span><?= $fmt($ln['line_total'] ?? 0, 2) ?></span></td>
</tr>
<?php if ($currentSectionId > 0 && $nextIsSectionOrEnd): ?>
<tr class="doc-section-subtotal-row" data-section-id="<?= $currentSectionId ?>">
    <td colspan="17" class="text-end text-muted small fw-semibold">Section Subtotal</td>
    <td class="text-end fw-semibold"><?= $fmt($sectionSubtotals[$currentSectionId] ?? 0, 2) ?></td>
</tr>
<?php endif; ?>
<?php
    elseif ($docType === 'customer_invoice'):
        $qty = (float)($ln['quantity'] ?? 0);
        $unitPrice = (float)($ln['unit_price'] ?? 0);
        $discType = strtolower((string)($ln['discount_type'] ?? 'percent'));
        if (!in_array($discType, ['percent', 'fixed'], true)) $discType = 'percent';
        $discSuffix = $discType === 'fixed' ? 'Fix' : '%';
        $discVal = isset($ln['discount_value']) ? (float)$ln['discount_value'] : 0.0;
        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
        $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : 0.0;
        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : 0.0;
        $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : (($qty * $unitPrice) - $discAmt + $taxAmt);
        $img = $normalizeImg(($ln['variant_image_url'] ?? '') !== '' ? ($ln['variant_image_url'] ?? '') : ($ln['product_image_url'] ?? ''));
        $code = (string)($ln['product_code'] ?? ($ln['product_id'] ?? ''));
        $name = trim((string)($ln['product_name'] ?? ''));
        $desc = trim((string)($ln['description'] ?? ''));
        $text = $name !== '' ? $name : ($desc !== '' ? $desc : ($code !== '' ? $code : '—'));
?>
<?php $lineNo++; ?>
<tr data-line-id="<?= $id ?>" data-display-type="line" data-line-updated-at="<?= esc((string)($ln['updated_at'] ?? '')) ?>" data-discount-type="<?= esc($discType) ?>">
    <td class="text-center text-muted"><?= $lineNo ?></td>
    <td><?= esc($code) ?></td>
    <td><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><img src="<?= esc($img) ?>" alt="" class="inv-line-image js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');"></td>
    <td>
        <div class="fw-semibold" style="line-height:1.2;"><?= esc($text) ?></div>
        <?php if ($desc !== '' && $desc !== $text): ?>
            <div class="text-muted" style="font-size:0.8rem;line-height:1.2;"><?= esc($desc) ?></div>
        <?php endif; ?>
    </td>
    <td><?= esc((string)($ln['unit'] ?? 'pcs')) ?></td>
    <td class="text-end"><?= $fmt($qty, 2) ?></td>
    <td class="text-end"><?= $fmt($unitPrice, 2) ?></td>
    <td class="text-end"><span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;"><?= esc($discSuffix) ?></span><span><?= esc(rtrim(rtrim(number_format($discVal, 2), '0'), '.')) ?></span></td>
    <td class="text-end"><?= $fmt($discAmt, 2) ?></td>
    <td class="text-end"><?= esc(rtrim(rtrim(number_format($taxRate, 2), '0'), '.') . '%') ?></td>
    <td class="text-end"><?= $fmt($taxAmt, 2) ?></td>
    <td class="text-end fw-semibold"><span><?= $fmt($lineTotal, 2) ?></span></td>
</tr>
<?php if ($currentSectionId > 0 && $nextIsSectionOrEnd): ?>
<tr class="doc-section-subtotal-row" data-section-id="<?= $currentSectionId ?>">
    <td colspan="11" class="text-end text-muted small fw-semibold">Section Subtotal</td>
    <td class="text-end fw-semibold"><?= $fmt($sectionSubtotals[$currentSectionId] ?? 0, 2) ?></td>
</tr>
<?php endif; ?>
<?php
    elseif ($docType === 'purchase_order'):
        $qty = $ln['qty'] ?? ($ln['quantity'] ?? 0);
        $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : ((float)$qty * (float)($ln['unit_price'] ?? 0));
?>
<?php $lineNo++; ?>
<tr data-line-id="<?= $id ?>" data-display-type="line" data-line-updated-at="<?= esc((string)($ln['updated_at'] ?? '')) ?>">
    <td class="text-center text-muted"><?= $lineNo ?></td>
    <td><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><?= esc((string)($ln['product_code'] ?? '')) ?></td>
    <td><?php $img = $normalizeImg(($ln['variant_image_url'] ?? '') !== '' ? ($ln['variant_image_url'] ?? '') : ($ln['product_image_url'] ?? '')); ?><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');"></td>
    <td><div class="fw-semibold" style="line-height:1.2;"><?= esc((string)($ln['product_name'] ?? ($ln['description'] ?? '—'))) ?></div></td>
    <td><?= esc((string)($ln['unit'] ?? ($ln['product_unit'] ?? 'pcs'))) ?></td>
    <td class="text-end"><?= $fmt($qty, 2) ?></td>
    <td class="text-end"><?= $fmt($ln['qty_received'] ?? 0, 2) ?></td>
    <td class="text-end"><?= $fmt(max(0, (float)$qty - (float)($ln['qty_received'] ?? 0)), 2) ?></td>
    <td class="text-end"><?= $fmt($ln['unit_price'] ?? 0, 2) ?></td>
    <td class="text-end fw-semibold"><span><?= $fmt($lineTotal, 2) ?></span></td>
</tr>
<?php if ($currentSectionId > 0 && $nextIsSectionOrEnd): ?>
<tr class="doc-section-subtotal-row" data-section-id="<?= $currentSectionId ?>">
    <td colspan="9" class="text-end text-muted small fw-semibold">Section Subtotal</td>
    <td class="text-end fw-semibold"><?= $fmt($sectionSubtotals[$currentSectionId] ?? 0, 2) ?></td>
</tr>
<?php endif; ?>
<?php
    else:
        $qty = $ln['qty'] ?? ($ln['quantity'] ?? 0);
        $price = $ln['unit_price'] ?? ($ln['unit_cost'] ?? 0);
        $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : ((float)$qty * (float)$price);
?>
<?php $lineNo++; ?>
<tr data-line-id="<?= $id ?>" data-display-type="line" data-line-updated-at="<?= esc((string)($ln['updated_at'] ?? '')) ?>">
    <td class="text-center text-muted"><?= $lineNo ?></td>
    <td><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><?= esc((string)($ln['product_code'] ?? '')) ?></td>
    <td><?php $img = $normalizeImg($ln['product_image_url'] ?? ''); ?><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= esc($defaultImg) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImg) ?>');"></td>
    <td><div class="fw-semibold" style="line-height:1.2;"><?= esc((string)($ln['product_name'] ?? ($ln['description'] ?? '—'))) ?></div></td>
    <td><?= esc((string)($ln['unit'] ?? 'pcs')) ?></td>
    <td class="text-end"><?= $fmt($qty, 2) ?></td>
    <td class="text-end"><?= $fmt($price, 2) ?></td>
    <td class="text-end"><span><?= $fmt($lineTotal, 2) ?></span></td>
</tr>
<?php if ($currentSectionId > 0 && $nextIsSectionOrEnd): ?>
<tr class="doc-section-subtotal-row" data-section-id="<?= $currentSectionId ?>">
    <td colspan="7" class="text-end text-muted small fw-semibold">Section Subtotal</td>
    <td class="text-end fw-semibold"><?= $fmt($sectionSubtotals[$currentSectionId] ?? 0, 2) ?></td>
</tr>
<?php endif; ?>
<?php
    endif;
endforeach;
