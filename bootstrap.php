<?php
/**
 * Shared bootstrap for the framework.
 * Sets up paths, autoloads, and instantiates Content/Index/Router.
 */

require_once __DIR__ . '/cms/vendor/autoload.php';

// Simple PSR-4 autoloader for our lib/ classes (in case composer dump-autoload wasn't run)
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'MD\\')) {
        $path = __DIR__ . '/cms/lib/' . str_replace('\\', '/', substr($class, 3)) . '.php';
        if (is_file($path)) require $path;
    }
});

$ROOT = __DIR__;
$CONTENT_DIR = $ROOT . '/site/content';
$CACHE_DIR = $ROOT . '/site/cache';
$TEMPLATE_DIR = $ROOT . '/site/templates';

$content = new MD\Content($CONTENT_DIR, $CACHE_DIR);
$index = new MD\Index($CONTENT_DIR, $CACHE_DIR, $content);
$router = new MD\Router($CONTENT_DIR);

// Expose as globals for templates
$GLOBALS['md_content'] = $content;
$GLOBALS['md_index'] = $index;
$GLOBALS['md_router'] = $router;
$GLOBALS['md_template_dir'] = $TEMPLATE_DIR;
$GLOBALS['md_content_dir'] = $CONTENT_DIR;

/**
 * Helper for templates: get all posts, optionally filtered.
 */
function posts(array $criteria = []): array
{
    $index = $GLOBALS['md_index'];
    return $criteria ? $index->filter($criteria) : $index->get();
}

/**
 * Helper: render a template with variables.
 */
function render(string $template, array $vars = []): void
{
    extract($vars);
    require $GLOBALS['md_template_dir'] . '/' . $template . '.php';
}
