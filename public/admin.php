<?php
$appRoot = dirname(__DIR__);
$cmsRoot = dirname(__DIR__) . '/cms';
require_once $cmsRoot . '/vendor/autoload.php';

spl_autoload_register(function ($class) use ($cmsRoot) {
    if (str_starts_with($class, 'MD\\')) {
        $path = $cmsRoot . '/lib/' . str_replace('\\', '/', substr($class, 3)) . '.php';
        if (is_file($path)) require $path;
    }
});

MD\Env::load($appRoot . '/.env');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$ADMIN_USER      = MD\Env::get('ADMIN_USER', 'admin');
$ADMIN_PASS_HASH = MD\Env::get('ADMIN_PASS_HASH', '');
$CONTENT_DIR     = $appRoot . '/site/content';
$UPLOADS_DIR     = __DIR__ . '/uploads';
$TEMPLATE_DIR    = $cmsRoot . '/templates';
$CACHE_DIR       = $appRoot . '/site/cache';

// ── Helpers ──────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool
{
    if (empty($_SESSION['csrf_token'])) return false;
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES);
}

function passwordCheck(string $input, string $hash, string $plain): bool
{
    if ($hash !== '') return password_verify($input, $hash);
    if ($plain !== '') return hash_equals($plain, $input);
    return false;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

$is_logged_in = !empty($_SESSION['admin_user']);

function require_auth(): void
{
    global $is_logged_in;
    if (!$is_logged_in) {
        redirect('/admin/login');
    }
}

// ── Routing ──────────────────────────────────────────────────────────────────

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$action = trim(preg_replace('#^/admin/?#', '', $uri), '/') ?: 'pages';
$method = $_SERVER['REQUEST_METHOD'];

// ── Login ─────────────────────────────────────────────────────────────────────

if ($action === 'login') {
    if ($is_logged_in) redirect('/admin/');
    $error = null;
    if ($method === 'POST') {
        if (!csrf_verify()) {
            $error = 'Invalid request — try again.';
        } elseif (
            ($_POST['username'] ?? '') === $ADMIN_USER
            && passwordCheck($_POST['password'] ?? '', $ADMIN_PASS_HASH, MD\Env::get('ADMIN_PASS', ''))
        ) {
            session_regenerate_id(true);
            $_SESSION['admin_user'] = $ADMIN_USER;
            redirect('/admin/');
        } else {
            $error = 'Invalid credentials.';
        }
    }
    csrf_token(); // seed token before rendering
    require $TEMPLATE_DIR . '/login.php';
    exit;
}

// ── Logout ────────────────────────────────────────────────────────────────────

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect('/admin/login');
}

// ── All other actions require auth ────────────────────────────────────────────

require_auth();

// ── Image upload (AJAX) ───────────────────────────────────────────────────────

if ($action === 'upload') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $file_key = array_key_first($_FILES) ?? '';
    $upload   = $_FILES[$file_key] ?? null;
    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['errorMessage' => 'No file or upload error']);
        exit;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $type    = mime_content_type($upload['tmp_name']);
    if (!in_array($type, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['errorMessage' => 'File type not allowed']);
        exit;
    }
    $ext  = match ($type) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    };
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!is_dir($UPLOADS_DIR)) mkdir($UPLOADS_DIR, 0755, true);
    if (!move_uploaded_file($upload['tmp_name'], $UPLOADS_DIR . '/' . $name)) {
        http_response_code(500);
        echo json_encode(['errorMessage' => 'Upload failed']);
        exit;
    }
    echo json_encode(['result' => [['url' => '/uploads/' . $name, 'name' => $name, 'size' => $upload['size']]]]);
    exit;
}

// ── Delete ────────────────────────────────────────────────────────────────────

if ($action === 'delete') {
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403);
        exit;
    }
    $relPath = trim($_POST['path'] ?? '', '/');
    $file    = realpath($CONTENT_DIR . '/' . $relPath . '.md');
    if ($file && str_starts_with($file, realpath($CONTENT_DIR) . '/') && is_file($file)) {
        unlink($file);
    }
    redirect('/admin/');
}

// ── New / Edit ────────────────────────────────────────────────────────────────

if ($action === 'new' || $action === 'edit') {
    $error    = null;
    $relPath  = trim($_GET['path'] ?? '', '/');
    $md_title = '';
    $md_body  = '';
    $is_new   = ($action === 'new');

    if ($method === 'POST') {
        if (!csrf_verify()) {
            $error = 'Invalid request — try again.';
        } else {
            $relPath  = trim($_POST['path'] ?? '', '/');
            $md_title = trim($_POST['title'] ?? '');
            $md_body  = $_POST['body'] ?? '';

            if (!preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $relPath)) {
                $error = 'Path must be lowercase alphanumeric with hyphens/slashes (e.g. blog/my-post).';
            } elseif ($md_title === '') {
                $error = 'Title is required.';
            } else {
                $file = $CONTENT_DIR . '/' . $relPath . '.md';
                $dir  = dirname($file);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $content = "---\ntitle: " . str_replace(["\r", "\n"], '', $md_title) . "\n---\n\n" . $md_body;
                file_put_contents($file, $content);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                redirect('/admin/');
            }
        }
    } elseif (!$is_new && $relPath !== '') {
        $file = realpath($CONTENT_DIR . '/' . $relPath . '.md');
        if ($file && str_starts_with($file, realpath($CONTENT_DIR) . '/') && is_file($file)) {
            $parser   = new MD\Content($CONTENT_DIR, $CACHE_DIR);
            $parsed   = $parser->parse($file);
            $md_title = $parsed['meta']['title'] ?? '';
            $md_body  = $parsed['html'];
        }
    }

    require $TEMPLATE_DIR . '/edit.php';
    exit;
}

// ── Pages list (default) ──────────────────────────────────────────────────────

$content_obj = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$index_obj   = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
$pages       = $index_obj->get(includeDrafts: true);

require $TEMPLATE_DIR . '/pages.php';
