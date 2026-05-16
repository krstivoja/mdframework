<?php
/**
 * Shared bootstrap for the framework.
 * Sets up paths, autoloads, and instantiates Content/Index/Router.
 */

defined('MD_BOOT') || exit;

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

// Load config.php once for both admin and public-site entry points so any
// runtime-toggleable behaviour (e.g. APP_ENV-gated SCSS compilation) sees
// the same values. Env::load is idempotent.
MD\Env::load($ROOT . '/config.php');

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

// Ensure `<webroot>/assets` is symlinked to the active theme's assets dir.
// The release-zip pipeline dereferences symlinks, so a fresh unzipped
// install may end up with a real `assets/` directory full of stale files —
// edits to site/themes/<active>/assets wouldn't take effect. Self-heal on
// first public-site request. No-op when already correctly linked.
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
    $twig = "$dir/$template.twig";

    if (!is_file($php) && !is_file($twig)) {
        throw new RuntimeException("Template not found: $template (looked for $php and $twig)");
    }

    // Buffer the rendered output so we can inject SEO tags into <head>
    // before flushing. Templates that don't produce HTML (feed.* writes
    // Atom XML and sets its own Content-Type) won't contain `</head>` and
    // pass through untouched. A theme that calls seo_head() explicitly
    // flips MD\Seo::markEmittedThisRequest() so we skip the implicit
    // injection here and don't double-emit.
    MD\Seo::resetForNextRequest();
    $GLOBALS['md_current_template'] = $template;
    $GLOBALS['md_current_vars']     = $vars;

    ob_start();
    if (is_file($php)) {
        extract($vars, EXTR_SKIP);
        require $php;
    } else {
        MD\TemplateRenderer::instance()->render("$template.twig", $vars);
    }
    $body = (string)ob_get_clean();

    if (!MD\Seo::wasEmittedThisRequest()) {
        $body = inject_seo($body, $template, $vars);
    }

    echo $body;
    admin_edit_button();
}

/**
 * Inject the framework-built SEO block into the rendered HTML right before
 * `</head>`. If the body is not HTML (no </head>), pass through untouched.
 *
 * @param array<string, mixed> $vars
 */
function inject_seo(string $body, string $template, array $vars): string
{
    $headClose = stripos($body, '</head>');
    if ($headClose === false) {
        return $body;
    }
    $config = $GLOBALS['md_config'] ?? null;
    $configArr = ($config && method_exists($config, 'all')) ? $config->all() : [];
    $url       = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $tags      = MD\Seo::tagsFor($template, $vars, $configArr, parse_url($url, PHP_URL_PATH) ?: '/');
    if ($tags === '') {
        return $body;
    }
    return substr($body, 0, $headClose) . $tags . substr($body, $headClose);
}

/**
 * Render a floating "Edit" link in the bottom-right corner of the public
 * site when the operator is logged in and the current route resolves to
 * an editable post/page. Tapping it deep-links into the admin editor for
 * that file. Quietly no-ops on non-editable routes (feeds, sitemap,
 * taxonomy archives, real 404s where `admin_edit_path` is null) and for
 * anonymous visitors.
 *
 * Styles are inlined so the button is independent of the active theme's
 * CSS — themes don't need to opt in and can't accidentally break it.
 */
function admin_edit_button(): void
{
    if (empty($GLOBALS['admin_logged_in']) || empty($GLOBALS['admin_edit_path'])) {
        return;
    }
    $path = (string)$GLOBALS['admin_edit_path'];
    // rawurlencode each path segment so spaces / non-ASCII slugs survive
    // the round-trip into React Router.
    $url  = '/admin/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $href = htmlspecialchars($url, ENT_QUOTES);
    ?>
<style>
  .md-admin-edit-btn {
    position: fixed; right: 16px; bottom: 16px; z-index: 2147483000;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 999px;
    background: #18181b; color: #fafafa;
    font: 500 13px/1 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0,0,0,.25), 0 0 0 1px rgba(255,255,255,.06) inset;
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .md-admin-edit-btn:hover, .md-admin-edit-btn:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 10px 28px rgba(0,0,0,.32), 0 0 0 1px rgba(255,255,255,.08) inset;
    color: #fafafa;
    outline: none;
  }
  .md-admin-edit-btn:focus-visible {
    box-shadow: 0 10px 28px rgba(0,0,0,.32), 0 0 0 2px #fafafa, 0 0 0 4px #18181b;
  }
  @media print { .md-admin-edit-btn { display: none; } }
</style>
<a class="md-admin-edit-btn" href="<?= $href ?>" rel="noopener">
  <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 20h9"></path>
    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
  </svg>
  Edit
</a>
<?php
}

/**
 * Emit a 404 response rendered through the active theme's 404 template.
 */
function not_found(?string $url = null): void
{
    http_response_code(404);
    render('404', ['url' => $url ?? ($_SERVER['REQUEST_URI'] ?? '/')]);
}
