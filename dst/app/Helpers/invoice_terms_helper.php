<?php

/**
 * Presentation helpers for the free-text Bank Details and Terms & Conditions
 * blocks that appear on the invoice screen, the print view and the PDF.
 *
 * Users type these as plain lines in Settings, so the formatting has to be
 * inferred. Styles are inline because the same markup is rendered by Dompdf,
 * the browser and the print stylesheet, and Dompdf will not see the page's
 * external CSS.
 */

if (! function_exists('invoice_text_lines')) {
    /**
     * Split typed text into trimmed, non-empty lines.
     *
     * A line that starts lower-case is treated as the tail of a sentence the
     * user hard-wrapped while typing, and is folded back into the line above,
     * so one clause does not end up as two bullets.
     */
    function invoice_text_lines(string $text): array
    {
        $raw = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        $lines = [];
        foreach ($raw as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($lines !== [] && preg_match('/^\p{Ll}/u', $line)) {
                $lines[count($lines) - 1] .= ' ' . $line;
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}

if (! function_exists('invoice_short_note')) {
    /**
     * Squeeze a payment term description into the space left beside the
     * schedule heading: first clause only, cut on a word boundary. The table
     * underneath carries the detail, so this is a hint, not the terms.
     */
    function invoice_short_note(string $text, int $max = 55): string
    {
        $note = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($note === '') {
            return '';
        }

        $note = rtrim(preg_split('/(?<=[.;])\s/', $note)[0] ?? $note, ' .;');
        if (mb_strlen($note) <= $max) {
            return $note;
        }

        $cut = mb_substr($note, 0, $max);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 20) {
            $cut = mb_substr($cut, 0, $sp);
        }

        return rtrim($cut, ' ,;.') . '...';
    }
}

if (! function_exists('invoice_rich_text_html')) {
    /**
     * Text written with the rich editor is already HTML, so it is passed
     * through instead of being re-formatted line by line. Only markup a
     * document needs survives: everything else, including scripts, event
     * handlers and javascript: links, is stripped, because this text is
     * authored in the back office but read by the customer.
     *
     * Returns null when the text is plain, so the caller falls back to the
     * line-based formatting typed-by-hand text still relies on.
     */
    function invoice_rich_text_html(string $text): ?string
    {
        if (! preg_match('/<(p|br|div|span|strong|b|em|i|u|ul|ol|li|table|h[1-6])[\s>\/]/i', $text)) {
            return null;
        }

        $allowed = '<p><br><div><span><strong><b><em><i><u><s><strike><sub><sup>'
            . '<ul><ol><li><table><thead><tbody><tfoot><tr><td><th><h1><h2><h3><h4><h5><h6><hr><a><font>';

        $html = strip_tags($text, $allowed);
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*["\']?\s*javascript:[^"\'>]*/i', '', $html) ?? $html;

        $html = trim($html);

        return $html !== '' ? $html : null;
    }
}

if (! function_exists('invoice_bank_details_html')) {
    /**
     * Render "Label : Value" lines as an aligned two-column table so the
     * account numbers line up and stay readable for a non-technical reader.
     * A line without a colon is kept as-is across both columns.
     */
    function invoice_bank_details_html(string $text): string
    {
        $rich = invoice_rich_text_html($text);
        if ($rich !== null) {
            return $rich;
        }

        $lines = invoice_text_lines($text);
        if ($lines === []) {
            return '';
        }

        $labelStyle = 'padding:1.5px 8px 1.5px 0; vertical-align:top; white-space:nowrap; color:#64748b; border-bottom:1px solid #eef2f7;';
        $valueStyle = 'padding:1.5px 0; vertical-align:top; color:#0f172a; font-weight:bold; border-bottom:1px solid #eef2f7;';

        $rows = '';
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) === 2 && trim($parts[1]) !== '') {
                $rows .= '<tr>'
                    . '<td style="' . $labelStyle . '">' . esc(rtrim(trim($parts[0]), ':')) . '</td>'
                    . '<td style="' . $valueStyle . '">' . esc(trim($parts[1])) . '</td>'
                    . '</tr>';
                continue;
            }

            $rows .= '<tr><td colspan="2" style="' . $valueStyle . '">' . esc(rtrim($line, ':')) . '</td></tr>';
        }

        return '<table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">' . $rows . '</table>';
    }
}

if (! function_exists('invoice_terms_html')) {
    /**
     * Render each typed line as a bullet. A "Label : detail" line keeps the
     * label in bold so the reader can scan for the clause that concerns them.
     * A bare heading line such as "Terms and Conditions:" is dropped because
     * the surrounding box already carries that title.
     *
     * Bullets are table rows, not <ul>, because Dompdf renders list markers
     * inconsistently at small font sizes.
     */
    function invoice_terms_html(string $text): string
    {
        $rich = invoice_rich_text_html($text);
        if ($rich !== null) {
            return $rich;
        }

        $lines = invoice_text_lines($text);
        if ($lines === []) {
            return '';
        }

        $dotStyle  = 'padding:2px 6px 2px 0; vertical-align:top; width:10px; color:#183153; font-weight:bold; border-bottom:1px solid #eef2f7;';
        $bodyStyle = 'padding:2px 0; vertical-align:top; color:#334155; border-bottom:1px solid #eef2f7;';

        $rows = '';
        foreach ($lines as $i => $line) {
            // Strip any bullet or numbering the user already typed.
            $line = trim(preg_replace('/^\s*(?:[-*\x{2022}\x{00B7}]|\d+[.)])\s*/u', '', $line) ?? $line);
            if ($line === '') {
                continue;
            }

            if ($i === 0 && str_ends_with($line, ':') && count($lines) > 1) {
                continue;
            }

            $parts = explode(':', $line, 2);
            $body  = esc($line);

            if (count($parts) === 2 && trim($parts[1]) !== '' && mb_strlen(trim($parts[0])) <= 40) {
                $body = '<strong style="color:#0f172a;">' . esc(rtrim(trim($parts[0]), ':')) . ':</strong> '
                    . esc(trim($parts[1]));
            }

            $rows .= '<tr>'
                . '<td style="' . $dotStyle . '">&bull;</td>'
                . '<td style="' . $bodyStyle . '">' . $body . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '';
        }

        return '<table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">' . $rows . '</table>';
    }
}
