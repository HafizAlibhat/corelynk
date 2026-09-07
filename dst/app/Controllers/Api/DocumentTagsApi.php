<?php

namespace App\Controllers\Api;

use App\Services\TagService;

class DocumentTagsApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'read')) {
            return $this->response;
        }

        $documentType = (string) ($this->request->getGet('document_type') ?? '');
        $documentId = (int) ($this->request->getGet('document_id') ?? 0);

        if ($documentType === '' || $documentId <= 0) {
            return $this->error('document_type and document_id are required.', 400);
        }

        $service = new TagService();
        $tags = $service->getTagsForDocument($documentType, $documentId);

        return $this->success(array_map(static function (array $tag): array {
            return [
                'id' => (int) $tag['id'],
                'name' => $tag['name'],
                'slug' => $tag['slug'],
            ];
        }, $tags));
    }

    public function save(): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'write')) {
            return $this->response;
        }

        $payload = $this->getJsonBody();
        $documentType = (string) ($payload['document_type'] ?? '');
        $documentId = isset($payload['document_id']) ? (int) $payload['document_id'] : 0;
        $tagNames = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];

        if ($documentType === '' || $documentId <= 0) {
            return $this->error('document_type and document_id are required.', 400);
        }
        if (! is_array($tagNames)) {
            return $this->error('tags must be an array.', 400);
        }

        try {
            $service = new TagService();
            $tags = $service->syncTagsForDocument($documentType, $documentId, $tagNames, (int) ($this->apiUser['id'] ?? 0));
            return $this->success(array_map(static function (array $tag): array {
                return [
                    'id' => (int) $tag['id'],
                    'name' => $tag['name'],
                    'slug' => $tag['slug'],
                ];
            }, $tags), 'Document tags saved.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->error('Failed to save document tags.', 500);
        }
    }

    public function delete(): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'edit')) {
            return $this->response;
        }

        $payload = $this->getJsonBody();
        $documentType = (string) ($payload['document_type'] ?? '');
        $documentId = isset($payload['document_id']) ? (int) $payload['document_id'] : 0;
        $tagId = isset($payload['tag_id']) ? (int) $payload['tag_id'] : 0;

        if ($documentType === '' || $documentId <= 0 || $tagId <= 0) {
            return $this->error('document_type, document_id and tag_id are required.', 400);
        }

        $service = new TagService();
        if (! $service->isValidDocumentType($documentType)) {
            return $this->error('Invalid document type.', 422);
        }

        $deleted = $service->removeTagFromDocument($documentType, $documentId, $tagId);
        if (! $deleted) {
            return $this->error('Failed to remove document tag.', 500);
        }

        return $this->success(null, 'Document tag removed.');
    }
}
