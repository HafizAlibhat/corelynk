import re

# Read the current file
with open('app/Views/products/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update pagination parameters section
old_pgParams = """    $pgParams  = array_filter([
        'search' => $current_search ?? '',
        'tag' => $current_tag ?? '',
        'attr_name' => $current_attr_name ?? '',
        'attr_value' => $current_attr_value ?? '',
        'category' => $current_category ?? '',
        'status' => $current_status ?? '',
        'type' => $current_type ?? '',
        'per_page' => $per_page ?? 20
    ], fn($v) => $v !== '' && $v !== null);
    $pgBase    = base_url('/products') . '?' . http_build_query($pgParams) . '&page=';
    $attributeOptions = $attribute_options ?? [];"""

new_pgParams = """    // Build pagination params with multiple attribute filters
    $pgParams  = array_filter([
        'search' => $current_search ?? '',
        'category' => $current_category ?? '',
        'status' => $current_status ?? '',
        'type' => $current_type ?? '',
        'per_page' => $per_page ?? 20
    ], fn($v) => $v !== '' && $v !== null);
    
    // Add attribute filters to pagination params (attr[0][name], attr[0][value], attr[1][name]...)
    $currentAttributes = $current_attributes ?? [];
    foreach ($currentAttributes as $idx => $attr) {
        $pgParams["attr[{$idx}][name]"] = $attr['name'] ?? '';
        $pgParams["attr[{$idx}][value]"] = $attr['value'] ?? '';
    }
    
    $pgBase    = base_url('/products') . '?' . http_build_query($pgParams) . '&page=';
    $attributeOptions = $attribute_options ?? [];"""

content = content.replace(old_pgParams, new_pgParams)

# 2. Remove the tag input from filter bar
old_tag_line = '        <input type="text" class="form-control pl-tag" name="tag" value="<?= esc($current_tag ?? \'\') ?>" placeholder="Tag / keyword (e.g. tweezers)">\n'
content = content.replace(old_tag_line, '')

# 3. Replace the old single attribute selector with new multi-selector UI
old_attr_section = """        <select class="form-select pl-attr-name" id="plAttrName" name="attr_name">
            <option value="">Attribute (e.g. Color)</option>
            <?php foreach ($attributeOptions as $attrName => $attrValues): ?>
                <option value="<?= esc($attrName) ?>" <?= (($current_attr_name ?? '') === $attrName) ? 'selected' : '' ?>><?= esc($attrName) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" class="form-control pl-attr-value" id="plAttrValue" name="attr_value" list="plAttrValueList" value="<?= esc($current_attr_value ?? '') ?>" placeholder="Attribute value (guided)">
        <datalist id="plAttrValueList"></datalist>"""

new_attr_section = """        <!-- Multi-Attribute Filter Section -->
        <div class="pl-attr-filter-group">
            <select class="form-select pl-attr-name-sel" id="plAttrNameSel">
                <option value="">Select Attribute</option>
                <?php foreach ($attributeOptions as $attrName => $attrValues): ?>
                    <option value="<?= esc($attrName) ?>"><?= esc($attrName) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control pl-attr-value-inp" id="plAttrValueInp" list="plAttrValueSuggestions" placeholder="Select value">
            <datalist id="plAttrValueSuggestions"></datalist>
            <button type="button" class="pl-btn" id="plAttrAddBtn" title="Add attribute filter"><i class="bi bi-plus-lg"></i></button>
        </div>
        <!-- Selected Attribute Tags -->
        <div class="pl-attr-tags-container" id="plAttrTagsContainer"></div>
        <!-- Hidden inputs for selected attributes (populate on form submit) -->
        <div id="plAttrHiddenInputs"></div>"""

content = content.replace(old_attr_section, new_attr_section)

# Write the updated file
with open('app/Views/products/index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("✓ Updated pagination parameters")
print("✓ Removed redundant tag input")
print("✓ Updated attribute filter UI to multi-select")
