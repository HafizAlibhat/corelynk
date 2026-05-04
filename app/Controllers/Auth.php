<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\LoginAttemptModel;
use App\Models\AuditLogModel;
use App\Models\AuthLogModel;
use App\Models\FeatureFlagModel;
use App\Libraries\RoleDataAccess;
use Config\Database;

class Auth extends BaseController
{
    protected $userModel;
    protected $loginAttemptModel;

    /** Max failed attempts before lockout */
    private const MAX_ATTEMPTS   = 5;
    /** Lockout window in minutes */
    private const LOCKOUT_WINDOW = 15;

    public function __construct()
    {
        $this->userModel        = new UserModel();
        $this->loginAttemptModel = new LoginAttemptModel();
    }

    // ------------------------------------------------------------------
    //  LOGIN
    // ------------------------------------------------------------------

    public function login()
    {
        if ($this->isLoggedIn()) {
            return redirect()->to('/');
        }

        return view('auth/login', $this->setPageData([
            'page_title' => 'Login – CoreLynk',
        ]));
    }

    public function processLogin()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (!$this->validate($rules)) {
            return view('auth/login', $this->setPageData([
                'page_title' => 'Login – CoreLynk',
                'validation' => \Config\Services::validation(),
            ]));
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // ── Brute-force check ────────────────────────────────────
        if ($this->userModel->isAccountLocked($email)) {
            $this->loginAttemptModel->record($email, false);
            AuthLogModel::record(AuthLogModel::ACTION_LOGIN_FAILED, null, $email, [
                'reason' => 'account_locked',
            ]);
            $this->session->setFlashdata('error', 'Account locked due to too many failed attempts. Try again in ' . self::LOCKOUT_WINDOW . ' minutes.');
            return redirect()->to('/auth/login');
        }

        // Also check IP-based throttle
        $ipFails = $this->loginAttemptModel->recentFailedCountByIp(
            $this->request->getIPAddress(), self::LOCKOUT_WINDOW
        );
        if ($ipFails >= self::MAX_ATTEMPTS * 3) { // IP gets 3× more leeway
            AuthLogModel::record(AuthLogModel::ACTION_LOGIN_FAILED, null, $email, [
                'reason' => 'ip_throttled',
            ]);
            $this->session->setFlashdata('error', 'Too many login attempts from this IP. Try again later.');
            return redirect()->to('/auth/login');
        }

        // ── Verify credentials ───────────────────────────────────
        $user = $this->userModel->verifyCredentialsByEmail($email, $password);

        if (!$user) {
            $this->loginAttemptModel->record($email, false);
            $this->userModel->recordFailedLogin($email, self::MAX_ATTEMPTS, self::LOCKOUT_WINDOW);

            // Log to dedicated auth_logs table
            AuthLogModel::record(AuthLogModel::ACTION_LOGIN_FAILED, null, $email, [
                'reason' => 'invalid_credentials',
            ]);

            // Compute remaining attempts
            $failedCount = $this->loginAttemptModel->recentFailedCount($email, self::LOCKOUT_WINDOW);
            $remaining   = max(0, self::MAX_ATTEMPTS - $failedCount);
            $msg = 'Invalid email or password.';
            if ($remaining > 0 && $remaining <= 3) {
                $msg .= " {$remaining} attempt(s) remaining before lockout.";
            }
            $this->session->setFlashdata('error', $msg);
            return redirect()->to('/auth/login');
        }

        // ── MFA check (if enabled) ──────────────────────────────
        $db  = \Config\Database::connect();
        $mfa = $db->table('user_mfa')->where('user_id', $user['id'])->where('mfa_enabled', 1)->get()->getRowArray();
        if ($mfa) {
            // Store partial session and redirect to MFA verify page
            $this->session->set([
                'mfa_pending_user_id' => $user['id'],
                'mfa_pending_email'   => $user['email'],
            ]);
            return redirect()->to('/auth/mfa-verify');
        }

        // If global MFA enforcement is enabled, route users to MFA setup
        // immediately after login when they do not have MFA enabled yet.
        if (FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_ENABLE_2FA)) {
            $this->completeLogin($user);
            $this->session->setFlashdata('warning', 'Two-factor authentication is required by admin policy. Please enable MFA to continue securely.');
            return redirect()->to('/auth/mfa-setup');
        }

        // ── Complete login ───────────────────────────────────────
        $this->completeLogin($user);

        // Honor intended destination set by AuthFilter when safe.
        $redirectUrl = $this->consumeSafeRedirectUrl();
        return redirect()->to($redirectUrl ?: '/');
    }

    /**
     * Finalise login: set session, record audit, clear lockout.
     */
    protected function completeLogin(array $user): void
    {
        // Prevent session fixation by rotating ID on successful login.
        $this->session->regenerate(true);

        // Load roles
        $roles = $this->userModel->getRoles((int) $user['id']);
        $roleSlugs = array_column($roles, 'slug');
        $primaryRole = $roleSlugs[0] ?? ($user['role'] ?? 'viewer');

        $this->session->set([
            'user_id'    => $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'role'       => $primaryRole,
            'role_slugs' => $roleSlugs,
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'tenant_id'  => $user['tenant_id'] ?? 1,
            'logged_in'  => true,
        ]);

        // Record successful attempt
        $this->loginAttemptModel->record($user['email'], true);

        // Audit
        AuditLogModel::record('login', (int) $user['id'], 'auth', null, [
            'ip' => $this->request->getIPAddress(),
        ]);

        // Dedicated auth log
        AuthLogModel::record(AuthLogModel::ACTION_LOGIN_SUCCESS, (int) $user['id'], $user['email']);

        $this->session->setFlashdata('success', 'Welcome back, ' . esc($user['first_name']) . '!');
    }

    /**
     * Get and clear the intended redirect URL from session when it is local.
     */
    protected function consumeSafeRedirectUrl(): ?string
    {
        $redirectUrl = (string) ($this->session->get('redirect_url') ?? '');
        $this->session->remove('redirect_url');

        if ($redirectUrl === '') {
            return null;
        }

        // Accept only same-host absolute URLs or app-relative paths.
        if (str_starts_with($redirectUrl, '/')) {
            // Strip the base URL path prefix so redirect()->to() doesn't double-prepend it.
            $basePath = rtrim((string) parse_url(base_url('/'), PHP_URL_PATH), '/');
            if ($basePath !== '' && str_starts_with($redirectUrl, $basePath)) {
                $redirectUrl = substr($redirectUrl, strlen($basePath)) ?: '/';
            }
            return $redirectUrl;
        }

        $baseHost = parse_url(base_url('/'), PHP_URL_HOST);
        $targetHost = parse_url($redirectUrl, PHP_URL_HOST);

        if ($baseHost && $targetHost && strcasecmp((string) $baseHost, (string) $targetHost) === 0) {
            $targetPath = (string) parse_url($redirectUrl, PHP_URL_PATH);
            $targetQuery = (string) parse_url($redirectUrl, PHP_URL_QUERY);
            $basePath = rtrim((string) parse_url(base_url('/'), PHP_URL_PATH), '/');

            if ($basePath === '') {
                return $targetPath !== '' ? ($targetPath . ($targetQuery !== '' ? ('?' . $targetQuery) : '')) : '/';
            }

            if (str_starts_with($targetPath, $basePath)) {
                $relative = substr($targetPath, strlen($basePath));
                if ($relative === false || $relative === '') {
                    $relative = '/';
                }
                if ($relative[0] !== '/') {
                    $relative = '/' . $relative;
                }
                return $relative . ($targetQuery !== '' ? ('?' . $targetQuery) : '');
            }

            return '/';
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  MFA  (Time-based OTP)
    // ------------------------------------------------------------------

    /**
     * Show MFA verification form.
     */
    public function mfaVerify()
    {
        if (!$this->session->get('mfa_pending_user_id')) {
            return redirect()->to('/auth/login');
        }
        return view('auth/mfa_verify', $this->setPageData([
            'page_title' => 'Two-Factor Authentication – CoreLynk',
        ]));
    }

    /**
     * Process MFA code submission.
     */
    public function mfaVerifyProcess()
    {
        $userId = $this->session->get('mfa_pending_user_id');
        if (!$userId) {
            return redirect()->to('/auth/login');
        }

        $code = trim($this->request->getPost('code') ?? '');
        if (strlen($code) < 6) {
            $this->session->setFlashdata('error', 'Please enter a valid 6-digit code.');
            return redirect()->to('/auth/mfa-verify');
        }

        $db  = \Config\Database::connect();
        $mfa = $db->table('user_mfa')->where('user_id', $userId)->where('mfa_enabled', 1)->get()->getRowArray();
        if (!$mfa) {
            return redirect()->to('/auth/login');
        }

        // Verify TOTP (simple HMAC-based OTP)
        $valid = $this->verifyTotp($mfa['mfa_secret'], $code);

        // Check recovery codes if TOTP failed
        if (!$valid && $mfa['recovery_codes']) {
            $recoveryCodes = json_decode($mfa['recovery_codes'], true) ?: [];
            foreach ($recoveryCodes as $idx => $hashedCode) {
                if (password_verify($code, $hashedCode)) {
                    $valid = true;
                    // Consume the code
                    unset($recoveryCodes[$idx]);
                    $db->table('user_mfa')->where('id', $mfa['id'])->update([
                        'recovery_codes' => json_encode(array_values($recoveryCodes)),
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);
                    break;
                }
            }
        }

        if (!$valid) {
            AuthLogModel::record(AuthLogModel::ACTION_MFA_FAILED, $userId, $this->session->get('mfa_pending_email'));
            $this->session->setFlashdata('error', 'Invalid verification code.');
            return redirect()->to('/auth/mfa-verify');
        }

        // Clear MFA pending state
        $this->session->remove(['mfa_pending_user_id', 'mfa_pending_email']);

        // Complete login
        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/auth/login');
        }
        unset($user['password_hash']);
        $this->completeLogin($user);
        $redirectUrl = $this->consumeSafeRedirectUrl();
        return redirect()->to($redirectUrl ?: '/');
    }

    /**
     * Simple TOTP verifier (RFC 6238 compatible, 30-second window).
     */
    protected function verifyTotp(string $secret, string $code): bool
    {
        $timeSlice = floor(time() / 30);
        $secretBytes = base64_decode($secret);
        if (!$secretBytes) return false;

        // Allow ±1 time window for clock drift
        for ($i = -1; $i <= 1; $i++) {
            $t = pack('N*', 0, (int) ($timeSlice + $i));
            $hash = hash_hmac('sha1', $t, $secretBytes, true);
            $offset = ord($hash[19]) & 0x0F;
            $otp = (
                ((ord($hash[$offset]) & 0x7F) << 24) |
                ((ord($hash[$offset + 1]) & 0xFF) << 16) |
                ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
                (ord($hash[$offset + 3]) & 0xFF)
            ) % 1000000;
            if (str_pad((string) $otp, 6, '0', STR_PAD_LEFT) === $code) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    //  LOGOUT
    // ------------------------------------------------------------------

    public function logout()
    {
        $userId = $this->session->get('user_id');
        $email  = $this->session->get('email');
        if ($userId) {
            AuditLogModel::record('logout', (int) $userId, 'auth');
            AuthLogModel::record(AuthLogModel::ACTION_LOGOUT, (int) $userId, $email);
        }

        $this->session->remove(['redirect_url', 'mfa_pending_user_id', 'mfa_pending_email']);
        $this->session->destroy();
        return redirect()->to('/auth/login')->with('success', 'Logged out successfully.');
    }

    // ------------------------------------------------------------------
    //  MFA SETUP
    // ------------------------------------------------------------------

    /**
     * Show MFA enrollment page – generate secret, display QR code URL.
     */
    public function mfaSetup()
    {
        $this->requireAuth();
        $userId = (int) $this->currentUser['id'];

        $db  = \Config\Database::connect();
        $mfa = $db->table('user_mfa')->where('user_id', $userId)->get()->getRowArray();

        $secret  = '';
        $enabled = false;

        if ($mfa) {
            $secret  = $mfa['mfa_secret'];
            $enabled = (bool) $mfa['mfa_enabled'];
        } else {
            // Generate a new random 20-byte secret
            $secret = base64_encode(random_bytes(20));
        }

        // Build otpauth:// URI for QR code
        $issuer = 'CoreLynk';
        $email  = $this->currentUser['email'] ?? 'user';
        $otpUri = 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
                . '?secret=' . $this->base32Encode(base64_decode($secret))
                . '&issuer=' . rawurlencode($issuer)
                . '&algorithm=SHA1&digits=6&period=30';

        return view('auth/mfa_setup', $this->setPageData([
            'page_title'  => 'MFA Setup – CoreLynk',
            'secret_b32'  => $this->base32Encode(base64_decode($secret)),
            'secret_b64'  => $secret,
            'otp_uri'     => $otpUri,
            'mfa_enabled' => $enabled,
        ]));
    }

    /**
     * Process MFA enrollment – verify the first TOTP code then enable MFA.
     */
    public function mfaSetupProcess()
    {
        $this->requireAuth();
        $userId = (int) $this->currentUser['id'];

        $action    = $this->request->getPost('action');
        $secretB64 = $this->request->getPost('secret');
        $code      = trim($this->request->getPost('code') ?? '');
        $db        = \Config\Database::connect();

        // ── Disable MFA ──────────────────────────────────────────
        if ($action === 'disable') {
            if (FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_ENABLE_2FA)) {
                return redirect()->to('/auth/mfa-setup')->with('error', 'MFA cannot be disabled while enforced by admin security policy.');
            }

            $db->table('user_mfa')->where('user_id', $userId)->update([
                'mfa_enabled' => 0,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            AuditLogModel::record('mfa_disabled', $userId, 'auth');
            return redirect()->to('/auth/mfa-setup')->with('success', 'Two-factor authentication has been disabled.');
        }

        // ── Enable MFA (verify first code) ───────────────────────
        if (strlen($code) < 6 || !$secretB64) {
            return redirect()->to('/auth/mfa-setup')->with('error', 'Please enter a valid 6-digit code from your authenticator app.');
        }

        if (!$this->verifyTotp($secretB64, $code)) {
            return redirect()->to('/auth/mfa-setup')->with('error', 'Invalid code. Please scan the QR code again and try a fresh code.');
        }

        // Generate 8 recovery codes
        $recoveryCodes = [];
        $hashedCodes   = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(4))); // 8-char hex
            $recoveryCodes[] = $plain;
            $hashedCodes[]   = password_hash($plain, PASSWORD_BCRYPT);
        }

        // Upsert MFA record
        $existing = $db->table('user_mfa')->where('user_id', $userId)->get()->getRowArray();
        $data = [
            'user_id'        => $userId,
            'mfa_secret'     => $secretB64,
            'mfa_enabled'    => 1,
            'recovery_codes' => json_encode($hashedCodes),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $db->table('user_mfa')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('user_mfa')->insert($data);
        }

        AuditLogModel::record('mfa_enabled', $userId, 'auth');

        // Show recovery codes once
        $this->session->setFlashdata('recovery_codes', $recoveryCodes);
        $this->session->setFlashdata('success', 'Two-factor authentication is now enabled! Save your recovery codes below.');
        return redirect()->to('/auth/mfa-setup');
    }

    /**
     * RFC 4648 Base32 encoder (for authenticator apps).
     */
    protected function base32Encode(string $data): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 5) * 5), '0');
        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $result .= $chars[bindec($chunk)];
        }
        // Pad to multiple of 8
        while (strlen($result) % 8 !== 0) {
            $result .= '=';
        }
        return $result;
    }

    // ------------------------------------------------------------------
    //  REGISTRATION  (admin-only)
    // ------------------------------------------------------------------

    public function register()
    {
        $this->requireAuth();
        $policy = service('policy');
        if (!$policy->isAdmin()) {
            return redirect()->to('/')->with('error', 'Only admins can register new users.');
        }

        $roleModel = new \App\Models\RoleModel();
        return view('auth/register', $this->setPageData([
            'page_title' => 'Register User – CoreLynk',
            'roles'      => $roleModel->findAll(),
        ]));
    }

    public function attemptRegister()
    {
        $this->requireAuth();
        $policy = service('policy');
        if (!$policy->isAdmin()) {
            return redirect()->to('/')->with('error', 'Only admins can register new users.');
        }

        $rules = [
            'username'         => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'confirm_password' => 'required|matches[password]',
            'first_name'       => 'required|min_length[2]|max_length[50]',
            'last_name'        => 'required|min_length[2]|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            $roleModel = new \App\Models\RoleModel();
            return view('auth/register', $this->setPageData([
                'page_title' => 'Register User – CoreLynk',
                'roles'      => $roleModel->findAll(),
                'validation' => \Config\Services::validation(),
            ]));
        }

        $roleIds = $this->request->getPost('role_ids') ?: [];
        $roleIds = array_map('intval', (array) $roleIds);

        // Determine legacy role slug from first selected role
        $legacyRole = 'viewer';
        if (!empty($roleIds)) {
            $db = \Config\Database::connect();
            $r  = $db->table('roles')->where('id', $roleIds[0])->get()->getRowArray();
            if ($r) $legacyRole = $r['slug'];
        }

        $userData = [
            'username'   => $this->request->getPost('username'),
            'email'      => $this->request->getPost('email'),
            'password'   => $this->request->getPost('password'),
            'first_name' => $this->request->getPost('first_name'),
            'last_name'  => $this->request->getPost('last_name'),
            'role'       => $legacyRole,
            'role_id'    => $roleIds[0] ?? null,
            'is_active'  => 1,
        ];

        $this->userModel->skipValidation(true);
        $newId = $this->userModel->insert($userData);
        if ($newId) {
            if (!empty($roleIds)) {
                $this->userModel->syncRoles($newId, $roleIds);
            }

            AuditLogModel::record('user_created', (int) $this->session->get('user_id'), 'users', $newId, [
                'username' => $userData['username'],
                'roles'    => $roleIds,
            ]);

            return redirect()->to('/admin/users')->with('success', 'User created successfully.');
        }

        return redirect()->back()->with('error', 'Failed to create user.');
    }

    // ------------------------------------------------------------------
    //  PASSWORD MANAGEMENT
    // ------------------------------------------------------------------

    public function forgotPassword()
    {
        if ($this->isLoggedIn()) return redirect()->to('/');
        return view('auth/forgot_password', $this->setPageData([
            'page_title' => 'Forgot Password – CoreLynk',
        ]));
    }

    public function sendResetLink()
    {
        $email = $this->request->getPost('email');
        // Always show the same message to prevent user enumeration
        $this->session->setFlashdata('success', 'If an account with that email exists, a password reset link has been sent.');
        return redirect()->to('/auth/login');
    }

    public function resetPassword($token = null)
    {
        return view('auth/reset_password', $this->setPageData([
            'page_title' => 'Reset Password – CoreLynk',
            'token'      => $token,
        ]));
    }

    public function updatePassword()
    {
        // Placeholder – in production, validate the token, then update.
        return redirect()->to('/auth/login')->with('success', 'Password updated successfully.');
    }

    public function changePassword()
    {
        $this->requireAuth();
        return view('auth/change_password', $this->setPageData([
            'page_title' => 'Change Password – CoreLynk',
        ]));
    }

    public function processChangePassword()
    {
        $this->requireAuth();

        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]',
        ];
        if (!$this->validate($rules)) {
            return view('auth/change_password', $this->setPageData([
                'page_title' => 'Change Password – CoreLynk',
                'validation' => \Config\Services::validation(),
            ]));
        }

        $user = $this->userModel->find($this->currentUser['id']);
        if (!password_verify($this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->to('/auth/change-password')->with('error', 'Current password is incorrect.');
        }

        $this->userModel->skipValidation(true)->update($this->currentUser['id'], [
            'password' => $this->request->getPost('new_password'),
        ]);

        AuditLogModel::record('password_changed', (int) $this->currentUser['id'], 'auth');
        AuthLogModel::record(AuthLogModel::ACTION_PASSWORD_CHANGED, (int) $this->currentUser['id'], $this->currentUser['email']);

        return redirect()->to('/')->with('success', 'Password changed successfully.');
    }

    // ------------------------------------------------------------------
    //  USER SETTINGS (document privacy)
    // ------------------------------------------------------------------

    public function userSettings()
    {
        $this->requireAuth();
        $userId = (int) $this->currentUser['id'];
        $canPrivate = (new RoleDataAccess())->canMakeDocumentsPrivate($userId);

        // Read current documents_private value for this user
        $documentsPrivate = 0;
        try {
            $db = Database::connect();
            if ($db->fieldExists('documents_private', 'users')) {
                $row = $db->table('users')->select('documents_private')->where('id', $userId)->get()->getRowArray();
                $documentsPrivate = (int) ($row['documents_private'] ?? 0);
            }
        } catch (\Throwable $_) {
        }

        return view('auth/user_settings', $this->setPageData([
            'page_title'        => 'My Settings – CoreLynk',
            'can_private'       => $canPrivate,
            'documents_private' => $documentsPrivate,
        ]));
    }

    public function saveUserSettings()
    {
        $this->requireAuth();
        $userId = (int) $this->currentUser['id'];

        // Only update documents_private if admin has granted this user the permission
        if ((new RoleDataAccess())->canMakeDocumentsPrivate($userId)) {
            $value = $this->request->getPost('documents_private') ? 1 : 0;
            try {
                $db = Database::connect();
                if (! $db->fieldExists('documents_private', 'users')) {
                    $db->query('ALTER TABLE users ADD COLUMN documents_private TINYINT(1) NOT NULL DEFAULT 0');
                }
            } catch (\Throwable $_) {
            }
            $this->userModel->skipValidation(true)->update($userId, ['documents_private' => $value]);
            AuditLogModel::record('user_settings_updated', $userId, 'users', $userId, ['documents_private' => $value]);
        }

        return redirect()->to('/auth/settings')->with('success', 'Settings saved.');
    }
}
