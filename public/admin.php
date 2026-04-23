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
$config          = new MD\Config($appRoot . '/site/config.json');

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

// ── Post types (content folders) ─────────────────────────────────────────────

$post_types = [];
if (is_dir($CONTENT_DIR)) {
    foreach (array_diff(scandir($CONTENT_DIR), ['.', '..']) as $entry) {
        if (is_dir($CONTENT_DIR . '/' . $entry)) $post_types[] = $entry;
    }
}
$active_folder = null;

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
    $extMap = [
        'jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif',
        'webp' => 'webp', 'svg' => 'svg', 'pdf' => 'pdf', 'zip' => 'zip',
    ];
    $origExt = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    if (!isset($extMap[$origExt])) {
        http_response_code(400);
        echo json_encode(['errorMessage' => 'File type not allowed: ' . $origExt]);
        exit;
    }
    $ext = $extMap[$origExt];
    $name = bin2hex(random_bytes(12)) . '.' . $ext;

    // Determine subfolder: per-page path or global media
    $raw_page_path = trim($_POST['page_path'] ?? '', '/');
    if ($raw_page_path !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $raw_page_path)) {
        $sub_dir = $UPLOADS_DIR . '/' . $raw_page_path;
        $url_prefix = '/uploads/' . $raw_page_path . '/';
    } else {
        $sub_dir    = $UPLOADS_DIR . '/media';
        $url_prefix = '/uploads/media/';
    }

    if (!is_dir($sub_dir)) mkdir($sub_dir, 0755, true);
    if (!move_uploaded_file($upload['tmp_name'], $sub_dir . '/' . $name)) {
        http_response_code(500);
        echo json_encode(['errorMessage' => 'Upload failed']);
        exit;
    }
    echo json_encode(['result' => [['url' => $url_prefix . $name, 'name' => $name, 'size' => $upload['size']]]]);
    exit;
}

// ── Inline save (AJAX) ────────────────────────────────────────────────────────

if ($action === 'inline-save') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $relPath = trim($_POST['page_path'] ?? '', '/');
    if (!preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $relPath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid path']);
        exit;
    }
    $file = realpath($CONTENT_DIR . '/' . $relPath . '.md');
    if (!$file || !str_starts_with($file, realpath($CONTENT_DIR) . '/') || !is_file($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found']);
        exit;
    }
    $parser       = new MD\Content($CONTENT_DIR, $CACHE_DIR);
    $existing_meta = $parser->parseMeta($file);
    if (!empty($_POST['title'])) {
        $existing_meta['title'] = trim($_POST['title']);
    }
    $yaml    = \Symfony\Component\Yaml\Yaml::dump($existing_meta, 2, 2);
    $body    = $_POST['body'] ?? '';
    $md_file = "---\n" . $yaml . "---\n\n" . $body;
    file_put_contents($file, $md_file);

    // Clear HTML cache for this page
    $cacheFile = $CACHE_DIR . '/html/' . md5($relPath) . '.php';
    if (is_file($cacheFile)) unlink($cacheFile);

    echo json_encode(['ok' => true]);
    exit;
}

// ── Media delete (AJAX) ───────────────────────────────────────────────────────

if ($action === 'media-delete') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $name      = basename($_POST['name'] ?? '');
    $mediaDir  = realpath($UPLOADS_DIR . '/media');
    $target    = $mediaDir ? $mediaDir . '/' . $name : null;
    if (!$target || !$mediaDir || !str_starts_with($target, $mediaDir . '/') || !is_file($target)) {
        http_response_code(400);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    unlink($target);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Media library ─────────────────────────────────────────────────────────────

if ($action === 'media') {
    $mediaDir   = $UPLOADS_DIR . '/media';
    $mediaFiles = [];
    if (is_dir($mediaDir)) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip'];
        foreach (array_diff(scandir($mediaDir), ['.', '..']) as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) continue;
            $full = $mediaDir . '/' . $file;
            $mediaFiles[] = [
                'name'  => $file,
                'url'   => '/uploads/media/' . $file,
                'size'  => filesize($full),
                'mtime' => filemtime($full),
            ];
        }
        usort($mediaFiles, fn($a, $b) => $b['mtime'] - $a['mtime']);
    }
    $active_folder = null;
    $action = 'media';
    require $TEMPLATE_DIR . '/media.php';
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
        // Invalidate HTML cache for this page
        $htmlCache = $CACHE_DIR . '/html/' . md5($relPath) . '.php';
        if (is_file($htmlCache)) unlink($htmlCache);
        // Invalidate index cache so deleted entry doesn't persist
        $indexCache = $CACHE_DIR . '/index.php';
        if (is_file($indexCache)) unlink($indexCache);
    }
    redirect('/admin/');
}

// ── New / Edit ────────────────────────────────────────────────────────────────

if ($action === 'new' || $action === 'edit') {
    $error        = null;
    $relPath      = trim($_GET['path'] ?? '', '/');
    $md_title     = '';
    $md_body      = '';
    $current_meta = [];
    $is_new       = ($action === 'new');

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

                // Preserve existing meta fields (date, draft, etc.)
                $existing_meta = [];
                if (!$is_new && is_file($file)) {
                    $existing_meta = (new MD\Content($CONTENT_DIR, $CACHE_DIR))->parseMeta($file);
                }
                $meta = array_merge($existing_meta, ['title' => $md_title]);

                // Taxonomy values
                $page_folder = explode('/', $relPath)[0];
                foreach ($config->get('taxonomies', []) as $taxSlug => $tax) {
                    $pt = $tax['post_types'] ?? [];
                    if (!empty($pt) && !in_array($page_folder, $pt, true)) continue;
                    $widget = 'select';
                    foreach ($tax['fields'] ?? [] as $f) {
                        if ($f['type'] === 'array') { $widget = $f['widget'] ?? 'select'; break; }
                    }
                    $val = $_POST['tax_' . $taxSlug] ?? null;
                    if ($widget === 'checkbox') {
                        $items = array_values(array_filter(array_map('trim', (array)($val ?? []))));
                        if ($items) $meta[$taxSlug] = $items; else unset($meta[$taxSlug]);
                    } else {
                        $v = trim((string)($val ?? ''));
                        if ($v !== '') $meta[$taxSlug] = $v; else unset($meta[$taxSlug]);
                    }
                }

                $yaml    = \Symfony\Component\Yaml\Yaml::dump($meta, 2, 2);
                $md_file = "---\n" . $yaml . "---\n\n" . $md_body;
                file_put_contents($file, $md_file);

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
            $parser       = new MD\Content($CONTENT_DIR, $CACHE_DIR);
            $parsed       = $parser->parse($file);
            $md_title     = $parsed['meta']['title'] ?? '';
            $md_body      = $parsed['body'];
            $md_body_html = $parsed['html'];
            $current_meta = $parsed['meta'];
        }
    }

    // Applicable taxonomies for this page's folder
    $page_folder           = $relPath ? explode('/', $relPath)[0] : null;
    $applicable_taxonomies = [];
    foreach ($config->get('taxonomies', []) as $taxSlug => $tax) {
        $pt = $tax['post_types'] ?? [];
        if (empty($pt) || ($page_folder && in_array($page_folder, $pt, true))) {
            $applicable_taxonomies[$taxSlug] = $tax;
        }
    }

    require $TEMPLATE_DIR . '/edit.php';
    exit;
}

// ── Settings ──────────────────────────────────────────────────────────────────

if ($action === 'settings') {
    $error = null;
    if ($method === 'POST') {
        if (!csrf_verify()) {
            $error = 'Invalid request — try again.';
        } else {
            $site = [
                'name' => trim($_POST['site_name'] ?? ''),
                'base' => '/' . trim(trim($_POST['site_base'] ?? ''), '/'),
            ];
            $raw = json_decode($_POST['taxonomies_json'] ?? '{}', true) ?? [];
            $taxonomies = [];
            foreach ($raw as $slug => $tax) {
                $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
                if (!$slug) continue;
                $fields = [];
                foreach ((array)($tax['fields'] ?? []) as $f) {
                    $name = preg_replace('/[^a-z0-9_-]/', '', strtolower($f['name'] ?? ''));
                    if (!$name) continue;
                    $type = ($f['type'] ?? '') === 'array' ? 'array' : 'single';
                    if ($type === 'array') {
                        $validWidgets = ['select', 'checkbox', 'radio'];
                        $widget = in_array($f['widget'] ?? '', $validWidgets, true) ? $f['widget'] : 'select';
                        $items  = array_values(array_filter(array_map('trim', (array)($f['items'] ?? []))));
                        $fields[] = ['name' => $name, 'type' => 'array', 'widget' => $widget, 'items' => $items];
                    } else {
                        $fields[] = ['name' => $name, 'type' => 'single', 'value' => trim($f['value'] ?? '')];
                    }
                }
                $post_types_raw = array_values(array_filter(array_map(
                    fn($pt) => preg_replace('/[^a-z0-9_-]/', '', strtolower($pt)),
                    (array)($tax['post_types'] ?? [])
                )));
                $taxonomies[$slug] = [
                    'label'      => trim($tax['label'] ?? $slug),
                    'multiple'   => !empty($tax['multiple']),
                    'post_types' => $post_types_raw,
                    'fields'     => $fields,
                ];
            }
            $config->save(['site' => $site, 'taxonomies' => $taxonomies]);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
            redirect('/admin/settings');
        }
    }
    require $TEMPLATE_DIR . '/settings.php';
    exit;
}

// ── Update check (AJAX) ───────────────────────────────────────────────────────

if ($action === 'update-check') {
    header('Content-Type: application/json');
    $updater = new MD\Updater($appRoot);
    $latest  = $updater->checkLatest();
    echo json_encode([
        'current' => $updater->currentVersion(),
        'latest'  => $latest,
        'has_update' => $latest
            ? version_compare($latest['version'], $updater->currentVersion(), '>')
            : false,
        'repo_configured' => !str_starts_with($updater->repo(), 'your-'),
    ]);
    exit;
}

// ── Update apply (AJAX POST) ──────────────────────────────────────────────────

if ($action === 'update-apply') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    $zipUrl  = trim($_POST['zip_url'] ?? '');
    if (!$zipUrl || !str_starts_with($zipUrl, 'https://')) {
        http_response_code(400); echo json_encode(['error' => 'Invalid URL']); exit;
    }
    $updater    = new MD\Updater($appRoot);
    $backupDir  = $appRoot . '/site/backups';
    echo json_encode($updater->apply($zipUrl, $backupDir));
    exit;
}

// ── Themes list ───────────────────────────────────────────────────────────────

if ($action === 'themes') {
    $themes_obj   = new MD\Themes($appRoot, $config);
    $themes_list  = $themes_obj->list();
    $active_theme = $themes_obj->active();
    $starters_dir = $cmsRoot . '/starters';
    $starters_list = [];
    foreach (glob($starters_dir . '/*/starter.json') ?: [] as $f) {
        $slug = basename(dirname($f));
        $meta = json_decode(file_get_contents($f), true) ?? [];
        $starters_list[$slug] = array_merge(['name' => $slug, 'description' => ''], $meta, ['slug' => $slug]);
    }
    require $TEMPLATE_DIR . '/themes.php';
    exit;
}

// ── Theme activate (AJAX POST) ────────────────────────────────────────────────

if ($action === 'themes-activate') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    $slug       = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');
    $themes_obj = new MD\Themes($appRoot, $config);
    echo json_encode($themes_obj->activate($slug));
    exit;
}

// ── Theme install from starter (AJAX POST) ────────────────────────────────────

if ($action === 'themes-install') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    $starter    = preg_replace('/[^a-z0-9_-]/', '', $_POST['starter'] ?? '');
    $themeSlug  = preg_replace('/[^a-z0-9_-]/', '', $_POST['theme_slug'] ?? $starter);
    $themes_obj = new MD\Themes($appRoot, $config);
    echo json_encode($themes_obj->installFromStarter($starter, $themeSlug, $cmsRoot . '/starters'));
    exit;
}

// ── Cache rebuild ─────────────────────────────────────────────────────────────

if ($action === 'cache') {
    header('Content-Type: application/json');
    if ($method !== 'POST' || !csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Clear html cache files
    $htmlCacheDir = $CACHE_DIR . '/html';
    if (is_dir($htmlCacheDir)) {
        foreach (glob($htmlCacheDir . '/*.php') as $f) unlink($f);
    }
    // Clear index cache
    $indexCache = $CACHE_DIR . '/index.php';
    if (is_file($indexCache)) unlink($indexCache);

    // Rebuild index + warm every page
    $content_obj = new MD\Content($CONTENT_DIR, $CACHE_DIR);
    $index_obj   = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
    $index_obj->build();
    $pages = $index_obj->get(includeDrafts: true);

    $count = 0;
    foreach ($pages as $page) {
        $content_obj->load($page['path']);
        $count++;
    }

    echo json_encode(['ok' => true, 'count' => $count]);
    exit;
}

// ── Pages list (default) ──────────────────────────────────────────────────────

$content_obj   = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$index_obj     = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
$all_pages     = $index_obj->get(includeDrafts: true);
$active_folder = $_GET['folder'] ?? null;

if ($active_folder && in_array($active_folder, $post_types, true)) {
    $pages = array_filter($all_pages, fn($p) => $p['folder'] === $active_folder);
} else {
    $active_folder = null;
    $pages = $all_pages;
}

require $TEMPLATE_DIR . '/pages.php';
