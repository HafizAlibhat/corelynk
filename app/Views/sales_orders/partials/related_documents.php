<?php
/**
 * Every document this sales order touched, in one browsable timeline: lots
 * sent to vendors, receipts back, the payable POs those lots were bundled
 * into, the bills raised against them, delivery order(s), and the customer
 * invoice(s). Expects: $relatedDocuments (see SalesOrders::buildRelatedDocuments).
 */
?>
<p class="text-muted small mb-2"><?= count($relatedDocuments) ?> document<?= count($relatedDocuments) === 1 ? '' : 's' ?> linked to this order — click any row to open it.</p>
<div class="table-responsive mb-2">
    <table class="table table-sm align-middle so-related-docs-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Document #</th>
                <th>Party</th>
                <th>Details</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($relatedDocuments as $doc): ?>
            <tr class="so-related-doc-row" onclick="window.open('<?= $doc['url'] ?>', '_blank')" style="cursor:pointer;">
                <td>
                    <span class="badge bg-<?= esc($doc['badge']) ?>-subtle text-<?= esc($doc['badge']) ?>-emphasis border">
                        <i class="bi <?= esc($doc['icon']) ?> me-1"></i><?= esc($doc['type']) ?>
                    </span>
                </td>
                <td>
                    <a href="<?= $doc['url'] ?>" target="_blank" class="fw-semibold text-decoration-none" onclick="event.stopPropagation()"><?= esc($doc['number']) ?></a>
                </td>
                <td><?= esc($doc['party']) ?></td>
                <td class="text-muted small"><?= esc($doc['detail']) ?></td>
                <td class="text-muted small"><?= $doc['date'] ? esc(date('d M Y', strtotime($doc['date']))) : '—' ?></td>
                <td><span class="badge bg-light text-dark border"><?= esc($doc['status']) ?></span></td>
                <td class="text-end fw-semibold"><?= $doc['amount'] !== null ? number_format((float) $doc['amount'], 2) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
