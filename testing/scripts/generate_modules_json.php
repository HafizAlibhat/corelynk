<?php
// Usage: php scripts/generate_modules_json.php
// Generates public/modules.json from app/Config/Routes.php

$routesFile = __DIR__ . '/../app/Config/Routes.php';
$outFile = __DIR__ . '/../public/modules.json';

if (!file_exists($routesFile)) {
    echo "Routes file not found: $routesFile\n";
    exit(1);
}

$content = file_get_contents($routesFile);

$modules = [];

// Find group definitions: $routes->group('name',
preg_match_all("/\\$routes->group\(\s*'([^']+)'/", $content, $groups);
if (!empty($groups[1])) {
    foreach ($groups[1] as $g) {
        $slug = trim($g, '/');
        $modules[$slug] = [
            'id' => $slug ?: $g,
            'name' => $g,
            'path' => '/' . $slug,
            'type' => 'group'
        ];
    }
}

// Find explicit top-level routes: $routes->get('route', 'Controller::method')
preg_match_all("/\\$routes->(get|post|delete|match)\(\s*'([^']+)'\s*,\s*'([^']+)'/", $content, $routes);
if (!empty($routes[2])) {
    foreach ($routes[2] as $r) {
        $route = trim($r, '/');
        // Skip empty route (root) because it's covered by '/'
        if ($route === '') $route = '/';
        $slug = str_replace('/', '_', $route);
        if (!isset($modules[$slug])) {
            $modules[$slug] = [
                'id' => $slug,
                'name' => $route,
                'path' => '/' . $route,
                'type' => 'route'
            ];
        }
    }
}

$list = array_values($modules);

if (!is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0755, true);
}

file_put_contents($outFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Generated modules JSON with " . count($list) . " entries to $outFile\n";
