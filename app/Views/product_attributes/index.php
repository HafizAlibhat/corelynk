<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-list-ul me-2"></i>Global Attributes</h2>
            <div class="d-flex gap-2">
                <a href="<?= base_url('product-attributes/create?modal=1') ?>" class="btn btn-sm btn-primary open-remote-modal">Add Attribute</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($attributes)): ?>
                    <div class="alert alert-info mb-0">No attributes yet. Use "Add Attribute" to create one.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:20%">Name</th>
                                    <th style="width:70%">Values</th>
                                    <th style="width:10%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($attributes as $a): ?>
                                <?php $values = json_decode($a['values'] ?? '[]', true) ?? []; ?>
                                <tr id="attribute-row-<?= esc($a['id']) ?>" data-attribute-id="<?= esc($a['id']) ?>" data-attribute-name="<?= esc($a['name']) ?>">
                                    <td class="fw-semibold"><?= esc($a['name']) ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <?php if (empty($values)): ?>
                                                <span class="text-muted"><em>No values</em></span>
                                            <?php else: ?>
                                                <?php foreach ($values as $val): ?>
                                                    <span class="badge bg-light text-dark attr-value-badge" data-attribute-id="<?= esc($a['id']) ?>" data-value="<?= esc($val) ?>" title="Click to edit">
                                                        <span class="value-text" style="cursor:pointer;"><?= esc($val) ?></span>
                                                        <a href="#" class="ms-1 text-decoration-none text-danger value-delete" title="Delete value">&times;</a>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-outline-primary btn-sm btn-add-value" data-id="<?= esc($a['id']) ?>">+ Value</button>
                                        </div>
                                    </td>
                                    <td class="text-end">
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
