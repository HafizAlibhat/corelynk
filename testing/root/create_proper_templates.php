<?php
// Connect to database
$mysqli = new mysqli("localhost", "root", "", "production_management_system");
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Creating proper Process Template system...\n\n";

// 1. Rename current process_templates to process_steps or similar
echo "1. Backing up current process_templates...\n";
$mysqli->query("CREATE TABLE process_steps_backup AS SELECT * FROM process_templates");

// 2. Create new Process Templates table (the main templates)
echo "2. Creating process_templates table (main workflow templates)...\n";
$mysqli->query("DROP TABLE IF EXISTS process_templates");
$createTemplatesSQL = "
CREATE TABLE process_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Template name like Tweezer Manufacturing',
    description TEXT COMMENT 'What this template is for',
    category_id INT UNSIGNED NULL COMMENT 'What category of products this applies to',
    estimated_total_time INT DEFAULT 0 COMMENT 'Total estimated time in minutes',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_templates_category (category_id),
    INDEX idx_templates_active (is_active)
) ENGINE=InnoDB COMMENT='Manufacturing workflow templates'";
$mysqli->query($createTemplatesSQL);

// 3. Create Process Template Steps table (individual steps in templates)
echo "3. Creating process_template_steps table...\n";
$createStepsSQL = "
CREATE TABLE process_template_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL COMMENT 'Which template this step belongs to',
    step_order INT NOT NULL COMMENT 'Order of this step in the workflow',
    step_name VARCHAR(100) NOT NULL COMMENT 'Name of this step',
    description TEXT COMMENT 'Details about this step',
    is_vendor_process BOOLEAN DEFAULT FALSE COMMENT 'Is this step outsourced',
    vendor_id INT UNSIGNED NULL COMMENT 'Which vendor if outsourced',
    estimated_time_minutes INT DEFAULT 0 COMMENT 'Expected time for this step',
    qc_requirements JSON COMMENT 'Quality control checks for this step',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES process_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    INDEX idx_steps_template (template_id),
    INDEX idx_steps_order (template_id, step_order),
    UNIQUE KEY unique_template_order (template_id, step_order)
) ENGINE=InnoDB COMMENT='Individual steps within process templates'";
$mysqli->query($createStepsSQL);

// 4. Create Product Process Templates table (which templates are applied to products)
echo "4. Creating product_process_templates table...\n";
$createProductTemplatesSQL = "
CREATE TABLE product_process_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT UNSIGNED NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES process_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product_templates_product (product_id),
    INDEX idx_product_templates_template (template_id),
    UNIQUE KEY unique_product_template (product_id, template_id)
) ENGINE=InnoDB COMMENT='Which process templates are applied to which products'";
$mysqli->query($createProductTemplatesSQL);

echo "5. Creating sample process templates...\n";

// Sample template 1: Tweezer Manufacturing
$mysqli->query("
INSERT INTO process_templates (name, description, category_id, estimated_total_time) VALUES 
('Tweezer Manufacturing', 'Complete manufacturing process for all types of tweezers', 6, 240)
");
$tweezer_template_id = $mysqli->insert_id;

// Steps for tweezer manufacturing
$tweezer_steps = [
    ['Steel Cutting', 'Cut steel bar to required length', false, null, 30],
    ['Rough Shaping', 'Shape the basic tweezer form', false, null, 45],
    ['Heat Treatment', 'Heat treatment for hardness', true, 1, 60],
    ['Fine Grinding', 'Precision grinding of tips', false, null, 40],
    ['Polishing', 'Surface polishing and finishing', false, null, 35],
    ['Quality Inspection', 'Final quality check and testing', false, null, 20],
    ['Packaging', 'Package for shipping', false, null, 10]
];

foreach ($tweezer_steps as $index => $step) {
    $order = $index + 1;
    $mysqli->query("
        INSERT INTO process_template_steps (template_id, step_order, step_name, description, is_vendor_process, vendor_id, estimated_time_minutes) 
        VALUES ($tweezer_template_id, $order, '{$step[0]}', '{$step[1]}', " . ($step[2] ? 1 : 0) . ", " . ($step[3] ?? 'NULL') . ", {$step[4]})
    ");
}

// Sample template 2: Electronic Component Assembly
$mysqli->query("
INSERT INTO process_templates (name, description, category_id, estimated_total_time) VALUES 
('Electronic Component Assembly', 'Standard assembly process for electronic components', 3, 180)
");
$electronics_template_id = $mysqli->insert_id;

$electronics_steps = [
    ['PCB Preparation', 'Prepare printed circuit board', false, null, 15],
    ['Component Placement', 'Place electronic components', false, null, 60],
    ['Soldering', 'Solder components to PCB', false, null, 45],
    ['Testing', 'Electrical testing and verification', false, null, 30],
    ['Enclosure Assembly', 'Assemble in protective housing', false, null, 20],
    ['Final Testing', 'Complete functional testing', false, null, 10]
];

foreach ($electronics_steps as $index => $step) {
    $order = $index + 1;
    $mysqli->query("
        INSERT INTO process_template_steps (template_id, step_order, step_name, description, is_vendor_process, vendor_id, estimated_time_minutes) 
        VALUES ($electronics_template_id, $order, '{$step[0]}', '{$step[1]}', " . ($step[2] ? 1 : 0) . ", " . ($step[3] ?? 'NULL') . ", {$step[4]})
    ");
}

echo "✅ Process Template system created successfully!\n\n";
echo "Summary:\n";
echo "- process_templates: Main workflow templates\n";
echo "- process_template_steps: Individual steps in each template\n";
echo "- product_process_templates: Which templates are applied to products\n";
echo "- Created sample templates: Tweezer Manufacturing, Electronic Component Assembly\n";

$mysqli->close();
?>
