<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Product Assets</h4>
            <div class="text-muted small">Select a product to manage asset groups, channels, uploads, and listings.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#hubChannelModal">Create Channel</button>
            <a href="<?= site_url('products') ?>" class="btn btn-outline-secondary btn-sm">Back to Products</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Dimensions</th>
                            <th>Max File Size</th>
                            <th>Formats</th>
                            <th>Background</th>
                        </tr>
                    </thead>
                    <tbody id="hubChannelRows">
                        <?php if (empty($channels)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No channels configured yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($channels as $c): ?>
                                <tr>
                                    <td><?= esc($c['name'] ?? '-') ?></td>
                                    <td><?= esc(($c['width'] ?: 'any') . ' x ' . ($c['height'] ?: 'any')) ?></td>
                                    <td><?= esc(!empty($c['max_file_size']) ? number_format((int)$c['max_file_size']) . ' bytes' : 'default') ?></td>
                                    <td><code><?= esc((string)($c['allowed_formats'] ?? '')) ?></code></td>
                                    <td><?= esc($c['background_rule'] ?? 'any') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="<?= site_url('product-assets') ?>" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Search Product</label>
                    <input type="text" class="form-control" name="q" value="<?= esc($q ?? '') ?>" placeholder="Name, code, SKU">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?= site_url('product-assets') ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th>Product</th>
                            <th>Code/SKU</th>
                            <th>Category</th>
                            <th style="width: 180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No products found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <?php $identifier = !empty($p['public_id']) ? $p['public_id'] : $p['id']; ?>
                                <tr>
                                    <td><?= (int) ($p['id'] ?? 0) ?></td>
                                    <td><?= esc($p['name'] ?? '-') ?></td>
                                    <td><code><?= esc(($p['code'] ?? '') !== '' ? $p['code'] : ($p['sku'] ?? '-')) ?></code></td>
                                    <td><?= esc($p['category_name'] ?? '-') ?></td>
                                    <td>
                                        <a href="<?= site_url('products/' . urlencode((string) $identifier) . '?tab=assets') ?>" class="btn btn-sm btn-primary">
                                            Open Assets
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($pager)): ?>
        <div class="mt-3"><?= $pager->links() ?></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="hubChannelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Channel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="hubChannelForm">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label">Channel Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" required maxlength="120">
                    </div>
                    <div class="row g-2">
                        <div class="col-4"><label class="form-label">Width</label><input type="number" name="width" class="form-control form-control-sm" min="1"></div>
                        <div class="col-4"><label class="form-label">Height</label><input type="number" name="height" class="form-control form-control-sm" min="1"></div>
                        <div class="col-4"><label class="form-label">Max Bytes</label><input type="number" name="max_file_size" class="form-control form-control-sm" min="1"></div>
                    </div>
                    <div class="mb-2 mt-2">
                        <label class="form-label">Allowed Formats</label>
                        <input type="text" name="allowed_formats" class="form-control form-control-sm" placeholder="jpg,png,webp,psd,ai">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Background Rule</label>
                        <select name="background_rule" class="form-select form-select-sm">
                            <option value="any">Any</option>
                            <option value="white">White</option>
                            <option value="transparent">Transparent</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Create Channel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const form = document.getElementById('hubChannelForm');
    if (!form) return;

    form.addEventListener('submit', async function(e){
        e.preventDefault();
        const fd = new FormData(form);
        try {
            const res = await fetch('<?= site_url('product-assets/channels/store') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd,
            });
            const j = await res.json();
            if (!res.ok || !j.success) {
                throw new Error(j.message || ('HTTP ' + res.status));
            }
            window.location.reload();
        } catch (err) {
            alert(err.message || 'Failed to create channel');
        }
    });
})();
</script>
<?= $this->endSection() ?>
