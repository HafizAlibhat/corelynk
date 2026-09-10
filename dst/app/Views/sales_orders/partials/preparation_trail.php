<?php
/**
 * Movement trail for one product on one sales order. Rendered into the trail
 * modal on the sales order page.
 * Expects: $sendNotes, $receivesByNote, $rejectionsByNote.
 */
$originLabel = [
    'sales_order' => ['Sent', 'primary'],
    'reroute'     => ['Rerouted to another vendor', 'warning'],
    'rework'      => ['Sent for rework', 'danger'],
    'resend'      => ['Re-sent', 'primary'],
];
$fmt = static fn ($q) => number_format((float) $q, 2);
?>
<?php if (empty($sendNotes)): ?>
    <p class="text-muted mb-0">Nothing has been sent out for this product yet.</p>
<?php else: ?>
    <div class="list-group list-group-flush">
    <?php foreach ($sendNotes as $note): ?>
        <?php
            $noteId = (int) $note['id'];
            [$label, $tone] = $originLabel[$note['origin'] ?? 'sales_order'] ?? $originLabel['sales_order'];
            $outstanding = max(0, (float) $note['qty'] - (float) $note['qty_received'] - (float) ($note['qty_rerouted'] ?? 0));
        ?>
        <div class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <span class="badge bg-<?= $tone ?>-subtle text-<?= $tone ?> border"><?= esc($label) ?></span>
                    <span class="fw-semibold ms-1"><?= $fmt($note['qty']) ?> pcs &rarr; <?= esc($note['vendor_name'] ?? 'Vendor') ?></span>
                    <div class="text-muted small">
                        <?= esc($note['reference_no']) ?>
                        <?php if (! empty($note['parent_send_note_id'])): ?>
                            &middot; split from note #<?= (int) $note['parent_send_note_id'] ?>
                        <?php endif; ?>
                        &middot; <?= esc($note['step_name'] ?? '-') ?>
                        &middot; <?= esc($note['from_location_name'] ?? '-') ?> &rarr; <?= esc($note['to_location_name'] ?? '-') ?>
                        &middot; <?= esc($note['created_at']) ?>
                        &middot; <a href="<?= site_url('vendor-receive/' . $noteId . '/send-slip') ?>" target="_blank">
                            <i class="bi bi-printer"></i> send note
                        </a>
                    </div>
                </div>
                <div class="text-end small">
                    <?php if ($outstanding > 0.0001): ?>
                        <span class="badge bg-info-subtle text-info border"><?= $fmt($outstanding) ?> still at vendor</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border">Closed</span>
                    <?php endif; ?>
                    <?php if ((float) ($note['qty_rerouted'] ?? 0) > 0): ?>
                        <div class="text-warning mt-1"><?= $fmt($note['qty_rerouted']) ?> rerouted away</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach (($receivesByNote[$noteId] ?? []) as $rec): ?>
                <div class="ms-3 mt-2 ps-2 border-start">
                    <div class="small">
                        <i class="bi bi-box-arrow-in-down me-1"></i>
                        Received <strong><?= $fmt($rec['qty_received']) ?></strong>
                        &middot; accepted <span class="text-success"><?= $fmt($rec['qty_accepted']) ?></span>
                        &middot; rejected <span class="text-danger"><?= $fmt($rec['qty_rejected']) ?></span>
                    </div>
                    <div class="text-muted small">
                        <?= esc($rec['reference_no']) ?> &middot; <?= esc($rec['created_at']) ?>
                        &middot; <a href="<?= site_url('vendor-receive/note/' . (int) $rec['id'] . '/slip') ?>" target="_blank">
                            <i class="bi bi-printer"></i> receipt
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach (($rejectionsByNote[$noteId] ?? []) as $rej): ?>
                <div class="ms-3 mt-2 ps-2 border-start border-danger">
                    <div class="small text-danger">
                        <i class="bi bi-x-octagon me-1"></i>
                        <?= $fmt($rej['qty_rejected']) ?> rejected
                        <?php if (! empty($rej['rejection_reason_text'])): ?>
                            &middot; <?= esc($rej['rejection_reason_text']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small">
                        <?= esc($rej['rejection_ref']) ?> &middot; action: <?= esc($rej['action_type']) ?>
                        &middot; status: <?= esc(str_replace('_', ' ', $rej['status'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
