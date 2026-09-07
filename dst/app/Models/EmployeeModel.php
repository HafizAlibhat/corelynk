<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'employee_code', 'first_name', 'last_name', 'phone', 'email', 'user_id',
        'department', 'designation', 'joining_date', 'monthly_salary',
        'salary_currency', 'is_active'
    ];
    protected $useTimestamps = true;
    protected $returnType = 'array';

    public function getEmployeesWithSkills(bool $includeInactive = false)
    {
        // A subquery rather than a join+GROUP BY: grouping on employees.id alone
        // is rejected under MySQL's ONLY_FULL_GROUP_BY.
        return $this->select("employees.*, (SELECT GROUP_CONCAT(es.skill_name ORDER BY es.skill_name SEPARATOR ', ')
                                              FROM employee_skills es
                                             WHERE es.employee_id = employees.id) AS skills", false)
                    ->where($includeInactive ? '1=1' : 'employees.is_active = 1', null, false)
                    ->orderBy('employees.is_active', 'DESC')
                    ->orderBy('employees.first_name', 'ASC')
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
        // Highest number actually used, not the newest row: codes may be blank,
        // edited by hand, or left behind by a deactivated employee.
        $highest = (int) ($this->db->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(employee_code, 4) AS UNSIGNED)), 0) AS n
               FROM employees WHERE employee_code REGEXP '^EMP[0-9]+$'"
        )->getRow()->n ?? 0);

        return 'EMP' . str_pad($highest + 1, 3, '0', STR_PAD_LEFT);
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
