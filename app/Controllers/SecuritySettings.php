<?php

namespace App\Controllers;

use App\Models\FeatureFlagModel;
use App\Models\AuthLogModel;
use App\Models\AuditLogModel;

/**
 * Admin Security Settings controller.
 *
 * Provides a UI for admins to toggle security feature flags
 * and view authentication logs.
 */
class SecuritySettings extends BaseController
{
    /**
     * Display security settings dashboard with all feature flags.
     */
    public function index()
    {
        $this->requireAuth();
        $policy = service('policy');
        if (! $policy->isAdmin()) {
            return redirect()->to('/')->with('error', 'Access denied.');
        }

        $flagModel = new FeatureFlagModel();

        return view('admin/security_settings', $this->setPageData([
            'page_title' => 'Security Settings – CoreLynk',
            'flags'      => $flagModel->getAllFlags(),
        ]));
    }

    /**
     * Toggle a feature flag on/off.
     */
    public function toggleFlag()
    {
        $this->requireAuth();
        $policy = service('policy');
        if (! $policy->isAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $flagKey = $this->request->getPost('flag_key');
        $enabled = (bool) $this->request->getPost('enabled');

        if (empty($flagKey)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Missing flag_key.'], 400);
        }

        $flagModel = new FeatureFlagModel();
        $flagModel->setFlag($flagKey, $enabled);

        AuditLogModel::record('feature_flag_toggled', (int) $this->session->get('user_id'), 'security', null, [
            'flag_key' => $flagKey,
            'enabled'  => $enabled,
        ]);

        return $this->jsonResponse(['success' => true, 'flag_key' => $flagKey, 'enabled' => $enabled]);
    }

    /**
     * View authentication logs.
     */
    public function authLogs()
    {
        $this->requireAuth();
        $policy = service('policy');
        if (! $policy->isAdmin()) {
            return redirect()->to('/')->with('error', 'Access denied.');
        }

        $logModel = new AuthLogModel();

        $action = $this->request->getGet('action');
        $from   = $this->request->getGet('from');
        $to     = $this->request->getGet('to');

        return view('admin/auth_logs', $this->setPageData([
            'page_title' => 'Authentication Logs – CoreLynk',
            'logs'       => $logModel->search($action, null, $from, $to),
            'pager'      => $logModel->pager,
            'filters'    => ['action' => $action, 'from' => $from, 'to' => $to],
        ]));
    }
}
