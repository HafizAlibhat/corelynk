<?php
// Insert sample data for process templates
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Connected successfully.\n";

// Insert sample process templates
echo "Inserting sample process templates...\n";

$templates = [
    // Machining Templates
    ['CNC Cutting', 'Computer Numerical Control cutting operation', 'machining', 0, null, 45, '["Check dimensions", "Surface finish quality", "Tool wear inspection"]'],
    ['Manual Cutting', 'Manual cutting using hand tools', 'machining', 0, null, 60, '["Measurement accuracy", "Edge quality", "Safety compliance"]'],
    ['Drilling Operations', 'Various drilling operations for holes', 'machining', 0, null, 30, '["Hole diameter", "Depth accuracy", "Burr removal"]'],
    ['Turning Operations', 'Lathe turning operations', 'machining', 0, null, 40, '["Dimensional accuracy", "Surface finish", "Concentricity"]'],
    
    // Assembly Templates
    ['Manual Assembly', 'Hand assembly of components', 'assembly', 0, null, 90, '["Component fit", "Fastener torque", "Assembly sequence"]'],
    ['Automated Assembly', 'Machine-assisted assembly process', 'assembly', 0, null, 60, '["Program verification", "Component alignment", "Cycle time"]'],
    ['Sub-Assembly Creation', 'Creating intermediate assemblies', 'assembly', 0, null, 75, '["Sub-assembly function", "Component count", "Documentation"]'],
    
    // Finishing Templates
    ['Surface Preparation', 'Cleaning and surface prep before finishing', 'finishing', 0, null, 25, '["Surface cleanliness", "Contamination check", "Drying time"]'],
    ['Painting/Coating', 'Application of paint or protective coating', 'finishing', 0, null, 35, '["Coverage uniformity", "Thickness measurement", "Drying/Curing"]'],
    ['Polishing', 'Surface polishing and buffing', 'finishing', 0, null, 40, '["Surface smoothness", "Scratch removal", "Final appearance"]'],
    
    // Quality Templates
    ['Incoming Inspection', 'Quality check of incoming materials', 'quality', 0, null, 20, '["Material certification", "Visual inspection", "Dimensional check"]'],
    ['In-Process Inspection', 'Quality checks during manufacturing', 'quality', 0, null, 15, '["Process parameters", "Intermediate dimensions", "Defect identification"]'],
    ['Final Inspection', 'Final quality verification before shipping', 'quality', 0, null, 30, '["Final dimensions", "Function test", "Packaging check"]'],
    
    // Packaging Templates
    ['Standard Packaging', 'Regular product packaging', 'packaging', 0, null, 15, '["Package integrity", "Label accuracy", "Protection adequacy"]'],
    ['Custom Packaging', 'Special packaging requirements', 'packaging', 0, null, 25, '["Custom requirements met", "Special handling", "Documentation"]'],
    
    // Testing Templates
    ['Function Testing', 'Operational function verification', 'testing', 0, null, 45, '["Performance parameters", "Safety features", "Calibration"]'],
    ['Stress Testing', 'Product stress and durability testing', 'testing', 0, null, 60, '["Load capacity", "Cycle count", "Failure modes"]']
];

$stmt = $mysqli->prepare("INSERT INTO process_templates (name, description, category, is_vendor_process, vendor_id, standard_time_minutes, qc_checklist) VALUES (?, ?, ?, ?, ?, ?, ?)");

$successCount = 0;
foreach ($templates as $template) {
    $stmt->bind_param("sssiiss", $template[0], $template[1], $template[2], $template[3], $template[4], $template[5], $template[6]);
    if ($stmt->execute()) {
        $successCount++;
        echo "✓ Added: {$template[0]}\n";
    } else {
        echo "✗ Failed to add: {$template[0]} - " . $mysqli->error . "\n";
    }
}

echo "\nInserted $successCount process templates.\n";

// Get some product IDs to create sample product-process assignments
$result = $mysqli->query("SELECT id, name FROM products LIMIT 5");
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

if (!empty($products)) {
    echo "\nCreating sample product-process assignments...\n";
    
    // Get process template IDs
    $result = $mysqli->query("SELECT id, name FROM process_templates ORDER BY id");
    $processTemplates = [];
    while ($row = $result->fetch_assoc()) {
        $processTemplates[] = $row;
    }
    
    // Sample assignments
    $assignments = [
        // Product 1: Complete manufacturing workflow
        [$products[0]['id'], $processTemplates[0]['id'], 1, 'Start with CNC cutting for main body'],
        [$products[0]['id'], $processTemplates[3]['id'], 2, 'Turning for cylindrical features'],
        [$products[0]['id'], $processTemplates[4]['id'], 3, 'Assembly of main components'],
        [$products[0]['id'], $processTemplates[7]['id'], 4, 'Surface preparation before coating'],
        [$products[0]['id'], $processTemplates[8]['id'], 5, 'Apply protective coating'],
        [$products[0]['id'], $processTemplates[12]['id'], 6, 'Final quality inspection'],
        
        // Product 2: Simple assembly workflow
        [$products[1]['id'], $processTemplates[1]['id'], 1, 'Manual cutting of components'],
        [$products[1]['id'], $processTemplates[6]['id'], 2, 'Sub-assembly creation'],
        [$products[1]['id'], $processTemplates[4]['id'], 3, 'Final assembly'],
        [$products[1]['id'], $processTemplates[11]['id'], 4, 'In-process quality check'],
        
        // Product 3: Machining focused
        [$products[2]['id'], $processTemplates[0]['id'], 1, 'CNC cutting primary operation'],
        [$products[2]['id'], $processTemplates[2]['id'], 2, 'Drilling for mounting holes'],
        [$products[2]['id'], $processTemplates[9]['id'], 3, 'Polishing for finish']
    ];
    
    if (count($products) >= 3) {
        $stmt2 = $mysqli->prepare("INSERT INTO product_processes (product_id, process_template_id, sequence_order, custom_notes) VALUES (?, ?, ?, ?)");
        
        $assignmentCount = 0;
        foreach ($assignments as $assignment) {
            $stmt2->bind_param("iiis", $assignment[0], $assignment[1], $assignment[2], $assignment[3]);
            if ($stmt2->execute()) {
                $assignmentCount++;
                echo "✓ Assigned process to product\n";
            } else {
                echo "✗ Failed assignment: " . $mysqli->error . "\n";
            }
        }
        
        echo "\nCreated $assignmentCount product-process assignments.\n";
    }
}

$mysqli->close();
echo "\n✅ Sample data insertion completed!\n";
?>
