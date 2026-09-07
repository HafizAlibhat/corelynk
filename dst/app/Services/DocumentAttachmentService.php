<?php

namespace App\Services;

use App\Models\DocumentAttachmentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentAttachmentService
{
    /**
     * Upload constraints
     */
    private const MAX_BYTES = 10485760; // 10 MB
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * Store a single attachment and create DB row.
     *
     * @return array{success:bool,message:string,attachment_id?:int,file_path?:string}
     */
    public function store(string $documentType, int $documentId, UploadedFile $file, ?int $uploadedBy = null): array
    {
        $documentType = strtolower(trim($documentType));
        $documentId = (int)$documentId;

        if ($documentType === '' || $documentId <= 0) {
            return ['success' => false, 'message' => 'Invalid document reference'];
        }

        if (! $file->isValid()) {
            return ['success' => false, 'message' => 'Invalid upload'];
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'File too large'];
        }

        $ext = strtolower((string)$file->getClientExtension());
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return ['success' => false, 'message' => 'Only PDF, JPG, PNG allowed'];
        }

        $mime = (string)$file->getClientMimeType();
        if ($mime && ! in_array($mime, self::ALLOWED_MIME, true)) {
            return ['success' => false, 'message' => 'Unsupported file type'];
        }

        $original = (string)$file->getClientName();

        // Storage folder: /public/uploads/accounting/
        $targetDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'accounting';
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        // Move to public folder
        $moved = $file->move($targetDir, $uniqueName);

        $relativePath = 'uploads/accounting/' . $uniqueName;

        // File size (bytes) if available
            $fileSize = null;
        try { $fileSize = (int)$file->getSize(); } catch (\Throwable $_) { $fileSize = null; }

        $m = new DocumentAttachmentModel();

        // Build canonical insert payload
        $insertData = [
            'document_type' => $documentType,
            'document_id' => $documentId,
            'file_path' => $relativePath,
            'original_name' => $original,
            'mime_type' => $mime ?: null,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'file_size' => $fileSize,
        ];

        // Defensive: adapt to slight schema variations (e.g., created_by vs uploaded_by)
        try {
            $dbFields = $m->db->getFieldNames($m->table);
        } catch (\Throwable $_ex) {
            $dbFields = array_keys($insertData);
        }

        // Map fallbacks: if 'uploaded_by' column missing but table has 'created_by', map to it
        if (!in_array('uploaded_by', $dbFields, true) && in_array('created_by', $dbFields, true) && isset($insertData['uploaded_by'])) {
            $insertData['created_by'] = $insertData['uploaded_by'];
            unset($insertData['uploaded_by']);
        }

        // Map uploaded_at -> created_at if needed
        if (!in_array('uploaded_at', $dbFields, true) && in_array('created_at', $dbFields, true) && isset($insertData['uploaded_at'])) {
            $insertData['created_at'] = $insertData['uploaded_at'];
            unset($insertData['uploaded_at']);
        }

        // Remove keys not present in actual DB columns to avoid unknown column errors
        $filtered = [];
        foreach ($insertData as $k => $v) {
            if (in_array($k, $dbFields, true)) $filtered[$k] = $v;
        }

        if (empty($filtered)) {
            return ['success' => false, 'message' => 'No valid insert columns found'];
        }

        try {
            $id = $m->insert($filtered, true);
        } catch (\Throwable $e) {
            // If insert fails, attempt minimal payload (document_type, document_id, file_path, original_name)
            $minimal = array_intersect_key($filtered, array_flip(['document_type','document_id','file_path','original_name']));
            try {
                $id = $m->insert($minimal, true);
            } catch (\Throwable $_e) {
                log_message('error', 'DocumentAttachment insert failed: ' . $_e->getMessage());
                return ['success' => false, 'message' => 'DB insert failed'];
            }
        }

        if (! $id) {
            return ['success' => false, 'message' => 'DB insert failed'];
        }

        return ['success' => true, 'message' => 'Uploaded', 'attachment_id' => (int)$id, 'file_path' => $relativePath, 'file_size' => $fileSize];
    }

    /**
     * Store multiple posted files.
     *
     * @param UploadedFile[] $files
     * @return array{success:bool,results:array}
     */
    public function storeMany(string $documentType, int $documentId, array $files, ?int $uploadedBy = null): array
    {
        $results = [];
        foreach ($files as $f) {
            if (! $f instanceof UploadedFile) continue;
            $results[] = $this->store($documentType, $documentId, $f, $uploadedBy);
        }
        $ok = true;
        foreach ($results as $r) {
            if (empty($r['success'])) { $ok = false; break; }
        }
        return ['success' => $ok, 'results' => $results];
    }

    /**
     * Friendly wrapper used by controllers in several places.
     * Accepts the typical `$this->request->getFiles()` or the direct array of UploadedFile
     * and normalizes to call storeMany().
     *
     * @param mixed $files
     * @param mixed $uploadedByOrMeta optional uploadedBy int or associative meta array
     */
    public function saveUploads(string $documentType, int $documentId, $files, $uploadedByOrMeta = null): array
    {
        // Normalize uploadedBy if passed as meta array
        $uploadedBy = null;
        if (is_int($uploadedByOrMeta)) $uploadedBy = $uploadedByOrMeta;
        if (is_array($uploadedByOrMeta) && isset($uploadedByOrMeta['uploaded_by'])) $uploadedBy = (int)$uploadedByOrMeta['uploaded_by'];

        // If $files is the result of $this->request->getFiles(), it may be an array containing
        // a key like 'attachments' => UploadedFile[] or a single UploadedFile.
        $normalized = [];
        if ($files instanceof UploadedFile) {
            $normalized = [$files];
        } elseif (is_array($files)) {
            // If associative with nested arrays (e.g., files['attachments'] => array)
            foreach ($files as $k => $v) {
                if ($v instanceof UploadedFile) {
                    $normalized[] = $v;
                    continue;
                }
                if (is_array($v)) {
                    // e.g. 'attachments' => [UploadedFile, UploadedFile]
                    foreach ($v as $item) {
                        if ($item instanceof UploadedFile) $normalized[] = $item;
                    }
                    continue;
                }
            }
        }

        if (empty($normalized)) {
            return ['success' => false, 'results' => [], 'message' => 'No files to upload'];
        }

        return $this->storeMany($documentType, $documentId, $normalized, $uploadedBy);
    }
}
