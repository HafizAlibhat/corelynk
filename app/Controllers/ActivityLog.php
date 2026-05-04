<?php

namespace App\Controllers;

use App\Libraries\DocumentLogger;

class ActivityLog extends BaseController
{
    /**
     * GET activity-log/{docType}/{docId}
     * Returns JSON array of log entries for the given document.
     */
    public function forDocument(string $docType, int $docId): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->requireAuth();

        $allowed = [
            DocumentLogger::TYPE_QUOTATION,
            DocumentLogger::TYPE_SALES_ORDER,
            DocumentLogger::TYPE_PURCHASE_ORDER,
            DocumentLogger::TYPE_PURCHASE_RFQ,
            DocumentLogger::TYPE_INVOICE,
        ];

        if (!in_array($docType, $allowed, true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid document type']);
        }

        $docId = (int) $docId;
        if ($docId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid document id']);
        }

        try {
            $entries = DocumentLogger::getForDocument($docType, $docId);
        } catch (\Throwable $e) {
            log_message('error', 'ActivityLog::forDocument failed: ' . $e->getMessage());
            $entries = [];
        }

        return $this->response->setJSON(['success' => true, 'entries' => $entries]);
    }
}
