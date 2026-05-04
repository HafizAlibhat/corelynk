<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeSkillModel extends Model
{
    protected $table = 'employee_skills';
    protected $primaryKey = 'id';
    protected $allowedFields = ['employee_id', 'skill_name', 'proficiency_level'];
    protected $useTimestamps = true;
    protected $returnType = 'array';

    public function getSkillsByEmployee($employeeId)
    {
        return $this->where('employee_id', $employeeId)->findAll();
    }

    public function getUniqueSkills()
    {
        return $this->distinct()->select('skill_name')->findAll();
    }
}
