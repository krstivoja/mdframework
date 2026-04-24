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
    'samesite' => 'Strict',
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
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
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
    if ($hash !== '') {
        return password_verify($input, $hash);
    }
    if ($plain !== '') {
        return hash_equals($plain, $input);
    }
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

/** @param array<string, mixed> $data */
function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** @param array<string, mixed> $data */
function json_ok(array $data = []): never
{
    json_response(['ok' => true, 'data' => $data]);
}

function json_fail(string $error, string $code = '', int $status = 400): never
{
    $body = ['ok' => false, 'error' => $error];
    if ($code !== '') {
        $body['code'] = $code;
    }
    json_response($body, $status);
}

function abort(int $code): never
{
    http_response_code($code);
    exit;
}

function require_post_auth(): void
{
    global $method;
    if ($method !== 'POST' || !csrf_verify()) {
        json_response(['error' => 'Forbidden'], 403);
    }
}

// ── Setup gate: refuse to serve admin if no credentials are configured ───────

if ($ADMIN_PASS_HASH === '' && $ADMIN_PASS === '') {
    http_response_code(503);
    $envFile = $appRoot . '/.env';
    require $TEMPLATE_DIR . '/setup-required.php';
    exit;
}

// ── Routing ───────────────────────────────────────────────────────────────────

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$action = trim(preg_replace('#^/admin/?#', '', $uri), '/') ?: 'pages';
$method = $_SERVER['REQUEST_METHOD'];

// ── Login ─────────────────────────────────────────────────────────────────────

if ($action === 'login') {
    if ($is_logged_in) {
        redirect('/admin/');
    }
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

$paths       = new MD\PathResolver($CONTENT_DIR, $UPLOADS_DIR, $CACHE_DIR, $appRoot . '/site/themes');
$content_obj = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$cache       = new MD\CacheService($paths, $CONTENT_DIR, $CACHE_DIR);
$repo        = new MD\ContentRepository($CONTENT_DIR, $cache, $content_obj);
$media       = new MD\MediaService($UPLOADS_DIR, $paths, $config->get('uploads', []));
$themes      = new MD\ThemeService($appRoot, $config);

// ── Post types (content folders) ─────────────────────────────────────────────

$post_types = [];
if (is_dir($CONTENT_DIR)) {
    foreach (array_diff(scandir($CONTENT_DIR), ['.', '..']) as $entry) {
        if (is_dir($CONTENT_DIR . '/' . $entry)) {
            $post_types[] = $entry;
        }
    }
}
$active_folder = null;

// ── Media metadata update (AJAX) ──────────────────────────────────────────────

if ($action === 'media-update') {
    require_post_auth();
    $name = basename($_POST['name'] ?? '');
    $ok   = $media->updateMeta($name, [
        'alt'     => $_POST['alt']     ?? '',
        'caption' => $_POST['caption'] ?? '',
    ]);
    if (!$ok) {
        json_response(['error' => 'Could not update metadata'], 400);
    }
    json_response(['ok' => true]);
}

// ── Images list (for editor picker) ───────────────────────────────────────────

if ($action === 'images') {
    $pagePath = trim($_GET['page_path'] ?? '', '/');
    $images   = [];

    foreach ($media->list() as $file) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            continue;
        }
        $file['source'] = 'media';
        $images[]       = $file;
    }

    if ($pagePath && preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $pagePath)) {
        $pageDir = $UPLOADS_DIR . '/' . $pagePath;
        if (is_dir($pageDir)) {
            $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            foreach (array_diff(scandir($pageDir), ['.', '..']) as $file) {
                if (str_contains($file, '.thumb.') || str_ends_with($file, '.meta.json')) {
                    continue;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $imgExts, true)) {
                    continue;
                }
                $stem      = pathinfo($file, PATHINFO_FILENAME);
                $thumbFile = $pageDir . '/' . $stem . '.thumb.' . $ext;
                $metaFile  = $pageDir . '/' . $stem . '.meta.json';
                $meta      = is_file($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?? []) : [];
                $images[]  = [
                    'name'      => $file,
                    'url'       => '/uploads/' . $pagePath . '/' . $file,
                    'thumb_url' => is_file($thumbFile) ? '/uploads/' . $pagePath . '/' . $stem . '.thumb.' . $ext : null,
                    'alt'       => $meta['alt']     ?? '',
                    'caption'   => $meta['caption'] ?? '',
                    'source'    => 'page',
                ];
            }
        }
    }

    json_response(['ok' => true, 'images' => $images]);
}

// ── Full-text search (AJAX) ────────────────────────────────────────────────────

if ($action === 'search') {
    $q = strtolower(trim($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        json_response(['ok' => true, 'results' => []]);
    }

    $idx_obj = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
    $all     = $idx_obj->get(includeDrafts: true);
    $results = [];

    foreach ($all as $page) {
        $titleMatch = str_contains(strtolower($page['title'] ?? ''), $q);
        $pathMatch  = str_contains(strtolower($page['path'] ?? ''), $q);
        $bodyMatch  = false;

        if (!$titleMatch && !$pathMatch) {
            $abs = $paths->contentFile($page['path']);
            if ($abs) {
                $bodyMatch = str_contains(strtolower(file_get_contents($abs)), $q);
            }
        }

        if ($titleMatch || $pathMatch || $bodyMatch) {
            $results[] = [
                'path'   => $page['path'],
                'title'  => $page['title']  ?? '',
                'folder' => $page['folder'] ?? '',
                'draft'  => !empty($page['draft']),
                'match'  => $titleMatch ? 'title' : ($pathMatch ? 'path' : 'body'),
            ];
        }
    }

    json_response(['ok' => true, 'results' => $results]);
}

// ── Upload (AJAX) ─────────────────────────────────────────────────────────────

if ($action === 'upload') {
    require_post_auth();
    $fileKey = array_key_first($_FILES) ?? '';
    $file    = $_FILES[$fileKey]        ?? null;
    if (!$file) {
        json_fail('No file', 'no_file');
    }

    $result = $media->upload($file, $_POST['page_path'] ?? '');
    if (!empty($result['error'])) {
        json_fail($result['error'], 'upload_error', (int)($result['code'] ?? 400));
    }
    json_ok(['url' => $result['url'], 'name' => $result['name'], 'size' => $result['size']]);
}

// ── Media delete (AJAX) ───────────────────────────────────────────────────────

if ($action === 'media-delete') {
    require_post_auth();
    $name = basename($_POST['name'] ?? '');
    if (!$media->delete($name)) {
        json_response(['error' => 'File not found'], 400);
    }
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
    if ($method !== 'POST' || !csrf_verify()) {
        abort(403);
    }
    $relPath = trim($_POST['path'] ?? '', '/');
    $absPath = $paths->contentFile($relPath);
    if ($absPath) {
        $repo->delete($relPath, $absPath);
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

            if (!$paths->isValidRelPath($relPath)) {
                $error = 'Path must be lowercase alphanumeric with hyphens/slashes (e.g. blog/my-post).';
            } elseif ($md_title === '') {
                $error = 'Title is required.';
            } elseif ($paths->resolveNewContentFile($relPath) === null) {
                $error = 'Invalid path.';
            } else {
                $existing = [];
                if (!$is_new) {
                    $absPath = $paths->contentFile($relPath);
                    if ($absPath) {
                        $existing = $repo->parseMeta($absPath);
                    }
                }
                $meta = array_merge($existing, ['title' => $md_title]);

                foreach (['description', 'canonical', 'og_image'] as $seoKey) {
                    $val = trim($_POST['meta_' . $seoKey] ?? '');
                    if ($val !== '') {
                        $meta[$seoKey] = $val;
                    } else {
                        unset($meta[$seoKey]);
                    }
                }

                if (($_POST['status'] ?? 'published') === 'draft') {
                    $meta['draft'] = true;
                } else {
                    unset($meta['draft']);
                }

                $page_folder = explode('/', $relPath)[0];
                foreach ($config->get('taxonomies', []) as $taxSlug => $tax) {
                    $pt = $tax['post_types'] ?? [];
                    if (!empty($pt) && !in_array($page_folder, $pt, true)) {
                        continue;
                    }
                    $widget = 'select';
                    foreach ($tax['fields'] ?? [] as $f) {
                        if ($f['type'] === 'array') {
                            $widget = $f['widget'] ?? 'select';
                            break;
                        }
                    }
                    $val = $_POST['tax_' . $taxSlug] ?? null;
                    if ($widget === 'checkbox') {
                        $items = array_values(array_filter(array_map('trim', (array)($val ?? []))));
                        if ($items) {
                            $meta[$taxSlug] = $items;
                        } else {
                            unset($meta[$taxSlug]);
                        }
                    } else {
                        $v = trim((string)($val ?? ''));
                        if ($v !== '') {
                            $meta[$taxSlug] = $v;
                        } else {
                            unset($meta[$taxSlug]);
                        }
                    }
                }

                $repo->save($relPath, $meta, $md_body);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    json_response(['ok' => true]);
                }
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
                if (!$slug) {
                    continue;
                }
                $fields = [];
                foreach ((array)($tax['fields'] ?? []) as $f) {
                    $name = preg_replace('/[^a-z0-9_-]/', '', strtolower($f['name'] ?? ''));
                    if (!$name) {
                        continue;
                    }
                    $type = ($f['type'] ?? '') === 'array' ? 'array' : 'single';
                    if ($type === 'array') {
                        $validWidgets = ['select', 'checkbox', 'radio'];
                        $widget       = in_array($f['widget'] ?? '', $validWidgets, true) ? $f['widget'] : 'select';
                        $items        = array_values(array_filter(array_map('trim', (array)($f['items'] ?? []))));
                        $fields[]     = ['name' => $name, 'type' => 'array', 'widget' => $widget, 'items' => $items];
                    } else {
                        $fields[] = ['name' => $name, 'type' => 'single', 'value' => trim($f['value'] ?? '')];
                    }
                }
                $post_types_raw = array_values(array_filter(array_map(
                    fn ($pt) => preg_replace('/[^a-z0-9_-]/', '', strtolower($pt)),
                    (array)($tax['post_types'] ?? [])
                )));
                $taxonomies[$slug] = [
                    'label'      => trim($tax['label'] ?? $slug),
                    'multiple'   => !empty($tax['multiple']),
                    'post_types' => $post_types_raw,
                    'fields'     => $fields,
                ];
            }
            $uploads = [
                'max_mb'     => max(1, min(512, (int)($_POST['upload_max_mb'] ?? 5))),
                'max_width'  => max(0, min(20000, (int)($_POST['upload_max_width'] ?? 0))),
                'max_height' => max(0, min(20000, (int)($_POST['upload_max_height'] ?? 0))),
            ];
            $config->save(array_merge($config->all(), [
                'site'       => $site,
                'taxonomies' => $taxonomies,
                'uploads'    => $uploads,
            ]));
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                json_response(['ok' => true]);
            }
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
    json_ok([
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
    if (!$zipUrl || !str_starts_with($zipUrl, 'https://')) {
        json_fail('Invalid URL', 'invalid_url');
    }
    $updater = new MD\Updater($appRoot);
    $result  = $updater->apply($zipUrl, $appRoot . '/site/backups');
    if (!empty($result['ok'])) {
        json_ok(['version' => $result['version'] ?? '']);
    } else {
        json_fail($result['error'] ?? 'Update failed', 'update_error', 500);
    }
}

// ── Themes ────────────────────────────────────────────────────────────────────

if ($action === 'themes') {
    $themes_list   = $themes->list();
    $active_theme  = $themes->active();
    $starters_dir  = $cmsRoot . '/starters';
    $starters_list = [];
    foreach (glob($starters_dir . '/*/starter.json') ?: [] as $f) {
        $slug                 = basename(dirname($f));
        $meta                 = json_decode(file_get_contents($f), true) ?? [];
        $starters_list[$slug] = array_merge(['name' => $slug, 'description' => ''], $meta, ['slug' => $slug]);
    }
    require $TEMPLATE_DIR . '/themes.php';
    exit;
}

if ($action === 'themes-activate') {
    require_post_auth();
    $slug   = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');
    $result = $themes->activate($slug);
    if (!empty($result['ok'])) {
        $cache->clearAllHtml();
        $cache->clearIndex();
        json_ok();
    }
    json_fail($result['error'] ?? 'Failed', 'theme_error');
}

if ($action === 'themes-install') {
    require_post_auth();
    $starter   = preg_replace('/[^a-z0-9_-]/', '', $_POST['starter'] ?? '');
    $themeSlug = preg_replace('/[^a-z0-9_-]/', '', $_POST['theme_slug'] ?? $starter);
    $result    = $themes->installFromStarter($starter, $themeSlug, $cmsRoot . '/starters');
    if (!empty($result['ok'])) {
        json_ok();
    } else {
        json_fail($result['error'] ?? 'Failed', 'theme_error');
    }
}

if ($action === 'themes-replace') {
    require_post_auth();
    $starter   = preg_replace('/[^a-z0-9_-]/', '', $_POST['starter'] ?? '');
    $themeSlug = preg_replace('/[^a-z0-9_-]/', '', $_POST['theme_slug'] ?? $themes->active());
    $result    = $themes->replaceTemplates($starter, $themeSlug, $cmsRoot . '/starters');
    if (!empty($result['ok'])) {
        $cache->clearAllHtml();
        json_ok();
    } else {
        json_fail($result['error'] ?? 'Failed', 'theme_error');
    }
}

// ── Backup (GET page + POST download) ────────────────────────────────────────

if ($action === 'backup') {
    $backupService = new MD\BackupService($appRoot, $UPLOADS_DIR);
    $backup_sizes  = [
        'full'     => $backupService->estimateSize('full'),
        'content'  => $backupService->estimateSize('content'),
        'settings' => $backupService->estimateSize('settings'),
    ];
    $restore_result = $_SESSION['restore_result'] ?? null;
    unset($_SESSION['restore_result']);
    require $TEMPLATE_DIR . '/backup.php';
    exit;
}

if ($action === 'backup/restore') {
    if ($method !== 'POST' || !csrf_verify()) {
        abort(403);
    }
    if (($_POST['confirm'] ?? '') !== 'RESTORE') {
        $_SESSION['restore_result'] = ['ok' => false, 'error' => 'Type RESTORE to confirm.'];
        redirect('/admin/backup');
    }
    if (($_FILES['backup']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['restore_result'] = ['ok' => false, 'error' => 'No file uploaded or upload failed.'];
        redirect('/admin/backup');
    }
    $tmp = $_FILES['backup']['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        abort(400);
    }

    $backupService = new MD\BackupService($appRoot, $UPLOADS_DIR);
    $result        = $backupService->restore($tmp);
    if ($result['ok']) {
        $cache->clearAllHtml();
        $cache->clearIndex();
    }
    $_SESSION['restore_result'] = $result;
    redirect('/admin/backup');
}

if ($action === 'backup/download') {
    if ($method !== 'POST' || !csrf_verify()) {
        abort(403);
    }
    $backupService = new MD\BackupService($appRoot, $UPLOADS_DIR);
    $scope         = $_POST['scope'] ?? 'full';
    if (!isset(MD\BackupService::SCOPES[$scope])) {
        $scope = 'full';
    }
    $stamp = date('Y-m-d');
    $label = $scope === 'full' ? 'backup' : $scope;

    $tmp = tempnam(sys_get_temp_dir(), 'mdbackup_');
    if ($tmp === false || !$backupService->writeZip($tmp, $scope)) {
        if ($tmp) {
            @unlink($tmp);
        }
        abort(500);
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="mdframework-' . $label . '-' . $stamp . '.zip"');
    header('Content-Length: ' . (string)filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

// ── Cache rebuild (AJAX POST) ─────────────────────────────────────────────────

if ($action === 'cache') {
    require_post_auth();
    $result = $cache->rebuild();
    if (!empty($result['ok'])) {
        json_ok(['count' => $result['count'] ?? 0]);
    } else {
        json_fail($result['error'] ?? 'Rebuild failed', 'cache_error');
    }
}

// ── Pages list (default) ──────────────────────────────────────────────────────

$index_obj     = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content_obj);
$all_pages     = $index_obj->get(includeDrafts: true);
$active_folder = $_GET['folder'] ?? null;

if ($active_folder && in_array($active_folder, $post_types, true)) {
    $pages = array_filter($all_pages, fn ($p) => $p['folder'] === $active_folder);
} else {
    $active_folder = null;
    $pages         = $all_pages;
}

require $TEMPLATE_DIR . '/pages.php';
