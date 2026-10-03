<?php

namespace App\Services;

use App\Models\CustomerInvoiceScheduleModel;
use App\Models\PaymentTermModel;

/**
 * Turns a milestone payment term ("80% advance, 20% net 30 after delivery")
 * into the concrete instalment schedule stored against an invoice, and reports
 * how far the invoice's payments have covered that schedule.
 *
 * Paid state is never stored: it is derived on read by pouring the invoice's
 * already-posted payment total across the milestones in due order, so the
 * schedule can never drift out of sync with the payment ledger.
 */
class InvoicePaymentScheduleService
{
    public const BASES = ['invoice_date', 'delivery_date'];

    private CustomerInvoiceScheduleModel $scheduleModel;
    private PaymentTermModel $termModel;

    public function __construct()
    {
        $this->scheduleModel = new CustomerInvoiceScheduleModel();
        $this->termModel     = new PaymentTermModel();
    }

    /**
     * Validate and clean a milestone definition coming from JSON or a form.
     * Returns [] when the definition is unusable so callers fall back to the
     * plain single-due-date behaviour.
     */
    public function normalizeMilestones($raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            $raw = $raw === '' ? [] : (json_decode($raw, true) ?: []);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $sum = 0.0;
        foreach ($raw as $m) {
            if (!is_array($m)) {
                continue;
            }
            $pct = round((float)($m['percentage'] ?? 0), 4);
            if ($pct <= 0) {
                continue;
            }

            $basis = (string)($m['basis'] ?? 'invoice_date');
            if (!in_array($basis, self::BASES, true)) {
                $basis = 'invoice_date';
            }

            $label = trim((string)($m['label'] ?? ''));
            if ($label === '') {
                $label = 'Instalment ' . (count($out) + 1);
            }

            $out[] = [
                'label'       => mb_substr($label, 0, 120),
                'percentage'  => $pct,
                'basis'       => $basis,
                'offset_days' => max(0, (int)($m['offset_days'] ?? 0)),
            ];
            $sum += $pct;
        }

        // A schedule that does not add up to the invoice would under- or
        // over-bill the customer, so reject it rather than silently prorating.
        if (empty($out) || abs($sum - 100.0) > 0.01) {
            return [];
        }

        return $out;
    }

    /** Milestones defined on a payment term, or a single 100% one for flat terms. */
    public function milestonesForTerm(?int $termId): array
    {
        if (!$termId) {
            return [];
        }
        $term = $this->termModel->find($termId);
        if (!$term) {
            return [];
        }

        $milestones = $this->normalizeMilestones($term['milestones'] ?? null);
        if (!empty($milestones)) {
            return $milestones;
        }

        // Flat term: one 100% milestone at net_days keeps a single code path.
        return [[
            'label'       => 'Full Payment',
            'percentage'  => 100.0,
            'basis'       => 'invoice_date',
            'offset_days' => max(0, (int)($term['net_days'] ?? 0)),
        ]];
    }

    /**
     * Delivery date backing "x days after delivery" milestones: the latest
     * confirmed delivery on the invoice's sales order, or null if undelivered.
     */
    public function resolveDeliveryDate(array $invoice): ?string
    {
        $soId = (int)($invoice['sales_order_id'] ?? 0);
        if ($soId <= 0) {
            return null;
        }

        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('delivery_orders')) {
                return null;
            }
            $cols = $db->getFieldNames('delivery_orders');

            $candidates = array_values(array_filter(
                ['delivered_at', 'delivery_confirmed_at'],
                static fn($c) => in_array($c, $cols, true)
            ));
            if (empty($candidates)) {
                return null;
            }

            $expr = count($candidates) > 1
                ? 'COALESCE(' . implode(', ', $candidates) . ')'
                : $candidates[0];

            $row = $db->table('delivery_orders')
                ->select('MAX(' . $expr . ') AS delivered_on', false)
                ->where('sales_order_id', $soId)
                ->get()->getFirstRow('array');

            $val = trim((string)($row['delivered_on'] ?? ''));
            if ($val === '' || strpos($val, '0000') === 0) {
                return null;
            }
            $ts = strtotime($val);

            return $ts ? date('Y-m-d', $ts) : null;
        } catch (\Throwable $e) {
            log_message('error', 'InvoicePaymentScheduleService::resolveDeliveryDate: ' . $e->getMessage());

            return null;
        }
    }

    private function dueDateFor(array $milestone, string $issueDate, ?string $deliveryDate): ?string
    {
        $anchor = $milestone['basis'] === 'delivery_date' ? $deliveryDate : $issueDate;
        if (empty($anchor)) {
            return null;
        }
        $ts = strtotime($anchor);
        if (!$ts) {
            return null;
        }

        return date('Y-m-d', strtotime('+' . (int)$milestone['offset_days'] . ' days', $ts));
    }

    /**
     * Split a total across milestone percentages. The last instalment absorbs
     * the rounding remainder so the schedule always sums to the total exactly.
     *
     * @return float[] amounts, indexed alongside $milestones
     */
    public function splitAmounts(array $milestones, float $total): array
    {
        $total  = round($total, 2);
        $out    = [];
        $running = 0.0;
        $last   = count($milestones) - 1;

        foreach ($milestones as $i => $m) {
            $amount = $i === $last
                ? round($total - $running, 2)
                : round($total * (((float)$m['percentage']) / 100.0), 2);
            $running = round($running + $amount, 2);
            $out[$i] = $amount;
        }

        return $out;
    }

    /**
     * Pour a paid total across instalment amounts in order, settling each in
     * full before moving to the next.
     *
     * @return float[] amount paid against each instalment
     */
    public function allocatePayments(array $amounts, float $paidTotal): array
    {
        $remaining = max(0.0, round($paidTotal, 2));
        $out = [];

        foreach ($amounts as $i => $amount) {
            $paid      = min(round((float)$amount, 2), $remaining);
            $remaining = round($remaining - $paid, 2);
            $out[$i]   = round($paid, 2);
        }

        return $out;
    }

    /**
     * Date each instalment was settled, using the same waterfall as
     * allocatePayments(): the date of the payment that carried the running
     * total past that instalment's share. Partly paid instalments report the
     * date of the last payment that touched them.
     *
     * @param array<int,float>              $amounts  instalment amounts, in due order
     * @param array<int,array<string,mixed>> $payments ordered by payment_date, each
     *                                                with allocated_amount + payment_date
     *
     * @return array<int,string|null>
     */
    public function paymentDates(array $amounts, array $payments): array
    {
        $dates   = array_fill_keys(array_keys($amounts), null);
        $running = 0.0;
        $lower   = 0.0;
        $bounds  = [];
        foreach ($amounts as $i => $amount) {
            $upper      = round($lower + round((float)$amount, 2), 2);
            $bounds[$i] = [$lower, $upper];
            $lower      = $upper;
        }

        foreach ($payments as $p) {
            $amount = round((float)($p['allocated_amount'] ?? 0), 2);
            $date   = $p['payment_date'] ?? null;
            if ($amount <= 0.005 || empty($date)) {
                continue;
            }
            $before  = $running;
            $running = round($running + $amount, 2);

            foreach ($bounds as $i => [$from, $to]) {
                // This payment moved money into instalment $i.
                if ($running > $from + 0.005 && $before < $to - 0.005) {
                    $dates[$i] = (string)$date;
                }
            }
        }

        return $dates;
    }

    /**
     * Read-only instalment schedule for a document that carries no payments of
     * its own (a quotation): same row shape as summary() so the invoice view
     * and PDF blocks render it unchanged, with everything still payable.
     *
     * Nothing is stored — a quote's schedule is a proposal, it only becomes
     * real rows once the invoice is raised.
     */
    public function preview(?int $termId, float $total, ?string $issueDate = null, ?string $deliveryDate = null): array
    {
        $empty = [
            'has_schedule' => false, 'rows' => [], 'term' => null,
            'total' => 0.0, 'paid' => 0.0, 'due' => 0.0,
            'now_due' => 0.0, 'now_due_label' => '', 'now_due_percentage' => 0.0,
        ];

        $milestones = $this->milestonesForTerm($termId ?: null);
        if (empty($milestones)) {
            return $empty;
        }

        // No anchor date means no concrete due dates: a quotation's schedule is
        // a proposal, so it reads relative ("30 days from invoice date").
        $total     = round($total, 2);
        $issueDate = trim((string)$issueDate);
        $amounts   = $this->splitAmounts($milestones, $total);

        $rows = [];
        foreach ($milestones as $i => $m) {
            $dueDate = $this->dueDateFor($m, $issueDate, $deliveryDate);
            $rows[] = [
                'seq'         => $i + 1,
                'label'       => $m['label'],
                'percentage'  => (float)$m['percentage'],
                'amount'      => $amounts[$i],
                'paid'        => 0.0,
                'balance'     => $amounts[$i],
                'status'      => 'pending',
                'basis'       => $m['basis'],
                'offset_days' => (int)$m['offset_days'],
                'due_date'    => $dueDate,
                'due_label'   => $this->dueLabel($m, $dueDate),
                'is_overdue'  => false,
                'paid_on'     => null,
            ];
        }

        $first = $rows[0];

        return [
            'has_schedule' => true,
            'rows'         => $rows,
            'term'         => $termId ? $this->termModel->find($termId) : null,
            'total'        => round(array_sum(array_column($rows, 'amount')), 2),
            'paid'         => 0.0,
            'due'          => round(array_sum(array_column($rows, 'amount')), 2),
            'now_due'      => (float)$first['amount'],
            'now_due_label' => (string)$first['label'],
            'now_due_percentage' => (float)$first['percentage'],
        ];
    }

    /**
     * (Re)build the stored schedule for an invoice. Safe to call repeatedly:
     * it replaces the rows wholesale, and stored rows carry no paid state.
     */
    public function generate(array $invoice): array
    {
        $invoiceId = (int)($invoice['id'] ?? 0);
        if ($invoiceId <= 0) {
            return [];
        }

        $milestones = $this->milestonesForTerm((int)($invoice['payment_term_id'] ?? 0) ?: null);
        $db = \Config\Database::connect();

        if (empty($milestones)) {
            $db->table('customer_invoice_schedules')->where('invoice_id', $invoiceId)->delete();

            return [];
        }

        $total        = round((float)($invoice['total_amount'] ?? 0), 2);
        $issueDate    = trim((string)($invoice['issue_date'] ?? '')) ?: date('Y-m-d');
        $deliveryDate = $this->resolveDeliveryDate($invoice);
        $now          = date('Y-m-d H:i:s');

        $amounts = $this->splitAmounts($milestones, $total);

        $rows = [];
        foreach ($milestones as $i => $m) {
            $amount = $amounts[$i];

            $rows[] = [
                'invoice_id'  => $invoiceId,
                'seq'         => $i + 1,
                'label'       => $m['label'],
                'percentage'  => $m['percentage'],
                'amount'      => $amount,
                'basis'       => $m['basis'],
                'offset_days' => $m['offset_days'],
                'due_date'    => $this->dueDateFor($m, $issueDate, $deliveryDate),
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        $db->transStart();
        $db->table('customer_invoice_schedules')->where('invoice_id', $invoiceId)->delete();
        $db->table('customer_invoice_schedules')->insertBatch($rows);
        $db->transComplete();

        return $this->scheduleModel->forInvoice($invoiceId);
    }

    /**
     * Schedule for display, with payments poured across the instalments.
     *
     * Self-healing: regenerates when the stored rows no longer match the
     * invoice total/term or a delivery date has since been confirmed, so
     * callers never have to remember to rebuild.
     *
     * ponytail: payments settle instalments in due order rather than being
     * linked to a specific one. Add a payment->instalment link only if a
     * customer starts paying instalments out of order.
     */
    public function summary(array $invoice, float $paidTotal, array $payments = []): array
    {
        $empty = [
            'has_schedule' => false, 'rows' => [], 'term' => null,
            'total' => 0.0, 'paid' => 0.0, 'due' => 0.0,
            'now_due' => 0.0, 'now_due_label' => '', 'now_due_percentage' => 0.0,
        ];

        $invoiceId = (int)($invoice['id'] ?? 0);
        if ($invoiceId <= 0) {
            return $empty;
        }

        $termId = (int)($invoice['payment_term_id'] ?? 0);
        $rows   = $this->scheduleModel->forInvoice($invoiceId);

        $total        = round((float)($invoice['total_amount'] ?? 0), 2);
        $deliveryDate = $this->resolveDeliveryDate($invoice);

        if ($this->isStale($rows, $termId, $total, $deliveryDate)) {
            $rows = $this->generate($invoice);
        }
        if (empty($rows)) {
            return $empty;
        }

        $amounts   = array_map(static fn($r) => round((float)$r['amount'], 2), $rows);
        $allocated = $this->allocatePayments($amounts, $paidTotal);
        $paidDates = $this->paymentDates($amounts, $payments);

        $today   = date('Y-m-d');
        $out     = [];
        $paidSum = 0.0;

        foreach ($rows as $i => $r) {
            $amount  = $amounts[$i];
            $paid    = $allocated[$i];
            $balance = round($amount - $paid, 2);
            $paidSum = round($paidSum + $paid, 2);

            $status = 'pending';
            if ($balance <= 0.005) {
                $status = 'paid';
            } elseif ($paid > 0.005) {
                $status = 'partial';
            }

            $dueDate = !empty($r['due_date']) ? (string)$r['due_date'] : null;
            $out[] = [
                'seq'         => (int)$r['seq'],
                'label'       => (string)$r['label'],
                'percentage'  => (float)$r['percentage'],
                'amount'      => $amount,
                'paid'        => round($paid, 2),
                'balance'     => $balance,
                'status'      => $status,
                'basis'       => (string)$r['basis'],
                'offset_days' => (int)$r['offset_days'],
                'due_date'    => $dueDate,
                'due_label'   => $this->dueLabel($r, $dueDate),
                'is_overdue'  => $status !== 'paid' && $dueDate !== null && $dueDate < $today,
                'paid_on'     => $paid > 0.005 ? ($paidDates[$i] ?? null) : null,
            ];
        }

        // The customer is asked for one instalment at a time, so "now payable"
        // is the earliest instalment still carrying a balance.
        $nowDue      = 0.0;
        $nowDueLabel = '';
        $nowDuePct   = 0.0;
        foreach ($out as $row) {
            if ($row['balance'] > 0.005) {
                $nowDue      = $row['balance'];
                $nowDueLabel = $row['label'];
                $nowDuePct   = (float)$row['percentage'];
                break;
            }
        }

        return [
            'has_schedule'  => true,
            'rows'          => $out,
            'term'          => $termId > 0 ? $this->termModel->find($termId) : null,
            'total'         => round(array_sum(array_column($out, 'amount')), 2),
            'paid'          => $paidSum,
            'due'           => round(array_sum(array_column($out, 'balance')), 2),
            'now_due'       => $nowDue,
            'now_due_label' => $nowDueLabel,
            'now_due_percentage' => $nowDuePct,
        ];
    }

    /** Stored rows no longer reflect the invoice's term, total, or delivery. */
    private function isStale(array $rows, int $termId, float $total, ?string $deliveryDate): bool
    {
        if (empty($rows)) {
            return $termId > 0;
        }
        if ($termId <= 0) {
            return true; // term was cleared
        }

        $summed = round(array_sum(array_map(static fn($r) => (float)$r['amount'], $rows)), 2);
        if (abs($summed - $total) > 0.005) {
            return true;
        }

        // A delivery has been confirmed since the schedule was built.
        if ($deliveryDate !== null) {
            foreach ($rows as $r) {
                if ($r['basis'] === 'delivery_date' && empty($r['due_date'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Human due text; delivery-based instalments read as relative until delivered. */
    private function dueLabel(array $row, ?string $dueDate): string
    {
        if ($dueDate !== null) {
            return date('d M Y', strtotime($dueDate));
        }

        $days = (int)$row['offset_days'];
        if ($row['basis'] === 'delivery_date') {
            return $days > 0 ? $days . ' days after delivery' : 'On delivery';
        }

        return $days > 0 ? $days . ' days from invoice date' : 'Due on receipt';
    }
}
