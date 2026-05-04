<?php
namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfGenerator
{
    protected $dompdf;

    public function __construct()
    {
        // Ensure Composer autoload is available (guards against CLI/custom bootstrap cases)
        if (!class_exists(Dompdf::class) || !class_exists(Options::class)) {
            $autoload = ROOTPATH . 'vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        $options = new Options();
        if (!$options->get('isRemoteEnabled')) {
            $options->set('isRemoteEnabled', true);
        }
        if (!$options->get('defaultFont')) {
            $options->set('defaultFont', 'DejaVu Sans');
        }
        // Always set chroot to FCPATH for local file access
        if (defined('FCPATH') && is_dir(FCPATH)) {
            $options->setChroot(FCPATH);
        }

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Backward-compatible generator that accepts either a flat invoice array
     * or a payload ['invoice' => header, 'lines' => [...]] as used by DualInvoiceService.
     */
    public function generate($payload, $type = 'system')
    {
        // When running from a plain CLI script (not through CI bootstrap), path constants might not be defined.
        // Provide safe fallbacks here (generate() is where paths are used).
        $fcPath = defined('FCPATH') ? FCPATH : (getcwd() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
        $rootPath = defined('ROOTPATH') ? ROOTPATH : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        $writePath = defined('WRITEPATH') ? WRITEPATH : (getcwd() . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR);

    $invoice = $payload['invoice'] ?? $payload; // accept old shape
    $lines = $payload['lines'] ?? ($invoice['lines'] ?? []);

        // Remove due_date from rendered payload to comply with layout requirement
        if (isset($invoice['due_date'])) {
            unset($invoice['due_date']);
        }

        $data = [
            'invoice' => $invoice,
            'lines' => $lines,
            'type' => $type,
            'date' => date('F j, Y'),
            'logo_path' => ''
        ];

        // Pass through customer/address when caller provided them so templates can render Bill To correctly
        if (!empty($payload['customer']) && is_array($payload['customer'])) {
            $data['customer'] = $payload['customer'];
        }
        if (!empty($payload['customerAddress']) && is_array($payload['customerAddress'])) {
            $data['customerAddress'] = $payload['customerAddress'];
        }

        foreach (['document_title', 'document_number_label', 'document_date_label', 'document_prefix', 'party_label', 'hide_company_logo', 'hide_company_website', 'pdf_show_header_address', 'pdf_show_footer'] as $metaKey) {
            if (array_key_exists($metaKey, $payload)) {
                $data[$metaKey] = $payload[$metaKey];
            }
        }

        // Pass payment snapshot details when provided by controller (paid/partial/unpaid rendering in PDF).
        if (array_key_exists('paymentSnapshot', $payload)) {
            $data['paymentSnapshot'] = $payload['paymentSnapshot'];
        }

        // If caller provided company info (common), pass it through and resolve logo path
        if (!empty($payload['company']) && is_array($payload['company'])) {
            $data['company'] = $payload['company'];

            // Clear PHP's internal stat/realpath cache so is_file() sees the freshly-uploaded logo
            clearstatcache(true);

            // Resolve logo path: settings saves to uploads/company/company-logo.png
            $logoFile = $payload['company']['logo_path']
                ?? $payload['company']['company_logo']
                ?? $payload['company']['logo']
                ?? '';

            $candidate = null;
            if (!empty($logoFile)) {
                if (preg_match('#^https?://#i', $logoFile)) {
                    $candidate = $logoFile; // allow remote if absolute URL
                } else {
                    $normalized = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $logoFile), DIRECTORY_SEPARATOR);

                    // If already absolute
                    if (is_file($normalized)) {
                        $candidate = $normalized;
                    }

                    // If stored as uploads/company/...
                    if ($candidate === null) {
                        $maybe = rtrim($fcPath, '/\\') . DIRECTORY_SEPARATOR . $normalized;
                        if (is_file($maybe)) $candidate = $maybe;
                    }

                    // Also try forcing uploads/company prefix if missing
                    if ($candidate === null) {
                        $maybe = rtrim($fcPath, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $normalized;
                        if (is_file($maybe)) $candidate = $maybe;
                    }
                    // Try a few more common locations (project root/public/uploads)
                    if ($candidate === null) {
                        // ROOTPATH/public/uploads/...
                        $maybe = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $normalized;
                        if (is_file($maybe)) $candidate = $maybe;
                    }
                    if ($candidate === null) {
                        // project root + normalized (in case uploads are at project root/uploads)
                        $maybe = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . $normalized;
                        if (is_file($maybe)) $candidate = $maybe;
                    }
                }
            }

            if ($candidate !== null) {
                if (preg_match('#^https?://#i', $candidate)) {
                    $data['company']['logo_path'] = $candidate;
                } else {
                    $mime = mime_content_type($candidate);
                    if (!$mime) {
                        $data['company']['logo_path'] = '';
                    } else {
                        // Always read the exact file that was uploaded and matched.
                        // Never fall back to a different extension — that caused stale-logo bugs
                        // when old files lingered on disk with a different format.
                        $content = file_get_contents($candidate);
                        $data['company']['logo_path'] = $content !== false
                            ? ('data:' . $mime . ';base64,' . base64_encode($content))
                            : '';
                    }
                }
            } else {
                $data['company']['logo_path'] = '';
            }
        }

        // Enrich lines with product code/image from products table
        $data['lines'] = $this->attachProductMeta($data['lines']);

        // Determine which template to use
        // Priority: payload['pdf_template'] > company['pdf_template'] > 'default'
        $pdfTemplate = $payload['pdf_template'] ?? ($data['company']['pdf_template'] ?? 'default');
        
        // Map template names to view paths
        $templateMap = [
            'default' => 'pdf/invoice_' . $type,
            'modern_blue' => 'pdf/templates/modern_blue',
            'classic_green' => 'pdf/templates/classic_green',
            'professional_gray' => 'pdf/templates/professional_gray',
            'bold_red' => 'pdf/templates/bold_red',
            'elegant_purple' => 'pdf/templates/elegant_purple',
        ];
        
        $templateView = $templateMap[$pdfTemplate] ?? $templateMap['default'];

        // Load appropriate template; fallback to minimal inline HTML if view missing
        $html = '';
        try {
            $html = view($templateView, $data);
        } catch (\Throwable $e) {
            // If custom template fails, try default, then fallback HTML
            try {
                $html = view('pdf/invoice_' . $type, $data);
            } catch (\Throwable $e2) {
                $html = $this->fallbackHtml($data);
            }
        }

        // Generate PDF
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        // Save to file
        $filename = 'invoice_' . ($invoice['invoice_number'] ?? $invoice['id'] ?? uniqid()) . '_' . $type . '_' . time() . '.pdf';
    $filepath = $writePath . 'uploads/invoices/' . $filename;

        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save PDF
        file_put_contents($filepath, $this->dompdf->output());

        return [
            'path' => $filepath,
            'name' => $filename,
        ];
    }

    public function generateSystemInvoice($payload)
    {
        return $this->generate($payload, 'system');
    }

    public function generateCustomInvoice($payload)
    {
        return $this->generate($payload, 'custom');
    }

    /**
     * Attach product code and image paths (Dompdf-safe) to invoice lines.
     */
    private function attachProductMeta(array $lines): array
    {
        if (empty($lines)) return $lines;

        $productIds = [];
        foreach ($lines as $ln) {
            if (!empty($ln['product_id'])) $productIds[] = (int)$ln['product_id'];
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        // Also collect lookup keys for lines that don't have a product_id (quick fallback)
        // IMPORTANT: be conservative here; a broad lookup on description can hang the whole app.
        $lookupKeys = [];
        foreach ($lines as $ln) {
            if (empty($ln['product_id'])) {
                // Prefer explicit product_code only (safe). Only fall back to description when it looks like a code.
                $pc = trim((string)($ln['product_code'] ?? ''));
                $desc = trim((string)($ln['description'] ?? ''));
                if ($pc !== '') {
                    $lookupKeys[] = $pc;
                } elseif ($desc !== '' && strpos($desc, '-') !== false) {
                    // descriptions like "T2-558" or "ABC-123" are safe to treat as codes
                    $lookupKeys[] = $desc;
                }
            }
        }
        $lookupKeys = array_values(array_unique($lookupKeys));

        if (empty($productIds) && empty($lookupKeys)) return $lines;

        $db = \Config\Database::connect();

        // Determine which optional columns exist to avoid "unknown column" errors across environments
        $optionalCols = ['code', 'sku', 'image', 'images'];
        $existingCols = [];
        try {
            $existingCols = $db->getFieldNames('products');
        } catch (\Throwable $e) {
            $existingCols = []; // fallback: only select id
        }
        $selectCols = ['id'];
        foreach ($optionalCols as $col) {
            if (in_array($col, $existingCols, true)) {
                $selectCols[] = $col;
            }
        }

        $products = [];
        if (!empty($productIds)) {
            $products = $db->table('products')
                ->select(implode(', ', $selectCols))
                ->whereIn('id', $productIds)
                ->get()
                ->getResultArray();
        }

        // If some invoice lines didn't include product_id, try to resolve them by code/sku/name (exact)
        if (!empty($lookupKeys)) {
            // build a clone of selectCols for this query (we may have different available columns)
            $q = $db->table('products')->select(implode(', ', $selectCols));
            // Try to match any of the lookup keys against available columns
            $q = $q->groupStart();
            foreach ($lookupKeys as $k) {
                if (in_array('code', $existingCols, true)) $q = $q->orWhere('code', $k);
                if (in_array('sku', $existingCols, true)) $q = $q->orWhere('sku', $k);
                if (in_array('name', $existingCols, true)) $q = $q->orWhere('name', $k);
            }
            $q = $q->groupEnd();
            try {
                $more = $q->get()->getResultArray();
                if (!empty($more)) {
                    // merge results, avoid duplicates
                    foreach ($more as $m) $products[] = $m;
                }
            } catch (\Throwable $e) {
                // ignore lookup failures and continue with whatever products we have
            }
        }

        $pMap = [];
        foreach ($products as $p) {
            $pid = (int)($p['id'] ?? 0);
            if (!$pid) continue;

            $code = $p['code'] ?? ($p['sku'] ?? '');
            $name = $p['name'] ?? '';
            $img = $p['image'] ?? '';

            // If images is JSON array, pick first
            if (empty($img) && !empty($p['images'])) {
                $arr = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                if (is_array($arr) && !empty($arr[0])) {
                    $img = $arr[0];
                }
            }
            // If the image is just a filename (no path), prepend uploads/products/
            if (!empty($img) && strpos($img, '/') === false && strpos($img, '\\') === false) {
                $img = 'uploads/products/' . $img;
            }

            $imgPath = '';
            if (!empty($img)) {
                $normalized = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $img), DIRECTORY_SEPARATOR);
                $candidates = [
                    rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $normalized,
                    rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $normalized,
                ];
                foreach ($candidates as $cand) {
                    if (is_file($cand)) {
                        $real = realpath($cand) ?: $cand;
                        $real = str_replace('\\', '/', $real);
                        $imgPath = preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
                        // Do NOT embed base64 here. Large images can exhaust memory and PNG decoding needs GD in Dompdf.
                        $imgData = '';
                        break;
                    }
                }
            }

            $pMap[$pid] = [
                'product_code' => $code,
                'product_name' => $name,
                'product_image_path' => $imgPath,
                'product_image_data' => $imgData ?? '',
            ];
        }

        foreach ($lines as &$ln) {
            $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
            if ($pid && isset($pMap[$pid])) {
                if (!empty($pMap[$pid]['product_code'])) {
                    $ln['product_code'] = $pMap[$pid]['product_code'];
                }
                // Only set product image if line doesn't already have a (variant) image path
                if (!empty($pMap[$pid]['product_image_path']) && empty($ln['product_image_path'])) {
                    $ln['product_image_path'] = $pMap[$pid]['product_image_path'];
                }
                continue;
            }

            // Fallback: try to match by product_code or description when product_id not present
            if (empty($pid)) {
                $keyCode = trim((string)($ln['product_code'] ?? ''));
                $keyDesc = trim((string)($ln['description'] ?? ''));
                if ($keyCode !== '' || $keyDesc !== '') {
                    foreach ($pMap as $map) {
                        // Prefer code match
                        if ($keyCode !== '' && !empty($map['product_code']) && strcasecmp($map['product_code'], $keyCode) === 0) {
                            if (!empty($map['product_image_path']) && empty($ln['product_image_path'])) $ln['product_image_path'] = $map['product_image_path'];
                            break;
                        }
                        // If no code on the line, try matching description to product name (exact, case-insensitive)
                        if ($keyCode === '' && $keyDesc !== '' && !empty($map['product_name']) && strcasecmp($map['product_name'], $keyDesc) === 0) {
                            if (!empty($map['product_image_path']) && empty($ln['product_image_path'])) $ln['product_image_path'] = $map['product_image_path'];
                            break;
                        }
                    }
                }
            }
        }
        unset($ln);

        return $lines;
    }

    private function fallbackHtml(array $data): string
    {
        $invoice = $data['invoice'];
        $lines = $data['lines'] ?? [];
        $num = $invoice['invoice_number'] ?? ($invoice['id'] ?? '');
        $date = $data['date'] ?? date('F j, Y');
        ob_start();
        ?>
        <html>
        <head><meta charset="utf-8"><title>Invoice <?= htmlspecialchars($num) ?></title></head>
        <body style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
            <h2>Invoice <?= htmlspecialchars($num) ?></h2>
            <p>Date: <?= htmlspecialchars($date) ?></p>
            <p>Customer: <?= htmlspecialchars($invoice['customer_id'] ?? '') ?></p>
            <table width="100%" cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse;">
                <thead><tr><th align="left">Description</th><th align="right">Qty</th><th align="right">Unit Price</th><th align="right">Line Total</th></tr></thead>
                <tbody>
                <?php foreach ($lines as $ln): ?>
                    <tr>
                        <td><?= htmlspecialchars($ln['description'] ?? '') ?></td>
                        <td align="right"><?= htmlspecialchars($ln['quantity'] ?? '') ?></td>
                        <td align="right"><?= htmlspecialchars($ln['unit_price'] ?? '') ?></td>
                        <td align="right"><?= htmlspecialchars($ln['line_total'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
