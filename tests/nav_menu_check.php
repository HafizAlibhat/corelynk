<?php

/**
 * Standalone sanity check for app/Config/ModuleNav.php (the navigation source of truth).
 * PHPUnit needs a live DB here, so this runs on its own:  php tests/nav_menu_check.php
 */

$modules = require __DIR__ . '/../app/Config/ModuleNav.php';

assert($modules !== [], 'ModuleNav must not be empty');

foreach ($modules as $group => $data) {
    assert(is_string($group) && $group !== '', 'group name must be a non-empty string');
    assert(!empty($data['icon']), "$group: missing group icon");
    assert(!empty($data['submodules']), "$group: has no submodules");

    $routes = [];
    foreach ($data['submodules'] as $i => $item) {
        foreach (['label', 'icon', 'route', 'perm'] as $key) {
            assert(!empty($item[$key]) && is_string($item[$key]), "{$group}[$i]: missing/invalid '$key'");
        }
        assert($item['route'][0] === '/', "{$group}[$i]: route must start with '/'");
        assert(str_contains($item['perm'], '.'), "{$group}[$i]: perm must be 'module.action'");
        assert(!in_array($item['route'], $routes, true), "$group: duplicate route {$item['route']}");
        $routes[] = $item['route'];
    }
}

// ERP layout expectations that are easy to break while re-ordering.
$routesIn = static fn (string $g): array => array_column($modules[$g]['submodules'] ?? [], 'route');

assert(in_array('/products', $routesIn('Sales'), true), 'Products must be reachable from Sales');
assert(in_array('/products', $routesIn('Inventory'), true), 'Products must be reachable from Inventory');
assert(in_array('/customers', $routesIn('Sales'), true), 'Customers belong to Sales');
assert(in_array('/vendors', $routesIn('Purchases'), true), 'Vendors belong to Purchases');
assert(array_key_first($modules) === 'Sales', 'Sales leads the module order');

echo "ModuleNav OK: " . count($modules) . " groups, "
    . array_sum(array_map(static fn ($m) => count($m['submodules']), $modules)) . " items\n";
