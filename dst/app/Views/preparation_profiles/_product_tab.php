<?php
$productId   = (int) ($product['id'] ?? 0);
$productName = trim((string) ($product['name'] ?? 'Product'));
// Support being called from variant-only context (legacy) or full product context
$variantId   = (int) ($variant['id'] ?? 0);
$isVariantContext = $variantId > 0;

// Profiles for the base product (no variant)
$productProfiles  = $preparation_profiles ?? [];
// Keyed variant profiles: [variantId => [profile, ...]]
$variantProfileMap = $variant_preparation_profiles ?? [];
// All product variants
$allVariants = $variants ?? [];

// Helper: render a mini profiles table
function _renderProfilesTable(array $profiles, string $csrfField): string {
    if (empty($profiles)) return '';
    $html = '<div class="table-responsive mt-2"><table class="table table-sm table-bordered align-middle mb-0" style="font-size:13px;">';
    $html .= '<thead class="table-light"><tr>'
        . '<th>Setup Name</th>'
        . '<th class="text-center" style="width:70px;">Steps</th>'
        . '<th class="text-center" style="width:90px;">Materials</th>'
        . '<th class="text-end" style="width:160px;">Actions</th>'
        . '</tr></thead><tbody>';
    foreach ($profiles as $p) {
        $pid = (int) ($p['id'] ?? 0);
        $html .= '<tr>';
        $html .= '<td><div class="fw-semibold">' . esc($p['name'] ?? '') . '</div>'
            . (!empty($p['description']) ? '<small class="text-muted">' . esc($p['description']) . '</small>' : '')
            . '</td>';
        $html .= '<td class="text-center">' . (int) ($p['steps_count'] ?? 0) . '</td>';
        $html .= '<td class="text-center">' . (int) ($p['materials_count'] ?? 0) . '</td>';
        $html .= '<td class="text-end">'
            . '<a href="' . base_url('preparation-profiles/' . $pid . '/edit') . '" class="btn btn-outline-primary btn-sm me-1">Edit</a>'
            . '<form method="post" action="' . base_url('preparation-profiles/' . $pid . '/delete') . '" class="d-inline" onsubmit="return confirm(\'Remove this setup profile?\');">'
            . $csrfField
            . '<button type="submit" class="btn btn-outline-danger btn-sm">Remove</button></form>'
            . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

$csrfField = csrf_field();
?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success mt-2"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger mt-2"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php if ($isVariantContext): ?>
    <?php /* ── Legacy: called from variant-edit page context ── */ ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-gear-fill text-primary me-2"></i>
                Setup Profiles for this Variant
            </h5>
            <a href="<?= base_url('preparation-profiles/variant/' . $variantId . '/create') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Setup Profile
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($productProfiles)): ?>
                <p class="text-muted mb-0">No setup profiles yet for this variant. Click <strong>Add Setup Profile</strong> to get started.</p>
            <?php else: ?>
                <?= _renderProfilesTable($productProfiles, $csrfField) ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php /* ── Main product view: show variant-centric layout ── */ ?>

    <?php if (!empty($allVariants)): ?>
    <?php /* ── Product HAS variants — show one section per variant ── */ ?>

    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
        <div>
            <h5 class="mb-0"><i class="bi bi-boxes text-primary me-2"></i>Variant Setup Profiles</h5>
            <small class="text-muted">Each product variant (e.g. Gold, Black) can have its own preparation setup with different materials and steps.</small>
        </div>
    </div>

    <?php foreach ($allVariants as $v): ?>
        <?php
        $vid = (int) ($v['id'] ?? 0);
        $vName = trim((string) ($v['name'] ?? ('Variant #' . $vid)));
        $vArt  = trim((string) ($v['art_number'] ?? ''));
        $vProfiles = $variantProfileMap[$vid] ?? [];
        $hasProfiles = !empty($vProfiles);
        $createVariantUrl = base_url('preparation-profiles/variant/' . $vid . '/create');
        ?>
        <div class="card mb-3 border" id="variant-prep-card-<?= $vid ?>">
            <div class="card-header d-flex justify-content-between align-items-center py-2"
                 style="background: #f8f9fb; border-bottom: 1px solid #dee2e6;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" style="font-size:11px;">Variant</span>
                    <strong><?= esc($vName) ?></strong>
                    <?php if ($vArt !== ''): ?>
                        <span class="text-muted small">[<?= esc($vArt) ?>]</span>
                    <?php endif; ?>
                    <?php if ($hasProfiles): ?>
                        <span class="badge bg-success" style="font-size:10px;"><?= count($vProfiles) ?> Profile<?= count($vProfiles) > 1 ? 's' : '' ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark" style="font-size:10px;">No setup yet</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($hasProfiles): ?>
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            onclick="openCopyModal(<?= $vid ?>, '<?= esc($vName, 'js') ?>', <?= $vid ?>, <?= htmlspecialchars(json_encode(array_column($vProfiles, 'name', 'id')), ENT_QUOTES) ?>)"
                            title="Copy this variant's setup to other variants — saves you time by reusing the same steps and materials">
                            <i class="bi bi-copy me-1"></i>Copy Setup to Other Variants
                        </button>
                    <?php endif; ?>
                    <a href="<?= $createVariantUrl ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Setup Profile
                    </a>
                </div>
            </div>
            <div class="card-body py-2">
                <?php if ($hasProfiles): ?>
                    <?= _renderProfilesTable($vProfiles, $csrfField) ?>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-3 py-2">
                        <div class="text-muted" style="font-size:13px;">
                            <i class="bi bi-info-circle me-1"></i>
                            No setup profile created yet for <strong><?= esc($vName) ?></strong>.
                            A setup profile defines what materials are needed and what steps to follow when preparing this variant.
                        </div>
                        <a href="<?= $createVariantUrl ?>" class="btn btn-sm btn-outline-primary flex-shrink-0">
                            <i class="bi bi-plus-lg me-1"></i>Create Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php /* ── Also show product-level profiles (no variant) if any exist ── */ ?>
    <?php if (!empty($productProfiles)): ?>
    <div class="card mb-3 border-dashed border-secondary">
        <div class="card-header d-flex justify-content-between align-items-center py-2" style="background:#fff8e1;">
            <div>
                <strong><i class="bi bi-box me-1 text-warning"></i>Base Product Profiles</strong>
                <small class="text-muted ms-2">(apply to product-level, not linked to a specific variant)</small>
            </div>
            <a href="<?= base_url('preparation-profiles/product/' . $productId . '/create') ?>" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-plus-lg me-1"></i>Add Base Profile
            </a>
        </div>
        <div class="card-body py-2">
            <?= _renderProfilesTable($productProfiles, $csrfField) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <?php /* ── Product has NO variants — show standard product-level profile list ── */ ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-gear-fill text-primary me-2"></i>Setup Profiles</h5>
            <a href="<?= base_url('preparation-profiles/product/' . $productId . '/create') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Setup Profile
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($productProfiles)): ?>
                <p class="text-muted mb-0">No setup profiles yet. Click <strong>Add Setup Profile</strong> to define what materials and steps are needed to prepare this product.</p>
            <?php else: ?>
                <?= _renderProfilesTable($productProfiles, $csrfField) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; // end non-variant-context ?>


<?php /* ──────────────────────────────────────────────────────────────────
   COPY SETUP TO OTHER VARIANTS MODAL
   ────────────────────────────────────────────────────────────────── */ ?>
<?php if (!empty($allVariants) && !$isVariantContext): ?>
<div class="modal fade" id="copyProfileModal" tabindex="-1" aria-labelledby="copyProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyProfileModalLabel">
                    <i class="bi bi-copy me-2 text-primary"></i>Copy Setup to Other Variants
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="copyModalSubtitle" style="font-size:13px;"></p>

                <!-- Profile picker (only shown if source variant has multiple profiles) -->
                <div id="copyProfilePickerWrapper" class="mb-3" style="display:none;">
                    <label class="form-label fw-semibold">Which setup profile do you want to copy?</label>
                    <select class="form-select form-select-sm" id="copyProfilePickerSelect"></select>
                </div>

                <label class="form-label fw-semibold">Copy this setup to:</label>
                <div id="copyTargetVariantsList" class="d-flex flex-column gap-2 mb-2">
                    <!-- populated by JS -->
                </div>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    A <strong>new copy</strong> of this setup profile (materials &amp; steps) will be created for each selected variant.
                    You can then edit each copy separately if needed.
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnDoCopy" onclick="doCopyProfile()">
                    <i class="bi bi-copy me-1"></i>Copy Now
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var _copySourceVariantId = null;
var _copyProfileMap      = {};       // { profileId: profileName }
var _allVariants = <?= json_encode(array_map(function($v) {
    return ['id' => (int) $v['id'], 'name' => trim((string) ($v['name'] ?? '')), 'art_number' => trim((string) ($v['art_number'] ?? ''))];
}, $allVariants)) ?>;

var _csrfTokenName = '<?= csrf_token() ?>';
var _csrfHash      = '<?= csrf_hash() ?>';

function openCopyModal(sourceVariantId, sourceVariantName, excludeVariantId, profileMap) {
    _copySourceVariantId = sourceVariantId;
    _copyProfileMap = profileMap; // { id: name, ... }

    var profileIds = Object.keys(profileMap);
    var subtitle = document.getElementById('copyModalSubtitle');
    subtitle.textContent = 'Source: ' + sourceVariantName + ' — select which variants should get a copy of this setup.';

    // Profile picker
    var pickerWrapper = document.getElementById('copyProfilePickerWrapper');
    var pickerSelect  = document.getElementById('copyProfilePickerSelect');
    pickerSelect.innerHTML = '';
    if (profileIds.length > 1) {
        profileIds.forEach(function(pid) {
            var opt = document.createElement('option');
            opt.value = pid;
            opt.textContent = profileMap[pid];
            pickerSelect.appendChild(opt);
        });
        pickerWrapper.style.display = '';
    } else {
        pickerWrapper.style.display = 'none';
    }

    // Target variant checkboxes (exclude the source variant)
    var container = document.getElementById('copyTargetVariantsList');
    container.innerHTML = '';
    _allVariants.forEach(function(v) {
        if (v.id === excludeVariantId) return;
        var label = v.name + (v.art_number ? ' [' + v.art_number + ']' : '');
        var wrapper = document.createElement('div');
        wrapper.className = 'form-check';
        wrapper.innerHTML = '<input class="form-check-input copy-target-check" type="checkbox" value="' + v.id + '" id="copyTarget_' + v.id + '">'
            + '<label class="form-check-label" for="copyTarget_' + v.id + '">' + label + '</label>';
        container.appendChild(wrapper);
    });

    var modal = new bootstrap.Modal(document.getElementById('copyProfileModal'));
    modal.show();
}

function doCopyProfile() {
    var checks = document.querySelectorAll('.copy-target-check:checked');
    var targetIds = [];
    checks.forEach(function(c) { targetIds.push(parseInt(c.value, 10)); });
    if (targetIds.length === 0) {
        alert('Please select at least one variant to copy to.');
        return;
    }

    var profileIds = Object.keys(_copyProfileMap);
    var selectedProfileId = profileIds.length > 1
        ? parseInt(document.getElementById('copyProfilePickerSelect').value, 10)
        : parseInt(profileIds[0], 10);

    if (!selectedProfileId) {
        alert('Could not determine which profile to copy. Please refresh and try again.');
        return;
    }

    var btn = document.getElementById('btnDoCopy');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Copying...';

    var payload = {};
    payload[_csrfTokenName] = _csrfHash;
    payload['target_variant_ids'] = targetIds;

    fetch('<?= base_url('preparation-profiles/') ?>' + selectedProfileId + '/copy-to-variants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-copy me-1"></i>Copy Now';
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('copyProfileModal')).hide();
            // Show a friendly inline success alert then reload to reflect new profiles
            var alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
            alertDiv.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + data.message
                + ' <strong>Refreshing page…</strong>'
                + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            document.querySelector('#tab-preparation') && document.querySelector('#tab-preparation').prepend(alertDiv)
                || document.body.prepend(alertDiv);
            setTimeout(function() { window.location.href = window.location.pathname + '?tab=preparation'; }, 1200);
        } else {
            alert('Error: ' + (data.message || 'Copy failed. Please try again.'));
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-copy me-1"></i>Copy Now';
        alert('Network error. Please check your connection and try again.');
    });
}
</script>
<?php endif; ?>
