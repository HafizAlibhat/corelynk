<?php

/**
 * Security helpers — loaded automatically via BaseController.
 *
 * Provides global convenience functions for tenant isolation,
 * public ID resolution, and feature flag checks.
 */

if (! function_exists('getCurrentTenantId')) {
    /**
     * Get the current user's tenant ID from session.
     *
     * Returns null when not logged in or no tenant assigned,
     * which callers should treat as "no tenant filter".
     */
    function getCurrentTenantId(): ?int
    {
        $session = session();
        $tid = $session->get('tenant_id');
        return $tid !== null ? (int) $tid : null;
    }
}

if (! function_exists('featureEnabled')) {
    /**
     * Quick check if a feature flag is enabled.
     *
     * Safe to call before migration (returns false).
     */
    function featureEnabled(string $flagKey): bool
    {
        static $memo = [];
        if (array_key_exists($flagKey, $memo)) {
            return $memo[$flagKey];
        }
        return $memo[$flagKey] = \App\Models\FeatureFlagModel::isEnabled($flagKey);
    }
}

if (! function_exists('issueFormSubmissionToken')) {
    /**
     * Generate and store a one-time form submission token in session.
     */
    function issueFormSubmissionToken(string $formKey): string
    {
        $token = bin2hex(random_bytes(16));
        session()->set('__form_submit_token_' . $formKey, $token);
        return $token;
    }
}

if (! function_exists('consumeFormSubmissionToken')) {
    /**
     * Validate and consume a one-time token.
     */
    function consumeFormSubmissionToken(string $formKey, ?string $token): bool
    {
        $sessionKey = '__form_submit_token_' . $formKey;
        $stored = session()->get($sessionKey);
        if (!is_string($token) || $token === '' || !is_string($stored) || $stored === '') {
            return false;
        }
        if (!hash_equals($stored, $token)) {
            return false;
        }
        session()->remove($sessionKey);
        return true;
    }
}

if (! function_exists('entityRouteIdentifier')) {
    /**
     * Return the canonical route identifier for an entity.
     */
    function entityRouteIdentifier(?array $entity): string
    {
        if (empty($entity)) {
            return '';
        }

        static $publicIdsEnabled = null;
        if ($publicIdsEnabled === null) {
            $publicIdsEnabled = featureEnabled('enable_public_ids');
        }

        if ($publicIdsEnabled && !empty($entity['public_id'])) {
            return (string) $entity['public_id'];
        }

        return isset($entity['id']) ? (string) $entity['id'] : '';
    }
}

if (! function_exists('resolveEntityId')) {
    /**
     * Resolve a route parameter that could be a numeric ID or a public_id UUID.
     *
     * @param \CodeIgniter\Model $model  The model to query
     * @param string|int $identifier     The route parameter
     * @return array|null                The record, or null if not found
     */
    function resolveEntityId($model, $identifier): ?array
    {
        if (is_numeric($identifier)) {
            return $model->find((int) $identifier) ?: null;
        }

        // Try public_id lookup
        try {
            $db = \Config\Database::connect();
            static $publicIdColumns = [];
            $table = (string)$model->getTable();
            if (!array_key_exists($table, $publicIdColumns)) {
                $publicIdColumns[$table] = (bool)$db->fieldExists('public_id', $table);
            }
            if ($publicIdColumns[$table]) {
                return $model->where('public_id', $identifier)->first() ?: null;
            }
        } catch (\Throwable) {
            // Column doesn't exist yet — fall through
        }

        return null;
    }
}
