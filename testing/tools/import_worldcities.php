<?php
// Usage: php import_worldcities.php
// Place worldcities.csv from SimpleMaps in the same directory as this script.
// https://simplemaps.com/data/world-cities (free version)

$csvFile = __DIR__ . '/worldcities.csv';
if (!file_exists($csvFile)) {
    echo "ERROR: worldcities.csv not found. Download from https://simplemaps.com/data/world-cities and place in tools/\n";
    exit(1);
}

$db = new mysqli('127.0.0.1', 'root', '', 'corelynk_db');
if ($db->connect_errno) {
    echo "DB ERROR: " . $db->connect_error . "\n";
    exit(1);
}

// Prepare lookup maps
$countries = [];
$states = [];
$cities = [];

// Read CSV
if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle);
    $col = array_flip($header);
    $rowCount = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $country = trim($row[$col['country']]);
        $countryCode = trim($row[$col['iso2']]);
        $state = trim($row[$col['admin_name']]);
        $city = trim($row[$col['city']]);
        if (!$country || !$state || !$city) continue;
        $countries[$countryCode] = $country;
        $states[$countryCode . '|' . $state] = ['country_code' => $countryCode, 'name' => $state];
        $cities[] = ['country_code' => $countryCode, 'state' => $state, 'name' => $city];
        $rowCount++;
    }
    fclose($handle);
    echo "Parsed $rowCount rows from CSV.\n";
} else {
    echo "ERROR: Unable to open CSV file.\n";
    exit(1);
}

// Insert countries
$countryIds = [];
$stmt = $db->prepare("INSERT IGNORE INTO countries (name, iso_code) VALUES (?, ?)");
foreach ($countries as $code => $name) {
    $stmt->bind_param('ss', $name, $code);
    $stmt->execute();
}
$stmt->close();
// Map country code to id
$res = $db->query("SELECT id, iso_code FROM countries");
while ($row = $res->fetch_assoc()) {
    $countryIds[$row['iso_code']] = $row['id'];
}

// Insert states
$stateIds = [];
$stmt = $db->prepare("INSERT IGNORE INTO states (country_id, name) VALUES (?, ?)");
foreach ($states as $key => $s) {
    $cid = $countryIds[$s['country_code']] ?? null;
    if (!$cid) continue;
    $stmt->bind_param('is', $cid, $s['name']);
    $stmt->execute();
}
$stmt->close();
// Map state name+country to id
$res = $db->query("SELECT id, country_id, name FROM states");
while ($row = $res->fetch_assoc()) {
    $stateIds[$row['country_id'] . '|' . $row['name']] = $row['id'];
}

// Insert cities
$stmt = $db->prepare("INSERT IGNORE INTO cities (state_id, name) VALUES (?, ?)");
$inserted = 0;
foreach ($cities as $c) {
    $cid = $countryIds[$c['country_code']] ?? null;
    if (!$cid) continue;
    $sid = $stateIds[$cid . '|' . $c['state']] ?? null;
    if (!$sid) continue;
    $stmt->bind_param('is', $sid, $c['name']);
    $stmt->execute();
    $inserted++;
}
$stmt->close();
echo "Imported countries: " . count($countryIds) . ", states: " . count($stateIds) . ", cities: $inserted\n";
