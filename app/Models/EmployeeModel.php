<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'employee_code', 'first_name', 'last_name', 'phone', 'email', 
        'department', 'is_active'
    ];
    protected $useTimestamps = true;
    protected $returnType = 'array';

    public function getEmployeesWithSkills()
    {
        return $this->select('employees.*, GROUP_CONCAT(employee_skills.skill_name) as skills')
                    ->join('employee_skills', 'employee_skills.employee_id = employees.id', 'left')
                    ->where('employees.is_active', 1)
                    ->groupBy('employees.id')
                    ->findAll();
    }

    public function getEmployeesBySkill($skillName)
    {
        return $this->select('employees.*')
                    ->join('employee_skills', 'employee_skills.employee_id = employees.id')
                    ->where('employee_skills.skill_name', $skillName)
                    ->where('employees.is_active', 1)
                    ->findAll();
    }

    public function generateEmployeeCode()
    {
        $lastEmployee = $this->orderBy('id', 'DESC')->first();
        if (!$lastEmployee) {
            return 'EMP001';
        }
        
        $lastCode = $lastEmployee['employee_code'];
        $number = (int) substr($lastCode, 3);
        $newNumber = $number + 1;
        
        return 'EMP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function addEmployeeWithSkills($employeeData, $skills = [])
    {
        $db = \Config\Database::connect();
        $db->transStart();
        
        // Generate employee code if not provided
        if (!isset($employeeData['employee_code'])) {
            $employeeData['employee_code'] = $this->generateEmployeeCode();
        }
        
        // Insert employee
        $employeeId = $this->insert($employeeData);
        
        // Insert skills
        if (!empty($skills) && $employeeId) {
            $skillsModel = new EmployeeSkillModel();
            foreach ($skills as $skill) {
                $skillData = [
                    'employee_id' => $employeeId,
                    'skill_name' => $skill['skill_name'],
                    'proficiency_level' => $skill['proficiency_level'] ?? 'basic'
                ];
                $skillsModel->insert($skillData);
            }
        }
        
        $db->transComplete();
        
        return $db->transStatus() ? $employeeId : false;
    }
}
