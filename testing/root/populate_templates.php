<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Populating process_templates table...\n";

// Insert sample process templates
$templates = [
    // Machining Templates
    ['CNC Cutting', 'Computer Numerical Control cutting operation', 'machining', false, null, 45, '["Check dimensions", "Surface finish quality", "Tool wear inspection"]'],
    ['Manual Cutting', 'Manual cutting using hand tools', 'machining', false, null, 60, '["Measurement accuracy", "Edge quality", "Safety compliance"]'],
    ['Drilling Operations', 'Various drilling operations for holes', 'machining', false, null, 30, '["Hole diameter", "Depth accuracy", "Burr removal"]'],
    ['Turning Operations', 'Lathe turning operations', 'machining', false, null, 40, '["Dimensional accuracy", "Surface finish", "Concentricity"]'],
    
    // Assembly Templates
    ['Manual Assembly', 'Hand assembly of components', 'assembly', false, null, 90, '["Component fit", "Fastener torque", "Assembly sequence"]'],
    ['Automated Assembly', 'Machine-assisted assembly process', 'assembly', false, null, 60, '["Program verification", "Component alignment", "Cycle time"]'],
    ['Sub-Assembly Creation', 'Creating intermediate assemblies', 'assembly', false, null, 75, '["Sub-assembly function", "Component count", "Documentation"]'],
    
    // Finishing Templates
    ['Surface Preparation', 'Cleaning and surface prep before finishing', 'finishing', false, null, 25, '["Surface cleanliness", "Contamination check", "Drying time"]'],
    ['Painting/Coating', 'Application of paint or protective coating', 'finishing', false, null, 35, '["Coverage uniformity", "Thickness measurement", "Drying/Curing"]'],
    ['Polishing', 'Surface polishing and buffing', 'finishing', false, null, 40, '["Surface smoothness", "Scratch removal", "Final appearance"]'],
    
    // Quality Templates
    ['Incoming Inspection', 'Quality check of incoming materials', 'quality', false, null, 20, '["Material certification", "Visual inspection", "Dimensional check"]'],
    ['In-Process Inspection', 'Quality checks during manufacturing', 'quality', false, null, 15, '["Process parameters", "Intermediate dimensions", "Defect identification"]'],
    ['Final Inspection', 'Final quality verification before shipping', 'quality', false, null, 30, '["Final dimensions", "Function test", "Packaging check"]'],
    
    // Packaging Templates
    ['Standard Packaging', 'Regular product packaging', 'packaging', false, null, 15, '["Package integrity", "Label accuracy", "Protection adequacy"]'],
    ['Custom Packaging', 'Special packaging requirements', 'packaging', false, null, 25, '["Custom requirements met", "Special handling", "Documentation"]'],
    
    // Testing Templates
    ['Function Testing', 'Operational function verification', 'testing', false, null, 45, '["Performance parameters", "Safety features", "Calibration"]'],
    ['Stress Testing', 'Product stress and durability testing', 'testing', false, null, 60, '["Load capacity", "Cycle count", "Failure modes"]']
];

$stmt = $mysqli->prepare("INSERT INTO process_templates (name, description, category, is_vendor_process, vendor_id, standard_time_minutes, qc_checklist) VALUES (?, ?, ?, ?, ?, ?, ?)");

$count = 0;
foreach ($templates as $template) {
    $stmt->bind_param("sssbids", $template[0], $template[1], $template[2], $template[3], $template[4], $template[5], $template[6]);
    if ($stmt->execute()) {
        $count++;
        echo "✓ Added: " . $template[0] . "\n";
    } else {
        echo "✗ Failed to add: " . $template[0] . " - " . $stmt->error . "\n";
    }
}

echo "\nSuccessfully added $count process templates!\n";

// Check the result
$result = $mysqli->query('SELECT COUNT(*) as count FROM process_templates');
$row = $result->fetch_assoc();
echo "Total process templates in database: " . $row['count'] . "\n";

$stmt->close();
$mysqli->close();
?>
