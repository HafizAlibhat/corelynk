<?php

namespace App\Controllers\Api;

use App\Models\CompanySettingsModel;

/**
 * GET /api/company-info
 *
 * Returns company name, address, phone, email and the logo URL.
 * Requires a valid Bearer token.
 *
 * Response:
 *   {
 *     "success": true,
 *     "data": {
 *       "name": "Acme Corp",
 *       "address": "...",
 *       "phone": "...",
 *       "email": "...",
 *       "logo_path": "uploads/company/company-logo.png",
 *       "logo_url":  "http://host/app/public/uploads/company/company-logo.png",
 *       "server_base": "http://host/app/public"
 *     },
 *     "message": "OK"
 *   }
 */
class CompanyInfoApi extends BaseApiController
{
    public function show(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }

        $companyModel = new CompanySettingsModel();
        $row          = $companyModel->first() ?? [];

        $serverBase  = $this->serverBaseUrl();
        $rawLogoPath = $row['logo_path'] ?? '';
        $logoUrl     = '';
        if (!empty($rawLogoPath)) {
            $logoUrl = str_starts_with($rawLogoPath, 'http')
                ? $rawLogoPath
                : $serverBase . '/' . ltrim($rawLogoPath, '/');
        }

        return $this->success([
            'name'        => $row['name']    ?? '',
            'address'     => $row['address'] ?? '',
            'phone'       => $row['phone']   ?? $row['contact'] ?? '',
            'email'       => $row['email']   ?? '',
            'logo_path'   => $rawLogoPath,
            'logo_url'    => $logoUrl,
            'server_base' => $serverBase,
        ]);
    }
}
