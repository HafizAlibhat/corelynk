<?php
$request = \Config\Services::request();
$plain = ($request->isAJAX() || $request->getGet('modal') == '1');
$isEdit = isset($category) && $category;
$brandCode = $brand_code ?? 'RI';
$currentSuffix = esc(strtoupper($category['suffix'] ?? ''));

if (! $plain):
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold">
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'folder-plus' ?> me-2 text-primary"></i>
            <?= $isEdit ? 'Edit Category' : 'Create New Category' ?>
        </h2>
        <p class="text-muted mb-0 mt-1 small"><?= $isEdit ? 'Update category details below.' : 'Fill in the details below to add a product category.' ?></p>
    </div>
    <a href="<?= base_url('/product-categories') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Categories
    </a>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2">
        <i class="bi bi-check-circle-fill fs-5 text-success mt-1"></i>
        <div class="flex-grow-1"><?= esc(session()->getFlashdata('success')) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-octagon-fill fs-5 text-danger mt-1"></i>
        <div class="flex-grow-1"><?= esc(session()->getFlashdata('error')) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('validation')): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" id="validationAlert">
        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger mt-1" style="flex-shrink:0"></i>
        <div class="flex-grow-1">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach (session()->getFlashdata('validation') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?= form_open($isEdit ? base_url('/product-categories/' . $category['id'] . '/update') : base_url('/product-categories/store'), ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'categoryForm', 'data-category-id' => $isEdit ? $category['id'] : '', 'data-brand-code' => $brandCode]) ?>

<style>
/* Product Categories Form — theme-aligned surfaces */
.pc-card-header {
    background: var(--gray-50) !important;
    border-bottom: 1px solid var(--gray-200);
}
.pc-brand-prefix {
    background: var(--gray-100) !important;
    color: var(--primary-color) !important;
    border-color: var(--gray-200) !important;
    font-weight: 700;
    letter-spacing: 1px;
}
.pc-preview-box {
    background: linear-gradient(135deg, rgba(79,70,229,.14) 0%, rgba(79,70,229,.06) 100%);
    border: 1px solid rgba(79,70,229,.28) !important;
}
.pc-preview-title { color: var(--primary-color) !important; }
.pc-preview-value { color: var(--gray-800); font-size:1.6rem; letter-spacing:2px; }
.pc-info-box {
    background: var(--gray-50);
    border: 1px solid var(--gray-200) !important;
}
.pc-legacy-box {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
}
/* Suffix conflict error box */
#suffixErrorBox {
    display: none;
    border-radius: 8px;
    border: 1px solid #f87171;
    background: rgba(248,113,113,.1);
    padding: 10px 14px;
    margin-top: 8px;
}
body.theme-dark #suffixErrorBox {
    background: rgba(248,113,113,.15);
    border-color: #ef4444;
}
.suffix-suggestion-chip {
    display: inline-block;
    cursor: pointer;
    border: 1.5px solid var(--primary-color);
    color: var(--primary-color);
    border-radius: 20px;
    padding: 2px 12px;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin: 2px 3px 2px 0;
    transition: background .15s, color .15s;
    user-select: none;
}
.suffix-suggestion-chip:hover {
    background: var(--primary-color);
    color: #fff;
}
/* Auto-suggest area */
#autoSuggestArea { display: none; }
.auto-suggest-chip {
    display: inline-block;
    cursor: pointer;
    border: 1px dashed var(--gray-400);
    color: var(--gray-600);
    border-radius: 20px;
    padding: 1px 10px;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin: 2px 3px 2px 0;
    transition: border-color .15s, color .15s;
}
.auto-suggest-chip:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}
/* suffix input state */
#suffix.is-conflict { border-color: #ef4444 !important; box-shadow: 0 0 0 .2rem rgba(239,68,68,.25) !important; }
#suffix.is-ok      { border-color: #22c55e !important; box-shadow: 0 0 0 .2rem rgba(34,197,94,.2) !important; }

body.theme-dark .pc-preview-box {
    background: linear-gradient(135deg, rgba(129,140,248,.16) 0%, rgba(129,140,248,.08) 100%);
    border-color: rgba(129,140,248,.34) !important;
}
body.theme-dark .pc-info-box,
body.theme-dark .pc-legacy-box {
    background: var(--gray-100);
    border-color: var(--gray-200) !important;
}
/* Step number badge */
.pc-step-badge {
    display:inline-flex; align-items:center; justify-content:center;
    width:26px; height:26px; border-radius:50%;
    background: var(--primary-color); color:#fff;
    font-size:.75rem; font-weight:700; flex-shrink:0;
    margin-right:8px;
}
</style>

<!-- ═══════════════════════════════════════════════════════
     SECTION 1 — Basic Information
     ═══════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header pc-card-header py-3">
        <div class="d-flex align-items-center">
            <span class="pc-step-badge">1</span>
            <div>
                <h5 class="card-title mb-0"><i class="bi bi-tag me-2 text-primary"></i>Basic Information</h5>
                <p class="mb-0 text-muted small mt-0">Give your category a clear name and a short unique code.</p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Category Name -->
            <div class="col-md-5">
                <label for="name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= old('name', $category['name'] ?? '') ?>"
                       required maxlength="100" placeholder="e.g., Surgical Instruments"
                       autocomplete="off">
                <div class="form-text">What type of products belong to this category?</div>
                <div class="invalid-feedback">Please enter a category name (2–100 characters).</div>
            </div>

            <!-- Category Code (Suffix) -->
            <div class="col-md-3">
                <label for="suffix" class="form-label fw-semibold">
                    Category Code <span class="text-danger">*</span>
                    <span class="ms-1 text-muted fw-normal" style="font-size:.8rem;" data-bs-toggle="tooltip"
                          title="A short 2–4 letter code added to every product art number in this category. E.g. 'SI' for Surgical Instruments.">
                        <i class="bi bi-question-circle"></i>
                    </span>
                </label>
                <div class="input-group">
                    <span class="input-group-text pc-brand-prefix" style="letter-spacing:1px;"><?= esc($brandCode) ?> –</span>
                    <input type="text" class="form-control text-uppercase fw-bold text-center" id="suffix" name="suffix"
                           value="<?= old('suffix', $currentSuffix) ?>"
                           required minlength="2" maxlength="4" pattern="[A-Za-z]{2,4}"
                           placeholder="SI" style="letter-spacing:2px; font-size:1.1rem;"
                           autocomplete="off">
                    <span class="input-group-text px-2" id="suffixStatusIcon" style="width:38px; justify-content:center; transition:all .2s;">
                        <i class="bi bi-dash text-muted" id="suffixStatusIco"></i>
                    </span>
                </div>
                <div class="form-text">2–4 letters only · Must be unique.</div>
                <!-- Auto-suggest chips (shown when name is typed but suffix is empty) -->
                <div id="autoSuggestArea" class="mt-2" style="display:none;">
                    <span class="text-muted" style="font-size:.73rem;">Quick picks based on name:</span><br>
                    <span id="autoSuggestChips"></span>
                </div>
                <!-- Conflict error box -->
                <div id="suffixErrorBox">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-octagon-fill text-danger mt-1" style="flex-shrink:0; font-size:1rem;"></i>
                        <div>
                            <div class="fw-semibold text-danger" style="font-size:.82rem;" id="suffixErrorMsg"></div>
                            <div id="suffixSuggestArea" class="mt-1" style="display:none;">
                                <span class="text-muted" style="font-size:.73rem;">Try one of these available codes:</span><br>
                                <span id="suffixSuggestChips"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback">Enter 2–4 uppercase letters (A–Z only).</div>
            </div>

            <!-- Parent Category -->
            <div class="col-md-4">
                <label for="parent_id" class="form-label fw-semibold">Parent Category</label>
                <select id="parent_id" name="parent_id" class="form-select">
                    <option value="">None (Top-level)</option>
                    <?php foreach ($categories_dropdown as $pid => $pname): ?>
                        <?php if (!($isEdit && $category['id'] == $pid) && $pid !== ''): ?>
                            <option value="<?= esc($pid) ?>" <?= (string)old('parent_id', $category['parent_id'] ?? '') === (string)$pid ? 'selected' : '' ?>><?= esc($pname) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Optional — leave as "None" for a top-level category.</div>
            </div>

            <!-- Description -->
            <div class="col-md-8">
                <label for="description" class="form-label fw-semibold">Description <span class="text-muted fw-normal small">(optional)</span></label>
                <textarea class="form-control" id="description" name="description" rows="2" maxlength="500"
                          placeholder="Briefly describe the types of products in this category…"><?= old('description', $category['description'] ?? '') ?></textarea>
                <div class="form-text">Helps your team understand what belongs here.</div>
            </div>

            <!-- Active Toggle -->
            <div class="col-md-4 d-flex align-items-end">
                <div class="p-3 rounded-3" style="background:var(--gray-50); border:1px solid var(--gray-200); width:100%;">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                               <?= old('is_active', $category['is_active'] ?? 1) ? 'checked' : '' ?> style="width:2.5em; height:1.3em;">
                        <label class="form-check-label fw-semibold ms-1" for="is_active">Active Category</label>
                    </div>
                    <div class="text-muted small">When active, products can be assigned to this category.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     SECTION 2 — Art Number System
     ═══════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header pc-card-header py-3">
        <div class="d-flex align-items-center">
            <span class="pc-step-badge">2</span>
            <div>
                <h5 class="card-title mb-0"><i class="bi bi-upc-scan me-2 text-primary"></i>Art Number System</h5>
                <p class="mb-0 text-muted small mt-0">See how product art numbers will look for this category.</p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-stretch">
            <!-- Live Preview -->
            <div class="col-lg-5">
                <div class="pc-preview-box border rounded-3 p-3 h-100">
                    <div class="pc-preview-title fw-semibold small mb-2">
                        <i class="bi bi-eye me-1"></i> Next Art Number Preview
                    </div>
                    <div class="font-monospace fw-bold mb-3 pc-preview-value" id="skuPreview">—</div>

                    <!-- Visual breakdown -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-primary bg-opacity-75 px-2 py-1" style="font-size:.8rem;"><?= esc($brandCode) ?></span>
                        <span class="text-muted">–</span>
                        <span class="badge bg-success bg-opacity-75 px-2 py-1" style="font-size:.8rem;" id="suffixBadge"><?= $currentSuffix ?: '??' ?></span>
                        <span class="text-muted">–</span>
                        <span class="badge bg-secondary bg-opacity-75 px-2 py-1" style="font-size:.8rem;">00001</span>
                    </div>

                    <div class="mt-3 small text-muted" style="line-height:1.5;">
                        <div class="d-flex align-items-start mb-1">
                            <span class="badge bg-primary bg-opacity-25 text-primary me-2" style="min-width:50px; font-size:.7rem;">Brand</span>
                            <span><strong><?= esc($brandCode) ?></strong> — from <a href="<?= base_url('/settings') ?>" class="text-decoration-none">Company Settings</a></span>
                        </div>
                        <div class="d-flex align-items-start mb-1">
                            <span class="badge bg-success bg-opacity-25 text-success me-2" style="min-width:50px; font-size:.7rem;">Code</span>
                            <span><strong id="suffixPreviewInline"><?= $currentSuffix ?: 'XX' ?></strong> — unique category code (set above)</span>
                        </div>
                        <div class="d-flex align-items-start">
                            <span class="badge bg-secondary bg-opacity-25 text-body me-2" style="min-width:50px; font-size:.7rem;">Seq</span>
                            <span>Auto-incrementing global sequence number</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How It Works / Info -->
            <div class="col-lg-7">
                <div class="pc-info-box border rounded-3 p-3 h-100">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle me-1 text-primary"></i> How Art Numbers Work</h6>
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start mb-2">
                                <i class="bi bi-1-circle text-primary me-2 mt-1"></i>
                                <div><strong>Brand Code</strong> is set once in <a href="<?= base_url('/settings') ?>" class="text-decoration-none">Company Settings</a> and shared across all products.</div>
                            </div>
                            <div class="d-flex align-items-start mb-2">
                                <i class="bi bi-2-circle text-primary me-2 mt-1"></i>
                                <div><strong>Category Code</strong> is the unique 2–4 letter code you set for this category (above).</div>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="bi bi-3-circle text-primary me-2 mt-1"></i>
                                <div><strong>Sequence</strong> is a global auto-incrementing number shared across all categories. You can change the starting number in <a href="<?= base_url('/settings') ?>" class="text-decoration-none">Company Settings</a>.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="alert alert-info py-2 px-3 mb-2 small">
                                <i class="bi bi-lightbulb me-1"></i>
                                <strong>Example:</strong> If the brand code is <code><?= esc($brandCode) ?></code> and the category code is <code>KN</code>, art numbers will be <code><?= esc($brandCode) ?>-KN-00001</code>, <code><?= esc($brandCode) ?>-KN-00002</code>, etc.
                            </div>
                            <?php if ($isEdit): ?>
                                <div class="alert alert-warning py-2 px-3 mb-0 small">
                                    <i class="bi bi-shield-lock me-1"></i>
                                    <strong>Note:</strong> The category code cannot be changed once products have been assigned to this category.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success py-2 px-3 mb-0 small">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Choose a short, meaningful code. Common examples: <code>KN</code> (Knives), <code>SI</code> (Surgical), <code>SC</code> (Scissors), <code>TL</code> (Tools).
                                </div>
                            <?php endif; ?>
                            <div class="alert alert-secondary py-2 px-3 mb-0 mt-2 small">
                                <i class="bi bi-gear me-1"></i>
                                <strong>Tip:</strong> To change the brand code or sequence starting number, go to <a href="<?= base_url('/settings') ?>" class="text-decoration-none fw-semibold">Company Settings <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legacy Settings (collapsed) -->
        <div class="mt-3">
            <a class="text-muted small text-decoration-none" data-bs-toggle="collapse" href="#legacySettings" role="button" aria-expanded="false">
                <i class="bi bi-gear me-1"></i> Legacy Range Settings <i class="bi bi-chevron-down ms-1" style="font-size:.65rem;"></i>
            </a>
            <div class="collapse mt-2" id="legacySettings">
                <div class="row g-2 p-3 pc-legacy-box rounded-3" style="max-width:500px;">
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">Legacy Prefix</label>
                        <input type="text" class="form-control form-control-sm" id="prefix" name="prefix"
                               value="<?= old('prefix', $category['prefix'] ?? '') ?>" maxlength="50" placeholder="e.g., RI-S">
                    </div>
                    <div class="col-3">
                        <label class="form-label small text-muted mb-1">Next #</label>
                        <input type="number" class="form-control form-control-sm" id="next_number" name="next_number"
                               value="<?= old('next_number', $category['next_number'] ?? '') ?>" placeholder="—">
                    </div>
                    <div class="col-2.5">
                        <label class="form-label small text-muted mb-1">Start</label>
                        <input type="number" class="form-control form-control-sm" id="start_range" name="start_range"
                               value="<?= old('start_range', $category['start_range'] ?? '') ?>" placeholder="—">
                    </div>
                    <div class="col-2.5">
                        <label class="form-label small text-muted mb-1">End</label>
                        <input type="number" class="form-control form-control-sm" id="end_range" name="end_range"
                               value="<?= old('end_range', $category['end_range'] ?? '') ?>" placeholder="—">
                    </div>
                    <div class="col-12"><div class="form-text small">These fields are from the old numbering system. They are kept for reference only.</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     SECTION 3 — Actions
     ═══════════════════════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center">
    <a href="<?= base_url('/product-categories') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-x-circle me-1"></i> Cancel
    </a>
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-circle me-1"></i>
        <?= $isEdit ? 'Update Category' : 'Create Category' ?>
    </button>
</div>

<?php if ($isEdit): ?>
<!-- Category History -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header pc-card-header py-2">
        <h6 class="card-title mb-0 small text-muted"><i class="bi bi-clock-history me-1"></i> History</h6>
    </div>
    <div class="card-body py-2">
        <div class="d-flex gap-4 small text-muted">
            <div><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($category['created_at'])) ?></div>
            <?php if ($category['updated_at'] !== $category['created_at']): ?>
                <div><strong>Updated:</strong> <?= date('M j, Y g:i A', strtotime($category['updated_at'])) ?></div>
            <?php endif; ?>
            <div>
                <strong>Status:</strong>
                <span class="badge bg-<?= $category['is_active'] ? 'success' : 'secondary' ?> bg-opacity-75"><?= $category['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= form_close() ?>

<?= $this->endSection() ?>

<?php endif; // close if (! $plain) ?>

<!-- ═══════════════════════════════════════════════════════
     Scripts
     ═══════════════════════════════════════════════════════ -->

<!-- Bootstrap Validation + Tooltip init -->
<script>
(function() {
    'use strict';
    // Init Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
    // Bootstrap form validation
    window.addEventListener('load', function() {
        Array.from(document.getElementsByClassName('needs-validation')).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                // Block submit if suffix conflict exists
                if (document.getElementById('suffixErrorBox') &&
                    document.getElementById('suffixErrorBox').style.display !== 'none') {
                    event.preventDefault();
                    event.stopPropagation();
                    document.getElementById('suffix').focus();
                    return;
                }
                if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
                form.classList.add('was-validated');
            });
        });
    });
})();
</script>

<!-- Suffix & Art Number Preview Logic -->
<script>
(function() {
    const suffixEl        = document.getElementById('suffix');
    const nameEl          = document.getElementById('name');
    const previewEl       = document.getElementById('skuPreview');
    const suffixInline    = document.getElementById('suffixPreviewInline');
    const suffixBadge     = document.getElementById('suffixBadge');
    const formEl          = document.getElementById('categoryForm');
    const categoryId      = formEl ? formEl.dataset.categoryId : '';
    const brandCode       = formEl ? (formEl.dataset.brandCode || 'RI') : 'RI';

    // Error / suggestion elements
    const suffixErrorBox  = document.getElementById('suffixErrorBox');
    const suffixErrorMsg  = document.getElementById('suffixErrorMsg');
    const suffixSuggestArea = document.getElementById('suffixSuggestArea');
    const suffixSuggestChips = document.getElementById('suffixSuggestChips');
    const suffixStatusIco = document.getElementById('suffixStatusIco');

    // Auto-suggest from name
    const autoSuggestArea  = document.getElementById('autoSuggestArea');
    const autoSuggestChips = document.getElementById('autoSuggestChips');

    let debounceTimer  = null;
    let suffixTimer    = null;
    let nameTimer      = null;
    let currentConflict = false;

    // ── Force uppercase & letters only ──────────────────────────────────────
    if (suffixEl) {
        suffixEl.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 4);
            const val = this.value || '??';
            if (suffixInline) suffixInline.textContent = val;
            if (suffixBadge)  suffixBadge.textContent  = val;
            setSuffixStatus('checking');
            hideSuffixError();
            schedulePreview();
            scheduleSuffixCheck();
            // Hide auto-suggest once user is typing their own code
            if (this.value.length >= 2) hideAutoSuggest();
        });
    }

    // ── Category name → auto-suggest suffix ────────────────────────────────
    if (nameEl) {
        nameEl.addEventListener('input', function() {
            clearTimeout(nameTimer);
            nameTimer = setTimeout(function() {
                const name = nameEl.value.trim();
                const currentSuffix = suffixEl ? suffixEl.value : '';
                if (name.length >= 2 && currentSuffix.length < 2) {
                    showAutoSuggest(generateSuffixCandidates(name).slice(0, 4));
                } else {
                    hideAutoSuggest();
                }
            }, 350);
        });
    }

    // ── Generate suffix candidates client-side (mirrors server logic) ───────
    function generateSuffixCandidates(name) {
        const words = name.toUpperCase().split(/[\s\-_\/]+/).filter(w => /[A-Z]/.test(w));
        const candidates = [];
        // Initials
        if (words.length >= 2) {
            const initials = words.slice(0, 4).map(w => w.replace(/[^A-Z]/g, '').charAt(0)).join('');
            if (initials.length >= 2) candidates.push(initials.substring(0, 4));
        }
        const first = (words[0] || '').replace(/[^A-Z]/g, '');
        if (first.length >= 2) candidates.push(first.substring(0, 2));
        if (first.length >= 3) candidates.push(first.substring(0, 3));
        if (words.length >= 2) {
            const w1 = (words[0] || '').replace(/[^A-Z]/g, '');
            const w2 = (words[1] || '').replace(/[^A-Z]/g, '');
            if (w1 && w2) {
                candidates.push((w1.charAt(0) + w2.substring(0, 2)).substring(0, 4));
                candidates.push((w1.substring(0, 2) + w2.charAt(0)).substring(0, 4));
            }
        }
        // Deduplicate
        return [...new Set(candidates.filter(c => c.length >= 2))];
    }

    function showAutoSuggest(candidates) {
        if (!autoSuggestArea || !autoSuggestChips || candidates.length === 0) return;
        autoSuggestChips.innerHTML = '';
        candidates.forEach(function(code) {
            const chip = document.createElement('span');
            chip.className = 'auto-suggest-chip';
            chip.textContent = code;
            chip.addEventListener('click', function() {
                if (suffixEl) {
                    suffixEl.value = code;
                    suffixEl.dispatchEvent(new Event('input'));
                }
                hideAutoSuggest();
            });
            autoSuggestChips.appendChild(chip);
        });
        autoSuggestArea.style.display = 'block';
    }

    function hideAutoSuggest() {
        if (autoSuggestArea) autoSuggestArea.style.display = 'none';
    }

    // ── Suffix status icon ───────────────────────────────────────────────────
    function setSuffixStatus(state) {
        if (!suffixStatusIco || !suffixEl) return;
        suffixEl.classList.remove('is-conflict', 'is-ok');
        if (state === 'ok') {
            suffixStatusIco.className = 'bi bi-check-circle-fill text-success';
            suffixEl.classList.add('is-ok');
        } else if (state === 'conflict') {
            suffixStatusIco.className = 'bi bi-x-circle-fill text-danger';
            suffixEl.classList.add('is-conflict');
        } else if (state === 'checking') {
            suffixStatusIco.className = 'bi bi-arrow-repeat text-muted';
        } else {
            suffixStatusIco.className = 'bi bi-dash text-muted';
        }
    }

    // ── Art Number Preview ───────────────────────────────────────────────────
    function clientPreview() {
        const s = getSuffix();
        if (!s || s.length < 2) { if (previewEl) previewEl.textContent = '—'; return; }
        if (previewEl) previewEl.textContent = brandCode + '-' + s + '-?????';
    }

    function serverPreview() {
        const s = getSuffix();
        if (!s || s.length < 2) { if (previewEl) previewEl.textContent = '—'; return; }
        const url = new URL('<?= base_url('/product-categories/data') ?>', window.location.origin);
        url.searchParams.set('action', 'preview_next_sku');
        url.searchParams.set('category_id', categoryId || '0');
        url.searchParams.set('suffix', s);
        fetch(url.toString(), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.sku && previewEl) previewEl.textContent = json.sku;
                else clientPreview();
            })
            .catch(() => clientPreview());
    }

    function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(serverPreview, 400);
    }

    // ── Suffix Uniqueness Check ──────────────────────────────────────────────
    function scheduleSuffixCheck() {
        clearTimeout(suffixTimer);
        suffixTimer = setTimeout(function() {
            const s = getSuffix();
            if (!s || s.length < 2) { setSuffixStatus('idle'); return; }
            const url = new URL('<?= base_url('/product-categories/data') ?>', window.location.origin);
            url.searchParams.set('action', 'check_suffix');
            url.searchParams.set('suffix', s);
            url.searchParams.set('name', nameEl ? nameEl.value : '');
            if (categoryId) url.searchParams.set('exclude_id', categoryId);
            fetch(url.toString(), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.exists) {
                        currentConflict = true;
                        setSuffixStatus('conflict');
                        const conflictName = json.existing_name || 'another category';
                        showSuffixError(
                            'The code "' + s + '" is already used by "' + conflictName + '". Please choose a different code.',
                            json.suggestions || []
                        );
                    } else {
                        currentConflict = false;
                        setSuffixStatus('ok');
                        hideSuffixError();
                    }
                })
                .catch(() => { setSuffixStatus('idle'); });
        }, 500);
    }

    function showSuffixError(msg, suggestions) {
        if (!suffixErrorBox) return;
        if (suffixErrorMsg) suffixErrorMsg.textContent = msg;
        if (suggestions && suggestions.length > 0 && suffixSuggestArea && suffixSuggestChips) {
            suffixSuggestChips.innerHTML = '';
            suggestions.forEach(function(code) {
                const chip = document.createElement('span');
                chip.className = 'suffix-suggestion-chip';
                chip.textContent = code;
                chip.addEventListener('click', function() {
                    if (suffixEl) {
                        suffixEl.value = code;
                        suffixEl.dispatchEvent(new Event('input'));
                    }
                });
                suffixSuggestChips.appendChild(chip);
            });
            suffixSuggestArea.style.display = 'block';
        } else if (suffixSuggestArea) {
            suffixSuggestArea.style.display = 'none';
        }
        suffixErrorBox.style.display = 'block';
    }

    function hideSuffixError() {
        if (suffixErrorBox) suffixErrorBox.style.display = 'none';
        currentConflict = false;
    }

    function getSuffix() {
        return (suffixEl && suffixEl.value) ? suffixEl.value.toUpperCase().replace(/[^A-Z]/g, '') : '';
    }

    // Initial load
    if (getSuffix().length >= 2) {
        serverPreview();
        scheduleSuffixCheck();
    } else {
        clientPreview();
        setSuffixStatus('idle');
    }
})();
</script>
