<?php

namespace App\Controllers;

use App\Database\Seeds\RbacSeeder;
use App\Filters\SetupFilter;
use App\Models\CompanySettingsModel;
use App\Models\UserModel;
use App\Services\SystemBackupService;
use Throwable;

/**
 * First-run setup wizard. Reachable only while there is no users row in the
 * database (see App\Filters\SetupFilter) — once an admin account exists,
 * every route (including this one) redirects to the normal login page.
 */
class Setup extends BaseController
{
    public function index()
    {
        if (SetupFilter::isInstalled()) {
            return redirect()->to('/auth/login');
        }

        return view('setup/index', [
            'active_tab' => session()->getFlashdata('active_tab') ?: 'fresh',
        ]);
    }

    /** Runs migrations + RBAC seed, then creates the company profile and first admin user. */
    public function runFresh()
    {
        if (!$this->request->is('post')) {
            return redirect()->to('/setup');
        }
        if (SetupFilter::isInstalled()) {
            return redirect()->to('/auth/login');
        }

        $rules = [
            'company_name'     => 'required|min_length[2]|max_length[150]',
            'admin_username'   => 'required|min_length[3]|max_length[50]',
            'admin_email'      => 'required|valid_email',
            'admin_first_name' => 'required|min_length[2]|max_length[50]',
            'admin_last_name'  => 'required|min_length[2]|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/setup')->withInput()
                ->with('validation', \Config\Services::validation())
                ->with('active_tab', 'fresh');
        }

        // Trimmed by hand, not via the "matches" rule: a trailing space or
        // newline pasted in from a password manager made the two fields
        // compare unequal even though the user typed the same password.
        $password        = trim((string) $this->request->getPost('admin_password'));
        $passwordConfirm = trim((string) $this->request->getPost('admin_password_confirm'));

        if (strlen($password) < 8) {
            return redirect()->to('/setup')->withInput()
                ->with('error', 'Password must be at least 8 characters.')
                ->with('active_tab', 'fresh');
        }
        if ($password !== $passwordConfirm) {
            return redirect()->to('/setup')->withInput()
                ->with('error', 'Password and confirmation do not match. Use the eye icon to check what you typed.')
                ->with('active_tab', 'fresh');
        }

        try {
            (new SystemBackupService())->installFreshSchema();
            \Config\Database::seeder()->call(RbacSeeder::class);

            (new CompanySettingsModel())->insert([
                'name'    => (string) $this->request->getPost('company_name'),
                'address' => (string) $this->request->getPost('company_address'),
                'phone'   => (string) $this->request->getPost('company_phone'),
                'email'   => (string) $this->request->getPost('company_email'),
            ]);

            $userModel = new UserModel();
            $userModel->skipValidation(true);
            $adminId = $userModel->insert([
                'username'   => (string) $this->request->getPost('admin_username'),
                'email'      => (string) $this->request->getPost('admin_email'),
                'password'   => $password,
                'first_name' => (string) $this->request->getPost('admin_first_name'),
                'last_name'  => (string) $this->request->getPost('admin_last_name'),
                'role'       => 'admin',
                'role_id'    => 1,
                'is_active'  => 1,
            ]);

            if (!$adminId) {
                throw new \RuntimeException('Failed to create the admin user.');
            }

            $userModel->syncRoles((int) $adminId, [1]);

            return redirect()->to('/auth/login')->with('success', 'CoreLynk is set up. Sign in with your new admin account.');
        } catch (Throwable $e) {
            log_message('error', 'Setup runFresh error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            return redirect()->to('/setup')->withInput()
                ->with('error', 'Setup failed: ' . $e->getMessage())
                ->with('active_tab', 'fresh');
        }
    }

    /** Restores an uploaded backup .zip directly, with no job history to look up (there can't be any yet). */
    public function restore()
    {
        if (!$this->request->is('post')) {
            return redirect()->to('/setup');
        }
        if (SetupFilter::isInstalled()) {
            return redirect()->to('/auth/login');
        }

        $file = $this->request->getFile('backup_file');
        if (!$file || !$file->isValid()) {
            return redirect()->to('/setup')->with('error', 'Please choose a valid CoreLynk backup .zip file.')->with('active_tab', 'restore');
        }
        if (strtolower((string) $file->getClientExtension()) !== 'zip') {
            return redirect()->to('/setup')->with('error', "Backup file must be a .zip produced by CoreLynk's backup tool.")->with('active_tab', 'restore');
        }

        $incomingDir = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'incoming';
        if (!is_dir($incomingDir)) {
            mkdir($incomingDir, 0775, true);
        }
        $stagedName = 'setup_restore_' . bin2hex(random_bytes(8)) . '.zip';

        try {
            $file->move($incomingDir, $stagedName);
            (new SystemBackupService())->restoreFromUploadedArchive($incomingDir . DIRECTORY_SEPARATOR . $stagedName, 'db_only');

            return redirect()->to('/auth/login')->with('success', 'Backup restored. Sign in with an account from the restored data.');
        } catch (Throwable $e) {
            log_message('error', 'Setup restore error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            return redirect()->to('/setup')->with('error', 'Restore failed: ' . $e->getMessage())->with('active_tab', 'restore');
        } finally {
            @unlink($incomingDir . DIRECTORY_SEPARATOR . $stagedName);
        }
    }
}
