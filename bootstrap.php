<?php
/**
 * Shared bootstrap for the framework.
 * Sets up paths, autoloads, and instantiates Content/Index/Router.
 */

require_once __DIR__ . '/cms/vendor/autoload.php';
require_once __DIR__ . '/cms/lib/template_helpers.php';

// Simple PSR-4 autoloader for our lib/ classes (in case composer dump-autoload wasn't run)
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'MD\\')) {
        $path = __DIR__ . '/cms/lib/' . str_replace('\\', '/', substr($class, 3)) . '.php';
        if (is_file($path)) require $path;
    }
});

$ROOT        = __DIR__;
$CONTENT_DIR = $ROOT . '/site/content';
$CACHE_DIR   = $ROOT . '/site/cache';
$UPLOADS_DIR = $ROOT . '/site/uploads';

// Load .env once for both the admin and public-site entry points so any
// runtime-toggleable behaviour (e.g. APP_ENV-gated SCSS compilation) sees the
// same values. Env::load is idempotent.
MD\Env::load($ROOT . '/.env');

// First-run only: copy starter content / config / theme into /site if it's
// empty. /site is gitignored — the defaults a user sees on a fresh install
// live under cms/starters/ and are seeded here. Idempotent and cheap when
// the directories already exist.
MD\Bootstrap::ensureSiteDefaults($ROOT);

$config = new MD\Config($ROOT . '/site/config.json');
$GLOBALS['md_config'] = $config;

$themes       = new MD\ThemeService($ROOT, $config);
$TEMPLATE_DIR = $themes->templateDir();
$GLOBALS['md_themes'] = $themes;

// Ensure public/assets is symlinked to the active theme's assets dir. The
// release-zip pipeline dereferences symlinks, so a fresh unzipped install
// has a real public/assets/ directory with a stale snapshot — edits to
// site/themes/<active>/assets don't take effect. Self-heal on first
// public-site request. No-op when already correctly linked.
$themes->ensureAssetsLink();

// Auto-compile the active theme's SCSS in development. Even though the
// freshness check is just a few stat() calls, paying it on every public-site
// request in production is pure overhead — there is nothing to recompile.
// `.env` ships APP_ENV=dev locally; in production omit it (or set =prod).
$themeDir = dirname($TEMPLATE_DIR);
if (MD\Env::get('APP_ENV', 'dev') === 'dev' && is_dir($themeDir . '/assets')) {
    (new MD\ScssCompiler())->compileTheme($themeDir);
}

$content = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$index = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content);
$router = new MD\Router($CONTENT_DIR);

// Expose as globals for templates
$GLOBALS['md_content'] = $content;
$GLOBALS['md_index'] = $index;
$GLOBALS['md_router'] = $router;
$GLOBALS['md_template_dir'] = $TEMPLATE_DIR;
$GLOBALS['md_content_dir'] = $CONTENT_DIR;
$GLOBALS['md_cache_dir'] = $CACHE_DIR;
$GLOBALS['md_uploads_dir'] = $UPLOADS_DIR;

/**
 * CSRF token — generates and stores a token in the session.
 * Defined here (guarded) so it's available in both admin and frontend contexts.
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Query posts with filtering, ordering, and pagination.
 *
 * $args keys:
 *   folder   string   — limit to a content folder (e.g. 'blog')
 *   filter   array    — key/value pairs matched against meta fields
 *   orderby  string   — field to sort by: 'date' (default), 'title', or any meta key
 *   order    string   — 'desc' (default) or 'asc'
 *   limit    int      — max number of posts to return (0 = all)
 *   offset   int      — skip N posts (for pagination)
 */
/**
 * @param  array<string, mixed> $args
 * @return array<int, array<string, mixed>>
 */
function posts(array $args = []): array
{
    $index = $GLOBALS['md_index'];

    // Build filter criteria
    $criteria = $args['filter'] ?? [];
    if (!empty($args['folder'])) $criteria['folder'] = $args['folder'];

    $posts = $criteria ? $index->filter($criteria) : $index->get();
    $posts = array_values($posts);

    // Sort
    $orderby = $args['orderby'] ?? 'date';
    $order   = strtolower($args['order'] ?? 'desc') === 'asc' ? 1 : -1;
    usort($posts, function ($a, $b) use ($orderby, $order) {
        $av = $a[$orderby] ?? $a['meta'][$orderby] ?? '';
        $bv = $b[$orderby] ?? $b['meta'][$orderby] ?? '';
        if ($orderby === 'date') {
            $av = $av ? strtotime((string)$av) : 0;
            $bv = $bv ? strtotime((string)$bv) : 0;
        }
        return ($av <=> $bv) * $order;
    });

    // Paginate
    $offset = (int)($args['offset'] ?? 0);
    $limit  = (int)($args['limit']  ?? 0);
    if ($offset || $limit) {
        $posts = array_slice($posts, $offset, $limit ?: null);
    }

    return $posts;
}

/**
 * Render a theme template by name (no extension). PHP wins if both files
 * exist, so existing themes are unchanged; a theme opts into Twig per-template
 * by shipping `<name>.twig` instead of `<name>.php`.
 */
/** @param array<string, mixed> $vars */
function render(string $template, array $vars = []): void
{
    $dir = $GLOBALS['md_template_dir'];
    $php = "$dir/$template.php";
    if (is_file($php)) {
        extract($vars, EXTR_SKIP);
        require $php;
        return;
    }
    $twig = "$dir/$template.twig";
    if (is_file($twig)) {
        MD\TemplateRenderer::instance()->render("$template.twig", $vars);
        return;
    }
    throw new RuntimeException("Template not found: $template (looked for $php and $twig)");
}

/**
 * Emit a 404 response rendered through the active theme's 404 template.
 */
function not_found(?string $url = null): void
{
    http_response_code(404);
    render('404', ['url' => $url ?? ($_SERVER['REQUEST_URI'] ?? '/')]);
}
