<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

class CustomsInvoicePdfGenerator
{
    private Dompdf $dompdf;

    public function __construct()
    {
        if (! class_exists(Dompdf::class) || ! class_exists(Options::class)) {
            $autoload = ROOTPATH . 'vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        if (defined('FCPATH') && is_dir(FCPATH)) {
            $options->setChroot(FCPATH);
        }

        $this->dompdf = new Dompdf($options);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{path:string,name:string,mime_type:string,size:int,sha256:string}
     */
    public function generate(array $payload, string $variant = 'preview'): array
    {
        $html = view('pdf/customs_invoice', [
            'doc' => $payload,
            'variant' => $variant,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        $baseDir = (defined('WRITEPATH') ? WRITEPATH : (getcwd() . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR))
            . 'uploads' . DIRECTORY_SEPARATOR . 'customs_invoices' . DIRECTORY_SEPARATOR;

        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $suffix = $variant === 'final' ? 'FINAL' : 'PREVIEW';
        $safeNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($payload['customs_invoice_no'] ?? 'CUSTOMS'));
        $filename = $safeNo . '_' . $suffix . '_' . date('Ymd_His') . '.pdf';
        $fullPath = $baseDir . $filename;
        $content = $this->dompdf->output();
        file_put_contents($fullPath, $content);

        return [
            'path' => $fullPath,
            'name' => $filename,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'sha256' => hash('sha256', $content),
        ];
    }
}
