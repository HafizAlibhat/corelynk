<?= $this->extend('layouts/main') ?>

<?php
    $attributeCount = is_array($attributes ?? null) ? count($attributes) : 0;
    $valueCount = 0;
    if (!empty($attributes) && is_array($attributes)) {
        foreach ($attributes as $__attr) {
            $vals = json_decode($__attr['values'] ?? '[]', true) ?? [];
            $valueCount += is_array($vals) ? count($vals) : 0;
        }
    }
?>

<?= $this->section('content') ?>
<div class="container-fluid cl-list-page cl-attributes-page" data-cl-list>
    <div class="cl-list-page-header">
        <div class="cl-attributes-title-block">
            <h2 class="mb-0"><i class="bi bi-list-ul me-2"></i>Global Attributes</h2>
            <div class="small text-muted">Manage shared product attribute groups and values</div>
            <div class="cl-attributes-header-metrics" aria-label="Attribute summary">
                <span><strong><?= number_format((int)$attributeCount) ?></strong> groups</span>
                <span><strong><?= number_format((int)$valueCount) ?></strong> values</span>
            </div>
        </div>
        <div class="cl-list-page-actions">
            <a href="<?= base_url('product-attributes/create?modal=1') ?>" class="btn btn-primary cl-attributes-add-btn open-remote-modal">
                <i class="bi bi-plus-circle me-2"></i>Add Attribute
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm cl-list-table-card">
        <div class="card-body p-0">
            <?php if (empty($attributes)): ?>
                <div class="cl-list-empty cl-list-empty--row">
                    <span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-list-ul"></i></span>
                    <h6>No attributes yet</h6>
                    <p>Use Add Attribute to create the first shared attribute group.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cl-table table table-sm table-hover align-middle cl-attributes-table mb-0">
                        <caption class="visually-hidden">Global attributes and their values</caption>
                        <colgroup>
                            <col style="width: 14%;">
                            <col style="width: 76%;">
                            <col style="width: 10%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Values</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attributes as $a): ?>
                            <?php $values = json_decode($a['values'] ?? '[]', true) ?? []; ?>
                            <tr id="attribute-row-<?= esc($a['id']) ?>" data-attribute-id="<?= esc($a['id']) ?>" data-attribute-name="<?= esc($a['name']) ?>">
                                <td class="fw-semibold cl-attribute-name"><?= esc($a['name']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2 align-items-center cl-attribute-values">
                                        <?php if (empty($values)): ?>
                                            <span class="text-muted cl-attribute-empty"><em>No values</em></span>
                                        <?php else: ?>
                                            <?php foreach ($values as $val): ?>
                                                <span class="attr-value-badge" data-attribute-id="<?= esc($a['id']) ?>" data-value="<?= esc($val) ?>" title="Click to edit">
                                                    <span class="value-text"><?= esc($val) ?></span>
                                                    <a href="#" class="value-delete" title="Delete value" aria-label="Delete value">&times;</a>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-outline-primary btn-sm btn-add-value cl-attribute-add-value" data-id="<?= esc($a['id']) ?>">
                                            <i class="bi bi-plus-lg me-1"></i>Value
                                        </button>
                                    </div>
                                </td>
                                <td class="text-end cl-attribute-actions">
                                    <a href="<?= base_url('product-attributes/' . (int)$a['id'] . '/edit?modal=1') ?>" class="btn btn-outline-secondary btn-sm open-remote-modal">Edit</a>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete-attribute" data-id="<?= esc($a['id']) ?>" data-name="<?= esc($a['name']) ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete modal for attributes -->
<div class="modal fade" id="deleteAttributeModal" tabindex="-1" aria-labelledby="deleteAttributeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAttributeModalLabel">Delete Attribute</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete attribute <strong id="deleteAttributeName"></strong>?</p>
                <div class="text-muted small mt-2">Deletion is blocked if any variants already use this attribute.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteAttribute">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var base = '<?= rtrim(base_url('product-attributes'), '/') ?>';
    var deleteModalEl = document.getElementById('deleteAttributeModal');
    var deleteNameEl = document.getElementById('deleteAttributeName');
    var confirmDeleteBtn = document.getElementById('confirmDeleteAttribute');
    var pendingDeleteId = null;

    function closest(el, selector) {
        while (el && el !== document) {
            if (el.matches && el.matches(selector)) return el;
            el = el.parentNode;
        }
        return null;
    }

    function postForm(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(data)
        }).then(function(res){
            return res.json().catch(function(){ return { success:false, message:'Invalid response' }; });
        });
    }

    // Inline value edit (click badge text)
    document.addEventListener('click', function(e){
        var badge = closest(e.target, '.attr-value-badge');
        if (!badge) return;
        if (closest(e.target, '.value-delete')) return;

        var span = badge.querySelector('.value-text');
        if (!span || badge.querySelector('input')) return;

        e.preventDefault();

        var oldValue = badge.getAttribute('data-value') || '';
        var attributeId = badge.getAttribute('data-attribute-id') || '';
        if (!attributeId) return;

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm d-inline-block';
        input.style.minWidth = '120px';
        input.value = oldValue;
        badge.insertBefore(input, span);
        span.style.display = 'none';
        input.focus();

        function cleanup(){
            span.style.display = '';
            try { if (input && input.parentNode) input.parentNode.removeChild(input); } catch (ex) {}
        }

        function finish(save){
            if (!save) { cleanup(); return; }
            var newValue = (input.value || '').trim();
            if (newValue === '' || newValue === oldValue) { cleanup(); return; }
            postForm(base + '/' + encodeURIComponent(attributeId) + '/value/update', { old_value: oldValue, new_value: newValue })
                .then(function(json){
                    if (json && json.success) {
                        badge.setAttribute('data-value', newValue);
                        span.textContent = newValue;
                    } else {
                        alert(json && json.message ? json.message : 'Failed to update value');
                    }
                })
                .catch(function(){ alert('Network error updating value'); })
                .finally(cleanup);
        }

        input.addEventListener('blur', function(){ finish(true); });
        input.addEventListener('keydown', function(ev){
            if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }
            if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }
        });
    });

    // Delete a value (server blocks if used in variants)
    document.addEventListener('click', function(e){
        var del = closest(e.target, '.value-delete');
        if (!del) return;
        var badge = closest(del, '.attr-value-badge');
        if (!badge) return;
        e.preventDefault();

        var attributeId = badge.getAttribute('data-attribute-id') || '';
        var val = badge.getAttribute('data-value') || '';
        if (!attributeId || !val) return;

        if (!confirm('Delete value "' + val + '"?')) return;

        postForm(base + '/' + encodeURIComponent(attributeId) + '/value/delete', { value: val })
            .then(function(json){
                if (json && json.success) {
                    try { if (badge && badge.parentNode) badge.parentNode.removeChild(badge); } catch (ex) {}
                } else {
                    alert(json && json.message ? json.message : 'Failed to delete value');
                }
            })
            .catch(function(){ alert('Network error deleting value'); });
    });

    // Add a value (append into JSON array)
    document.addEventListener('click', function(e){
        var btn = closest(e.target, '.btn-add-value');
        if (!btn) return;
        e.preventDefault();
        var attributeId = btn.getAttribute('data-id') || '';
        if (!attributeId) return;
        var val = prompt('Enter new value');
        if (val === null) return;
        val = (val || '').trim();
        if (!val) return;
        postForm(base + '/' + encodeURIComponent(attributeId) + '/value/add', { value: val })
            .then(function(json){
                if (json && json.success) {
                    // simplest refresh to ensure consistent rendering
                    try { location.reload(); } catch (ex) {}
                } else {
                    alert(json && json.message ? json.message : 'Failed to add value');
                }
            })
            .catch(function(){ alert('Network error adding value'); });
    });

    // Delete attribute (server blocks if used in variants)
    document.addEventListener('click', function(e){
        var btn = closest(e.target, '.btn-delete-attribute');
        if (!btn) return;
        e.preventDefault();
        pendingDeleteId = btn.getAttribute('data-id');
        if (deleteNameEl) deleteNameEl.textContent = btn.getAttribute('data-name') || '';
        if (deleteModalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
        } else {
            if (confirm('Delete attribute?')) {
                postForm(base + '/' + encodeURIComponent(pendingDeleteId) + '/delete', {})
                    .then(function(json){
                        if (json && json.success) location.reload();
                        else alert(json && json.message ? json.message : 'Delete blocked');
                    });
            }
        }
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(){
            if (!pendingDeleteId) return;
            postForm(base + '/' + encodeURIComponent(pendingDeleteId) + '/delete', {})
                .then(function(json){
                    if (json && json.success) {
                        try { location.reload(); } catch (ex) {}
                    } else {
                        alert(json && json.message ? json.message : 'Delete blocked');
                    }
                })
                .catch(function(){ alert('Network error deleting attribute'); });
        });
    }
})();
</script>

<?= $this->endSection() ?>
