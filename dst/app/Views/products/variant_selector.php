<?php
// Reusable partial to render an attribute-based radio selector for a product
// Usage: include('products/variant_selector', ['product_id' => $product['id'], 'attributes_definitions' => $product['attributes_definitions']])
// It renders attribute radio groups and exposes a hidden input `selected_variant_id`.

$productId = $product_id ?? ($product['id'] ?? null);
$attrsJson = $attributes_definitions ?? ($product['attributes_definitions'] ?? '[]');
?>

<div class="variant-selector" data-product-id="<?= esc($productId) ?>">
    <div id="variantAttributeGroups"></div>
    <input type="hidden" name="selected_variant_id" id="selected_variant_id" value="">
    <div class="mt-2 small text-muted" id="variantSelectionInfo">Select options to pick a variant.</div>
</div>

<script>
(function(){
    const wrapper = document.querySelector('.variant-selector[data-product-id="<?= $productId ?>"]');
    if (!wrapper) return;
    const productId = wrapper.getAttribute('data-product-id');
    const groupsContainer = wrapper.querySelector('#variantAttributeGroups');
    const infoEl = wrapper.querySelector('#variantSelectionInfo');
    const selectedInput = wrapper.querySelector('#selected_variant_id');

    let attributeDefs = [];
    try { attributeDefs = JSON.parse('<?= addslashes($attrsJson) ?>' || '[]'); } catch (e) { attributeDefs = []; }

    // If product has no saved attribute defs, attempt to fetch via API (the product may be variable and saved differently)
    function renderGroups(defs) {
        groupsContainer.innerHTML = '';
        defs.forEach((def, idx) => {
            const name = def.name || ('Attr' + idx);
            const vals = def.values || [];
            const group = document.createElement('div');
            group.className = 'mb-2';
            let html = `<label class="form-label small mb-1">${escapeHtml(name)}</label><div>`;
            vals.forEach((v,i) => {
                const id = `attr_${idx}_${i}`;
                html += `<div class="form-check form-check-inline me-2"><input class="form-check-input variant-attr" type="radio" name="attr_${idx}" id="${id}" value="${escapeHtml(v)}" data-attr-name="${escapeHtml(name)}"><label class="form-check-label" for="${id}">${escapeHtml(v)}</label></div>`;
            });
            html += '</div>';
            group.innerHTML = html;
            groupsContainer.appendChild(group);
        });

        // listen for changes
        groupsContainer.querySelectorAll('.variant-attr').forEach(el => el.addEventListener('change', onSelectionChange));
    }

    // fetch variants for product
    let variants = [];
    function loadVariants() {
        fetch('<?= base_url('/product-variants/api/') ?>' + encodeURIComponent(productId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    variants = d.variants || [];
                } else {
                    variants = [];
                }
            }).catch(err => { variants = []; console.warn('Failed to load variants', err); });
    }

    function onSelectionChange() {
        // collect selected values per attribute name
        const selected = {};
        const radios = groupsContainer.querySelectorAll('input.variant-attr');
        radios.forEach(r => {
            if (r.checked) {
                const an = r.getAttribute('data-attr-name');
                selected[an] = r.value;
            }
        });

        // if not all groups selected, clear selection
        const expectedGroups = attributeDefs.length;
        const selectedCount = Object.keys(selected).length;
        if (selectedCount < expectedGroups) {
            selectedInput.value = '';
            infoEl.textContent = 'Please select all attributes to pick a variant.';
            return;
        }

        // find matching variant by attributes map
        const found = variants.find(v => {
            const a = v.attributes || {};
            // all keys in selected must match exactly
            return Object.keys(selected).every(k => (a[k] || '') === (selected[k] || ''));
        });

        if (found) {
            selectedInput.value = found.id;
            infoEl.innerHTML = `Selected variant: <strong>${escapeHtml(found.name || found.art_number || found.id)}</strong> &nbsp; <span class="text-muted">Price: ${found.price || '—'}</span>`;
        } else {
            selectedInput.value = '';
            infoEl.textContent = 'No variant matches the selected combination.';
        }
    }

    function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/[&<>"'`]/g, function (t) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[t]; }); }

    // init
    if (attributeDefs && attributeDefs.length) {
        renderGroups(attributeDefs);
        loadVariants();
    } else {
        // no defs inlined - show notice
        groupsContainer.innerHTML = '<div class="alert alert-warning small">No attribute definitions available for this product.</div>';
    }
})();
</script>
