<?php
// Update products view for multi-attribute support

$filePath = __DIR__ . '/app/Views/products/index.php';
$content = file_get_contents($filePath);

// 1. Update pagination parameters
$old = <<<'EOD'
    $pgParams  = array_filter([
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
    $attributeOptions = $attribute_options ?? [];
EOD;

$new = <<<'EOD'
    // Build pagination params with multiple attribute filters
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
    $attributeOptions = $attribute_options ?? [];
EOD;

$content = str_replace($old, $new, $content);

// 2. Remove tag input line
$content = str_replace('        <input type="text" class="form-control pl-tag" name="tag" value="<?= esc($current_tag ?? \'\') ?>" placeholder="Tag / keyword (e.g. tweezers)">' . "\n", '', $content);

file_put_contents($filePath, $content);

echo "✓ 1. Updated pagination parameters\n";
echo "✓ 2. Removed tag input from filter bar\n";
?>
