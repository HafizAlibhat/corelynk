<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

function norm_name(string $name): string {
    return mb_strtolower(trim($name));
}

function decode_values($raw): array {
    $arr = json_decode((string)$raw, true);
    return is_array($arr) ? $arr : [];
}

function merge_values(array $values): array {
    $seen = [];
    $out = [];
    foreach ($values as $v) {
        $v = trim((string)$v);
        if ($v === '') {
            continue;
        }
        $k = mb_strtolower($v);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $v;
    }
    return $out;
}

$rows = [];
$res = $mysqli->query("SELECT id, name, `values`, is_active, created_at, updated_at FROM product_attributes ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
}

$groups = [];
foreach ($rows as $row) {
    $key = norm_name((string)($row['name'] ?? ''));
    if ($key === '') {
        continue;
    }
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $row;
}

$tablesWithAttributeId = [];
$sql = "
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ?
  AND COLUMN_NAME = 'attribute_id'
  AND TABLE_NAME <> 'product_attributes'
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $dbname);
$stmt->execute();
$refRes = $stmt->get_result();
while ($r = $refRes->fetch_assoc()) {
    $tablesWithAttributeId[] = $r['TABLE_NAME'];
}
$stmt->close();

$mergedCount = 0;
$deletedCount = 0;

$mysqli->begin_transaction();
try {
    foreach ($groups as $key => $list) {
        if (count($list) <= 1) {
            continue;
        }

        // Canonical row: first (lowest id)
        $canonical = $list[0];
        $canonicalId = (int)$canonical['id'];

        $allValues = [];
        $active = (int)$canonical['is_active'];
        foreach ($list as $row) {
            $active = max($active, (int)($row['is_active'] ?? 0));
            $vals = decode_values($row['values'] ?? '[]');
            $allValues = array_merge($allValues, $vals);
        }
        $mergedValues = merge_values($allValues);

        $upd = $mysqli->prepare("UPDATE product_attributes SET `values` = ?, is_active = ?, updated_at = ? WHERE id = ?");
        $valuesJson = json_encode($mergedValues);
        $now = date('Y-m-d H:i:s');
        $upd->bind_param('sisi', $valuesJson, $active, $now, $canonicalId);
        $upd->execute();
        $upd->close();

        for ($i = 1; $i < count($list); $i++) {
            $dupId = (int)$list[$i]['id'];

            foreach ($tablesWithAttributeId as $tableName) {
                $q = "UPDATE `" . $mysqli->real_escape_string($tableName) . "` SET attribute_id = ? WHERE attribute_id = ?";
                $move = $mysqli->prepare($q);
                $move->bind_param('ii', $canonicalId, $dupId);
                $move->execute();
                $move->close();
            }

            $del = $mysqli->prepare("DELETE FROM product_attributes WHERE id = ?");
            $del->bind_param('i', $dupId);
            $del->execute();
            $del->close();

            $deletedCount++;
        }

        $mergedCount++;
    }

    $mysqli->commit();
    echo 'Dedup complete. Merged groups: ' . $mergedCount . ', Removed duplicates: ' . $deletedCount . PHP_EOL;
} catch (Throwable $e) {
    $mysqli->rollback();
    die('Dedup failed: ' . $e->getMessage() . PHP_EOL);
}

$mysqli->close();
