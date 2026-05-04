<?php
// Backfill global product attributes from existing products and variants.
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

function norm_attr_name(string $value): string {
    return mb_strtolower(trim($value));
}

function parse_defs($raw): array {
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($raw) ? $raw : [];
}

$existing = [];
$res = $mysqli->query("SELECT id, name, `values` FROM product_attributes");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            $existing[norm_attr_name($name)] = $row;
        }
    }
    $res->free();
}

$defsToSync = [];
$products = $mysqli->query("SELECT id, name, attributes_definitions FROM products");
if ($products) {
    while ($product = $products->fetch_assoc()) {
        foreach (parse_defs($product['attributes_definitions'] ?? '[]') as $def) {
            $name = trim((string)($def['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $defsToSync[norm_attr_name($name)] = [
                'name' => $name,
                'values' => array_values(array_filter(array_map('trim', (array)($def['values'] ?? []))))
            ];
        }
    }
    $products->free();
}

$variants = $mysqli->query("SELECT id, product_id, attributes FROM product_variants");
if ($variants) {
    while ($variant = $variants->fetch_assoc()) {
        $attrs = json_decode((string)($variant['attributes'] ?? '{}'), true);
        if (!is_array($attrs)) {
            continue;
        }
        foreach ($attrs as $name => $value) {
            $name = trim((string)$name);
            $value = trim((string)$value);
            if ($name === '') {
                continue;
            }
            $key = norm_attr_name($name);
            if (!isset($defsToSync[$key])) {
                $defsToSync[$key] = ['name' => $name, 'values' => []];
            }
            if ($value !== '' && !in_array($value, $defsToSync[$key]['values'], true)) {
                $defsToSync[$key]['values'][] = $value;
            }
        }
    }
    $variants->free();
}

$inserted = 0;
$updated = 0;
$now = date('Y-m-d H:i:s');

$mysqli->begin_transaction();
try {
    foreach ($defsToSync as $key => $def) {
        $name = $def['name'];
        $valuesJson = json_encode(array_values(array_unique($def['values'])));

        if (!isset($existing[$key])) {
            $stmt = $mysqli->prepare("INSERT INTO product_attributes (name, `values`, is_active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)");
            $stmt->bind_param('ssss', $name, $valuesJson, $now, $now);
            $stmt->execute();
            $stmt->close();
            $inserted++;
            continue;
        }

        $row = $existing[$key];
        $currentValues = json_decode((string)($row['values'] ?? '[]'), true);
        if (!is_array($currentValues)) {
            $currentValues = [];
        }
        $merged = array_values(array_unique(array_merge($currentValues, $def['values'])));
        if (json_encode($merged) !== json_encode(array_values($currentValues))) {
            $stmt = $mysqli->prepare("UPDATE product_attributes SET `values` = ?, updated_at = ? WHERE id = ?");
            $id = (int)$row['id'];
            $stmt->bind_param('ssi', $valuesJson, $now, $id);
            $stmt->execute();
            $stmt->close();
            $updated++;
        }
    }

    $mysqli->commit();
    echo "Backfill complete. Inserted: {$inserted}, Updated: {$updated}" . PHP_EOL;
} catch (Throwable $e) {
    $mysqli->rollback();
    die('Backfill failed: ' . $e->getMessage() . PHP_EOL);
}

$mysqli->close();
