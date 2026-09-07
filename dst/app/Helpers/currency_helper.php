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

/**
 * Presentation helpers for multi-currency documents.
 *
 * Rule: amounts in different currencies are NEVER summed into one figure.
 * Reports group by currency_code and each figure is labelled with its own code.
 * Conversion only happens where a caller explicitly asks via convert_amount().
 */

if (! function_exists('currency_catalog')) {
    /** [code => ['code','symbol','decimals','is_base']] from the currencies table. */
    function currency_catalog(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = [];
        try {
            $rows = Database::connect()->table('currencies')->get()->getResultArray();
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $catalog[$code] = [
                    'code'     => $code,
                    'symbol'   => (string) ($row['symbol'] ?? ''),
                    'decimals' => (int) ($row['decimals'] ?? 2),
                    'is_base'  => (bool) ($row['is_base'] ?? false),
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'currency_catalog error: ' . $e->getMessage());
        }

        return $catalog;
    }
}

if (! function_exists('base_currency_code')) {
    function base_currency_code(): string
    {
        foreach (currency_catalog() as $code => $meta) {
            if ($meta['is_base']) {
                return $code;
            }
        }

        return 'PKR';
    }
}

if (! function_exists('currency_code_or_base')) {
    /** Normalize a stored code; blank/legacy rows fall back to the base currency. */
    function currency_code_or_base(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return $code !== '' ? $code : base_currency_code();
    }
}

if (! function_exists('format_money')) {
    /**
     * Always renders the ISO code next to the number so two currencies can never
     * be read as one total. Symbols are skipped on purpose: several rows in the
     * currencies table carry unreliable symbol bytes.
     */
    function format_money($amount, ?string $code = null, bool $showCode = true): string
    {
        $code     = currency_code_or_base($code);
        $decimals = currency_catalog()[$code]['decimals'] ?? 2;
        $number   = number_format((float) $amount, $decimals);

        return $showCode ? $code . ' ' . $number : $number;
    }
}
