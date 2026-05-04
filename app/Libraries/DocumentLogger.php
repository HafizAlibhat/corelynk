<?php

namespace App\Libraries;

use Config\Database;

/**
 * DocumentLogger — Immutable activity log for all business documents.
 *
 * Records CANNOT be updated or deleted — not by code, not by any user.
 * Think Odoo chatter: every creation, status change, field edit, line change
 * is captured here permanently.
 *
 * Usage:
 *   DocumentLogger::log('quotation', $id, 'created');
 *   DocumentLogger::log('quotation', $id, 'status_changed', ['from' => 'draft', 'to' => 'sent']);
 *   DocumentLogger::log('quotation', $id, 'line_added',    ['product' => 'Knife', 'qty' => 5, 'price' => 31.00]);
 *   DocumentLogger::log('quotation', $id, 'field_changed', ['field' => 'customer', 'from' => 'Old Co', 'to' => 'New Co']);
 */
class DocumentLogger
{
    // ── Document type constants ──────────────────────────────────────────────
    const TYPE_QUOTATION       = 'quotation';
    const TYPE_SALES_ORDER     = 'sales_order';
    const TYPE_PURCHASE_ORDER  = 'purchase_order';
    const TYPE_PURCHASE_RFQ    = 'purchase_rfq';
    const TYPE_INVOICE         = 'customer_invoice';

    // ── Action type constants ────────────────────────────────────────────────
    const ACTION_CREATED        = 'created';
    const ACTION_UPDATED        = 'updated';
    const ACTION_STATUS_CHANGED = 'status_changed';
    const ACTION_LINE_ADDED     = 'line_added';
    const ACTION_LINE_REMOVED   = 'line_removed';
    const ACTION_LINE_UPDATED   = 'line_updated';
    const ACTION_FIELD_CHANGED  = 'field_changed';
    const ACTION_PDF_DOWNLOADED = 'pdf_downloaded';
    const ACTION_CANCELLED      = 'cancelled';
    const ACTION_CONFIRMED      = 'confirmed';
    const ACTION_SENT           = 'sent';
    const ACTION_POSTED         = 'posted';

    // ── Write ────────────────────────────────────────────────────────────────

    /**
     * Write an immutable log entry.
     *
     * @param string $documentType  e.g. 'quotation'
     * @param int    $documentId    primary key of the document
     * @param string $action        one of the ACTION_* constants
     * @param array  $context       optional metadata (from/to, field, product, qty…)
     */
    public static function log(
        string $documentType,
        int    $documentId,
        string $action,
        array  $context = []
    ): void {
        try {
            self::ensureSchema();

            $userId  = (int) (session()->get('user_id') ?? 0);
            $request = service('request');

            Database::connect()->table('document_logs')->insert([
                'document_type' => $documentType,
                'document_id'   => $documentId,
                'user_id'       => $userId ?: null,
                'action'        => $action,
                'context'       => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'    => $request->getIPAddress(),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Never crash the main request because of a logging failure
            log_message('warning', '[DocumentLogger] Failed to write log: ' . $e->getMessage());
        }
    }

    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * Return all log entries for a specific document, oldest-first,
     * joined with the user's name and avatar initial.
     *
     * @return array[]
     */
    public static function getForDocument(string $documentType, int $documentId): array
    {
        try {
            self::ensureSchema();
            $db = Database::connect();

            $rows = $db->table('document_logs dl')
                ->select('dl.*, u.first_name, u.last_name, u.username, u.email')
                ->join('users u', 'u.id = dl.user_id', 'left')
                ->where('dl.document_type', $documentType)
                ->where('dl.document_id',   $documentId)
                ->orderBy('dl.id', 'ASC')
                ->get()
                ->getResultArray();

            return array_map([self::class, 'formatRow'], $rows);
        } catch (\Throwable $e) {
            log_message('warning', '[DocumentLogger] getForDocument failed: ' . $e->getMessage());
            return [];
        }
    }

    // ── Formatting helper ────────────────────────────────────────────────────

    private static function formatRow(array $row): array
    {
        $context = [];
        if (!empty($row['context'])) {
            $decoded = json_decode($row['context'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName  = trim((string) ($row['last_name']  ?? ''));
        $username  = trim((string) ($row['username']   ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            $displayName = trim($firstName . ' ' . $lastName);
        } elseif ($username !== '') {
            $displayName = $username;
        } else {
            $displayName = 'System';
        }

        // Avatar initials (up to 2 letters)
        $initials = '';
        if ($firstName !== '') $initials .= strtoupper($firstName[0]);
        if ($lastName  !== '') $initials .= strtoupper($lastName[0]);
        if ($initials  === '') $initials  = strtoupper($username[0] ?? 'S');

        return [
            'id'            => (int) $row['id'],
            'document_type' => $row['document_type'],
            'document_id'   => (int) $row['document_id'],
            'user_id'       => (int) ($row['user_id'] ?? 0),
            'display_name'  => $displayName,
            'initials'      => $initials,
            'action'        => $row['action'],
            'context'       => $context,
            'ip_address'    => $row['ip_address'] ?? '',
            'created_at'    => $row['created_at'],
            'human_time'    => self::humanTime($row['created_at']),
            'description'   => self::describe($row['action'], $context),
        ];
    }

    /**
     * Convert an action + context into a human-readable sentence.
     */
    public static function describe(string $action, array $ctx): string
    {
        switch ($action) {
            case self::ACTION_CREATED:
                return 'Created this document.';

            case self::ACTION_STATUS_CHANGED:
                $from = ucfirst((string) ($ctx['from'] ?? ''));
                $to   = ucfirst((string) ($ctx['to']   ?? ''));
                if ($from && $to) return "Status changed from <strong>{$from}</strong> → <strong>{$to}</strong>.";
                if ($to)          return "Status set to <strong>{$to}</strong>.";
                return 'Status updated.';

            case self::ACTION_CONFIRMED:
                return 'Confirmed this document.';

            case self::ACTION_SENT:
                return 'Marked as <strong>Sent</strong>.';

            case self::ACTION_POSTED:
                return 'Posted this document.';

            case self::ACTION_CANCELLED:
                return 'Cancelled this document.';

            case self::ACTION_LINE_ADDED:
                $prod = esc((string) ($ctx['product'] ?? 'item'));
                $qty  = isset($ctx['qty'])   ? ' × ' . $ctx['qty']  : '';
                $price= isset($ctx['price']) ? ' @ '  . $ctx['price'] : '';
                return "Added line: <strong>{$prod}</strong>{$qty}{$price}.";

            case self::ACTION_LINE_REMOVED:
                $prod = esc((string) ($ctx['product'] ?? 'item'));
                return "Removed line: <strong>{$prod}</strong>.";

            case self::ACTION_LINE_UPDATED:
                $prod = esc((string) ($ctx['product'] ?? 'item'));
                $parts = [];
                if (isset($ctx['qty_from'], $ctx['qty_to'])) {
                    $parts[] = "qty <strong>{$ctx['qty_from']}</strong> → <strong>{$ctx['qty_to']}</strong>";
                }
                if (isset($ctx['price_from'], $ctx['price_to'])) {
                    $parts[] = "price <strong>{$ctx['price_from']}</strong> → <strong>{$ctx['price_to']}</strong>";
                }
                $detail = $parts ? ' (' . implode(', ', $parts) . ')' : '';
                return "Updated line: <strong>{$prod}</strong>{$detail}.";

            case self::ACTION_FIELD_CHANGED:
                $field = ucfirst(str_replace('_', ' ', (string) ($ctx['field'] ?? 'field')));
                $from  = esc((string) ($ctx['from'] ?? ''));
                $to    = esc((string) ($ctx['to']   ?? ''));
                if ($from !== '' && $to !== '') {
                    return "{$field} changed from <strong>{$from}</strong> → <strong>{$to}</strong>.";
                }
                if ($to !== '') return "{$field} set to <strong>{$to}</strong>.";
                return "{$field} updated.";

            case self::ACTION_UPDATED:
                return 'Document details updated.';

            case self::ACTION_PDF_DOWNLOADED:
                return 'PDF downloaded.';

            default:
                return ucfirst(str_replace('_', ' ', $action)) . '.';
        }
    }

    /**
     * Human-readable relative timestamp.
     */
    private static function humanTime(?string $datetime): string
    {
        if (!$datetime) return '';
        try {
            $ts  = strtotime($datetime);
            $now = time();
            $diff = $now - $ts;

            if ($diff < 60)     return 'Just now';
            if ($diff < 3600)   return floor($diff / 60)   . 'm ago';
            if ($diff < 86400)  return floor($diff / 3600)  . 'h ago';
            if ($diff < 604800) return floor($diff / 86400) . 'd ago';

            return date('M j, Y g:ia', $ts);
        } catch (\Throwable $_) {
            return $datetime;
        }
    }

    // ── Schema ───────────────────────────────────────────────────────────────

    private static bool $schemaChecked = false;

    private static function ensureSchema(): void
    {
        if (self::$schemaChecked) return;
        self::$schemaChecked = true;

        $db = Database::connect();

        if (!$db->tableExists('document_logs')) {
            $db->query('
                CREATE TABLE document_logs (
                    id            BIGINT       UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    document_type VARCHAR(50)  NOT NULL,
                    document_id   INT          UNSIGNED NOT NULL,
                    user_id       INT          UNSIGNED NULL,
                    action        VARCHAR(80)  NOT NULL,
                    context       TEXT         NULL,
                    ip_address    VARCHAR(45)  NULL,
                    created_at    DATETIME     NOT NULL,
                    INDEX idx_dl_doc  (document_type, document_id),
                    INDEX idx_dl_user (user_id),
                    INDEX idx_dl_time (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }
    }
}
