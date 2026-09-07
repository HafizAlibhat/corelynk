<?php

namespace App\Services;

use App\Models\DocumentTagModel;
use App\Models\TagModel;
use CodeIgniter\Database\ConnectionInterface;
use Config\Database;

class TagService
{
    public const DOCUMENT_TYPES = [
        'quotation',
        'sales_order',
        'purchase_order',
        'purchase_rfq',
        'customs_invoice',
        'delivery_order',
        'vendor_bill',
        'work_order',
    ];

    protected TagModel $tagModel;
    protected DocumentTagModel $documentTagModel;
    protected ConnectionInterface $db;

    public function __construct(?TagModel $tagModel = null, ?DocumentTagModel $documentTagModel = null)
    {
        $this->tagModel = $tagModel ?? new TagModel();
        $this->documentTagModel = $documentTagModel ?? new DocumentTagModel();
        $this->db = Database::connect();
        $this->ensureTagTables();
    }

    protected function ensureTagTables(): void
    {
        try {
            if (! $this->db->tableExists('tags')) {
                $this->db->query("CREATE TABLE IF NOT EXISTS `tags` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(120) NOT NULL,
                    `description` TEXT NULL,
                    `created_by` INT UNSIGNED NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL,
                    KEY `idx_tags_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }

            if (! $this->db->tableExists('document_tags')) {
                $this->db->query("CREATE TABLE IF NOT EXISTS `document_tags` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tag_id` INT UNSIGNED NOT NULL,
                    `document_type` VARCHAR(80) NOT NULL,
                    `document_id` INT UNSIGNED NOT NULL,
                    `created_by` INT UNSIGNED NULL,
                    `created_at` DATETIME NULL,
                    UNIQUE KEY `uq_document_tags` (`tag_id`, `document_type`, `document_id`),
                    KEY `idx_document_tags_document` (`document_type`, `document_id`),
                    KEY `idx_document_tags_tag` (`tag_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
        } catch (\Throwable $_) {
            // If schema cannot be created, caller methods will handle missing tables gracefully.
        }
    }

    protected function hasTagsSchema(): bool
    {
        return $this->db->tableExists('tags') && $this->db->tableExists('document_tags');
    }

    public static function normalizeTagName(string $name): string
    {
        $name = trim((string) $name);
        $name = strip_tags($name);
        $name = preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $name);
        $name = trim($name, " -_\t\n\r\0\x0B");
        return $name;
    }

    public static function slugify(string $value): string
    {
        $value = strtolower((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value);
        $value = str_replace(['_', '/', '\\', '+'], '-', $value);
        $value = preg_replace('/[^a-z0-9\-\s]+/u', '', $value);
        $value = preg_replace('/[\s\-]+/u', '-', $value);
        $value = trim($value, '-');
        return $value;
    }

    public function isValidDocumentType(string $documentType): bool
    {
        return in_array($documentType, self::DOCUMENT_TYPES, true);
    }

    public function getTagBySlug(string $slug): ?array
    {
        if (! $this->db->tableExists('tags')) {
            return null;
        }

        $slug = trim((string) $slug);
        if ($slug === '') {
            return null;
        }
        return $this->tagModel->where('slug', $slug)->first();
    }

    public function getTagByName(string $name): ?array
    {
        if (! $this->db->tableExists('tags')) {
            return null;
        }

        $name = self::normalizeTagName($name);
        if ($name === '') {
            return null;
        }
        return $this->tagModel->where('name', $name)->first();
    }

    public function getOrCreateTag(string $name, int $createdBy = 0): ?array
    {
        $name = self::normalizeTagName($name);
        if ($name === '') {
            return null;
        }

        $slug = self::slugify($name);
        if ($slug === '') {
            return null;
        }

        $existing = $this->tagModel->where('slug', $slug)->first();
        if ($existing) {
            return $existing;
        }

        $existingByName = $this->tagModel->where('name', $name)->first();
        if ($existingByName) {
            return $existingByName;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->tagModel->insert($data);
        return $this->tagModel->find($this->tagModel->getInsertID());
    }

    public function getTagById(int $id): ?array
    {
        if (! $this->db->tableExists('tags')) {
            return null;
        }

        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return $this->tagModel->find($id);
    }

    public function hasTagAssignments(int $tagId): bool
    {
        $tagId = (int) $tagId;
        if ($tagId <= 0 || ! $this->db->tableExists('document_tags')) {
            return false;
        }

        return $this->db->table('document_tags')->where('tag_id', $tagId)->countAllResults() > 0;
    }

    public function createTag(string $name, int $createdBy = 0, string $description = ''): array
    {
        $name = self::normalizeTagName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        $slug = self::slugify($name);
        if ($slug === '') {
            throw new \InvalidArgumentException('Tag name is invalid.');
        }

        if (! $this->db->tableExists('tags')) {
            throw new \InvalidArgumentException('Tag storage is not available. Please run database setup or open Settings again.');
        }

        $conflict = $this->tagModel->where('slug', $slug)->orWhere('name', $name)->first();
        if ($conflict) {
            throw new \InvalidArgumentException('A tag with this name already exists.');
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $description),
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->tagModel->insert($data);
        return $this->tagModel->find($this->tagModel->getInsertID());
    }

    public function updateTag(int $tagId, string $name, int $updatedBy = 0, string $description = ''): array
    {
        $tagId = (int) $tagId;
        if ($tagId <= 0) {
            throw new \InvalidArgumentException('Invalid tag identifier.');
        }

        if (! $this->db->tableExists('tags')) {
            throw new \InvalidArgumentException('Tag storage is not available. Please run database setup or open Settings again.');
        }

        $tag = $this->getTagById($tagId);
        if (! $tag) {
            throw new \InvalidArgumentException('Tag not found.');
        }

        if ($this->hasTagAssignments($tagId)) {
            throw new \InvalidArgumentException('Cannot edit a tag that is attached to documents.');
        }

        $name = self::normalizeTagName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        $slug = self::slugify($name);
        if ($slug === '') {
            throw new \InvalidArgumentException('Tag name is invalid.');
        }

        $conflict = $this->tagModel
            ->where('id !=', $tagId)
            ->groupStart()
                ->where('slug', $slug)
                ->orWhere('name', $name)
            ->groupEnd()
            ->first();
        if ($conflict) {
            throw new \InvalidArgumentException('Another tag already uses this name.');
        }

        $this->tagModel->update($tagId, [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) $description),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->getTagById($tagId) ?: [];
    }

    public function getTagsForDocument(string $documentType, int $documentId): array
    {
        $documentType = trim((string) $documentType);
        if (! $this->isValidDocumentType($documentType) || $documentId <= 0 || ! $this->db->tableExists('document_tags') || ! $this->db->tableExists('tags')) {
            return [];
        }

        return $this->db->table('document_tags dt')
            ->select('t.id, t.name, t.slug')
            ->join('tags t', 't.id = dt.tag_id')
            ->where('dt.document_type', $documentType)
            ->where('dt.document_id', $documentId)
            ->orderBy('t.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function syncTagsForDocument(string $documentType, int $documentId, array $tagNames, int $userId = 0): array
    {
        $documentType = trim((string) $documentType);
        if (! $this->isValidDocumentType($documentType)) {
            throw new \InvalidArgumentException('Invalid document type for tag assignment.');
        }

        $documentId = (int) $documentId;
        if ($documentId <= 0) {
            throw new \InvalidArgumentException('Invalid document identifier for tag assignment.');
        }

        $tagNames = array_unique(array_filter(array_map([self::class, 'normalizeTagName'], $tagNames), static fn($value) => $value !== ''));

        $this->db->transStart();
        try {
            $existingLinks = $this->documentTagModel
                ->where('document_type', $documentType)
                ->where('document_id', $documentId)
                ->findAll();

            $existingTagIds = array_map(static fn(array $row): int => (int) $row['tag_id'], $existingLinks);
            $desiredTagIds = [];

            foreach ($tagNames as $tagName) {
                $tag = $this->getTagBySlug(self::slugify($tagName));
                if (! $tag) {
                    $tag = $this->getOrCreateTag($tagName, $userId);
                }
                if ($tag) {
                    $desiredTagIds[] = (int) $tag['id'];
                }
            }

            $desiredTagIds = array_values(array_unique($desiredTagIds));

            $toDelete = array_diff($existingTagIds, $desiredTagIds);
            $toAdd = array_diff($desiredTagIds, $existingTagIds);

            if (! empty($toDelete)) {
                $this->documentTagModel
                    ->where('document_type', $documentType)
                    ->where('document_id', $documentId)
                    ->whereIn('tag_id', $toDelete)
                    ->delete();
            }

            foreach ($toAdd as $tagId) {
                $this->documentTagModel->insert([
                    'tag_id' => $tagId,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'created_by' => $userId > 0 ? $userId : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->transComplete();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->getTagsForDocument($documentType, $documentId);
    }

    public function searchTags(string $query = '', int $limit = 20): array
    {
        if (! $this->db->tableExists('tags')) {
            return [];
        }

        $query = trim((string) $query);
        if ($this->db->tableExists('document_tags')) {
            $builder = $this->db->table('tags t')
                ->select('t.id, t.name, t.slug, COALESCE(COUNT(dt.id), 0) AS usage_count')
                ->join('document_tags dt', 'dt.tag_id = t.id', 'left');
        } else {
            $builder = $this->db->table('tags t')
                ->select('t.id, t.name, t.slug, 0 AS usage_count');
        }

        $builder->groupBy(['t.id', 't.name', 't.slug'])
            ->orderBy('usage_count', 'DESC')
            ->orderBy('t.name', 'ASC')
            ->limit($limit);

        if ($query !== '') {
            $queryLower = mb_strtolower($query);
            $builder->groupStart()
                ->like('LOWER(t.name)', $queryLower)
                ->orLike('LOWER(t.slug)', $queryLower)
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    public function getAllTags(string $search = '', int $limit = 200): array
    {
        if (! $this->db->tableExists('tags')) {
            return [];
        }

        $search = trim((string) $search);
        if ($this->db->tableExists('document_tags')) {
            $builder = $this->db->table('tags t')
                ->select('t.id, t.name, t.slug, t.description, t.created_by, t.created_at, t.updated_at, COALESCE(COUNT(dt.id), 0) AS usage_count')
                ->join('document_tags dt', 'dt.tag_id = t.id', 'left');
        } else {
            $builder = $this->db->table('tags t')
                ->select('t.id, t.name, t.slug, t.description, t.created_by, t.created_at, t.updated_at, 0 AS usage_count');
        }

        $builder->groupBy(['t.id', 't.name', 't.slug', 't.description', 't.created_by', 't.created_at', 't.updated_at'])
            ->orderBy('usage_count', 'DESC')
            ->orderBy('t.name', 'ASC')
            ->limit($limit);

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $builder->groupStart()
                ->like('LOWER(t.name)', $searchLower)
                ->orLike('LOWER(t.slug)', $searchLower)
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    public function deleteTag(int $tagId): bool
    {
        $tagId = (int) $tagId;
        if ($tagId <= 0 || ! $this->db->tableExists('tags')) {
            return false;
        }

        if ($this->hasTagAssignments($tagId)) {
            return false;
        }

        $this->db->transStart();
        try {
            $this->tagModel->delete($tagId);
            $this->db->transComplete();
            return $this->db->transStatus();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return false;
        }
    }

    public function removeTagFromDocument(string $documentType, int $documentId, int $tagId): bool
    {
        $documentType = trim((string) $documentType);
        if (! $this->isValidDocumentType($documentType) || $documentId <= 0 || $tagId <= 0 || ! $this->db->tableExists('document_tags')) {
            return false;
        }

        return (bool) $this->db->table('document_tags')
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('tag_id', $tagId)
            ->delete();
    }
}
