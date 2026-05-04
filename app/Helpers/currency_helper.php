<?php

use Config\Database;

if (! function_exists('get_active_rate')) {
    function get_active_rate(string $base, string $quote, ?string $asOf = null): ?array
    {
        if ($base === $quote) {
            return ['rate' => 1.0, 'as_of' => date('Y-m-d')];
        }
        try {
            $db = Database::connect();
            $asOf = $asOf ?: date('Y-m-d');
            $sql = "SELECT * FROM exchange_rate WHERE base_code = ? AND quote_code = ? AND as_of <= ? ORDER BY as_of DESC, id DESC LIMIT 1";
            $row = $db->query($sql, [$base, $quote, $asOf])->getRowArray();
            return $row ?: null;
        } catch (\Throwable $e) {
            // Table may not yet exist; return null so caller can gracefully skip conversion
            log_message('error', 'get_active_rate error: ' . $e->getMessage());
            return null;
        }
    }
}

if (! function_exists('convert_amount')) {
    function convert_amount(float $amount, string $from, string $to, ?string $asOf = null): float
    {
        if ($from === $to) return $amount;
        try {
            $rateRow = get_active_rate($from, $to, $asOf);
            if (!$rateRow) return $amount; // Graceful fallback
            $rate = (float)$rateRow['rate'];
            return round($amount * $rate, 2);
        } catch (\Throwable $e) {
            log_message('error', 'convert_amount error: ' . $e->getMessage());
            return $amount; // Do not break app flow
        }
    }
}
