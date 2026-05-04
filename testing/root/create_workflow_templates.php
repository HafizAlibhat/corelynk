<?php
// Create workflow-based process template system alongside existing individual process templates
$mysqli = new mysqli("localhost", "root", "", "production_management_system");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Creating Process Workflow Template system...\n\n";

try {
    // 1. Create process_workflow_templates table (main workflows like "Tweezer Manufacturing")
    echo "1. Creating process_workflow_templates table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS process_workflow_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        category_id INT UNSIGNED,
        estimated_total_time_minutes INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_workflow_name (name),
        INDEX idx_workflow_category (category_id),
        FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    if (!$mysqli->query($sql)) {
        throw new Exception("Error creating process_workflow_templates: " . $mysqli->error);
    }
    
    // 2. Create process_workflow_steps table (individual steps in workflows)
    echo "2. Creating process_workflow_steps table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS process_workflow_steps (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        workflow_template_id INT UNSIGNED NOT NULL,
        step_number INT NOT NULL,
        process_template_id INT UNSIGNED NOT NULL,
        description TEXT,
        estimated_time_minutes INT DEFAULT 0,
        is_vendor_process BOOLEAN DEFAULT FALSE,
        vendor_id INT UNSIGNED NULL,
        qc_required BOOLEAN DEFAULT FALSE,
        qc_checklist JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_workflow_steps (workflow_template_id, step_number),
        FOREIGN KEY (workflow_template_id) REFERENCES process_workflow_templates(id) ON DELETE CASCADE,
        FOREIGN KEY (process_template_id) REFERENCES process_templates(id) ON DELETE CASCADE,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    if (!$mysqli->query($sql)) {
        throw new Exception("Error creating process_workflow_steps: " . $mysqli->error);
    }
    
    // 3. Create product_workflow_assignments table (assign workflows to products)
    echo "3. Creating product_workflow_assignments table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS product_workflow_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        workflow_template_id INT UNSIGNED NOT NULL,
        assigned_by INT UNSIGNED,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_product_workflow (product_id),
        INDEX idx_workflow_product (workflow_template_id),
        UNIQUE KEY unique_product_workflow (product_id, workflow_template_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (workflow_template_id) REFERENCES process_workflow_templates(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    if (!$mysqli->query($sql)) {
        throw new Exception("Error creating product_workflow_assignments: " . $mysqli->error);
    }
    
    // 4. Insert sample workflow templates
    echo "4. Creating sample workflow templates...\n";
    
    // Get a category ID for tools/surgical instruments
    $result = $mysqli->query("SELECT id FROM process_categories WHERE name LIKE '%tool%' OR name LIKE '%metal%' OR name LIKE '%manufacturing%' LIMIT 1");
    $category_id = null;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $category_id = $row['id'];
    }
    
    $workflows = [
        [
            'name' => 'Tweezer Manufacturing',
            'description' => 'Complete manufacturing process for precision tweezers from raw material to finished product',
            'category_id' => $category_id
        ],
        [
            'name' => 'Surgical Instrument Assembly',
            'description' => 'Assembly process for complex surgical instruments with multiple components',
            'category_id' => $category_id
        ],
        [
            'name' => 'Electronic Component Production',
            'description' => 'Manufacturing workflow for electronic components with testing and QC',
            'category_id' => $category_id
        ]
    ];
    
    foreach ($workflows as $workflow) {
        $stmt = $mysqli->prepare("INSERT INTO process_workflow_templates (name, description, category_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $workflow['name'], $workflow['description'], $workflow['category_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error inserting workflow: " . $stmt->error);
        }
        $workflow_id = $mysqli->insert_id;
        echo "   Created workflow: {$workflow['name']} (ID: $workflow_id)\n";
        
        // Add sample steps for Tweezer Manufacturing
        if ($workflow['name'] === 'Tweezer Manufacturing') {
            $steps = [
                ['step' => 1, 'process' => 'CNC Cutting', 'time' => 15],
                ['step' => 2, 'process' => 'Turning Operations', 'time' => 30],
                ['step' => 3, 'process' => 'Surface Preparation', 'time' => 20],
                ['step' => 4, 'process' => 'Polishing', 'time' => 25],
                ['step' => 5, 'process' => 'Manual Assembly', 'time' => 10]
            ];
            
            foreach ($steps as $step) {
                // Find the process template ID
                $process_result = $mysqli->query("SELECT id FROM process_templates WHERE name LIKE '%{$step['process']}%' LIMIT 1");
                if ($process_result && $process_result->num_rows > 0) {
                    $process_row = $process_result->fetch_assoc();
                    $process_id = $process_row['id'];
                    
                    $step_stmt = $mysqli->prepare("INSERT INTO process_workflow_steps (workflow_template_id, step_number, process_template_id, estimated_time_minutes) VALUES (?, ?, ?, ?)");
                    $step_stmt->bind_param("iiii", $workflow_id, $step['step'], $process_id, $step['time']);
                    $step_stmt->execute();
                    echo "     Added step {$step['step']}: {$step['process']}\n";
                }
            }
            
            // Update total estimated time
            $mysqli->query("UPDATE process_workflow_templates SET estimated_total_time_minutes = 100 WHERE id = $workflow_id");
        }
    }
    
    echo "\n✓ Process Workflow Template system created successfully!\n\n";
    echo "New tables created:\n";
    echo "- process_workflow_templates (main workflow templates)\n";
    echo "- process_workflow_steps (individual steps in workflows)\n";
    echo "- product_workflow_assignments (assign workflows to products)\n\n";
    echo "Sample workflow 'Tweezer Manufacturing' created with 5 steps.\n";
    echo "Existing process_templates table remains unchanged for individual processes.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$mysqli->close();
?>
