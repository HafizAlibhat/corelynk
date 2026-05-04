<?php

require_once 'vendor/autoload.php';

// Bootstrap CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

$db = \Config\Database::connect();

try {
    // Insert sample employees
    $employees = [
        [
            'employee_code' => 'EMP001',
            'first_name' => 'Rajesh',
            'last_name' => 'Kumar',
            'phone' => '+91-9876543210',
            'email' => 'rajesh.kumar@company.com',
            'department' => 'Production',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'employee_code' => 'EMP002',
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'phone' => '+91-9876543211',
            'email' => 'priya.sharma@company.com',
            'department' => 'Quality Control',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'employee_code' => 'EMP003',
            'first_name' => 'Amit',
            'last_name' => 'Singh',
            'phone' => '+91-9876543212',
            'email' => 'amit.singh@company.com',
            'department' => 'Production',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];

    foreach ($employees as $employee) {
        $db->table('employees')->insert($employee);
    }

    // Get inserted employee IDs for skills
    $emp1 = $db->table('employees')->where('employee_code', 'EMP001')->get()->getRow();
    $emp2 = $db->table('employees')->where('employee_code', 'EMP002')->get()->getRow();
    $emp3 = $db->table('employees')->where('employee_code', 'EMP003')->get()->getRow();

    if ($emp1 && $emp2 && $emp3) {
        // Insert employee skills
        $skills = [
            ['employee_id' => $emp1->id, 'skill_name' => 'Laser Cutting', 'proficiency_level' => 'expert', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['employee_id' => $emp1->id, 'skill_name' => 'Metal Forming', 'proficiency_level' => 'advanced', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['employee_id' => $emp2->id, 'skill_name' => 'Quality Inspection', 'proficiency_level' => 'expert', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['employee_id' => $emp2->id, 'skill_name' => 'Testing', 'proficiency_level' => 'advanced', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['employee_id' => $emp3->id, 'skill_name' => 'Assembly', 'proficiency_level' => 'intermediate', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['employee_id' => $emp3->id, 'skill_name' => 'Packing', 'proficiency_level' => 'advanced', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ];

        foreach ($skills as $skill) {
            $db->table('employee_skills')->insert($skill);
        }
        
        echo "Sample employee data with skills inserted successfully!\n";
    } else {
        echo "Sample employee data inserted successfully!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
