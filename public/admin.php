<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$cmsRoot = dirname(__DIR__) . '/cms';
require_once $cmsRoot . '/vendor/autoload.php';

spl_autoload_register(function ($class) use ($cmsRoot) {
    if (str_starts_with($class, 'MD\\')) {
        $path = $cmsRoot . '/lib/' . str_replace('\\', '/', substr($class, 3)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

MD\Env::load($appRoot . '/.env');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$ADMIN_USER      = MD\Env::get('ADMIN_USER', 'admin');
$ADMIN_PASS_HASH = MD\Env::get('ADMIN_PASS_HASH', '');
$ADMIN_PASS      = MD\Env::get('ADMIN_PASS', '');
$CONTENT_DIR     = $appRoot . '/site/content';
$UPLOADS_DIR     = __DIR__ . '/uploads';
$TEMPLATE_DIR    = $cmsRoot . '/templates';
$CACHE_DIR       = $appRoot . '/site/cache';
$config          = new MD\Config($appRoot . '/site/config.json');

// ── Helpers (used by API controllers via the global namespace) ───────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function passwordCheck(string $input, string $hash, string $plain): bool
{
    if ($hash !== '') {
        return password_verify($input, $hash);
    }
    if ($plain !== '') {
        return hash_equals($plain, $input);
    }
    return false;
}

/** @param array<string, mixed> $data */
function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

// ── Setup gate: refuse if no credentials are configured ──────────────────────

if ($ADMIN_PASS_HASH === '' && $ADMIN_PASS === '') {
    http_response_code(503);
    $envFile = $appRoot . '/.env';
    require $TEMPLATE_DIR . '/setup-required.php';
    exit;
}

// ── Routing ──────────────────────────────────────────────────────────────────

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// JSON API
if (preg_match('#^/admin/api/(.*)$#', $uri, $apiMatch)) {
    MD\Api\Router::dispatch($apiMatch[1], $method, [
        'appRoot'         => $appRoot,
        'cmsRoot'         => $cmsRoot,
        'contentDir'      => $CONTENT_DIR,
        'uploadsDir'      => $UPLOADS_DIR,
        'cacheDir'        => $CACHE_DIR,
        'themesDir'       => $appRoot . '/site/themes',
        'config'          => $config,
        'ADMIN_USER'      => $ADMIN_USER,
        'ADMIN_PASS_HASH' => $ADMIN_PASS_HASH,
        'ADMIN_PASS'      => $ADMIN_PASS,
    ]);
    exit;
}

// Anything else under /admin/* renders the React SPA shell. React handles
// auth gating internally by calling /admin/api/me at boot.
require $TEMPLATE_DIR . '/spa.php';
