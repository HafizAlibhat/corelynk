<?php
/**
 * Reusable tag input component
 * Usage: <?= $this->include('components/tag_input', [
 *     'document_type' => 'quotation',
 *     'document_id' => $quote['id'] ?? 0,
 *     'existing_tags' => $tags ?? [],
 *     'readonly' => false,
 * ]) ?>
 */
$document_type = $document_type ?? '';
$document_id = (int)($document_id ?? 0);
$existing_tags = $existing_tags ?? [];
$readonly = $readonly ?? false;
?>
<?php if ($document_type && $document_id > 0): ?>
<div id="document-tags-<?= esc($document_type) ?>-<?= (int)$document_id ?>" class="tag-input-container mb-3">
    <label class="form-label" for="tag_search_<?= esc($document_type) ?>_<?= $document_id ?>">
        Tags <small class="text-muted">Organize this document by topic</small>
    </label>
    <div class="input-group">
        <input type="text" 
            id="tag_search_<?= esc($document_type) ?>_<?= $document_id ?>" 
            class="form-control tag-search-input" 
            placeholder="Search or add tags..." 
            autocomplete="off"
            aria-controls="tag_suggestions_<?= esc($document_type) ?>_<?= $document_id ?>"
            data-document-type="<?= esc($document_type) ?>"
            data-document-id="<?= (int)$document_id ?>"
            <?= $readonly ? 'disabled' : '' ?>>
        <button type="button" 
            class="btn btn-outline-secondary tag-add-btn" 
            data-document-type="<?= esc($document_type) ?>"
            data-document-id="<?= (int)$document_id ?>"
            <?= $readonly ? 'disabled' : '' ?>>
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add
        </button>
    </div>
    <div id="tag_list_<?= esc($document_type) ?>_<?= $document_id ?>" class="tag-list mt-2 d-flex flex-wrap gap-2" role="status" aria-live="polite">
        <?php foreach ($existing_tags as $tag): ?>
            <span class="badge bg-light text-dark tag-badge" data-tag-id="<?= (int)$tag['id'] ?>" data-tag-name="<?= esc($tag['name']) ?>">
                <?= esc($tag['name']) ?>
                <?php if (!$readonly): ?>
                    <button type="button" class="btn-close btn-close-sm ms-1 tag-remove-btn" data-tag-id="<?= (int)$tag['id'] ?>" aria-label="Remove <?= esc($tag['name']) ?> tag"></button>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="tag-suggestions mt-2" id="tag_suggestions_<?= esc($document_type) ?>_<?= $document_id ?>" hidden>
        <small class="text-muted d-block mb-1">Suggestions</small>
        <div id="tag_suggestions_list_<?= esc($document_type) ?>_<?= $document_id ?>" class="d-flex flex-wrap gap-2"></div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning py-2 mb-3">
    Tag controls are unavailable because the document ID is not available.
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const docType = '<?= esc($document_type) ?>';
    const docId = <?= (int)$document_id ?>;

    if (!docType || !docId) return;

    const searchInput = document.getElementById('tag_search_' + docType + '_' + docId);
    const addBtn = document.querySelector('[data-document-type="' + docType + '"][data-document-id="' + docId + '"].tag-add-btn');
    const tagList = document.getElementById('tag_list_' + docType + '_' + docId);

    if (!searchInput || !addBtn) return;

    // Handle add button click
    addBtn.addEventListener('click', function() {
        const tagName = searchInput.value.trim();
        if (tagName) {
            addTag(tagName, docType, docId);
            searchInput.value = '';
            loadSuggestions(docType, docId);
        }
    });

    // Handle enter key in search input
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addBtn.click();
        }
    });

    // Handle remove button clicks
    tagList.addEventListener('click', function(e) {
        if (e.target.classList.contains('tag-remove-btn')) {
            const tagId = e.target.dataset.tagId;
            removeTag(tagId, docType, docId);
        }
    });

    // Load suggestions on input
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length > 0) {
            loadSuggestions(docType, docId, query);
        } else {
            document.getElementById('tag_suggestions_' + docType + '_' + docId).hidden = true;
        }
    });

    function addTag(tagName, docType, docId) {
        fetch('<?= site_url('document-tags') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_type: docType,
                document_id: docId,
                tags: [tagName],
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    reloadTags(docType, docId);
                } else {
                    alert('Failed to add tag: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error adding tag:', err);
                alert('Error adding tag');
            });
    }

    function removeTag(tagId, docType, docId) {
        fetch('<?= site_url('document-tags') ?>', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_type: docType,
                document_id: docId,
                tag_id: tagId,
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    reloadTags(docType, docId);
                } else {
                    alert('Failed to remove tag: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error removing tag:', err);
                alert('Error removing tag');
            });
    }

    function reloadTags(docType, docId) {
        fetch('<?= site_url('document-tags') ?>?document_type=' + docType + '&document_id=' + docId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const tagList = document.getElementById('tag_list_' + docType + '_' + docId);
                    tagList.innerHTML = '';
                    data.data.forEach(tag => {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light text-dark tag-badge';
                        badge.dataset.tagId = tag.id;
                        badge.dataset.tagName = tag.name;
                        const label = document.createElement('span');
                        label.textContent = tag.name;
                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'btn-close btn-close-sm ms-1 tag-remove-btn';
                        removeButton.dataset.tagId = tag.id;
                        removeButton.setAttribute('aria-label', 'Remove ' + tag.name + ' tag');
                        badge.appendChild(label);
                        badge.appendChild(removeButton);
                        tagList.appendChild(badge);
                    });
                }
            });
    }

    function loadSuggestions(docType, docId, query = '') {
        const suggestContainer = document.getElementById('tag_suggestions_' + docType + '_' + docId);
        const suggestionsList = document.getElementById('tag_suggestions_list_' + docType + '_' + docId);

        fetch('<?= site_url('tags') ?>?q=' + encodeURIComponent(query) + '&limit=5')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    suggestionsList.innerHTML = '';
                    data.data.forEach(tag => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'tag-suggestion-item';
                        item.textContent = tag.name + ' (' + tag.usage_count + ')';
                        item.addEventListener('click', function() {
                            addTag(tag.name, docType, docId);
                        });
                        suggestionsList.appendChild(item);
                    });
                    suggestContainer.hidden = false;
                } else {
                    suggestContainer.hidden = true;
                }
            })
            .catch(err => {
                console.error('Error loading suggestions:', err);
                suggestContainer.hidden = true;
            });
    }
});
</script>
