<div class="modal fade" id="tagManageModal" tabindex="-1" aria-labelledby="tagManageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tagManageModalTitle"><i class="bi bi-tags me-2" aria-hidden="true"></i>Manage Tags</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tagManageDocType" value="">
                <input type="hidden" id="tagManageDocId" value="">
                <div class="mb-2">
                    <label class="form-label" for="tagManageInput">Add tag</label>
                    <div class="input-group">
                        <input id="tagManageInput" class="form-control" placeholder="Search or type to add tag" autocomplete="off" aria-controls="tagManageSuggestions">
                        <button id="tagManageAddBtn" class="btn btn-outline-secondary" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add</button>
                    </div>
                    <div id="tagManageSuggestions" class="tag-suggestions mt-2 d-flex flex-wrap gap-2" hidden></div>
                </div>

                <div>
                    <div class="form-label">Assigned tags</div>
                    <div id="tagManageList" class="tag-list d-flex flex-wrap gap-2" role="status" aria-live="polite">
                        <div class="text-muted">Loading…</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button id="tagManageSave" type="button" class="btn btn-primary">Save tags</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modalEl = document.getElementById('tagManageModal');
    if (!modalEl) return;
    const bsModal = new bootstrap.Modal(modalEl);
    const input = document.getElementById('tagManageInput');
    const addBtn = document.getElementById('tagManageAddBtn');
    const suggestions = document.getElementById('tagManageSuggestions');
    const listEl = document.getElementById('tagManageList');
    const saveBtn = document.getElementById('tagManageSave');
    const docTypeEl = document.getElementById('tagManageDocType');
    const docIdEl = document.getElementById('tagManageDocId');

    let currentTags = [];

    function renderList(){
        listEl.innerHTML = '';
        if (!currentTags.length) {
            listEl.innerHTML = '<div class="text-muted">No tags assigned.</div>';
            return;
        }
        currentTags.forEach(function(t){
            const span = document.createElement('span');
            span.className = 'badge bg-light text-dark tag-badge';
            const label = document.createElement('span');
            label.textContent = t.name;
            span.appendChild(label);
            if (t.id) {
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn-close btn-close-sm ms-1 tag-manage-remove';
                removeButton.dataset.tagName = t.name;
                removeButton.dataset.tagId = t.id || 0;
                removeButton.setAttribute('aria-label', 'Remove ' + t.name + ' tag');
                span.appendChild(removeButton);
            }
            listEl.appendChild(span);
        });
    }

    function loadTags(docType, docId){
        listEl.innerHTML = '<div class="text-muted">Loading...</div>';
        listEl.setAttribute('aria-busy', 'true');
        fetch('<?= site_url('document-tags') ?>?document_type=' + encodeURIComponent(docType) + '&document_id=' + encodeURIComponent(docId))
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    currentTags = data.data.map(function(t){ return { id: t.id, name: t.name }; });
                } else {
                    currentTags = [];
                }
                listEl.setAttribute('aria-busy', 'false');
                renderList();
            }).catch(function(){ currentTags = []; listEl.setAttribute('aria-busy', 'false'); renderList(); });
    }

    function loadSuggestions(q){
        suggestions.hidden = true;
        suggestions.innerHTML = '';
        if (!q || q.length < 1) return;
        fetch('<?= site_url('tags') ?>?q=' + encodeURIComponent(q) + '&limit=6')
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success || !data.data.length) return;
                suggestions.innerHTML = '';
                data.data.forEach(function(t){
                    const btn = document.createElement('button');
                    btn.className = 'btn btn-sm btn-outline-secondary tag-suggest';
                    btn.type = 'button';
                    btn.textContent = t.name + (t.usage_count ? ' ('+ t.usage_count +')' : '');
                    btn.addEventListener('click', function(){
                        addLocalTag(t.name);
                    });
                    suggestions.appendChild(btn);
                });
                suggestions.hidden = false;
            }).catch(()=>{});
    }

    function addLocalTag(name){
        name = name.trim();
        if (!name) return;
        // avoid duplicates
        if (currentTags.some(t => t.name.toLowerCase() === name.toLowerCase())) return;
        currentTags.push({ id: 0, name: name });
        renderList();
    }

    document.addEventListener('click', function(e){
        const target = e.target.closest ? e.target.closest('.btn-manage-tags') : null;
        if (!target) return;
        const docType = target.dataset.docType;
        const docId = target.dataset.docId;
        if (!docType || !docId) return;
        docTypeEl.value = docType;
        docIdEl.value = docId;
        input.value = '';
        suggestions.hidden = true;
        loadTags(docType, docId);
        bsModal.show();
    });

    input.addEventListener('input', function(){ loadSuggestions(this.value.trim()); });
    addBtn.addEventListener('click', function(){ addLocalTag(input.value.trim()); input.value = ''; suggestions.hidden = true; });

    listEl.addEventListener('click', function(e){
        if (e.target && e.target.classList && e.target.classList.contains('tag-manage-remove')){
            const name = e.target.dataset.tagName;
            currentTags = currentTags.filter(function(t){ return t.name.toLowerCase() !== (name||'').toLowerCase(); });
            renderList();
        }
    });

    saveBtn.addEventListener('click', function(){
        const docType = docTypeEl.value;
        const docId = docIdEl.value;
        const names = currentTags.map(t => t.name);
        saveBtn.disabled = true;
        fetch('<?= site_url('document-tags') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ document_type: docType, document_id: parseInt(docId||0,10), tags: names })
        }).then(r => r.json()).then(data => {
            saveBtn.disabled = false;
            if (data && data.success) {
                // update any list row display for this doc
                document.querySelectorAll('.doc-tags[data-doc-type="' + docType + '"][data-doc-id="' + docId + '"]').forEach(function(td){
                    td.innerHTML = '';
                    data.data.forEach(function(tag){
                        var span = document.createElement('span');
                        span.className = 'badge bg-light text-dark me-1';
                        span.textContent = tag.name;
                        td.appendChild(span);
                    });
                });
                bsModal.hide();
            } else {
                alert('Failed to save tags: ' + (data && data.message ? data.message : 'Unknown'));
            }
        }).catch(function(err){ saveBtn.disabled = false; alert('Error saving tags'); });
    });
});
</script>
