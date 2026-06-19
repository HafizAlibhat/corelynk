<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\ChannelModel;
use App\Models\ProductAssetGroupModel;
use App\Models\ProductAssetListingModel;
use App\Models\ProductAssetModel;
use App\Models\ProductModel;
use Config\Database;
use CodeIgniter\HTTP\ResponseInterface;

class ProductAssets extends BaseController
{
    private const DEFAULT_FINAL_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    private const DEFAULT_SOURCE_FORMATS = ['psd', 'ai', 'cdr', 'pdf', 'svg', 'eps'];
    private const DEFAULT_RAW_IMAGE_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif', 'tif', 'tiff'];
    private const DEFAULT_RAW_VIDEO_FORMATS = ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'];
    private const DEFAULT_FINAL_MAX_MB = 500;
    private const DEFAULT_RAW_MAX_MB = 1000;
    private const DEFAULT_CHANNEL_MAX_MB = 2500;
    private const COMMON_GROUP_NAME = '__COMMON__';
    private const COMMON_GROUP_DESCRIPTION = 'System group for common raw/final assets.';
    private const LEGACY_DEFAULT_BRAND_NAME = 'Master Assets';
    private const LEGACY_DEFAULT_CHANNEL_NAME = 'Core Production';
    private const LEGACY_DEFAULT_CHANNEL_CODE = 'CORE';

    private ProductModel $productModel;
    private ProductAssetGroupModel $groupModel;
    private ProductAssetModel $assetModel;
    private ChannelModel $channelModel;
    private ProductAssetListingModel $listingModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->groupModel = new ProductAssetGroupModel();
        $this->assetModel = new ProductAssetModel();
        $this->channelModel = new ChannelModel();
        $this->listingModel = new ProductAssetListingModel();
    }

    public function index($productIdentifier = null)
    {
        $this->requireAuth();
        $this->requireAssetPermission('read');

        $product = $this->resolveProductOrFail($productIdentifier);

        return redirect()->to(site_url('products/' . urlencode((string) ($product['public_id'] ?? $product['id'])) . '?tab=assets'));
    }

    public function hub()
    {
        $this->requireAuth();
        $this->requireAssetPermission('read');

        if (! $this->ensureModuleTablesReady(false)) {
            return $this->response->setStatusCode(500)->setBody('Product Assets tables are missing. Please run migration or schema setup.');
        }

        $q = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $this->productModel
            ->select('products.id, products.public_id, products.name, products.code, products.sku, product_categories.name as category_name')
            ->join('product_categories', 'product_categories.id = products.category_id', 'left')
            ->orderBy('products.name', 'ASC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('products.name', $q)
                ->orLike('products.code', $q)
                ->orLike('products.sku', $q)
                ->groupEnd();
        }

        $products = $builder->paginate(20);
        $channels = $this->channelModel->orderBy('name', 'ASC')->findAll();

        $data = $this->setPageData([
            'page_title' => 'Product Assets',
            'products' => $products,
            'pager' => $this->productModel->pager,
            'q' => $q,
            'channels' => $channels,
        ]);

        return view('product_assets/hub', $data);
    }

    public function createChannelHub()
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        [$payload, $errors] = $this->buildChannelPayloadFromRequest(false);
        if (!empty($errors)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid channel settings',
                'errors' => $errors,
            ]);
        }

        $payload['created_by'] = (int) ($this->session->get('user_id') ?? 0) ?: null;
        $payload['created_at'] = date('Y-m-d H:i:s');

        if (! $this->channelModel->insert($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Failed to create channel',
                'errors' => $this->channelModel->errors(),
            ]);
        }

        $channelId = (int) $this->channelModel->getInsertID();
        AuditLogModel::record('product_asset_channel_created', (int) ($this->session->get('user_id') ?? 0), 'product_assets', null, [
            'channel_id' => $channelId,
            'name' => (string) ($payload['name'] ?? ''),
        ]);

        return $this->response->setJSON(['success' => true, 'channel_id' => $channelId]);
    }

    public function data($productIdentifier = null)
    {
        try {
            $this->requireAuth();
            $this->requireAssetPermission('read');

            if (! $this->ensureModuleTablesReady(false)) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Product Assets tables are not initialized',
                ]);
            }

            $product = $this->resolveProductOrFail($productIdentifier);
            $productId = (int) $product['id'];
            $this->cleanupLegacyDefaults($productId);
            $this->syncUniversalBrandsToProduct($productId);

            $filters = [
                'variant_id' => (int) ($this->request->getGet('variant_id') ?? 0),
                'channel_id' => (int) ($this->request->getGet('channel_id') ?? 0),
                'type' => trim((string) ($this->request->getGet('type') ?? '')),
                'q' => trim((string) ($this->request->getGet('q') ?? '')),
            ];

            $groupBuilder = $this->groupModel
                ->where('product_id', $productId)
                ->whereNotIn('name', [self::COMMON_GROUP_NAME, self::LEGACY_DEFAULT_BRAND_NAME])
                ->orderBy('id', 'DESC');

            if ($filters['variant_id'] > 0) {
                $groupBuilder->where('variant_id', $filters['variant_id']);
            }

            $groups = $groupBuilder->findAll();

            $assetGroupBuilder = $this->groupModel
                ->select('id')
                ->where('product_id', $productId);

            if ($filters['variant_id'] > 0) {
                // Variant scope must be isolated. Do not include common product assets here,
                // otherwise variant pages appear to share uploaded assets across all variants.
                $assetGroupBuilder->where('variant_id', $filters['variant_id']);
            }

            $assetGroups = $assetGroupBuilder->findAll();
            $groupIds = array_values(array_filter(array_map(static fn($g) => (int) ($g['id'] ?? 0), $assetGroups)));
            $assets = [];
            if (!empty($groupIds)) {
                $builder = $this->assetModel
                    ->select('product_assets.*, channels.name as channel_name')
                    ->join('channels', 'channels.id = product_assets.channel_id', 'left')
                    ->whereIn('asset_group_id', $groupIds)
                    ->orderBy('is_primary', 'DESC')
                    ->orderBy('id', 'DESC');

                if ($filters['channel_id'] > 0) {
                    $builder->where('product_assets.channel_id', $filters['channel_id']);
                }
                if ($filters['type'] !== '') {
                    $builder->where('product_assets.type', $filters['type']);
                }
                if ($filters['q'] !== '') {
                    $builder->groupStart()
                        ->like('product_assets.file_name', $filters['q'])
                        ->orLike('product_assets.tags', $filters['q'])
                        ->groupEnd();
                }

                $assets = $builder->findAll();
                foreach ($assets as &$asset) {
                    $asset['file_url'] = $this->toPublicUrl((string) ($asset['file_path'] ?? ''));
                    $asset['thumbnail_url'] = $this->toPublicUrl((string) ($asset['thumbnail_path'] ?? ''));
                }
                unset($asset);
            }

            $channels = array_values(array_filter(
                $this->channelModel->orderBy('name', 'ASC')->findAll(),
                static function (array $channel): bool {
                    $name = strtolower(trim((string) ($channel['name'] ?? '')));
                    $code = strtoupper(trim((string) ($channel['short_code'] ?? '')));
                    return !($name === strtolower(self::LEGACY_DEFAULT_CHANNEL_NAME) || $code === self::LEGACY_DEFAULT_CHANNEL_CODE);
                }
            ));
            foreach ($channels as &$channel) {
                $channelId = (int) ($channel['id'] ?? 0);
                $rules = $this->getChannelRules($channel);
                $channel['rules'] = $rules;
                $channel['asset_count'] = $this->channelAssetCount($channelId);
                $channel['is_locked'] = $channel['asset_count'] > 0;
                // Keep old fields aligned with new rules for existing UI fragments.
                $channel['allowed_formats'] = json_encode($rules['final']['formats']);
                $channel['max_file_size'] = (int) $rules['final']['max_file_size_bytes'];
            }
            unset($channel);
            $listings = $this->listingModel
                ->select('product_asset_listings.*, channels.name as channel_name')
                ->join('channels', 'channels.id = product_asset_listings.channel_id', 'left')
                ->where('product_id', $productId)
                ->orderBy('id', 'DESC')
                ->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'product' => $this->buildLimitedProductSummary($productId),
                    'groups' => $groups,
                    'channels' => $channels,
                    'assets' => $assets,
                    'listings' => $listings,
                    'can_manage' => $this->canManageAssets(),
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ProductAssets::data() Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function createGroup($productIdentifier = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];

        $name = trim((string) $this->request->getPost('name'));
        $description = trim((string) ($this->request->getPost('description') ?? ''));
        $variantIdRaw = $this->request->getPost('variant_id');
        $variantId = is_numeric($variantIdRaw) && (int) $variantIdRaw > 0 ? (int) $variantIdRaw : null;

        $syncResult = $this->createUniversalBrand($name, $productId, $description, $variantId);
        if (! $syncResult['success']) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Failed to create asset group',
                'errors' => $syncResult['errors'] ?? [],
            ]);
        }

        $groupId = (int) ($syncResult['group_id'] ?? 0);
        if ($groupId > 0) {
            $this->ensureStorageStructure($productId, $groupId);
        }

        AuditLogModel::record('product_asset_group_created', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'group_id' => $groupId,
            'name' => $name,
            'variant_id' => $variantId,
        ]);

        return $this->response->setJSON(['success' => true, 'group_id' => $groupId]);
    }

    public function createChannel($productIdentifier = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];

        [$payload, $errors] = $this->buildChannelPayloadFromRequest(false);
        if (!empty($errors)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid channel settings',
                'errors' => $errors,
            ]);
        }

        $payload['created_by'] = (int) ($this->session->get('user_id') ?? 0) ?: null;
        $payload['created_at'] = date('Y-m-d H:i:s');

        if (! $this->channelModel->insert($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Failed to create channel',
                'errors' => $this->channelModel->errors(),
            ]);
        }

        $channelId = (int) $this->channelModel->getInsertID();

        AuditLogModel::record('product_asset_channel_created', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'channel_id' => $channelId,
            'name' => (string) ($payload['name'] ?? ''),
        ]);

        return $this->response->setJSON(['success' => true, 'channel_id' => $channelId]);
    }

    public function updateChannel($productIdentifier = null, $channelId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $channelId = (int) $channelId;

        $channel = $this->channelModel->find($channelId);
        if (! $channel) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Channel not found']);
        }

        if ($this->channelAssetCount($channelId) > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Cannot edit channel settings because assets are already attached to this channel.',
            ]);
        }

        [$payload, $errors] = $this->buildChannelPayloadFromRequest(true);
        if (!empty($errors)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid channel settings',
                'errors' => $errors,
            ]);
        }

        $payload['id'] = $channelId;

        if (! $this->channelModel->update($channelId, $payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Failed to update channel',
                'errors' => $this->channelModel->errors(),
            ]);
        }

        AuditLogModel::record('product_asset_channel_updated', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'channel_id' => $channelId,
            'name' => (string) ($payload['name'] ?? ''),
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function deleteChannel($productIdentifier = null, $channelId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $channelId = (int) $channelId;

        $channel = $this->channelModel->find($channelId);
        if (! $channel) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Channel not found']);
        }

        if ($this->channelAssetCount($channelId) > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Cannot delete channel because assets are attached to it.',
            ]);
        }

        if (! $this->channelModel->delete($channelId)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to delete channel']);
        }

        AuditLogModel::record('product_asset_channel_deleted', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'channel_id' => $channelId,
            'name' => (string) ($channel['name'] ?? ''),
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function upload($productIdentifier = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];

        $groupId = (int) ($this->request->getPost('asset_group_id') ?? 0);
        $channelId = (int) ($this->request->getPost('channel_id') ?? 0);
        $variantIdRaw = $this->request->getPost('variant_id');
        $variantId = is_numeric($variantIdRaw) && (int) $variantIdRaw > 0 ? (int) $variantIdRaw : null;
        $type = trim((string) ($this->request->getPost('type') ?? 'final'));
        $sectionKey = trim((string) ($this->request->getPost('section_key') ?? ''));
        $sourceFile = $this->request->getFile('source_file');
        $isPrimary = (int) ($this->request->getPost('is_primary') ?? 0) === 1;
        $tagsRaw = trim((string) ($this->request->getPost('tags') ?? ''));
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,\n\r]+/', $tagsRaw ?: ''))));

        if (!in_array($type, ['source', 'final', 'watermark', 'template'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid upload request']);
        }

        $isCommonSection = in_array($sectionKey, ['raw_images', 'final_plain'], true);
        if ($isCommonSection && $groupId <= 0) {
            $groupId = $this->getOrCreateCommonGroup($productId, $variantId);
        }

        if ($groupId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid brand/group selection']);
        }

        $group = $this->groupModel->where('id', $groupId)->where('product_id', $productId)->first();
        if (! $group) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Asset group not found']);
        }
        if ($variantId !== null && (int) ($group['variant_id'] ?? 0) !== $variantId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Asset group does not belong to this variant']);
        }
        if ($variantId === null && !empty($group['variant_id'])) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Variant-scoped group requires variant context']);
        }

        $channel = null;
        if ($channelId > 0) {
            $channel = $this->channelModel->find($channelId);
            if (! $channel) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Channel not found']);
            }
        }

        if (!$isCommonSection && $channelId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Channel is required for channel-specific uploads']);
        }

        // Common sections remain product-level assets even if a channel is selected in the UI.
        $persistChannelId = $isCommonSection ? 0 : $channelId;

        $rules = $channel ? $this->getChannelRules($channel) : $this->getDefaultRules();
        $sectionOptions = $this->buildSectionOptions($rules);
        $sectionMap = [];
        foreach ($sectionOptions as $opt) {
            $sectionMap[(string) $opt['key']] = $opt;
        }

        if ($sectionKey === '' && $type === 'source') {
            $sectionKey = 'source';
        }
        if ($sectionKey === '' && $type !== 'source') {
            $sectionKey = 'final_plain';
        }

        if (!isset($sectionMap[$sectionKey])) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Selected channel section is not allowed']);
        }

        $selectedSection = $sectionMap[$sectionKey];
        $type = (string) ($selectedSection['type'] ?? $type);
        $isCommonRawImages = $sectionKey === 'raw_images';
        $requiresSourceFile = !$isCommonRawImages && $type !== 'source';

        $files = $this->request->getFileMultiple('files');
        if (empty($files)) {
            $single = $this->request->getFile('files');
            if ($single) {
                $files = [$single];
            }
        }
        if (empty($files)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'No files uploaded']);
        }

        if ($requiresSourceFile) {
            if (! $sourceFile || ! $sourceFile->isValid() || $sourceFile->hasMoved()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Source file is required for this section.',
                ]);
            }

            if (count($files) !== 1) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Upload one output image at a time when source file is required.',
                ]);
            }
        }

        if (count($files) > 50) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Too many files in one request (max 50)']);
        }

        $globalLimits = $this->getGlobalUploadLimits();
        $requestCap = (int) ($globalLimits['channel_max_bytes'] ?? (self::DEFAULT_CHANNEL_MAX_MB * 1024 * 1024));
        $requestCapMb = max(1, (int) round($requestCap / 1024 / 1024));
        $totalBytes = 0;
        foreach ($files as $f) {
            if ($f && $f->isValid() && !$f->hasMoved()) {
                $totalBytes += (int) $f->getSize();
            }
        }
        if ($totalBytes > $requestCap) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Total upload exceeds endpoint limit of ' . $requestCapMb . 'MB per request.',
            ]);
        }

        [$storagePathAbs, $storagePathRel] = $this->ensureStorageStructure($productId, $groupId);

        $allowedFormats = $type === 'source'
            ? (array) ($rules['source']['formats'] ?? [])
            : (array) ($rules['final']['formats'] ?? []);
        $maxBytes = (int) ($globalLimits['channel_max_bytes'] ?? (self::DEFAULT_CHANNEL_MAX_MB * 1024 * 1024));

        // Common raw images are product-level references and should not be blocked by strict final output rules.
        if ($isCommonRawImages) {
            $allowedFormats = array_values(array_unique(array_merge(self::DEFAULT_RAW_IMAGE_FORMATS, self::DEFAULT_RAW_VIDEO_FORMATS)));
            $maxBytes = (int) ($globalLimits['raw_max_bytes'] ?? (self::DEFAULT_RAW_MAX_MB * 1024 * 1024));
        } elseif ($isCommonSection) {
            $maxBytes = (int) ($globalLimits['final_max_bytes'] ?? (self::DEFAULT_FINAL_MAX_MB * 1024 * 1024));
        }

        if ($type === 'source') {
            $existingSource = (int) $this->assetModel
                ->where('asset_group_id', $groupId)
                ->where('channel_id', $persistChannelId)
                ->where('type', 'source')
                ->countAllResults();
            if ($existingSource > 0) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Only one source file is allowed for this channel section.',
                ]);
            }
        }

        $sourceAssetId = null;
        if ($requiresSourceFile) {
            $sourceName = (string) $sourceFile->getClientName();
            $sourceMime = $this->detectMime($sourceFile->getTempName()) ?: (string) $sourceFile->getMimeType();
            $sourceSize = (int) $sourceFile->getSize();
            $sourceExt = strtolower((string) $sourceFile->getClientExtension());
            $sourceAllowedFormats = (array) ($rules['source']['formats'] ?? self::DEFAULT_SOURCE_FORMATS);

            if ($this->isDangerousUpload($sourceMime, $sourceExt)) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Source file type is blocked']);
            }

            if ($sourceSize <= 0) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Source file has invalid size']);
            }

            if (! $this->isMimeAllowed($sourceMime, $sourceExt, $sourceAllowedFormats) || ! $this->isMimeAllowedForAssetType($sourceMime, $sourceExt, 'source')) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Source file format is not allowed']);
            }

            $sourceBase = bin2hex(random_bytes(16));
            $sourceSafeExt = $sourceExt !== '' ? $sourceExt : 'bin';
            $sourceStorageName = $sourceBase . '.' . $sourceSafeExt;
            if (! $sourceFile->move($storagePathAbs, $sourceStorageName, true)) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to store source file']);
            }

            $sourceRelPath = trim($storagePathRel, '/') . '/' . $sourceStorageName;
            $sourceThumbPath = null;
            if (str_starts_with($sourceMime, 'image/')) {
                $sourceThumbPath = $this->createThumbnail($storagePathAbs, $storagePathRel, $sourceStorageName);
            }

            $sourceRow = [
                'asset_group_id' => $groupId,
                'channel_id' => $persistChannelId > 0 ? $persistChannelId : null,
                'type' => 'source',
                'section_key' => 'source',
                'section_label' => 'Source Files',
                'file_path' => $sourceRelPath,
                'thumbnail_path' => $sourceThumbPath,
                'file_name' => $sourceName,
                'file_size' => $sourceSize,
                'mime_type' => $sourceMime,
                'is_primary' => 0,
                'tags' => json_encode(['auto_source_pair']),
                'uploaded_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (! $this->assetModel->insert($sourceRow)) {
                @unlink($storagePathAbs . DIRECTORY_SEPARATOR . $sourceStorageName);
                if ($sourceThumbPath) {
                    $this->safeDeletePublicPath($sourceThumbPath);
                }
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to save source file']);
            }
            $sourceAssetId = (int) $this->assetModel->getInsertID();
        }

        $errors = [];
        $inserted = [];
        $sourceInsertedInRequest = false;

        foreach ($files as $idx => $file) {
            if ($type === 'source' && $sourceInsertedInRequest) {
                $errors[] = 'Only one source file is allowed per channel section';
                break;
            }

            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                $errors[] = 'File #' . ($idx + 1) . ' is invalid';
                continue;
            }

            $clientName = (string) $file->getClientName();
            $serverMime = (string) $file->getMimeType();
            $finfoMime = $this->detectMime($file->getTempName()) ?: $serverMime;
            $size = (int) $file->getSize();
            $ext = strtolower((string) $file->getClientExtension());

            if ($this->isDangerousUpload($finfoMime, $ext)) {
                $errors[] = $clientName . ': blocked file type';
                continue;
            }

            if ($size <= 0) {
                $errors[] = $clientName . ': invalid file size';
                continue;
            }

            if ($type !== 'source' && $size > $maxBytes) {
                $errors[] = $clientName . ': file size exceeds channel rule';
                continue;
            }

            if (! $this->isMimeAllowed($finfoMime, $ext, $allowedFormats)) {
                $errors[] = $clientName . ': file format/mime not allowed for this channel';
                continue;
            }

            if (! $isCommonRawImages && ! $this->isMimeAllowedForAssetType($finfoMime, $ext, $type)) {
                $errors[] = $clientName . ': file type is not allowed for asset type ' . $type;
                continue;
            }

            if (!$isCommonRawImages && str_starts_with($finfoMime, 'image/')) {
                $requiredWidth = is_array($channel) ? (int) ($channel['width'] ?? 0) : 0;
                $requiredHeight = is_array($channel) ? (int) ($channel['height'] ?? 0) : 0;
                $dimError = $this->validateImageDimensions($file->getTempName(), $requiredWidth, $requiredHeight);
                if ($dimError !== null) {
                    $errors[] = $clientName . ': ' . $dimError;
                    continue;
                }
            }

            $secureBase = bin2hex(random_bytes(16));
            $safeExt = $ext !== '' ? $ext : 'bin';
            $newName = $secureBase . '.' . $safeExt;

            if (! $file->move($storagePathAbs, $newName, true)) {
                $errors[] = $clientName . ': failed to store file';
                continue;
            }

            $relPath = trim($storagePathRel, '/') . '/' . $newName;
            $thumbPath = null;

            if (str_starts_with($finfoMime, 'image/')) {
                $thumbPath = $this->createThumbnail($storagePathAbs, $storagePathRel, $newName);
            }

            if ($isPrimary) {
                $this->assetModel
                    ->where('asset_group_id', $groupId)
                    ->where('channel_id', $persistChannelId)
                    ->where('type', $type)
                    ->set(['is_primary' => 0])
                    ->update();
            }

            $row = [
                'asset_group_id' => $groupId,
                'channel_id' => $persistChannelId > 0 ? $persistChannelId : null,
                'type' => $type,
                'section_key' => $sectionKey,
                'section_label' => (string) ($selectedSection['label'] ?? $sectionKey),
                'file_path' => $relPath,
                'thumbnail_path' => $thumbPath,
                'file_name' => $clientName,
                'file_size' => $size,
                'mime_type' => $finfoMime,
                'is_primary' => $isPrimary ? 1 : 0,
                'tags' => !empty($tags) ? json_encode($tags) : null,
                'uploaded_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if ($sourceAssetId !== null && $this->hasSourceAssetColumn()) {
                $row['source_asset_id'] = $sourceAssetId;
            }

            if (! $this->assetModel->insert($row)) {
                @unlink($storagePathAbs . DIRECTORY_SEPARATOR . $newName);
                $errors[] = $clientName . ': failed to save database record';
                continue;
            }

            $assetId = (int) $this->assetModel->getInsertID();
            $inserted[] = $assetId;
            if ($type === 'source') {
                $sourceInsertedInRequest = true;
            }
        }

        AuditLogModel::record('product_assets_uploaded', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'group_id' => $groupId,
            'channel_id' => $persistChannelId,
            'type' => $type,
            'uploaded_count' => count($inserted),
            'errors' => $errors,
        ]);

        return $this->response->setJSON([
            'success' => !empty($inserted),
            'uploaded' => count($inserted),
            'asset_ids' => $inserted,
            'errors' => $errors,
        ]);
    }

    public function setPrimary($productIdentifier = null, $assetId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $assetId = (int) $assetId;

        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Asset not found']);
        }

        $group = $this->groupModel->find((int) $asset['asset_group_id']);
        if (! $group || (int) ($group['product_id'] ?? 0) !== $productId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Asset does not belong to this product']);
        }

        $this->assetModel
            ->where('asset_group_id', (int) $asset['asset_group_id'])
            ->where('channel_id', (int) ($asset['channel_id'] ?? 0))
            ->where('type', (string) ($asset['type'] ?? 'final'))
            ->set(['is_primary' => 0])
            ->update();

        $this->assetModel->update($assetId, ['is_primary' => 1]);

        AuditLogModel::record('product_asset_primary_set', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'asset_id' => $assetId,
            'group_id' => (int) $asset['asset_group_id'],
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function deleteAsset($productIdentifier = null, $assetId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $assetId = (int) $assetId;

        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Asset not found']);
        }

        $group = $this->groupModel->find((int) ($asset['asset_group_id'] ?? 0));
        if (! $group || (int) ($group['product_id'] ?? 0) !== $productId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Asset does not belong to this product']);
        }

        $filePath = (string) ($asset['file_path'] ?? '');
        $thumbPath = (string) ($asset['thumbnail_path'] ?? '');

        if (! $this->assetModel->delete($assetId)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to delete asset record']);
        }

        $this->safeDeletePublicPath($filePath);
        $this->safeDeletePublicPath($thumbPath);

        AuditLogModel::record('product_asset_deleted', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'asset_id' => $assetId,
            'group_id' => (int) ($asset['asset_group_id'] ?? 0),
            'channel_id' => (int) ($asset['channel_id'] ?? 0),
            'type' => (string) ($asset['type'] ?? ''),
            'section_key' => (string) ($asset['section_key'] ?? ''),
            'file_name' => (string) ($asset['file_name'] ?? ''),
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function updateAssetFile($productIdentifier = null, $assetId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $assetId = (int) $assetId;

        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Asset not found']);
        }

        $group = $this->groupModel->find((int) ($asset['asset_group_id'] ?? 0));
        if (! $group || (int) ($group['product_id'] ?? 0) !== $productId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Asset does not belong to this product']);
        }

        $channel = null;
        $channelId = (int) ($asset['channel_id'] ?? 0);
        if ($channelId > 0) {
            $channel = $this->channelModel->find($channelId);
            if (! $channel) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Channel not found']);
            }
        }

        $rules = $channel ? $this->getChannelRules($channel) : $this->getDefaultRules();
        $sectionOptions = $this->buildSectionOptions($rules);
        $sectionMap = [];
        foreach ($sectionOptions as $opt) {
            $sectionMap[(string) $opt['key']] = $opt;
        }

        $sectionKey = trim((string) ($asset['section_key'] ?? ''));
        if ($sectionKey === '') {
            $sectionKey = ((string) ($asset['type'] ?? '') === 'source') ? 'source' : 'final_plain';
        }

        $selectedSection = $sectionMap[$sectionKey] ?? [
            'key' => $sectionKey,
            'label' => (string) ($asset['section_label'] ?? $sectionKey),
            'type' => (string) ($asset['type'] ?? 'final'),
        ];

        $type = (string) ($selectedSection['type'] ?? ($asset['type'] ?? 'final'));
        $isCommonRawImages = $sectionKey === 'raw_images';

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'No valid replacement file uploaded']);
        }

        $clientName = (string) $file->getClientName();
        $serverMime = (string) $file->getMimeType();
        $finfoMime = $this->detectMime($file->getTempName()) ?: $serverMime;
        $size = (int) $file->getSize();
        $ext = strtolower((string) $file->getClientExtension());

        if ($this->isDangerousUpload($finfoMime, $ext)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Blocked file type']);
        }

        if ($size <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid file size']);
        }

        $allowedFormats = $type === 'source'
            ? (array) ($rules['source']['formats'] ?? [])
            : (array) ($rules['final']['formats'] ?? []);
        $globalLimits = $this->getGlobalUploadLimits();
        $maxBytes = (int) ($globalLimits['channel_max_bytes'] ?? (self::DEFAULT_CHANNEL_MAX_MB * 1024 * 1024));

        if ($isCommonRawImages) {
            $allowedFormats = array_values(array_unique(array_merge(self::DEFAULT_RAW_IMAGE_FORMATS, self::DEFAULT_RAW_VIDEO_FORMATS)));
            $maxBytes = (int) ($globalLimits['raw_max_bytes'] ?? (self::DEFAULT_RAW_MAX_MB * 1024 * 1024));
        } elseif ($sectionKey === 'final_plain') {
            $maxBytes = (int) ($globalLimits['final_max_bytes'] ?? (self::DEFAULT_FINAL_MAX_MB * 1024 * 1024));
        }

        if ($type !== 'source' && $size > $maxBytes) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'File size exceeds allowed limit']);
        }

        if (! $this->isMimeAllowed($finfoMime, $ext, $allowedFormats)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'File format is not allowed']);
        }

        if (! $isCommonRawImages && ! $this->isMimeAllowedForAssetType($finfoMime, $ext, $type)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'File type is not allowed for this asset']);
        }

        if (! $isCommonRawImages && str_starts_with($finfoMime, 'image/')) {
            $requiredWidth = is_array($channel) ? (int) ($channel['width'] ?? 0) : 0;
            $requiredHeight = is_array($channel) ? (int) ($channel['height'] ?? 0) : 0;
            $dimError = $this->validateImageDimensions($file->getTempName(), $requiredWidth, $requiredHeight);
            if ($dimError !== null) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $dimError]);
            }
        }

        $oldFilePath = (string) ($asset['file_path'] ?? '');
        $oldThumbPath = (string) ($asset['thumbnail_path'] ?? '');

        $dirRel = trim(str_replace('\\', '/', dirname($oldFilePath)), '/');
        if ($dirRel === '' || $dirRel === '.') {
            [, $fallbackRel] = $this->ensureStorageStructure($productId, (int) ($asset['asset_group_id'] ?? 0));
            $dirRel = trim($fallbackRel, '/');
        }

        $dirAbs = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirRel);
        if (!is_dir($dirAbs)) {
            mkdir($dirAbs, 0755, true);
        }

        $secureBase = bin2hex(random_bytes(16));
        $safeExt = $ext !== '' ? $ext : 'bin';
        $newName = $secureBase . '.' . $safeExt;

        if (! $file->move($dirAbs, $newName, true)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to store replacement file']);
        }

        $newRelPath = trim($dirRel, '/') . '/' . $newName;
        $newThumbPath = null;
        if (str_starts_with($finfoMime, 'image/')) {
            $newThumbPath = $this->createThumbnail($dirAbs, $dirRel, $newName);
        }

        $ok = $this->assetModel->update($assetId, [
            'file_path' => $newRelPath,
            'thumbnail_path' => $newThumbPath,
            'file_name' => $clientName,
            'file_size' => $size,
            'mime_type' => $finfoMime,
        ]);

        if (! $ok) {
            @unlink($dirAbs . DIRECTORY_SEPARATOR . $newName);
            if ($newThumbPath) {
                $this->safeDeletePublicPath($newThumbPath);
            }
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to update asset record']);
        }

        $this->safeDeletePublicPath($oldFilePath);
        $this->safeDeletePublicPath($oldThumbPath);

        AuditLogModel::record('product_asset_file_replaced', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'asset_id' => $assetId,
            'old_file' => $oldFilePath,
            'new_file' => $newRelPath,
            'section_key' => $sectionKey,
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function attachSource($productIdentifier = null, $assetId = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];
        $assetId = (int) $assetId;

        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Asset not found']);
        }

        $group = $this->groupModel->find((int) $asset['asset_group_id']);
        if (! $group || (int) ($group['product_id'] ?? 0) !== $productId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Asset does not belong to this product']);
        }

        $sourceFile = $this->request->getFile('source_file');
        if (! $sourceFile || ! $sourceFile->isValid() || $sourceFile->hasMoved()) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'No valid source file uploaded']);
        }

        $groupId = (int) $asset['asset_group_id'];
        $channelId = (int) ($asset['channel_id'] ?? 0);

        $channel = $channelId > 0 ? $this->channelModel->find($channelId) : null;
        $rules = $channel ? $this->getChannelRules($channel) : $this->getDefaultRules();
        $sourceAllowedFormats = (array) ($rules['source']['formats'] ?? self::DEFAULT_SOURCE_FORMATS);

        $sourceName = (string) $sourceFile->getClientName();
        $sourceMime = $this->detectMime($sourceFile->getTempName()) ?: (string) $sourceFile->getMimeType();
        $sourceSize = (int) $sourceFile->getSize();
        $sourceExt = strtolower((string) $sourceFile->getClientExtension());

        if ($this->isDangerousUpload($sourceMime, $sourceExt)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Source file type is blocked']);
        }
        if ($sourceSize <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid source file size']);
        }
        if (! $this->isMimeAllowed($sourceMime, $sourceExt, $sourceAllowedFormats) || ! $this->isMimeAllowedForAssetType($sourceMime, $sourceExt, 'source')) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Source file format is not allowed']);
        }

        [$storagePathAbs, $storagePathRel] = $this->ensureStorageStructure($productId, $groupId);

        $sourceBase = bin2hex(random_bytes(16));
        $sourceSafeExt = $sourceExt !== '' ? $sourceExt : 'bin';
        $sourceStorageName = $sourceBase . '.' . $sourceSafeExt;

        if (! $sourceFile->move($storagePathAbs, $sourceStorageName, true)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to store source file']);
        }

        $sourceRelPath = trim($storagePathRel, '/') . '/' . $sourceStorageName;
        $sourceThumbPath = null;
        if (str_starts_with($sourceMime, 'image/')) {
            $sourceThumbPath = $this->createThumbnail($storagePathAbs, $storagePathRel, $sourceStorageName);
        }

        $sourceRow = [
            'asset_group_id' => $groupId,
            'channel_id' => $channelId > 0 ? $channelId : null,
            'type' => 'source',
            'section_key' => 'source',
            'section_label' => 'Source Files',
            'file_path' => $sourceRelPath,
            'thumbnail_path' => $sourceThumbPath,
            'file_name' => $sourceName,
            'file_size' => $sourceSize,
            'mime_type' => $sourceMime,
            'is_primary' => 0,
            'tags' => json_encode(['attached_source']),
            'uploaded_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (! $this->assetModel->insert($sourceRow)) {
            @unlink($storagePathAbs . DIRECTORY_SEPARATOR . $sourceStorageName);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to save source file record']);
        }

        $sourceAssetId = (int) $this->assetModel->getInsertID();

        if ($this->hasSourceAssetColumn()) {
            $this->assetModel->update($assetId, ['source_asset_id' => $sourceAssetId]);
        }

        AuditLogModel::record('product_asset_source_attached', (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'asset_id' => $assetId,
            'source_asset_id' => $sourceAssetId,
        ]);

        return $this->response->setJSON(['success' => true, 'source_asset_id' => $sourceAssetId]);
    }

    public function saveListing($productIdentifier = null)
    {
        $this->requireAuth();
        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        if (! $this->ensureModuleTablesReady()) {
            return;
        }

        $product = $this->resolveProductOrFail($productIdentifier);
        $productId = (int) $product['id'];

        $channelId = (int) ($this->request->getPost('channel_id') ?? 0);
        $listingUrl = trim((string) ($this->request->getPost('listing_url') ?? ''));
        $notes = trim((string) ($this->request->getPost('notes') ?? '')) ?: null;

        if ($channelId <= 0 || $listingUrl === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Channel and listing URL are required']);
        }

        if (! filter_var($listingUrl, FILTER_VALIDATE_URL)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid listing URL']);
        }

        $existing = $this->listingModel
            ->where('product_id', $productId)
            ->where('channel_id', $channelId)
            ->first();

        $payload = [
            'product_id' => $productId,
            'channel_id' => $channelId,
            'listing_url' => $listingUrl,
            'notes' => $notes,
            'created_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->listingModel->update((int) $existing['id'], [
                'listing_url' => $listingUrl,
                'notes' => $notes,
            ]);
            $listingId = (int) $existing['id'];
            $action = 'product_asset_listing_updated';
        } else {
            if (! $this->listingModel->insert($payload)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Failed to save listing',
                    'errors' => $this->listingModel->errors(),
                ]);
            }
            $listingId = (int) $this->listingModel->getInsertID();
            $action = 'product_asset_listing_created';
        }

        AuditLogModel::record($action, (int) ($this->session->get('user_id') ?? 0), 'products', $productId, [
            'listing_id' => $listingId,
            'channel_id' => $channelId,
            'listing_url' => $listingUrl,
        ]);

        return $this->response->setJSON(['success' => true, 'listing_id' => $listingId]);
    }

    private function resolveProductOrFail($identifier): array
    {
        $product = $this->productModel->findByPublicIdOrId($identifier);
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        return $product;
    }

    private function buildLimitedProductSummary(int $productId): array
    {
        $db = \Config\Database::connect();
        $row = $db->table('products p')
            ->select('p.id, p.public_id, p.name, p.description, p.code, p.sku, pc.name as category_name')
            ->join('product_categories pc', 'pc.id = p.category_id', 'left')
            ->where('p.id', $productId)
            ->get()
            ->getRowArray();

        return [
            'id' => (int) ($row['id'] ?? $productId),
            'public_id' => $row['public_id'] ?? null,
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'category' => $row['category_name'] ?? '',
            'sku' => $row['code'] ?? ($row['sku'] ?? ''),
        ];
    }

    private function canManageAssets(): bool
    {
        if ($this->hasPermission('product_assets.edit') || $this->hasPermission('product_assets.write')) {
            return true;
        }

        if ($this->hasPermission('products.edit')) {
            return true;
        }

        return false;
    }

    private function canViewAssets(): bool
    {
        if ($this->hasPermission('product_assets.read') || $this->hasPermission('product_assets.view')) {
            return true;
        }

        if ($this->hasPermission('products.edit')) {
            return true;
        }

        return false;
    }

    private function requireAssetPermission(string $level = 'read'): void
    {
        $allowed = $level === 'read' ? $this->canViewAssets() : $this->canManageAssets();
        if ($allowed) {
            return;
        }

        if ($level === 'read') {
            $this->response->setStatusCode(403)->setBody('You do not have permission to access product assets.')->send();
            exit;
        }

        $this->ensureCanManageAssets();
    }

    private function ensureCanManageAssets(): void
    {
        if ($this->canManageAssets()) {
            return;
        }

        $this->response->setStatusCode(403)->setJSON([
            'success' => false,
            'message' => 'You do not have permission to manage product assets.',
        ])->send();
        exit;
    }

    private function ensureCsrf(): void
    {
        $tokenName = csrf_token();
        $posted = (string) ($this->request->getPost($tokenName) ?? '');
        $current = (string) csrf_hash();

        if ($posted !== '' && hash_equals($current, $posted)) {
            return;
        }

        $headerToken = (string) ($this->request->getHeaderLine('X-CSRF-TOKEN') ?? '');
        if ($headerToken !== '' && hash_equals($current, $headerToken)) {
            return;
        }

        $this->response->setStatusCode(403)->setJSON([
            'success' => false,
            'message' => 'CSRF validation failed.',
        ])->send();
        exit;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (int) $value;
        return $n > 0 ? $n : null;
    }

    private function ensureStorageStructure(int $productId, int $groupId): array
    {
        $baseRel = 'uploads/products/' . $productId . '/' . $groupId;
        $baseAbs = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $baseRel);
        $thumbAbs = $baseAbs . DIRECTORY_SEPARATOR . 'thumbs';

        if (!is_dir($baseAbs)) {
            mkdir($baseAbs, 0755, true);
        }
        if (!is_dir($thumbAbs)) {
            mkdir($thumbAbs, 0755, true);
        }

        $indexPath = $baseAbs . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($indexPath)) {
            @file_put_contents($indexPath, '');
        }

        $htaccessPath = $baseAbs . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $rules = "Options -ExecCGI\n<FilesMatch \"\\.(php|phtml|phar|pl|py|jsp|asp|sh|cgi)$\">\n  Require all denied\n</FilesMatch>\n";
            @file_put_contents($htaccessPath, $rules);
        }

        return [$baseAbs, $baseRel];
    }

    private function createThumbnail(string $baseAbs, string $baseRel, string $fileName): ?string
    {
        try {
            $source = $baseAbs . DIRECTORY_SEPARATOR . $fileName;
            $thumbName = 'thumb_' . $fileName;
            $thumbAbs = $baseAbs . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $thumbName;

            service('image')
                ->withFile($source)
                ->fit(240, 240, 'center')
                ->save($thumbAbs);

            return trim($baseRel, '/') . '/thumbs/' . $thumbName;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildChannelPayloadFromRequest(bool $isUpdate): array
    {
        $name = trim((string) ($this->request->getPost('name') ?? ''));
        $shortCode = strtoupper(trim((string) ($this->request->getPost('short_code') ?? '')));
        $backgroundRule = trim((string) ($this->request->getPost('background_rule') ?? 'any'));

        $finalFormats = $this->parseAllowedFormats((string) ($this->request->getPost('final_allowed_formats') ?? ''));
        if (empty($finalFormats)) {
            $finalFormats = self::DEFAULT_FINAL_FORMATS;
        }

        $sourceFormats = $this->parseAllowedFormats((string) ($this->request->getPost('source_allowed_formats') ?? ''));
        if (empty($sourceFormats)) {
            $sourceFormats = self::DEFAULT_SOURCE_FORMATS;
        }

        $globalLimits = $this->getGlobalUploadLimits();
        $finalMaxBytes = (int) ($globalLimits['channel_max_bytes'] ?? (self::DEFAULT_CHANNEL_MAX_MB * 1024 * 1024));
        $rawMaxBytes = (int) ($globalLimits['raw_max_bytes'] ?? (self::DEFAULT_RAW_MAX_MB * 1024 * 1024));

        $enableFinalWatermark = $this->request->getPost('enable_final_watermark') !== null;
        $enableFinalTemplate = $this->request->getPost('enable_final_template') !== null;

        $extraFinalRaw = trim((string) ($this->request->getPost('extra_final_sections') ?? ''));
        $extraFinal = array_values(array_filter(array_map(static fn($v) => trim((string) $v), preg_split('/[,\n\r]+/', $extraFinalRaw) ?: [])));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Channel name is required';
        }
        if (!in_array($backgroundRule, ['white', 'transparent', 'any'], true)) {
            $errors['background_rule'] = 'Invalid background rule';
        }
        $sections = [];
        // Mandatory core sections for every channel and every product flow.
        $sections[] = ['key' => 'raw_images', 'label' => 'Raw Images', 'type' => 'final', 'max_files' => null];
        $sections[] = ['key' => 'source', 'label' => 'Source Files', 'type' => 'source', 'max_files' => 1];
        $sections[] = ['key' => 'final_plain', 'label' => 'Final Images', 'type' => 'final', 'max_files' => null];

        if ($enableFinalWatermark) {
            $sections[] = ['key' => 'final_watermark', 'label' => 'Final With Watermark', 'type' => 'watermark', 'max_files' => null];
        }
        if ($enableFinalTemplate) {
            $sections[] = ['key' => 'final_template', 'label' => 'Final With Template', 'type' => 'template', 'max_files' => null];
        }
        foreach ($extraFinal as $label) {
            $key = 'final_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($label));
            $key = trim((string) $key, '_');
            if ($key === 'final') {
                continue;
            }
            $sections[] = ['key' => $key, 'label' => $label, 'type' => 'final', 'max_files' => null];
        }

        $rules = [
            'raw' => [
                'max_file_size_bytes' => $rawMaxBytes,
            ],
            'source' => [
                'formats' => array_values(array_unique($sourceFormats)),
                'max_files' => 1,
            ],
            'final' => [
                'formats' => array_values(array_unique($finalFormats)),
                'max_file_size_bytes' => $finalMaxBytes,
            ],
            'sections' => $sections,
        ];

        $payload = [
            'name' => $name,
            'short_code' => $shortCode !== '' ? $shortCode : null,
            'width' => $this->nullableInt($this->request->getPost('width')),
            'height' => $this->nullableInt($this->request->getPost('height')),
            // Legacy fields retained for backward compatibility with older views/reporting.
            'max_file_size' => $rules['final']['max_file_size_bytes'],
            'allowed_formats' => json_encode($rules['final']['formats']),
            'rules_json' => json_encode($rules),
            'background_rule' => $backgroundRule,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')) ?: null,
        ];

        return [$payload, $errors];
    }

    private function channelAssetCount(int $channelId): int
    {
        if ($channelId <= 0) {
            return 0;
        }
        return (int) $this->assetModel->where('channel_id', $channelId)->countAllResults();
    }

    private function getChannelRules(array $channel): array
    {
        $defaults = [
            'raw' => [
                'max_file_size_bytes' => self::DEFAULT_RAW_MAX_MB * 1024 * 1024,
            ],
            'source' => [
                'formats' => self::DEFAULT_SOURCE_FORMATS,
                'max_files' => 1,
            ],
            'final' => [
                'formats' => self::DEFAULT_FINAL_FORMATS,
                'max_file_size_bytes' => self::DEFAULT_FINAL_MAX_MB * 1024 * 1024,
            ],
            'sections' => [
                ['key' => 'raw_images', 'label' => 'Raw Images', 'type' => 'final', 'max_files' => null],
                ['key' => 'source', 'label' => 'Source Files', 'type' => 'source', 'max_files' => 1],
                ['key' => 'final_plain', 'label' => 'Final Images', 'type' => 'final', 'max_files' => null],
                ['key' => 'final_watermark', 'label' => 'Final With Watermark', 'type' => 'watermark', 'max_files' => null],
                ['key' => 'final_template', 'label' => 'Final With Template', 'type' => 'template', 'max_files' => null],
            ],
        ];

        $raw = (string) ($channel['rules_json'] ?? '');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $sourceFormats = $decoded['source']['formats'] ?? $this->parseAllowedFormats((string) ($channel['source_allowed_formats'] ?? ''));
        if (!is_array($sourceFormats) || empty($sourceFormats)) {
            $sourceFormats = $defaults['source']['formats'];
        }

        $finalFormats = $decoded['final']['formats'] ?? $this->parseAllowedFormats((string) ($channel['allowed_formats'] ?? ''));
        if (!is_array($finalFormats) || empty($finalFormats)) {
            $finalFormats = $defaults['final']['formats'];
        }

        $globalLimits = $this->getGlobalUploadLimits();
        $finalMax = (int) ($globalLimits['channel_max_bytes'] ?? $defaults['final']['max_file_size_bytes']);
        $rawMax = (int) ($globalLimits['raw_max_bytes'] ?? $defaults['raw']['max_file_size_bytes']);

        $sections = $decoded['sections'] ?? null;
        if (!is_array($sections) || empty($sections)) {
            $sections = $defaults['sections'];
        } else {
            $sections = $this->mergeMandatorySections($sections);
        }

        return [
            'raw' => [
                'max_file_size_bytes' => $rawMax,
            ],
            'source' => [
                'formats' => array_values(array_unique(array_map(static fn($v) => strtolower(trim((string) $v)), $sourceFormats))),
                'max_files' => 1,
            ],
            'final' => [
                'formats' => array_values(array_unique(array_map(static fn($v) => strtolower(trim((string) $v)), $finalFormats))),
                'max_file_size_bytes' => $finalMax,
            ],
            'sections' => array_values($sections),
        ];
    }

    private function buildSectionOptions(array $rules): array
    {
        $sections = $rules['sections'] ?? [];
        $out = [];
        foreach ($sections as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $type = trim((string) ($row['type'] ?? 'final'));
            if ($key === '' || $label === '') {
                continue;
            }
            if (!in_array($type, ['source', 'final', 'watermark', 'template'], true)) {
                $type = 'final';
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
            ];
        }

        if (empty($out)) {
            $out[] = ['key' => 'raw_images', 'label' => 'Raw Images', 'type' => 'final'];
            $out[] = ['key' => 'source', 'label' => 'Source Files', 'type' => 'source'];
            $out[] = ['key' => 'final_plain', 'label' => 'Final Images', 'type' => 'final'];
        }

        return $out;
    }

    private function parseAllowedFormats(string $jsonOrCsv): array
    {
        $raw = trim($jsonOrCsv);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $list = [];

        if (is_array($decoded)) {
            $list = $decoded;
        } else {
            $list = preg_split('/[,\n\r]+/', $raw) ?: [];
        }

        return array_values(array_filter(array_map(static fn($v) => strtolower(trim((string) $v)), $list)));
    }

    private function isMimeAllowed(string $mime, string $ext, array $allowedFormats): bool
    {
        if (empty($allowedFormats)) {
            return true;
        }

        $mime = strtolower(trim($mime));
        $ext = strtolower(trim($ext));

        foreach ($allowedFormats as $allowed) {
            if ($allowed === '') {
                continue;
            }
            $a = strtolower(trim($allowed));

            if ($a === $mime || ($ext !== '' && ltrim($a, '.') === $ext)) {
                return true;
            }

            if ($a === 'jpg' || $a === 'jpeg') {
                if ($ext === 'jpg' || $ext === 'jpeg' || $mime === 'image/jpeg') {
                    return true;
                }
            }
            if ($a === 'png' && ($ext === 'png' || $mime === 'image/png')) {
                return true;
            }
            if ($a === 'webp' && ($ext === 'webp' || $mime === 'image/webp')) {
                return true;
            }
            if ($a === 'gif' && ($ext === 'gif' || $mime === 'image/gif')) {
                return true;
            }
            if ($a === 'psd' && ($ext === 'psd' || $mime === 'image/vnd.adobe.photoshop')) {
                return true;
            }
            if ($a === 'ai' && ($ext === 'ai' || str_contains($mime, 'postscript') || str_contains($mime, 'illustrator'))) {
                return true;
            }
            if ($a === 'mp4' && ($ext === 'mp4' || $mime === 'video/mp4')) {
                return true;
            }
            if ($a === 'mov' && ($ext === 'mov' || $mime === 'video/quicktime')) {
                return true;
            }
            if ($a === 'webm' && ($ext === 'webm' || $mime === 'video/webm')) {
                return true;
            }
            if ($a === 'm4v' && ($ext === 'm4v' || $mime === 'video/x-m4v' || $mime === 'video/mp4')) {
                return true;
            }
            if ($a === 'avi' && ($ext === 'avi' || $mime === 'video/x-msvideo')) {
                return true;
            }
            if ($a === 'mkv' && ($ext === 'mkv' || $mime === 'video/x-matroska')) {
                return true;
            }
        }

        return false;
    }

    private function validateImageDimensions(string $tmpPath, int $requiredWidth, int $requiredHeight): ?string
    {
        if ($requiredWidth <= 0 && $requiredHeight <= 0) {
            return null;
        }

        $size = @getimagesize($tmpPath);
        if (!is_array($size) || count($size) < 2) {
            return 'invalid image dimensions';
        }

        $width = (int) $size[0];
        $height = (int) $size[1];

        if ($requiredWidth > 0 && $width !== $requiredWidth) {
            return 'width must be ' . $requiredWidth . 'px (got ' . $width . 'px)';
        }

        if ($requiredHeight > 0 && $height !== $requiredHeight) {
            return 'height must be ' . $requiredHeight . 'px (got ' . $height . 'px)';
        }

        return null;
    }

    private function isMimeAllowedForAssetType(string $mime, string $ext, string $type): bool
    {
        $mime = strtolower(trim($mime));
        $ext = strtolower(trim($ext));
        $type = strtolower(trim($type));

        $isImage = str_starts_with($mime, 'image/');

        if ($type === 'final' || $type === 'watermark' || $type === 'template') {
            return $isImage;
        }

        if ($type === 'source') {
            if ($isImage) {
                return true;
            }

            if (in_array($ext, ['psd', 'ai', 'cdr', 'pdf', 'svg', 'eps'], true)) {
                return true;
            }

            if (str_contains($mime, 'photoshop') || str_contains($mime, 'postscript') || str_contains($mime, 'illustrator')) {
                return true;
            }

            return false;
        }

        return false;
    }

    private function detectMime(string $tmpPath): ?string
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }

        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if (!$finfo) {
                return null;
            }
            $mime = finfo_file($finfo, $tmpPath) ?: null;
            finfo_close($finfo);
            return is_string($mime) ? $mime : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isDangerousUpload(string $mime, string $ext): bool
    {
        $mime = strtolower(trim($mime));
        $ext = strtolower(trim($ext));

        $blockedExtensions = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
            'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'sh', 'ps1',
            'jsp', 'asp', 'aspx', 'cgi', 'pl',
            'js', 'mjs', 'vbs', 'jar',
        ];

        $blockedMimeFragments = [
            'text/x-php',
            'application/x-httpd-php',
            'application/x-php',
            'application/x-msdownload',
            'application/x-dosexec',
            'application/javascript',
            'text/javascript',
            'application/x-sh',
        ];

        if (in_array($ext, $blockedExtensions, true)) {
            return true;
        }

        foreach ($blockedMimeFragments as $frag) {
            if ($mime === $frag || str_contains($mime, $frag)) {
                return true;
            }
        }

        return false;
    }

    private function toPublicUrl(string $path): string
    {
        $clean = trim(str_replace('\\', '/', $path), '/');
        if ($clean === '') {
            return '';
        }

        return base_url($clean);
    }

    private function safeDeletePublicPath(string $relativePath): void
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return;
        }

        // Ensure deletes are constrained to public directory paths.
        $absolute = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $publicRoot = realpath(FCPATH);
        if ($publicRoot === false) {
            return;
        }

        $targetDir = realpath(dirname($absolute));
        if ($targetDir === false || !str_starts_with($targetDir, $publicRoot)) {
            return;
        }

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function ensureModuleTablesReady(bool $json = true): bool
    {
        $db = \Config\Database::connect();
        $required = [
            'product_asset_groups',
            'channels',
            'product_assets',
            'product_asset_listings',
        ];

        foreach ($required as $tbl) {
            if (! $db->tableExists($tbl)) {
                if ($json) {
                    $this->response->setStatusCode(500)->setJSON([
                        'success' => false,
                        'message' => 'Product Assets schema is missing table: ' . $tbl . '. Run migration/schema setup.',
                    ])->send();
                    exit;
                }
                return false;
            }
        }

        return true;
    }

    private function mergeMandatorySections(array $sections): array
    {
        $map = [];
        foreach ($sections as $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = trim((string) ($row['key'] ?? ''));
            if ($k === '') {
                continue;
            }
            $map[$k] = $row;
        }

        $mandatory = [
            ['key' => 'raw_images', 'label' => 'Raw Images', 'type' => 'final', 'max_files' => null],
            ['key' => 'source', 'label' => 'Source Files', 'type' => 'source', 'max_files' => 1],
            ['key' => 'final_plain', 'label' => 'Final Images', 'type' => 'final', 'max_files' => null],
        ];

        foreach ($mandatory as $rule) {
            $map[$rule['key']] = $rule;
        }

        return array_values($map);
    }

    private function cleanupLegacyDefaults(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $commonGroupId = $this->getOrCreateCommonGroup($productId);

        $legacyGroups = $this->groupModel
            ->where('product_id', $productId)
            ->where('name', self::LEGACY_DEFAULT_BRAND_NAME)
            ->findAll();

        foreach ($legacyGroups as $group) {
            $groupId = (int) ($group['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            if ($commonGroupId > 0) {
                $commonAssets = $this->assetModel
                    ->select('id, source_asset_id')
                    ->where('asset_group_id', $groupId)
                    ->whereIn('section_key', ['raw_images', 'final_plain'])
                    ->findAll();

                $commonAssetIds = array_values(array_filter(array_map(static fn($row) => (int) ($row['id'] ?? 0), $commonAssets)));
                $sourceIds = array_values(array_filter(array_unique(array_map(static fn($row) => (int) ($row['source_asset_id'] ?? 0), $commonAssets))));

                if (!empty($commonAssetIds)) {
                    $this->assetModel
                        ->whereIn('id', $commonAssetIds)
                        ->set([
                            'asset_group_id' => $commonGroupId,
                            'channel_id' => null,
                        ])
                        ->update();
                }

                if (!empty($sourceIds)) {
                    $this->assetModel
                        ->whereIn('id', $sourceIds)
                        ->set([
                            'asset_group_id' => $commonGroupId,
                            'channel_id' => null,
                        ])
                        ->update();
                }
            }

            $assetCount = (int) $this->assetModel->where('asset_group_id', $groupId)->countAllResults();
            if ($assetCount === 0) {
                $this->groupModel->delete($groupId);
            }
        }

        $legacyChannels = $this->channelModel
            ->groupStart()
                ->where('name', self::LEGACY_DEFAULT_CHANNEL_NAME)
                ->orWhere('short_code', self::LEGACY_DEFAULT_CHANNEL_CODE)
            ->groupEnd()
            ->findAll();

        foreach ($legacyChannels as $channel) {
            $channelId = (int) ($channel['id'] ?? 0);
            if ($channelId <= 0) {
                continue;
            }
            $assetCount = $this->channelAssetCount($channelId);
            if ($assetCount === 0) {
                $this->channelModel->delete($channelId);
            }
        }
    }

    private function getOrCreateCommonGroup(int $productId, ?int $variantId = null): int
    {
        if ($productId <= 0) {
            return 0;
        }

        $existing = $this->groupModel
            ->where('product_id', $productId)
            ->where('name', self::COMMON_GROUP_NAME)
            ->where('variant_id', $variantId)
            ->first();
        if ($existing) {
            return (int) ($existing['id'] ?? 0);
        }

        $ok = $this->groupModel->insert([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'name' => self::COMMON_GROUP_NAME,
            'description' => self::COMMON_GROUP_DESCRIPTION,
            'created_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $ok ? (int) $this->groupModel->getInsertID() : 0;
    }

    private function syncUniversalBrandsToProduct(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $existingForProduct = $this->groupModel
            ->select('name')
            ->where('product_id', $productId)
            ->where('variant_id', null)
            ->findAll();
        $existingNames = array_map(static fn(array $row) => strtolower(trim((string) ($row['name'] ?? ''))), $existingForProduct);
        $existingNames = array_values(array_filter($existingNames));

        $allBrands = $this->groupModel
            ->select('name')
            ->where('variant_id', null)
            ->whereNotIn('name', [self::COMMON_GROUP_NAME, self::LEGACY_DEFAULT_BRAND_NAME])
            ->groupBy('name')
            ->findAll();

        foreach ($allBrands as $brand) {
            $name = trim((string) ($brand['name'] ?? ''));
            if ($name === '' || in_array(strtolower($name), $existingNames, true)) {
                continue;
            }

            $this->groupModel->insert([
                'product_id' => $productId,
                'variant_id' => null,
                'name' => $name,
                'description' => null,
                'created_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function createUniversalBrand(string $name, int $targetProductId, string $description = '', ?int $variantId = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Brand name is required']];
        }

        $productIds = [];
        if ($variantId !== null) {
            $productIds = [$targetProductId];
        } else {
            $productIds = array_values(array_filter(array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                $this->productModel->select('id')->findAll()
            )));
        }

        if (empty($productIds)) {
            return ['success' => false, 'errors' => ['products' => 'No products available']];
        }

        $targetGroupId = 0;
        foreach ($productIds as $pid) {
            $existing = $this->groupModel
                ->where('product_id', $pid)
                ->where('variant_id', $variantId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                if ($pid === $targetProductId) {
                    $targetGroupId = (int) ($existing['id'] ?? 0);
                }
                continue;
            }

            $ok = $this->groupModel->insert([
                'product_id' => $pid,
                'variant_id' => $variantId,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'created_by' => (int) ($this->session->get('user_id') ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if (! $ok) {
                return ['success' => false, 'errors' => $this->groupModel->errors()];
            }

            if ($pid === $targetProductId) {
                $targetGroupId = (int) $this->groupModel->getInsertID();
            }
        }

        if ($targetGroupId <= 0) {
            $fallback = $this->groupModel
                ->where('product_id', $targetProductId)
                ->where('name', $name)
                ->where('variant_id', $variantId)
                ->first();
            $targetGroupId = (int) ($fallback['id'] ?? 0);
        }

        return ['success' => true, 'group_id' => $targetGroupId];
    }

    private function getDefaultRules(): array
    {
        $globalLimits = $this->getGlobalUploadLimits();
        return [
            'raw' => [
                'max_file_size_bytes' => (int) ($globalLimits['raw_max_bytes'] ?? (self::DEFAULT_RAW_MAX_MB * 1024 * 1024)),
            ],
            'source' => [
                'formats' => self::DEFAULT_SOURCE_FORMATS,
                'max_files' => 1,
            ],
            'final' => [
                'formats' => self::DEFAULT_FINAL_FORMATS,
                'max_file_size_bytes' => (int) ($globalLimits['channel_max_bytes'] ?? (self::DEFAULT_CHANNEL_MAX_MB * 1024 * 1024)),
            ],
            'sections' => [
                ['key' => 'raw_images', 'label' => 'Raw Images', 'type' => 'final', 'max_files' => null],
                ['key' => 'source', 'label' => 'Source Files', 'type' => 'source', 'max_files' => 1],
                ['key' => 'final_plain', 'label' => 'Final Images', 'type' => 'final', 'max_files' => null],
                ['key' => 'final_watermark', 'label' => 'Final With Watermark', 'type' => 'watermark', 'max_files' => null],
                ['key' => 'final_template', 'label' => 'Final With Template', 'type' => 'template', 'max_files' => null],
            ],
        ];
    }

    private function getGlobalUploadLimits(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $rawMb = self::DEFAULT_RAW_MAX_MB;
        $finalMb = self::DEFAULT_FINAL_MAX_MB;
        $channelMb = self::DEFAULT_CHANNEL_MAX_MB;

        try {
            $db = Database::connect();
            if ($db->tableExists('system_settings')) {
                $rows = $db->table('system_settings')
                    ->select('setting_key, setting_value')
                    ->whereIn('setting_key', [
                        'product_assets_raw_max_mb',
                        'product_assets_final_max_mb',
                        'product_assets_channel_max_mb',
                    ])
                    ->get()->getResultArray();

                foreach ($rows as $row) {
                    $key = (string) ($row['setting_key'] ?? '');
                    $value = (int) ($row['setting_value'] ?? 0);
                    if ($value <= 0) {
                        continue;
                    }
                    if ($key === 'product_assets_raw_max_mb') {
                        $rawMb = $value;
                    } elseif ($key === 'product_assets_final_max_mb') {
                        $finalMb = $value;
                    } elseif ($key === 'product_assets_channel_max_mb') {
                        $channelMb = $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ProductAssets::getGlobalUploadLimits failed: ' . $e->getMessage());
        }

        $cached = [
            'raw_max_mb' => $rawMb,
            'raw_max_bytes' => $rawMb * 1024 * 1024,
            'final_max_mb' => $finalMb,
            'final_max_bytes' => $finalMb * 1024 * 1024,
            'channel_max_mb' => $channelMb,
            'channel_max_bytes' => $channelMb * 1024 * 1024,
        ];

        return $cached;
    }

    private function hasSourceAssetColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $db = \Config\Database::connect();
        $hasColumn = $db->fieldExists('source_asset_id', 'product_assets');
        return $hasColumn;
    }

    public function moveAsset(): ResponseInterface
    {
        if (!$this->ensureModuleTablesReady()) {
            return $this->fail('Schema missing', 500);
        }

        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        $assetId = (int) $this->request->getPost('asset_id');
        $targetGroupId = (int) $this->request->getPost('target_group_id');
        $targetChannelId = (int) $this->request->getPost('target_channel_id');

        if (!$assetId || !$targetGroupId || !$targetChannelId) {
            return $this->fail('Missing required parameters', 400);
        }

        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            return $this->fail('Asset not found', 404);
        }

        $targetGroup = $this->groupModel->find($targetGroupId);
        if (!$targetGroup) {
            return $this->fail('Target group not found', 404);
        }

        $targetChannel = $this->channelModel->find($targetChannelId);
        if (!$targetChannel) {
            return $this->fail('Target channel not found', 404);
        }

        // Validate MIME against channel rules
        if (!$this->isMimeAllowedForAssetType($asset['mime_type'], pathinfo($asset['file_name'], PATHINFO_EXTENSION), $asset['type'])) {
            return $this->fail('File type not allowed in target channel', 400);
        }

        // Move the asset
        $updated = $this->assetModel->update($assetId, [
            'asset_group_id' => $targetGroupId,
            'channel_id' => $targetChannelId,
        ]);

        if (!$updated) {
            return $this->fail('Failed to move asset', 500);
        }

        // Log the action
        add_audit_log([
            'entity_type' => 'ProductAsset',
            'entity_id' => $assetId,
            'action' => 'moved',
            'changes' => json_encode([
                'from_group' => $asset['asset_group_id'],
                'from_channel' => $asset['channel_id'],
                'to_group' => $targetGroupId,
                'to_channel' => $targetChannelId,
            ]),
            'user_id' => $this->session->get('user_id'),
        ]);

        return $this->respondCreated(['success' => true, 'message' => 'Asset moved successfully']);
    }

    public function copyAsset(): ResponseInterface
    {
        if (!$this->ensureModuleTablesReady()) {
            return $this->fail('Schema missing', 500);
        }

        $this->ensureCsrf();
        $this->ensureCanManageAssets();

        $assetId = (int) $this->request->getPost('asset_id');
        $targetGroupId = (int) $this->request->getPost('target_group_id');
        $targetChannelId = (int) $this->request->getPost('target_channel_id');

        if (!$assetId || !$targetGroupId || !$targetChannelId) {
            return $this->fail('Missing required parameters', 400);
        }

        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            return $this->fail('Asset not found', 404);
        }

        $targetGroup = $this->groupModel->find($targetGroupId);
        if (!$targetGroup) {
            return $this->fail('Target group not found', 404);
        }

        $targetChannel = $this->channelModel->find($targetChannelId);
        if (!$targetChannel) {
            return $this->fail('Target channel not found', 404);
        }

        // Validate MIME against channel rules
        if (!$this->isMimeAllowedForAssetType($asset['mime_type'], pathinfo($asset['file_name'], PATHINFO_EXTENSION), $asset['type'])) {
            return $this->fail('File type not allowed in target channel', 400);
        }

        // Copy the file to new location
        $sourceFile = FCPATH . ltrim($asset['file_path'], '/');
        if (!is_file($sourceFile)) {
            return $this->fail('Source file not found on disk', 500);
        }

        // Generate new filename for copy
        $fileName = $asset['file_name'];
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $newFileName = $baseName . '_copy_' . bin2hex(random_bytes(4)) . '.' . $ext;

        // Storage path
        $storagePathAbs = FCPATH . 'uploads/product_assets/';
        $storagePathRel = 'uploads/product_assets/';

        if (!is_dir($storagePathAbs)) {
            mkdir($storagePathAbs, 0755, true);
        }

        $newFilePath = $storagePathAbs . $newFileName;
        $newFilePathRel = 'uploads/product_assets/' . $newFileName;

        if (!copy($sourceFile, $newFilePath)) {
            return $this->fail('Failed to copy file', 500);
        }

        // Generate thumbnail if image
        $thumbPath = null;
        if (str_starts_with($asset['mime_type'], 'image/')) {
            $thumbPath = $this->createThumbnail($storagePathAbs, $storagePathRel, $newFileName);
        }

        // Create new asset record
        $newAssetId = $this->assetModel->insert([
            'asset_group_id' => $targetGroupId,
            'channel_id' => $targetChannelId,
            'type' => $asset['type'],
            'file_path' => $newFilePathRel,
            'thumbnail_path' => $thumbPath,
            'file_name' => $fileName,
            'file_size' => $asset['file_size'],
            'mime_type' => $asset['mime_type'],
            'is_primary' => 0,
            'tags' => $asset['tags'],
            'uploaded_by' => $this->session->get('user_id'),
        ], true);

        // Log the action
        add_audit_log([
            'entity_type' => 'ProductAsset',
            'entity_id' => $newAssetId,
            'action' => 'copied',
            'changes' => json_encode([
                'from_asset' => $assetId,
                'from_group' => $asset['asset_group_id'],
                'from_channel' => $asset['channel_id'],
                'to_group' => $targetGroupId,
                'to_channel' => $targetChannelId,
            ]),
            'user_id' => $this->session->get('user_id'),
        ]);

        return $this->respondCreated(['success' => true, 'message' => 'Asset copied successfully', 'new_asset_id' => $newAssetId]);
    }
}
