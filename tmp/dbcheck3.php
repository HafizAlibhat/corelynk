<?php
require __DIR__ . '/../vendor/autoload.php';
// Bootstrap CI4
$_SERVER['CI_ENVIRONMENT'] = 'development';
define('FCPATH', dirname(__DIR__) . '/public/');
define('ROOTPATH', dirname(__DIR__) . '/');

$output = [];
try {
    $pdo = new PDO('mysql:host=localhost;dbname=corelynk_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simulate what getMatchingVariantsByProductIds does for product IDs 9, 22, 23
    // with attributeFilters = [['name'=>'Shape','value'=>'Curved'],['name'=>'Size','value'=>'20cm']]
    
    $productIds = [9, 22, 23];
    $attributeFilters = [
        ['name' => 'Shape', 'value' => 'Curved'],
        ['name' => 'Size', 'value' => '20cm'],
    ];
    
    // Build the query manually like the model does
    $idList = implode(',', $productIds);
    
    $conditions = [];
    foreach ($attributeFilters as $filter) {
        $nl = strtolower($filter['name']);
        $vl = strtolower($filter['value']);
        $conditions[] = "LOWER(pv.attributes) LIKE '%{$nl}%'";
        $conditions[] = "LOWER(pv.attributes) LIKE '%{$vl}%'";
    }
    $condStr = implode(' AND ', $conditions);
    $sql = "SELECT pv.id, pv.product_id, pv.art_number, pv.name, pv.attributes FROM product_variants pv WHERE pv.product_id IN ({$idList}) AND {$condStr} ORDER BY pv.product_id, pv.id LIMIT 50";
    
    $output[] = "SQL: $sql";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $output[] = "Found: " . count($rows) . " matching variants";
    foreach ($rows as $r) {
        $output[] = "P#{$r['product_id']} | V#{$r['id']} | {$r['art_number']} | {$r['attributes']}";
    }

    // Also check what searchTerm "tweezer" would add
    $output[] = "\n--- With search term 'tweezer' too ---";
    $sql2 = "SELECT pv.id, pv.product_id, pv.art_number, pv.name, pv.attributes FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.product_id IN ({$idList}) AND (pv.art_number LIKE '%tweezer%' OR pv.name LIKE '%tweezer%' OR LOWER(pv.attributes) LIKE '%tweezer%') AND {$condStr} ORDER BY pv.product_id, pv.id LIMIT 20";
    $rows2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
    $output[] = "Found with search: " . count($rows2);
    
    // The search term "tweezer" also exists - does it match any variant directly?
    $sql3 = "SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id IN ({$idList}) AND (pv.art_number LIKE '%tweezer%' OR pv.name LIKE '%tweezer%' OR LOWER(pv.attributes) LIKE '%tweezer%')";
    $cnt = $pdo->query($sql3)->fetchColumn();
    $output[] = "Variants matching 'tweezer' search: $cnt";
    
} catch (Exception $e) {
    $output[] = "ERROR: " . $e->getMessage();
}

file_put_contents(__DIR__ . '/dbcheck3_result.txt', implode("\n", $output) . "\n");
echo "Written to dbcheck3_result.txt\n";
