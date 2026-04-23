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

// ── Auth helpers ──────────────────────────────────────────────────────────────

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
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
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
    if (!$is_logged_in) redirect('/admin/login');
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_post_auth(): void
{
    global $method;
    if ($method !== 'POST' || !csrf_verify()) json_response(['error' => 'Forbidden'], 403);
}

// ── Routing ───────────────────────────────────────────────────────────────────

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
    csrf_token();
    require $TEMPLATE_DIR . '/login.php';
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect('/admin/login');
}

require_auth();

// ── Services ──────────────────────────────────────────────────────────────────

$paths       = new MD\PathResolver($CONTENT_DIR, $UPLOADS_DIR, $CACHE_DIR);
$content_obj = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$cache       = new MD\CacheService($paths, $CONTENT_DIR, $CACHE_DIR);
$repo        = new MD\ContentRepository($CONTENT_DIR, $cache, $content_obj);
$media       = new MD\MediaService($UPLOADS_DIR, $paths);
$themes      = new MD\ThemeService($appRoot, $config);

// ── Post types (content folders) ─────────────────────────────────────────────

$post_types = [];
if (is_dir($CONTENT_DIR)) {
    foreach (array_diff(scandir($CONTENT_DIR), ['.', '..']) as $entry) {
        if (is_dir($CONTENT_DIR . '/' . $entry)) $post_types[] = $entry;
    }
}
$active_folder = null;

// ── Upload (AJAX) ─────────────────────────────────────────────────────────────

if ($action === 'upload') {
    require_post_auth();
    $fileKey = array_key_first($_FILES) ?? '';
    $file    = $_FILES[$fileKey] ?? null;
    if (!$file) json_response(['errorMessage' => 'No file'], 400);

    $result = $media->upload($file, $_POST['page_path'] ?? '');
    if (!empty($result['error'])) json_response(['errorMessage' => $result['error']], $result['code']);
    json_response(['result' => [['url' => $result['url'], 'name' => $result['name'], 'size' => $result['size']]]]);
}

// ── Inline save (AJAX) ────────────────────────────────────────────────────────

if ($action === 'inline-save') {
    require_post_auth();
    $relPath = trim($_POST['page_path'] ?? '', '/');
    $absPath = $paths->contentFile($relPath);
    if (!$absPath) json_response(['error' => 'Page not found'], 404);

    $existing = $repo->parseMeta($absPath);
    $fields   = $_POST['ie'] ?? [];
    $body     = '';
    foreach ($fields as $key => $value) {
        if ($key === 'body') $body = $value;
        else $existing[$key] = trim($value);
    }
    $repo->save($relPath, $existing, $body);
    json_response(['ok' => true]);
}

// ── Media delete (AJAX) ───────────────────────────────────────────────────────

if ($action === 'media-delete') {
    require_post_auth();
    $name = basename($_POST['name'] ?? '');
    if (!$media->delete($name)) json_response(['error' => 'File not found'], 400);
    json_response(['ok' => true]);
}

// ── Template save (AJAX) ──────────────────────────────────────────────────────

if ($action === 'template-save') {
    require_post_auth();
    $templateName = preg_replace('/[^a-z0-9_-]/', '', $_POST['template'] ?? '');
    if (!$templateName) json_response(['error' => 'Missing template name'], 400);

    $templateFile = $themes->templateDir() . '/' . $templateName . '.php';
    $realTemplate = realpath($templateFile);
    $themesDir    = realpath($appRoot . '/site/themes');
    if (!$realTemplate || !$themesDir || !str_starts_with($realTemplate, $themesDir . '/')) {
        json_response(['error' => 'Template not found'], 403);
    }
    $replacements = json_decode($_POST['replacements'] ?? '[]', true);
    if (!is_array($replacements)) json_response(['error' => 'Invalid replacements'], 400);

    $contents = file_get_contents($realTemplate);
    foreach ($replacements as $r) {
        $orig        = $r['orig']        ?? '';
        $replacement = $r['replacement'] ?? '';
        if ($orig === '' || $orig === $replacement) continue;
        $contents = str_replace($orig, $replacement, $contents);
    }
    file_put_contents($realTemplate, $contents);
    json_response(['ok' => true]);
}

// ── Media library ─────────────────────────────────────────────────────────────

if ($action === 'media') {
    $mediaFiles    = $media->list();
    $active_folder = null;
    $action        = 'media';
    require $TEMPLATE_DIR . '/media.php';
    exit;
}

// ── Delete ────────────────────────────────────────────────────────────────────

if ($action === 'delete') {
    if ($method !== 'POST' || !csrf_verify()) { http_response_code(403); exit; }
    $relPath = trim($_POST['path'] ?? '', '/');
    $absPath = $paths->contentFile($relPath);
    if ($absPath) $repo->delete($relPath, $absPath);
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

            if (!$paths->isValidRelPath($relPath)) {
                $error = 'Path must be lowercase alphanumeric with hyphens/slashes (e.g. blog/my-post).';
            } elseif ($md_title === '') {
                $error = 'Title is required.';
            } else {
                $existing = [];
                if (!$is_new) {
                    $absPath = $paths->contentFile($relPath);
                    if ($absPath) $existing = $repo->parseMeta($absPath);
                }
                $meta = array_merge($existing, ['title' => $md_title]);

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

                $repo->save($relPath, $meta, $md_body);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) json_response(['ok' => true]);
                redirect('/admin/');
            }
        }
    } elseif (!$is_new && $relPath !== '') {
        $absPath = $paths->contentFile($relPath);
        if ($absPath) {
            $parsed       = $repo->parse($absPath);
            $md_title     = $parsed['meta']['title'] ?? '';
            $md_body      = $parsed['body'];
            $md_body_html = $parsed['html'];
            $current_meta = $parsed['meta'];
        }
    }

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
            $raw        = json_decode($_POST['taxonomies_json'] ?? '{}', true) ?? [];
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
                        $widget   = in_array($f['widget'] ?? '', $validWidgets, true) ? $f['widget'] : 'select';
                        $items    = array_values(array_filter(array_map('trim', (array)($f['items'] ?? []))));
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
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) json_response(['ok' => true]);
            redirect('/admin/settings');
        }
    }
    require $TEMPLATE_DIR . '/settings.php';
    exit;
}

// ── Update check (AJAX) ───────────────────────────────────────────────────────

if ($action === 'update-check') {
    $updater = new MD\Updater($appRoot);
    $latest  = $updater->checkLatest();
    json_response([
        'current'         => $updater->currentVersion(),
        'latest'          => $latest,
        'has_update'      => $latest ? version_compare($latest['version'], $updater->currentVersion(), '>') : false,
        'repo_configured' => !str_starts_with($updater->repo(), 'your-'),
    ]);
}

// ── Update apply (AJAX POST) ──────────────────────────────────────────────────

if ($action === 'update-apply') {
    require_post_auth();
    $zipUrl = trim($_POST['zip_url'] ?? '');
    if (!$zipUrl || !str_starts_with($zipUrl, 'https://')) json_response(['error' => 'Invalid URL'], 400);
    $updater = new MD\Updater($appRoot);
    json_response($updater->apply($zipUrl, $appRoot . '/site/backups'));
}

// ── Themes ────────────────────────────────────────────────────────────────────

if ($action === 'themes') {
    $themes_list  = $themes->list();
    $active_theme = $themes->active();
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

if ($action === 'themes-activate') {
    require_post_auth();
    $slug = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');
    json_response($themes->activate($slug));
}

if ($action === 'themes-install') {
    require_post_auth();
    $starter   = preg_replace('/[^a-z0-9_-]/', '', $_POST['starter'] ?? '');
    $themeSlug = preg_replace('/[^a-z0-9_-]/', '', $_POST['theme_slug'] ?? $starter);
    json_response($themes->installFromStarter($starter, $themeSlug, $cmsRoot . '/starters'));
}

// ── Cache rebuild (AJAX POST) ─────────────────────────────────────────────────

if ($action === 'cache') {
    require_post_auth();
    json_response($cache->rebuild());
}

// ── Pages list (default) ──────────────────────────────────────────────────────

$index_obj     = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
$all_pages     = $index_obj->get(includeDrafts: true);
$active_folder = $_GET['folder'] ?? null;

if ($active_folder && in_array($active_folder, $post_types, true)) {
    $pages = array_filter($all_pages, fn($p) => $p['folder'] === $active_folder);
} else {
    $active_folder = null;
    $pages         = $all_pages;
}

require $TEMPLATE_DIR . '/pages.php';
