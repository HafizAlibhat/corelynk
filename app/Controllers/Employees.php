<?php

namespace App\Controllers;

use App\Models\EmployeeModel;
use App\Models\EmployeeSkillModel;

class Employees extends BaseController
{
    protected $employeeModel;
    protected $skillModel;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->skillModel = new EmployeeSkillModel();
    }

    public function index()
    {
        $this->requireAuth();
        
        $employees = [];
        $employeeError = null;
        try {
            $employees = $this->employeeModel->getEmployeesWithSkills();
        } catch (\Exception $e) {
            log_message('error', 'Failed to load employees: ' . $e->getMessage());
            $employeeError = 'Employee data is not available. Please ensure the employees table and related migrations have been applied.';
        }

        $data = [
            'page_title' => 'Employees Management',
            'employees' => $employees,
            'employeeError' => $employeeError
        ];

        return view('employees/index', $data);
    }

    public function create()
    {
        $this->requireAuth();
        
        $skills = [];
        $employeeError = null;
        try {
            $skills = $this->skillModel->getUniqueSkills();
        } catch (\Exception $e) {
            log_message('error', 'Failed to load employee skills: ' . $e->getMessage());
            $employeeError = 'Employee skills are not available. Please ensure the employee_skills table exists.';
        }

        $data = [
            'page_title' => 'Add New Employee',
            'skills' => $skills,
            'employeeError' => $employeeError
        ];

        return view('employees/form', $data);
    }

    public function store()
    {
        $this->requireAuth();
        
        $validation = \Config\Services::validation();
        $validation->setRules([
            'first_name' => 'required|min_length[1]|max_length[50]',
            'last_name' => 'required|min_length[1]|max_length[50]',
            'phone' => 'permit_empty|min_length[10]|max_length[20]',
            'email' => 'permit_empty|valid_email',
            'department' => 'permit_empty|max_length[50]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            session()->setFlashdata('error', 'Please correct the form errors');
            return redirect()->back()->withInput()->with('validation', $validation);
        }

        $employeeData = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => $this->request->getPost('phone'),
            'email' => $this->request->getPost('email'),
            'department' => $this->request->getPost('department'),
            'is_active' => 1
        ];

        // Handle skills
        $skills = [];
        $skillNames = $this->request->getPost('skill_names') ?? [];
        $skillLevels = $this->request->getPost('skill_levels') ?? [];
        
        for ($i = 0; $i < count($skillNames); $i++) {
            if (!empty($skillNames[$i])) {
                $skills[] = [
                    'skill_name' => $skillNames[$i],
                    'proficiency_level' => $skillLevels[$i] ?? 'basic'
                ];
            }
        }

        $employeeId = $this->employeeModel->addEmployeeWithSkills($employeeData, $skills);

        if ($employeeId) {
            session()->setFlashdata('success', 'Employee added successfully');
            return redirect()->to('/employees');
        } else {
            session()->setFlashdata('error', 'Failed to add employee');
            return redirect()->back()->withInput()->with('validation', $validation);
        }
    }

    public function show($id)
    {
        $this->requireAuth();
        
        $employeeError = null;
        try {
            $employee = $this->employeeModel->find($id);
            if (!$employee) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Employee not found');
            }

            $skills = $this->skillModel->getSkillsByEmployee($id);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            log_message('error', 'Failed to load employee/show: ' . $e->getMessage());
            $employeeError = 'Employee details are not available due to a database issue.';
            $employee = null;
            $skills = [];
        }

        $data = [
            'page_title' => 'Employee Details',
            'employee' => $employee,
            'skills' => $skills,
            'employeeError' => $employeeError
        ];

        return view('employees/show', $data);
    }

    public function edit($id)
    {
        $this->requireAuth();
        
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Employee not found');
        }

        $skills = $this->skillModel->getSkillsByEmployee($id);

        $data = [
            'page_title' => 'Edit Employee',
            'employee' => $employee,
            'skills' => $skills,
            'all_skills' => $this->skillModel->getUniqueSkills()
        ];

        return view('employees/form', $data);
    }

    public function update($id)
    {
        $this->requireAuth();
        
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Employee not found');
        }

        $validation = \Config\Services::validation();
        $validation->setRules([
            'first_name' => 'required|min_length[1]|max_length[50]',
            'last_name' => 'required|min_length[1]|max_length[50]',
            'phone' => 'permit_empty|min_length[10]|max_length[20]',
            'email' => 'permit_empty|valid_email',
            'department' => 'permit_empty|max_length[50]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            session()->setFlashdata('error', 'Please correct the form errors');
            return redirect()->back()->withInput()->with('validation', $validation);
        }

        $employeeData = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => $this->request->getPost('phone'),
            'email' => $this->request->getPost('email'),
            'department' => $this->request->getPost('department')
        ];

        $this->employeeModel->update($id, $employeeData);

        // Update skills (delete old ones and add new ones)
        $this->skillModel->where('employee_id', $id)->delete();
        
        $skillNames = $this->request->getPost('skill_names') ?? [];
        $skillLevels = $this->request->getPost('skill_levels') ?? [];
        
        for ($i = 0; $i < count($skillNames); $i++) {
            if (!empty($skillNames[$i])) {
                $this->skillModel->insert([
                    'employee_id' => $id,
                    'skill_name' => $skillNames[$i],
                    'proficiency_level' => $skillLevels[$i] ?? 'basic'
                ]);
            }
        }

        session()->setFlashdata('success', 'Employee updated successfully');
        return redirect()->to('/employees');
    }

    public function delete($id)
    {
        $this->requireAuth();
        
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Employee not found');
        }

        // Soft delete by setting is_active to 0
        $this->employeeModel->update($id, ['is_active' => 0]);
        
        session()->setFlashdata('success', 'Employee deactivated successfully');
        return redirect()->to('/employees');
    }

    // AJAX endpoint to get employees by skill
    public function getBySkill()
    {
        $this->requireAuth();
        
        $skill = $this->request->getGet('skill');
        if (!$skill) {
            return $this->response->setJSON(['success' => false, 'message' => 'Skill parameter required']);
        }

        $employees = [];
        try {
            $employees = $this->employeeModel->getEmployeesBySkill($skill);
        } catch (\Exception $e) {
            log_message('error', 'Failed to get employees by skill: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Employee data is not available']);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'employees' => $employees
        ]);
    }

    // AJAX endpoint to get all active employees
    public function getAll()
    {
        $this->requireAuth();
        
        $employees = [];
        try {
            // Return only minimal fields and a computed name for UI dropdowns
            $employees = $this->employeeModel
                ->select("id, CONCAT(first_name, ' ', last_name) AS name, employee_code, department")
                ->where('is_active', 1)
                ->orderBy('first_name', 'ASC')
                ->orderBy('last_name', 'ASC')
                ->findAll();
        } catch (\Exception $e) {
            log_message('error', 'Failed to get all employees: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Employee data is not available']);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'employees' => $employees
        ]);
    }
}
