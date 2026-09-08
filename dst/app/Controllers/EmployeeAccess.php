<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\EmployeeModel;
use App\Models\RoleModel;
use App\Models\UserModel;

/**
 * System access for an employee: the login they use to get into CoreLynk.
 *
 * Employees and users stay separate records — owners and service accounts have
 * logins without being on the payroll — and `employees.user_id` joins the two.
 * Everything here runs under the admin route group, so only admins can hand out
 * or revoke access; the employee page shows the same information read-only to
 * everyone else.
 */
class EmployeeAccess extends BaseController
{
    protected EmployeeModel $employees;
    protected UserModel $users;

    public function __construct()
    {
        $this->employees = new EmployeeModel();
        $this->users     = new UserModel();
    }

    /** Link an existing login, or create a new one for this employee. */
    public function save(int $employeeId)
    {
        $employee = $this->employees->find($employeeId);
        if (! $employee) {
            return redirect()->to('/employees')->with('error', 'Employee not found.');
        }

        $back = '/employees/' . $employeeId;

        if ($this->request->getPost('mode') === 'link') {
            $userId = (int) $this->request->getPost('user_id');
            $user   = $userId > 0 ? $this->users->find($userId) : null;
            if (! $user) {
                return redirect()->to($back)->with('error', 'Choose a user account to link.');
            }
            if ($this->employees->where('user_id', $userId)->where('id !=', $employeeId)->first()) {
                return redirect()->to($back)->with('error', 'That login is already linked to another employee.');
            }

            $this->employees->update($employeeId, ['user_id' => $userId] + $this->emailFromLogin($employee, $user['email']));
            AuditLogModel::record('employee_user_linked', (int) session('user_id'), 'employees', $employeeId, [
                'user_id' => $userId,
            ]);

            return redirect()->to($back)->with('success', 'Login ' . $user['username'] . ' linked to this employee.');
        }

        // Creating a brand new login.
        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to($back)->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $roleIds = array_map('intval', (array) ($this->request->getPost('role_ids') ?: []));
        $newId   = $this->users->skipValidation(true)->insert([
            'username'   => $this->request->getPost('username'),
            'email'      => $this->request->getPost('email'),
            'password'   => $this->request->getPost('password'),
            'first_name' => $employee['first_name'],
            'last_name'  => $employee['last_name'],
            'role'       => $this->legacySlug($roleIds[0] ?? null),
            'role_id'    => $roleIds[0] ?? null,
            'is_active'  => 1,
        ]);

        if (! $newId) {
            return redirect()->to($back)->with('error', 'Could not create the login.');
        }

        if ($roleIds) {
            $this->users->syncRoles($newId, $roleIds);
        }
        $this->employees->update($employeeId, ['user_id' => $newId]
            + $this->emailFromLogin($employee, (string) $this->request->getPost('email')));

        AuditLogModel::record('user_created', (int) session('user_id'), 'users', $newId, [
            'username'    => $this->request->getPost('username'),
            'roles'       => $roleIds,
            'employee_id' => $employeeId,
        ]);

        return redirect()->to($back)->with('success', 'Login created. The employee can sign in with their email or username.');
    }

    /**
     * Carry the login's email onto the employee record when it has none, so
     * HR does not have to type it twice. An email already on the employee is
     * kept — the employee page then shows it as the secondary address.
     *
     * @return array<string, string> the email field to merge into the update, or []
     */
    private function emailFromLogin(array $employee, string $loginEmail): array
    {
        return (trim((string) $employee['email']) === '' && $loginEmail !== '')
            ? ['email' => $loginEmail]
            : [];
    }

    /** Unlink only — the user account itself is left for Settings to manage. */
    public function unlink(int $employeeId)
    {
        $employee = $this->employees->find($employeeId);
        if (! $employee) {
            return redirect()->to('/employees')->with('error', 'Employee not found.');
        }

        $this->employees->update($employeeId, ['user_id' => null]);
        AuditLogModel::record('employee_user_unlinked', (int) session('user_id'), 'employees', $employeeId, [
            'user_id' => $employee['user_id'],
        ]);

        return redirect()->to('/employees/' . $employeeId)
            ->with('success', 'Login unlinked. The user account still exists under Settings → Users.');
    }

    /** Allow or block this employee from signing in. */
    public function toggle(int $employeeId)
    {
        [$employee, $user] = $this->pair($employeeId);
        if (! $user) {
            return redirect()->to('/employees/' . $employeeId)->with('error', 'This employee has no login yet.');
        }

        $active = $user['is_active'] ? 0 : 1;
        $this->users->skipValidation(true)->update($user['id'], ['is_active' => $active]);
        AuditLogModel::record($active ? 'user_activated' : 'user_deactivated',
            (int) session('user_id'), 'users', (int) $user['id']);

        return redirect()->to('/employees/' . $employeeId)
            ->with('success', $active ? 'Sign-in enabled.' : 'Sign-in blocked. The account and its history stay in place.');
    }

    /** Set a new password for the employee's login. */
    public function password(int $employeeId)
    {
        [$employee, $user] = $this->pair($employeeId);
        if (! $user) {
            return redirect()->to('/employees/' . $employeeId)->with('error', 'This employee has no login yet.');
        }

        $password = (string) $this->request->getPost('password');
        if (strlen($password) < 8) {
            return redirect()->to('/employees/' . $employeeId)->with('error', 'The password must be at least 8 characters.');
        }

        // Clear the lockout too: a reset is usually the answer to a locked account.
        $this->users->skipValidation(true)->update($user['id'], [
            'password'           => $password,
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);
        AuditLogModel::record('user_password_reset', (int) session('user_id'), 'users', (int) $user['id']);

        return redirect()->to('/employees/' . $employeeId)
            ->with('success', 'Password updated for ' . $user['username'] . '.');
    }

    /** @return array{0: array|null, 1: array|null} */
    private function pair(int $employeeId): array
    {
        $employee = $this->employees->find($employeeId);
        $user     = $employee && $employee['user_id'] ? $this->users->find($employee['user_id']) : null;

        return [$employee, $user];
    }

    /** The users table keeps a legacy role slug alongside role_id. */
    private function legacySlug(?int $roleId): string
    {
        if (! $roleId) {
            return 'viewer';
        }

        $role  = (new RoleModel())->find($roleId);
        $slug  = strtolower(trim((string) ($role['slug'] ?? '')));
        $known = ['admin', 'planner', 'production', 'qc', 'stores', 'accounts', 'viewer'];

        if (in_array($slug, $known, true)) {
            return $slug;
        }

        return $slug === 'warehouse' ? 'stores' : 'viewer';
    }
}
