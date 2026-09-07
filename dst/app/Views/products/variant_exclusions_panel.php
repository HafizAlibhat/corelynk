<?php
/**
 * Variant Exclusion Rules Management UI
 * 
 * Embedded in: app/Views/products/form.php in the Attributes & Variants tab
 * 
 * Allows users to define rules like:
 * - "Exclude: Type=Billet AND Shape=Flat" (exclude specific combos)
 * - "Include only: Material=Aluminum" (restrict to only certain values)
 */
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom">
        <h5 class="card-title mb-0">
            <i class="bi bi-shield-exclamation me-2 text-warning"></i>
            Variant Exclusion Rules
        </h5>
        <small class="text-muted d-block mt-2">
            Define which attribute value combinations should <strong>not</strong> be generated as variants.
            <br>Example: "Flat Billet is only for Feather finish, exclude it for Twist and other finishes"
        </small>
    </div>
    <div class="card-body">
        <!-- Rules List Section -->
        <div id="exclusionRulesContainer" style="display:none;">
            <div class="mb-3">
                <h6 class="text-uppercase small fw-bold text-muted">Active Rules</h6>
                <div id="exclusionRulesList" class="list-group list-group-sm">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="exclusionEmptyState">
            <div class="alert alert-info mb-0">
                <i class="bi bi-lightbulb me-2"></i>
                No exclusion rules yet. Use the button below to create one.
            </div>
        </div>

        <!-- Add Rule Button -->
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-primary" id="addExclusionRuleBtn" data-product-id="<?= $product['id'] ?? 0 ?>">
                <i class="bi bi-plus-circle me-2"></i>Add Exclusion Rule
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Rule Modal -->
<div class="modal fade" id="exclusionRuleModal" tabindex="-1" aria-labelledby="exclusionRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title" id="exclusionRuleModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add Exclusion Rule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div id="exclusionRuleForm">
                <div class="modal-body">
                    <input type="hidden" id="exclusionRuleId" name="rule_id">
                    <input type="hidden" id="exclusionProductId" name="product_id">

                    <!-- Rule Name -->
                    <div class="mb-3">
                        <label for="exclusionRuleName" class="form-label">Rule Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="exclusionRuleName" name="name" 
                               placeholder="e.g., 'Flat Billet - Feather Only'" required maxlength="255">
                        <small class="text-muted">A descriptive name for this rule</small>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="exclusionRuleDesc" class="form-label">Description</label>
                        <textarea class="form-control" id="exclusionRuleDesc" name="description" 
                                  rows="2" maxlength="500" placeholder="Explain why this exclusion is needed"></textarea>
                    </div>

                    <!-- Rule Type -->
                    <div class="mb-3">
                        <label class="form-label">Rule Type <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="rule_type" id="exclusionTypeExclude" 
                                   value="exclude" checked>
                            <label class="btn btn-outline-danger" for="exclusionTypeExclude">
                                <i class="bi bi-x-circle me-1"></i>Exclude (Skip these combos)
                            </label>

                            <input type="radio" class="btn-check" name="rule_type" id="exclusionTypeInclude" 
                                   value="include">
                            <label class="btn btn-outline-success" for="exclusionTypeInclude">
                                <i class="bi bi-check-circle me-1"></i>Include Only (Allow only these)
                            </label>
                        </div>
                        <small class="d-block text-muted mt-2">
                            <strong>Exclude:</strong> Skip variants matching these conditions
                            <br><strong>Include:</strong> Create variants ONLY for these conditions
                        </small>
                    </div>

                    <!-- Conditions Section -->
                    <div class="mb-3">
                        <label class="form-label">Conditions (What to match)</label>
                        <small class="text-muted d-block mb-2">
                            All conditions below must be true for the rule to apply.
                        </small>

                        <div id="exclusionConditionsList" class="list-group list-group-sm mb-3">
                            <!-- Conditions populated by JS -->
                        </div>

                        <!-- Add Condition -->
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addConditionBtn">
                            <i class="bi bi-plus me-1"></i>Add Condition
                        </button>
                    </div>

                    <!-- Active Checkbox -->
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="exclusionIsActive" 
                               name="is_active" value="1" checked>
                        <label class="form-check-label" for="exclusionIsActive">
                            Activate this rule immediately
                        </label>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveExclusionRuleBtn" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Save Rule
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Condition Modal -->
<div class="modal fade" id="addConditionModal" tabindex="-1" aria-labelledby="addConditionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title" id="addConditionModalLabel">Add Condition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div id="addConditionForm">
                <input type="hidden" id="conditionRuleId" name="rule_id">

                <div class="modal-body">
                    <!-- Attribute Select -->
                    <div class="mb-3">
                        <label for="conditionAttribute" class="form-label">Attribute <span class="text-danger">*</span></label>
                        <select id="conditionAttribute" class="form-select" required>
                            <option value="">Select attribute...</option>
                        </select>
                        <small class="text-muted">Choose which attribute to match</small>
                    </div>

                    <!-- Attribute Value Select -->
                    <div class="mb-3">
                        <label for="conditionValue" class="form-label">Attribute Value</label>
                        <select id="conditionValue" class="form-select">
                            <option value="">Any value (just having this attribute)</option>
                        </select>
                        <small class="text-muted">Leave empty to match any value of the selected attribute</small>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveAddConditionBtn" class="btn btn-primary">Add Condition</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('addExclusionRuleBtn');
    if (!addBtn) return;

    const productId = parseInt(addBtn.getAttribute('data-product-id') || '0', 10);

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('exclusionRuleModal'));
    const conditionModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addConditionModal'));

    let currentRuleId = null;
    let currentConditions = [];
    let allAttributes = [];

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    async function loadAttributes() {
        try {
            const response = await fetch('<?= base_url('/product-attributes/list') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            allAttributes = data && data.success && Array.isArray(data.data) ? data.data : [];
        } catch (_) {
            allAttributes = [];
        }
    }

    async function loadRules() {
        try {
            const response = await fetch(`<?= base_url('/variant-exclusions?product_id=') ?>${productId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            updateRulesUI(data && data.success && Array.isArray(data.data) ? data.data : []);
        } catch (_) {
            updateRulesUI([]);
        }
    }

    function updateRulesUI(rules) {
        const container = document.getElementById('exclusionRulesContainer');
        const emptyState = document.getElementById('exclusionEmptyState');
        const list = document.getElementById('exclusionRulesList');
        list.innerHTML = '';

        if (!rules.length) {
            container.style.display = 'none';
            emptyState.style.display = '';
            return;
        }

        container.style.display = '';
        emptyState.style.display = 'none';

        rules.forEach(rule => {
            const isExclude = (rule.rule_type || 'exclude') === 'exclude';
            const statusBadge = rule.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
            const condCount = rule.condition_count || 0;
            const condText = condCount === 1 ? '1 condition' : `${condCount} conditions`;

            const item = document.createElement('div');
            item.className = `list-group-item list-group-item-action ${isExclude ? 'border-danger' : 'border-success'}`;
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${isExclude ? '❌' : '✓'} ${escapeHtml(rule.name || '')}</div>
                        <small class="text-muted d-block">${escapeHtml(rule.description || 'No description')}</small>
                        <small class="text-muted d-block">${escapeHtml(rule.condition_preview || condText)}</small>
                    </div>
                    <div class="text-end">
                        ${statusBadge}
                        <div class="btn-group btn-group-sm mt-2">
                            <button type="button" class="btn btn-outline-primary edit-rule" data-rule-id="${rule.id}" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-warning toggle-rule" data-rule-id="${rule.id}" title="Toggle"><i class="bi bi-toggle-${rule.is_active ? 'on text-success' : 'off'}"></i></button>
                            <button type="button" class="btn btn-outline-danger delete-rule" data-rule-id="${rule.id}" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            `;
            list.appendChild(item);
        });

        list.querySelectorAll('.edit-rule').forEach(btn => btn.addEventListener('click', () => editRule(parseInt(btn.dataset.ruleId || '0', 10))));
        list.querySelectorAll('.toggle-rule').forEach(btn => btn.addEventListener('click', () => toggleRule(parseInt(btn.dataset.ruleId || '0', 10))));
        list.querySelectorAll('.delete-rule').forEach(btn => btn.addEventListener('click', () => {
            if (confirm('Delete this rule?')) {
                deleteRule(parseInt(btn.dataset.ruleId || '0', 10));
            }
        }));
    }

    function renderConditions() {
        const container = document.getElementById('exclusionConditionsList');
        container.innerHTML = '';
        if (!currentConditions.length) {
            container.innerHTML = '<small class="text-muted">No conditions added yet</small>';
            return;
        }

        currentConditions.forEach((cond, idx) => {
            const row = document.createElement('div');
            row.className = 'list-group-item';
            row.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div><strong>${escapeHtml(cond.attribute_name || 'Unknown')}</strong> = <span class="text-muted">${escapeHtml(cond.attribute_value_name || '(any)')}</span></div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-condition" data-idx="${idx}"><i class="bi bi-trash"></i></button>
                </div>
            `;
            container.appendChild(row);
        });

        container.querySelectorAll('.remove-condition').forEach(btn => btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const idx = parseInt(this.dataset.idx || '-1', 10);
            if (idx < 0 || !currentConditions[idx]) return;
            const cond = currentConditions[idx];

            if (cond.id && !String(cond.id).startsWith('tmp_')) {
                const response = await fetch(`<?= base_url('/variant-exclusions/conditions/') ?>${cond.id}`, {
                    method: 'DELETE',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const result = await response.json().catch(() => ({}));
                if (!result.success) {
                    alert(result.message || 'Failed to remove condition');
                    return;
                }
            }

            currentConditions.splice(idx, 1);
            renderConditions();
        }));
    }

    async function editRule(ruleId) {
        if (!ruleId) return;
        const response = await fetch(`<?= base_url('/variant-exclusions/') ?>${ruleId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await response.json().catch(() => ({}));
        if (!data.success || !data.data) {
            alert('Failed to load rule');
            return;
        }

        const rule = data.data;
        currentRuleId = ruleId;
        currentConditions = Array.isArray(rule.conditions) ? rule.conditions : [];

        document.getElementById('exclusionRuleId').value = String(ruleId);
        document.getElementById('exclusionProductId').value = String(productId);
        document.getElementById('exclusionRuleName').value = rule.name || '';
        document.getElementById('exclusionRuleDesc').value = rule.description || '';
        document.querySelector(`input[name="rule_type"][value="${rule.rule_type || 'exclude'}"]`).checked = true;
        document.getElementById('exclusionIsActive').checked = !!Number(rule.is_active);

        renderConditions();
        document.getElementById('exclusionRuleModalLabel').textContent = 'Edit Exclusion Rule';
        modal.show();
    }

    addBtn.addEventListener('click', function() {
        // Manually clear fields (no native form.reset() since element is a div to avoid nested-form issues)
        document.getElementById('exclusionRuleName').value = '';
        document.getElementById('exclusionRuleDesc').value = '';
        const excTypeExclude = document.getElementById('exclusionTypeExclude');
        if (excTypeExclude) excTypeExclude.checked = true;
        const excIsActive = document.getElementById('exclusionIsActive');
        if (excIsActive) excIsActive.checked = true;
        document.getElementById('exclusionRuleId').value = '';
        document.getElementById('exclusionProductId').value = String(productId);
        currentRuleId = null;
        currentConditions = [];
        renderConditions();
        document.getElementById('exclusionRuleModalLabel').textContent = 'Add Exclusion Rule';
        modal.show();
    });

    document.getElementById('addConditionBtn').addEventListener('click', function(e) {
        e.preventDefault();
        const attrSelect = document.getElementById('conditionAttribute');
        const valueSelect = document.getElementById('conditionValue');
        attrSelect.innerHTML = '<option value="">Select attribute...</option>';
        allAttributes.forEach(attr => {
            const option = document.createElement('option');
            option.value = String(attr.id);
            option.textContent = attr.name || '';
            attrSelect.appendChild(option);
        });
        valueSelect.innerHTML = '<option value="">Any value (just having this attribute)</option>';
        conditionModal.show();
    });

    document.getElementById('conditionAttribute').addEventListener('change', async function() {
        const attrId = parseInt(this.value || '0', 10);
        const valueSelect = document.getElementById('conditionValue');
        valueSelect.innerHTML = '<option value="">Any value (just having this attribute)</option>';
        if (!attrId) return;

        const response = await fetch(`<?= base_url('/variant-exclusions/attribute-values/') ?>${attrId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await response.json().catch(() => ({}));
        const values = data && data.success && Array.isArray(data.data) ? data.data : [];
        values.forEach(v => {
            const option = document.createElement('option');
            option.value = String(v.id || '');
            option.textContent = v.name || '';
            valueSelect.appendChild(option);
        });
    });

    document.getElementById('saveAddConditionBtn').addEventListener('click', function() {
        const attrSelect = document.getElementById('conditionAttribute');
        const valueSelect = document.getElementById('conditionValue');
        const attributeId = parseInt(attrSelect.value || '0', 10);
        if (!attributeId) {
            alert('Select an attribute');
            return;
        }

        const attributeName = attrSelect.options[attrSelect.selectedIndex]?.text || `Attr#${attributeId}`;
        const valueIdRaw = (valueSelect.value || '').trim();
        const valueName = valueIdRaw !== '' ? (valueSelect.options[valueSelect.selectedIndex]?.text || '') : null;

        currentConditions.push({
            id: `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
            attribute_id: attributeId,
            attribute_name: attributeName,
            attribute_value_id: valueIdRaw !== '' ? parseInt(valueIdRaw, 10) : null,
            attribute_value_name: valueName,
        });

        renderConditions();
        conditionModal.hide();
        attrSelect.value = '';
        document.getElementById('conditionValue').innerHTML = '<option value="">Any value (just having this attribute)</option>';
    });

    document.getElementById('saveExclusionRuleBtn').addEventListener('click', async function() {

        const ruleId = (document.getElementById('exclusionRuleId').value || '').trim();
        const isEdit = ruleId !== '';
        if (!productId) {
            alert('Please save the product first before adding exclusion rules.');
            return;
        }
        const payload = {
            product_id: productId,
            name: document.getElementById('exclusionRuleName').value.trim(),
            description: document.getElementById('exclusionRuleDesc').value.trim(),
            rule_type: document.querySelector('input[name="rule_type"]:checked').value,
            is_active: document.getElementById('exclusionIsActive').checked ? 1 : 0,
        };

        const response = await fetch(isEdit ? `<?= base_url('/variant-exclusions/') ?>${ruleId}` : '<?= base_url('/variant-exclusions') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (!result.success) {
            alert(result.message || 'Failed to save rule');
            return;
        }

        const savedRuleId = parseInt((result.data && result.data.id) ? result.data.id : ruleId, 10);

        for (const cond of currentConditions) {
            if (!String(cond.id || '').startsWith('tmp_')) continue;
            await fetch(`<?= base_url('/variant-exclusions/') ?>${savedRuleId}/conditions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    attribute_id: cond.attribute_id,
                    attribute_value_id: cond.attribute_value_id,
                    attribute_value_name: cond.attribute_value_name,
                }),
            });
        }

        modal.hide();
        await loadRules();
    });

    async function toggleRule(ruleId) {
        if (!ruleId) return;
        const response = await fetch(`<?= base_url('/variant-exclusions/') ?>${ruleId}/toggle`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const result = await response.json().catch(() => ({}));
        if (!result.success) {
            alert(result.message || 'Failed to toggle rule');
            return;
        }
        await loadRules();
    }

    async function deleteRule(ruleId) {
        if (!ruleId) return;
        const response = await fetch(`<?= base_url('/variant-exclusions/') ?>${ruleId}`, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const result = await response.json().catch(() => ({}));
        if (!result.success) {
            alert(result.message || 'Failed to delete rule');
            return;
        }
        await loadRules();
    }

    loadAttributes().then(() => { if (productId) loadRules(); });
});
</script>
