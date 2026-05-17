<?php
/**
 * Router for `php -S`.
 *
 * The built-in PHP dev server doesn't read .htaccess, so this file mirrors
 * the rewrite rules: real files are served as-is, /admin/* dispatches to
 * admin.php, everything else to index.php.
 *
 * Usage: php -S localhost:8080 router.php
 *
 * Production deployments use Apache + .htaccess (or an equivalent rewrite
 * config) and ignore this file.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Real files (assets, /admin/assets/*) → let the server serve them
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// /admin and /admin/* → admin SPA shell
if (preg_match('#^/admin(/|$)#', $uri)) {
    require __DIR__ . '/admin/index.php';
    return;
}

// Everything else → public front controller
require __DIR__ . '/index.php';
