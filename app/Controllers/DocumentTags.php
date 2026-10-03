<?php

namespace App\Controllers;

use App\Services\TagService;

class DocumentTags extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.read')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $documentType = (string) ($this->request->getGet('document_type') ?? '');
        $documentId = (int) ($this->request->getGet('document_id') ?? 0);

        if ($documentType === '' || $documentId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'document_type and document_id are required']);
        }

        $service = new TagService();
        $tags = $service->getTagsForDocument($documentType, $documentId);

        return $this->response->setJSON(['success' => true, 'data' => $tags]);
    }

    public function save()
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.write')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $documentType = (string) ($payload['document_type'] ?? '');
        $documentId = isset($payload['document_id']) ? (int) $payload['document_id'] : 0;
        $tagNames = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];

        if ($documentType === '' || $documentId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'document_type and document_id are required']);
        }
        if (! is_array($tagNames)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'tags must be an array']);
        }

        try {
            $service = new TagService();
            $tags = $service->syncTagsForDocument($documentType, $documentId, $tagNames, (int) ($this->session->get('user_id') ?? 0));
            return $this->response->setJSON(['success' => true, 'data' => $tags, 'message' => 'Document tags saved.']);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to save document tags.']);
        }
    }

    public function delete()
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.edit')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $documentType = (string) ($payload['document_type'] ?? '');
        $documentId = isset($payload['document_id']) ? (int) $payload['document_id'] : 0;
        $tagId = isset($payload['tag_id']) ? (int) $payload['tag_id'] : 0;

        if ($documentType === '' || $documentId <= 0 || $tagId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'document_type, document_id and tag_id are required']);
        }

        $service = new TagService();
        $deleted = $service->removeTagFromDocument($documentType, $documentId, $tagId);
        if (! $deleted) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to remove document tag.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Document tag removed.']);
    }
}
