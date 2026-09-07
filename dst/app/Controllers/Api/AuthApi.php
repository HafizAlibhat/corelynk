<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\ApiTokenModel;
use App\Models\CompanySettingsModel;

/**
 * POST /api/login   — Exchange credentials for a bearer token
 * POST /api/logout  — Revoke the current token
 */
class AuthApi extends BaseApiController
{
    /**
     * POST /api/login
     *
     * Body (JSON or form-encoded):
     *   { "email": "...", "password": "..." }
     *
     * Success (200):
     *   { "success": true, "data": { "token": "...", "user": {...} }, "message": "Login successful" }
     *
     * Error (401):
     *   { "success": false, "data": null, "message": "Invalid credentials" }
     */
    public function login(): \CodeIgniter\HTTP\Response
    {
        $body = $this->getJsonBody();

        $email    = trim($body['email']    ?? '');
        $password = trim($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->error('email and password are required.', 422);
        }

        // Basic email format guard (not authoritative, just catches obvious mistakes)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format.', 422);
        }

        $userModel  = new UserModel();
        $user       = $userModel->verifyCredentialsByEmail($email, $password);

        if ($user === false) {
            // Intentionally vague to resist enumeration attacks
            return $this->error('Invalid credentials.', 401);
        }

        $tokenModel = new ApiTokenModel();
        $rawToken   = $tokenModel->createToken($user['id'], 'api');

        // Strip anything we don't want to expose in the payload
        $safeUser = array_filter($user, static fn($k) => !in_array($k, [
            'password', 'password_hash', 'failed_login_count', 'locked_until',
        ], true), ARRAY_FILTER_USE_KEY);

        // Include company settings (name, address, logo) so mobile can display
        // the company logo in PDFs without a separate API call.
        $companyModel    = new CompanySettingsModel();
        $companyRow      = $companyModel->first() ?? [];
        $serverBase      = $this->serverBaseUrl();
        $rawLogoPath     = $companyRow['logo_path'] ?? '';
        $logoUrl         = '';
        if (!empty($rawLogoPath)) {
            $logoUrl = str_starts_with($rawLogoPath, 'http')
                ? $rawLogoPath
                : $serverBase . '/' . ltrim($rawLogoPath, '/');
        }
        $company = [
            'name'        => $companyRow['name']    ?? '',
            'address'     => $companyRow['address'] ?? '',
            'phone'       => $companyRow['phone']   ?? $companyRow['contact'] ?? '',
            'email'       => $companyRow['email']   ?? '',
            'logo_path'   => $rawLogoPath,
            'logo_url'    => $logoUrl,
            'server_base' => $serverBase,
        ];

        return $this->success([
            'token'   => $rawToken,
            'user'    => array_values($safeUser) === $safeUser ? $safeUser : (object) $safeUser,
            'company' => $company,
        ], 'Login successful.');
    }

    /**
     * POST /api/logout
     *
     * Revokes the bearer token that was used to authenticate this request.
     * Requires a valid `Authorization: Bearer {token}` header.
     */
    public function logout(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }

        $tokenModel = new ApiTokenModel();
        $tokenModel->revokeByRawToken($this->rawToken);

        return $this->success(null, 'Logged out successfully.');
    }
}
