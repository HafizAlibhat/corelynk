<?php

// Shared navigation data for every nav renderer (sidebar + top bar).
// Menu content comes only from app/Config/ModuleNav.php; visibility only from PolicyEngine.

if (! function_exists('cl_nav_modules')) {
    /**
     * Modules in config order, with submodules filtered to what the user may read.
     * Groups left with no visible submodule are dropped.
     */
    function cl_nav_modules(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $modules = require APPPATH . 'Config/ModuleNav.php';
        $policy  = service('policy');
        $isAdmin = $policy->isAdmin();

        $visible = [];
        foreach ($modules as $group => $data) {
            $subs = $data['submodules'] ?? [];

            if (! $isAdmin) {
                $subs = array_values(array_filter($subs, static function ($item) use ($policy) {
                    if (empty($item['perm'])) {
                        return true;
                    }
                    $parts = explode('.', $item['perm'], 2);

                    return $policy->can($parts[0], $parts[1] ?? 'read');
                }));
            }

            if ($subs === []) {
                continue;
            }

            $data['submodules'] = $subs;
            $visible[$group]    = $data;
        }

        return $cache = $visible;
    }
}

if (! function_exists('cl_nav_current_path')) {
    /** Current request path with the install sub-directory stripped, always leading-slashed. */
    function cl_nav_current_path(): string
    {
        static $path = null;
        if ($path !== null) {
            return $path;
        }

        $current  = (string) (parse_url(current_url(), PHP_URL_PATH) ?? '');
        $basePath = rtrim((string) (parse_url(base_url('/'), PHP_URL_PATH) ?? ''), '/');

        return $path = '/' . ltrim(substr($current, strlen($basePath)), '/');
    }
}

if (! function_exists('cl_nav_is_active')) {
    /** True when the given menu route is the current page or one of its children. */
    function cl_nav_is_active(string $route): bool
    {
        $normalized = '/' . trim($route, '/');
        $current    = cl_nav_current_path();

        return $current === $normalized || str_starts_with($current, $normalized . '/');
    }
}

if (! function_exists('cl_nav_module_id')) {
    /** Stable DOM id for a module group, shared by sidebar and top bar. */
    function cl_nav_module_id(string $group, string $prefix = 'nav'): string
    {
        return $prefix . '-' . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $group));
    }
}
