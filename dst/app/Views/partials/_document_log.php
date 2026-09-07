<?php
/**
 * _document_log.php — Reusable activity log panel (Odoo-style chatter)
 *
 * Required variables:
 *   $logEntries  array  — from DocumentLogger::getForDocument()
 *
 * Optional:
 *   $logDocType  string — for the heading label
 */

$entries = $logEntries ?? [];
?>

<div class="document-log-panel mt-5" id="documentActivityLog">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-clock-history text-muted fs-5"></i>
        <h6 class="mb-0 fw-semibold text-muted text-uppercase tracking-wide" style="letter-spacing:.05em;font-size:.8rem;">
            Activity Log
        </h6>
        <span class="badge bg-secondary rounded-pill ms-1 fw-normal" style="font-size:.7rem;">
            <?= count($entries) ?>
        </span>
        <span class="ms-auto small text-muted fst-italic" style="font-size:.72rem;">
            All changes are recorded permanently. Nobody can edit or delete these logs.
        </span>
    </div>

    <?php if (empty($entries)): ?>
    <div class="text-center text-muted py-4" style="font-size:.85rem;">
        <i class="bi bi-journal-x fs-4 d-block mb-2 opacity-50"></i>
        No activity recorded yet for this document.
    </div>
    <?php else: ?>

    <div class="document-log-timeline">
        <?php foreach ($entries as $i => $entry):
            $action   = $entry['action']      ?? '';
            $initials = $entry['initials']     ?? '?';
            $name     = $entry['display_name'] ?? 'System';
            $desc     = $entry['description']  ?? '';
            $time     = $entry['human_time']   ?? '';
            $fullDate = $entry['created_at']   ?? '';
            $isFirst  = ($i === 0);
            $isLast   = ($i === count($entries) - 1);

            // Badge colour per action
            $badgeClass = match(true) {
                str_starts_with($action, 'created')        => 'text-success',
                str_starts_with($action, 'status_changed') => 'text-primary',
                str_starts_with($action, 'confirmed')      => 'text-success',
                str_starts_with($action, 'posted')         => 'text-success',
                str_starts_with($action, 'sent')           => 'text-info',
                str_starts_with($action, 'cancelled')      => 'text-danger',
                str_starts_with($action, 'line_added')     => 'text-success',
                str_starts_with($action, 'line_removed')   => 'text-danger',
                str_starts_with($action, 'line_updated')   => 'text-warning',
                str_starts_with($action, 'pdf')            => 'text-secondary',
                default                                    => 'text-muted',
            };

            $icon = match(true) {
                str_starts_with($action, 'created')        => 'bi-plus-circle-fill',
                str_starts_with($action, 'status_changed') => 'bi-arrow-right-circle-fill',
                str_starts_with($action, 'confirmed')      => 'bi-check-circle-fill',
                str_starts_with($action, 'posted')         => 'bi-check2-all',
                str_starts_with($action, 'sent')           => 'bi-send-fill',
                str_starts_with($action, 'cancelled')      => 'bi-x-circle-fill',
                str_starts_with($action, 'line_added')     => 'bi-plus-square-fill',
                str_starts_with($action, 'line_removed')   => 'bi-dash-square-fill',
                str_starts_with($action, 'line_updated')   => 'bi-pencil-square',
                str_starts_with($action, 'pdf')            => 'bi-file-earmark-arrow-down',
                default                                    => 'bi-dot',
            };

            // Avatar colour from initials
            $colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
            $avatarColor = $colors[abs(crc32($name)) % count($colors)];
        ?>
        <div class="log-entry d-flex gap-3 <?= $isLast ? '' : 'mb-3' ?>">
            <!-- Timeline spine -->
            <div class="log-spine d-flex flex-column align-items-center" style="width:32px;flex-shrink:0;">
                <div class="log-avatar d-flex align-items-center justify-content-center rounded-circle fw-bold text-white"
                     style="width:30px;height:30px;font-size:.72rem;background:<?= $avatarColor ?>;flex-shrink:0;">
                    <?= esc($initials) ?>
                </div>
                <?php if (!$isLast): ?>
                <div style="width:2px;flex:1;background:var(--bs-border-color,#dee2e6);margin-top:4px;min-height:16px;"></div>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="log-content flex-grow-1 pb-3" <?= $isLast ? 'style="padding-bottom:0!important;"' : '' ?>>
                <div class="d-flex align-items-start gap-2 flex-wrap">
                    <i class="bi <?= $icon ?> <?= $badgeClass ?> mt-1" style="font-size:.85rem;flex-shrink:0;"></i>
                    <div class="flex-grow-1">
                        <span class="fw-semibold" style="font-size:.85rem;"><?= esc($name) ?></span>
                        <span class="text-muted" style="font-size:.85rem;"> — <?= $desc /* already escaped inside DocumentLogger::describe() */ ?></span>
                    </div>
                    <span class="text-muted ms-auto text-nowrap"
                          style="font-size:.75rem;"
                          title="<?= esc($fullDate) ?>">
                        <?= esc($time) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<style>
.document-log-panel { border-top: 1px solid var(--bs-border-color, #dee2e6); padding-top: 1.25rem; }
.document-log-timeline .log-entry:last-child .log-content { border-bottom: none; }
</style>
