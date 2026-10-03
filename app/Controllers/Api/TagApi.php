<?php

namespace App\Controllers\Api;

use App\Services\TagService;

class TagApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }

        if (! $this->requirePermission('tags', 'read')) {
            return $this->response;
        }

        $query = (string) ($this->request->getGet('query') ?? $this->request->getGet('q') ?? '');
        $limit = max(1, min(100, (int) ($this->request->getGet('limit') ?? 20)));

        $service = new TagService();
        $tags = $service->searchTags($query, $limit);

        return $this->success(array_map(static function (array $tag): array {
            return [
                'id' => (int) $tag['id'],
                'name' => $tag['name'],
                'slug' => $tag['slug'],
                'usage_count' => isset($tag['usage_count']) ? (int) $tag['usage_count'] : 0,
            ];
        }, $tags));
    }

    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'read')) {
            return $this->response;
        }

        $service = new TagService();
        $tag = $service->getAllTags('', 1000);
        foreach ($tag as $row) {
            if ((int) $row['id'] === $id) {
                return $this->success([
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'description' => $row['description'] ?? null,
                    'usage_count' => isset($row['usage_count']) ? (int) $row['usage_count'] : 0,
                ]);
            }
        }

        return $this->error('Tag not found.', 404);
    }

    public function create(): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'write')) {
            return $this->response;
        }

        $payload = $this->getJsonBody();
        $name = (string) ($payload['name'] ?? '');
        if (trim($name) === '') {
            return $this->error('Tag name is required.', 422);
        }

        $service = new TagService();
        $tag = $service->getOrCreateTag($name, (int) ($this->apiUser['id'] ?? 0));

        if (! $tag) {
            return $this->error('Failed to create tag.', 500);
        }

        return $this->success([
            'id' => (int) $tag['id'],
            'name' => $tag['name'],
            'slug' => $tag['slug'],
        ], 'Tag created.', 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'edit')) {
            return $this->response;
        }

        $payload = $this->getJsonBody();
        $name = (string) ($payload['name'] ?? '');
        if (trim($name) === '') {
            return $this->error('Tag name is required.', 422);
        }

        $service = new TagService();
        $tagModel = new \App\Models\TagModel();
        $tag = $tagModel->find($id);
        if (! $tag) {
            return $this->error('Tag not found.', 404);
        }

        $newName = TagService::normalizeTagName($name);
        if ($newName === '') {
            return $this->error('Invalid tag name.', 422);
        }

        $newSlug = TagService::slugify($newName);
        if ($newSlug === '') {
            return $this->error('Invalid tag slug.', 422);
        }

        $conflict = $tagModel->where('slug', $newSlug)->where('id !=', $id)->first();
        if ($conflict) {
            return $this->error('Another tag already uses this name or slug.', 409);
        }

        $tagModel->update($id, [
            'name' => $newName,
            'slug' => $newSlug,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = $tagModel->find($id);
        return $this->success([
            'id' => (int) $updated['id'],
            'name' => $updated['name'],
            'slug' => $updated['slug'],
        ], 'Tag updated.');
    }

    public function delete(int $id): \CodeIgniter\HTTP\Response
    {
        if (! $this->authenticate()) {
            return $this->response;
        }
        if (! $this->requirePermission('tags', 'delete')) {
            return $this->response;
        }

        $service = new TagService();
        $tag = $service->getTagById($id);
        if (! $tag) {
            return $this->error('Tag not found.', 404);
        }
        if ($service->hasTagAssignments($id)) {
            return $this->error('Cannot delete a tag that is attached to documents.', 422);
        }

        if (! $service->deleteTag($id)) {
            return $this->error('Failed to delete tag.', 500);
        }

        return $this->success(null, 'Tag deleted.');
    }
}
