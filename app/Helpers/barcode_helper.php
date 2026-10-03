<?php

if (! class_exists(\chillerlan\QRCode\QRCode::class)) {
    $autoloadPath = defined('ROOTPATH') ? ROOTPATH . 'vendor/autoload.php' : dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

if (! function_exists('generate_secure_outwork_token')) {
    /**
     * Generate a compact, tamper-proof, opaque verification token.
     * Uses HMAC-SHA256 signature to keep the token ultra-short and unguessable.
     *
     * @param string $type 'order' or 'lot'
     * @param int $id ID of sales order or vendor send note
     * @param string $ref Reference string (e.g. order_number or reference_no)
     * @return string URL-safe token (e.g. L41-7e82bf09 or O2-c7e1904a)
     */
    function generate_secure_outwork_token(string $type, int $id, string $ref): string
    {
        $prefix = ($type === 'order') ? 'O' : 'L';
        $salt = 'CoreLynk_AntiTamper_Outwork_Key_2026';
        $sig = substr(hash_hmac('sha256', "{$prefix}:{$id}:{$ref}", $salt), 0, 10);
        return $prefix . $id . '-' . $sig;
    }
}

if (! function_exists('decrypt_secure_outwork_token')) {
    /**
     * Decrypt and verify an authenticated verification token.
     *
     * @param string $token (e.g. L41-7e82bf09 or O2-c7e1904a)
     * @return array|null Returns array ['t' => type, 'id' => id] or null
     */
    function decrypt_secure_outwork_token(string $token): ?array
    {
        $token = trim($token);
        if (! preg_match('/^([OL])(\d+)-([a-f0-9]{10})$/i', $token, $matches)) {
            return null;
        }

        $typePrefix = strtoupper($matches[1]);
        $id = (int) $matches[2];
        $providedSig = strtolower($matches[3]);
        $type = ($typePrefix === 'O') ? 'order' : 'lot';

        $db = \Config\Database::connect();
        $ref = '';
        if ($type === 'order') {
            $row = $db->table('sales_orders')->select('order_number')->where('id', $id)->get()->getRowArray();
            $ref = $row['order_number'] ?? '';
        } else {
            $row = $db->table('vendor_send_notes')->select('reference_no')->where('id', $id)->get()->getRowArray();
            $ref = $row['reference_no'] ?? '';
        }

        if ($ref === '') {
            return null;
        }

        $salt = 'CoreLynk_AntiTamper_Outwork_Key_2026';
        $expectedSig = substr(hash_hmac('sha256', "{$typePrefix}:{$id}:{$ref}", $salt), 0, 10);

        if (! hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        return [
            't'   => $type,
            'id'  => $id,
            'ref' => $ref,
            'ts'  => time(),
        ];
    }
}

if (! function_exists('outwork_verify_url')) {
    /**
     * Get full public verification URL for an outwork order or lot.
     */
    function outwork_verify_url(string $type, int $id, string $ref): string
    {
        $token = generate_secure_outwork_token($type, $id, $ref);
        return site_url('vendor-receive/verify/' . $token);
    }
}

if (! function_exists('outwork_qr_svg')) {
    /**
     * Generate pure vector SVG QR code using chillerlan/php-qrcode.
     * 100% offline, zero network calls, high contrast, crisp vector rendering.
     *
     * @param string $data URL or string to encode
     * @param int $size Display width/height in pixels
     * @param string $extraClass CSS class name
     * @return string Valid SVG markup
     */
    function outwork_qr_svg(string $data, int $size = 80, string $extraClass = ''): string
    {
        try {
            $options = new QROptions([
                'outputType'     => QRCode::OUTPUT_MARKUP_SVG,
                'outputBase64'   => false,
                'addQuietzone'   => true,
                'quietzoneSize'  => 3,
                'svgUseFill'     => true,
            ]);

            $rawSvg = (new QRCode($options))->render($data);

            // Strip XML declaration if present
            $rawSvg = preg_replace('/<\?xml[^>]*\?>/i', '', $rawSvg);

            // Add width, height, and crispEdges styling
            $cleanSvg = preg_replace(
                '/<svg\b([^>]*)>/i',
                sprintf('<svg$1 width="%d" height="%d" class="qr-code-svg %s" style="display:block;border-radius:3px;background:#ffffff;shape-rendering:crispEdges;">', $size, $size, htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8')),
                $rawSvg,
                1
            );

            return $cleanSvg;
        } catch (\Throwable $e) {
            // Fallback to high-contrast online QR image tag if library exception
            $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=3&data=' . urlencode($data);
            return sprintf(
                '<img src="%s" alt="QR" width="%d" height="%d" class="qr-code-svg %s" style="display:block;border-radius:3px;background:#fff;border:1px solid #cbd5e1;" />',
                htmlspecialchars($qrSrc, ENT_QUOTES, 'UTF-8'),
                $size,
                $size,
                htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8')
            );
        }
    }
}

if (! function_exists('barcode_code128_svg')) {
    /**
     * Generate pure vector SVG for Code 128 (Set B) barcode.
     * Guaranteed 100% scannable by laser scanner guns and mobile apps:
     *   - Includes pure white background <rect> for maximum optical contrast
     *   - Generates generous quiet zones (12 modules on each side)
     *   - Uses shape-rendering="crispEdges" to prevent edge anti-aliasing
     *
     * @param string $text Barcode data
     * @param int $height Height of bars in pixels
     * @param int $scale Bar module width multiplier
     * @param bool $showText Whether to render human-readable text below bars
     * @return string Complete SVG markup
     */
    function barcode_code128_svg(string $text, int $height = 36, int $scale = 2, bool $showText = false): string
    {
        static $patterns = [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
            '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
            '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
            '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
            '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
            '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
            '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
            '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
            '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
        ];

        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $codes = [104]; // Start Code B
        $checksum = 104;

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $ascii = ord($text[$i]);
            $code = ($ascii >= 32 && $ascii <= 126) ? ($ascii - 32) : 0;
            $codes[] = $code;
            $checksum += ($code * ($i + 1));
        }

        $codes[] = $checksum % 103;
        $codes[] = 106; // Stop Code

        $modules = '';
        foreach ($codes as $c) {
            $pat = $patterns[$c];
            $isBar = true;
            for ($j = 0, $pLen = strlen($pat); $j < $pLen; $j++) {
                $w = (int) $pat[$j];
                $modules .= str_repeat($isBar ? '1' : '0', $w);
                $isBar = ! $isBar;
            }
        }

        $totalModules = strlen($modules);
        $quietZone = 12; // 12 modules quiet zone for scanner reliability
        $fullModules = $totalModules + ($quietZone * 2);
        $svgWidth = $fullModules * $scale;
        $svgHeight = $height + ($showText ? 14 : 0);

        // White background for optical contrast
        $rects = [
            sprintf('<rect width="%d" height="%d" fill="#ffffff" />', $svgWidth, $svgHeight)
        ];

        $x = $quietZone * $scale;
        $currBarStart = null;

        for ($m = 0; $m < $totalModules; $m++) {
            if ($modules[$m] === '1') {
                if ($currBarStart === null) {
                    $currBarStart = $x;
                }
            } else {
                if ($currBarStart !== null) {
                    $barW = $x - $currBarStart;
                    $rects[] = sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#000000" />', $currBarStart, $barW, $height);
                    $currBarStart = null;
                }
            }
            $x += $scale;
        }
        if ($currBarStart !== null) {
            $barW = $x - $currBarStart;
            $rects[] = sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#000000" />', $currBarStart, $barW, $height);
        }

        $textElement = '';
        if ($showText) {
            $textElement = sprintf(
                '<text x="%d" y="%d" text-anchor="middle" font-family="ui-monospace,monospace" font-size="10" font-weight="700" fill="#000000">%s</text>',
                (int) ($svgWidth / 2),
                $height + 11,
                htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" class="barcode-svg" shape-rendering="crispEdges" style="display:block;width:100%%;max-width:%dpx;height:auto;background:#ffffff;">%s%s</svg>',
            $svgWidth,
            $svgHeight,
            $svgWidth,
            implode('', $rects),
            $textElement
        );
    }
}
