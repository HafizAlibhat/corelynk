<?php $isEdit = isset($attribute) && is_array($attribute) && !empty($attribute['id']); ?>

<form id="attributeForm" data-action="<?= $isEdit ? base_url('/product-attributes/' . (int)$attribute['id'] . '/update') : base_url('/product-attributes/store') ?>">
    <div class="mb-3">
        <label class="form-label">Attribute Name</label>
        <input type="text" name="name" class="form-control" value="<?= esc($isEdit ? ($attribute['name'] ?? '') : '') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label"><?= $isEdit ? 'Add New Values (optional)' : 'Values (add one per row)' ?></label>
        <div id="valuesList">
            <div class="input-group mb-2 value-row">
                <input type="text" name="values[]" class="form-control" placeholder="Enter value">
                <button type="button" class="btn btn-outline-danger remove-value-btn">Remove</button>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="addValueBtn" class="btn btn-sm btn-outline-primary">Add Value</button>
            <div class="form-text">
                <?= $isEdit ? 'Existing values are managed on the list screen. Here you can append new values.' : 'Click Add Value to add multiple values. Values are saved separately.' ?>
            </div>
        </div>
    </div>
    <div class="text-end">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Attribute' : 'Save Attribute' ?></button>
    </div>
</form>

<script>
// Initialize attribute form immediately. When this view is loaded via AJAX the
// DOMContentLoaded event may have already fired, so run the setup right away and
// guard presence of elements.
(function initAttributeForm(){
    const form = document.getElementById('attributeForm');
    if (!form) return; // nothing to do

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const data = new URLSearchParams(new FormData(form));
        const action = form.getAttribute('data-action') || '<?= base_url('/product-attributes/store') ?>';
        fetch(action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(r=>r.json()).then(d=>{
            if (d && d.success) {
                // close modal and refresh attribute selects if present
                const modalEl = document.querySelector('.modal.show');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    try { bootstrap.Modal.getInstance(modalEl).hide(); } catch(e){}
                }
                // If product form has a refresh function, call it
                if (window.refreshGlobalAttributes) window.refreshGlobalAttributes();
                // On the attributes list page, reload to show updated name/values
                try { if (location && location.reload) location.reload(); } catch(e){}
            } else {
                alert('Failed to save attribute: ' + (d && d.message ? d.message : 'Unknown'));
            }
        }).catch(err=>{
            console.error(err); alert('Request failed');
        });
    });

    // value row management
    const valuesList = document.getElementById('valuesList');
    const addValueBtn = document.getElementById('addValueBtn');
    if (addValueBtn && valuesList) {
        addValueBtn.addEventListener('click', function(){
            const row = document.createElement('div');
            row.className = 'input-group mb-2 value-row';
            row.innerHTML = '<input type="text" name="values[]" class="form-control" placeholder="Enter value"> <button type="button" class="btn btn-outline-danger remove-value-btn">Remove</button>';
            valuesList.appendChild(row);
            // focus the new input for convenience
            const input = row.querySelector('input'); if (input) input.focus();
        });

        valuesList.addEventListener('click', function(e){
            if (e.target && e.target.matches('.remove-value-btn')) {
                const row = e.target.closest('.value-row');
                if (row) row.remove();
            }
        });
    }
})();
</script>
