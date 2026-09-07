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
        
        $showInactive = (bool) $this->request->getGet('inactive');

        $employees = [];
        $employeeError = null;
        try {
            $employees = $this->employeeModel->getEmployeesWithSkills($showInactive);
        } catch (\Exception $e) {
            log_message('error', 'Failed to load employees: ' . $e->getMessage());
            $employeeError = 'Employee data is not available. Please ensure the employees table and related migrations have been applied.';
        }

        $data = [
            'page_title' => 'Employees Management',
            'employees' => $employees,
            'showInactive' => $showInactive,
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
            'department' => 'permit_empty|max_length[50]',
            'designation' => 'permit_empty|max_length[100]',
            'monthly_salary' => 'permit_empty|decimal|greater_than_equal_to[0]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            session()->setFlashdata('error', 'Please correct the form errors');
            return redirect()->back()->withInput()->with('validation', $validation);
        }

        $employeeData = array_merge($this->employmentFields(), ['is_active' => 1]);

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

    /** The posted employee fields, shared by store() and update(). */
    private function employmentFields(): array
    {
        helper('currency');
        $salary = $this->request->getPost('monthly_salary');

        return [
            'first_name'      => $this->request->getPost('first_name'),
            'last_name'       => $this->request->getPost('last_name'),
            'phone'           => $this->request->getPost('phone'),
            'email'           => $this->request->getPost('email'),
            'department'      => $this->request->getPost('department'),
            'designation'     => $this->request->getPost('designation'),
            'joining_date'    => $this->request->getPost('joining_date') ?: null,
            'monthly_salary'  => ($salary === null || $salary === '') ? null : (float) $salary,
            'salary_currency' => $this->request->getPost('salary_currency') ?: base_currency_code(),
        ];
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
            'salaryHistory' => $employee ? (new \App\Models\SalaryPaymentModel())->historyFor($id) : [],
            'employeeError' => $employeeError
        ] + $this->accessData($employee);

        return view('employees/show', $data);
    }

    /**
     * The login attached to this employee, plus what an admin needs to attach
     * one: the user accounts nobody else is using, and the roles on offer.
     */
    private function accessData(?array $employee): array
    {
        $userModel = new \App\Models\UserModel();
        // Same test the admin route filter uses, so the buttons only show
        // when they would actually work.
        $isAdmin   = in_array('admin', (array) (session('role_slugs') ?: [session('role')]), true);

        $account = ($employee && ! empty($employee['user_id']))
            ? $userModel->find($employee['user_id'])
            : null;

        if ($account) {
            $account['roles'] = $userModel->getRoles((int) $account['id']);
        }

        $free = [];
        if ($isAdmin && ! $account) {
            $taken = array_map('intval', array_filter(
                array_column($this->employeeModel->select('user_id')->findAll(), 'user_id')
            ));
            $free = $userModel->select('id, username, email, first_name, last_name, is_active')
                              ->orderBy('username', 'ASC')
                              ->findAll();
            $free = array_values(array_filter($free, static fn ($u) => ! in_array((int) $u['id'], $taken, true)));
        }

        return [
            'account'        => $account,
            'canManageUsers' => $isAdmin,
            'freeUsers'      => $free,
            'allRoles'       => $isAdmin ? (new \App\Models\RoleModel())->orderBy('name', 'ASC')->findAll() : [],
        ];
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
            'department' => 'permit_empty|max_length[50]',
            'designation' => 'permit_empty|max_length[100]',
            'monthly_salary' => 'permit_empty|decimal|greater_than_equal_to[0]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            session()->setFlashdata('error', 'Please correct the form errors');
            return redirect()->back()->withInput()->with('validation', $validation);
        }

        $employeeData = $this->employmentFields();

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

    /**
     * Deactivate: the employee stays on record so past payslips, cheques and
     * work-order history keep pointing at a real person.
     *
     * Called over AJAX, so it answers JSON — a redirect here left the page
     * showing "Network error occurred" while the change had actually gone through.
     */
    public function delete($id)
    {
        $this->requireAuth();

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Employee not found']);
        }

        $this->employeeModel->update($id, ['is_active' => 0]);
        \App\Models\AuditLogModel::record('employee_deactivated', (int) session('user_id'), 'employees', (int) $id, [
            'employee_code' => $employee['employee_code'],
        ]);
        session()->setFlashdata('success', 'Employee deactivated. They are hidden from lists but their history is intact.');

        return $this->response->setJSON(['success' => true, 'message' => 'Employee deactivated']);
    }

    /** Put a deactivated employee back to work. */
    public function restore($id)
    {
        $this->requireAuth();

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Employee not found']);
        }

        $this->employeeModel->update($id, ['is_active' => 1]);
        \App\Models\AuditLogModel::record('employee_reactivated', (int) session('user_id'), 'employees', (int) $id, [
            'employee_code' => $employee['employee_code'],
        ]);
        session()->setFlashdata('success', 'Employee reactivated');

        return $this->response->setJSON(['success' => true, 'message' => 'Employee reactivated']);
    }

    /**
     * Permanent delete — only for records with no history behind them
     * (a typo, a duplicate). Anyone with payslips or cheques must be
     * deactivated instead, or the accounts would point at nothing.
     */
    public function destroy($id)
    {
        $this->requireAuth();

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Employee not found']);
        }

        $blockers = $this->historyBlockers((int) $id);
        if ($blockers) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'This employee cannot be deleted because of ' . implode(' and ', $blockers)
                    . '. Deactivate them instead so the records stay intact.',
            ]);
        }

        $this->skillModel->where('employee_id', $id)->delete();
        $this->employeeModel->delete($id, true);

        // The row is gone for good, so keep enough of it to answer "who deleted whom".
        \App\Models\AuditLogModel::record('employee_deleted', (int) session('user_id'), 'employees', (int) $id, [
            'employee_code' => $employee['employee_code'],
            'name'          => trim($employee['first_name'] . ' ' . $employee['last_name']),
            'department'    => $employee['department'],
        ]);
        session()->setFlashdata('success', 'Employee deleted permanently');

        return $this->response->setJSON(['success' => true, 'message' => 'Employee deleted permanently']);
    }

    /** Human-readable reasons an employee record must be kept. */
    private function historyBlockers(int $id): array
    {
        $db     = \Config\Database::connect();
        $labels = [
            'salary_payments'              => 'salary slip',
            'cheques'                      => 'cheque',
            'process_employee_assignments' => 'work assignment',
            'process_batch_employees'      => 'batch assignment',
        ];

        $blockers = [];
        foreach ($labels as $table => $label) {
            if (!$db->tableExists($table)) {
                continue;
            }
            $n = (int) $db->table($table)->where('employee_id', $id)->countAllResults();
            if ($n > 0) {
                $blockers[] = $n . ' ' . $label . ($n === 1 ? '' : 's');
            }
        }

        return $blockers;
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
