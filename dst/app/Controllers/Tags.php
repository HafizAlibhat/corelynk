<?php

namespace App\Controllers;

use App\Services\TagService;

class Tags extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.read')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $search = (string) ($this->request->getGet('q') ?? '');
        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $page = (int) ($this->request->getGet('page') ?? 1);

        $service = new TagService();
        $result = $service->searchTags($search, $limit, $page);

        return $this->response->setJSON(['success' => true, 'data' => $result]);
    }

    public function create()
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.write')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $name = trim((string) ($payload['name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($name === '') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Tag name is required']);
        }

        try {
            $service = new TagService();
            $tag = $service->createTag($name, (int) ($this->session->get('user_id') ?? 0), $description);
            return $this->response->setJSON(['success' => true, 'data' => $tag, 'message' => 'Tag created.']);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function update($id)
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.edit')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $name = trim((string) ($payload['name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($name === '') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Tag name is required']);
        }

        try {
            $service = new TagService();
            $tag = $service->updateTag((int) $id, $name, (int) ($this->session->get('user_id') ?? 0), $description);
            return $this->response->setJSON(['success' => true, 'data' => $tag, 'message' => 'Tag updated.']);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        $this->requireAuth();
        if (! $this->hasPermission('tags.delete')) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden']);
        }

        $service = new TagService();
        $tag = $service->getTagById((int) $id);
        if (! $tag) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Tag not found']);
        }
        if ($service->hasTagAssignments((int) $id)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Cannot delete a tag that is attached to documents.']);
        }

        if (! $service->deleteTag((int) $id)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to delete tag.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Tag deleted.']);
    }
}
