<?php

defined('FRONTPRESS_BOOT') || exit;
/**
 * Template helpers — usable from PHP and Twig templates.
 *
 * Each helper is a global function so it can be called the same way in either
 * engine. TemplateRenderer registers these as Twig functions of the same name,
 * delegating to these implementations.
 */

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output. Accepts scalars and Stringable.
     */
    function e(mixed $value): string
    {
        if ($value === null || $value === false) return '';
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('partial')) {
    /**
     * Render a partial from the active theme. Resolution order:
     *   1. components/<name>.php
     *   2. components/<name>.twig
     *   3. components/<name>.html
     *   4. _<name>.php          (legacy convention)
     *   5. <name>.php           (legacy convention)
     *   6. _<name>.twig         (legacy convention)
     *   7. <name>.twig          (legacy convention)
     *   8. _<name>.html
     *   9. <name>.html
     *
     * `.twig` partials are routed through `FrontPress\TemplateRenderer`. PHP
     * partials are required directly with `$vars` extracted into local scope.
     * `.html` partials are emitted verbatim — they carry no template logic,
     * so `$vars` are ignored. Use `.twig` when you need dynamic content.
     *
     * @param array<string, mixed> $vars
     */
    function partial(string $name, array $vars = []): void
    {
        // Reject anything that isn't a plain partial name. Slashes are allowed
        // for nested partials (e.g. "blocks/hero") but `..`, leading slashes,
        // and any non-alphanumeric segment characters are rejected to prevent
        // path traversal into the wider filesystem.
        if (!preg_match('#^[a-z0-9][a-z0-9_/-]*$#i', $name) || str_contains($name, '..')) {
            throw new RuntimeException("Invalid partial name: $name");
        }
        $dir = $GLOBALS['fp_template_dir'];
        $candidates = [
            ["components/{$name}.php",  'php'],
            ["components/{$name}.twig", 'twig'],
            ["components/{$name}.html", 'html'],
            ["_{$name}.php",            'php'],
            ["{$name}.php",             'php'],
            ["_{$name}.twig",           'twig'],
            ["{$name}.twig",            'twig'],
            ["_{$name}.html",           'html'],
            ["{$name}.html",            'html'],
        ];
        foreach ($candidates as [$rel, $kind]) {
            $path = "$dir/$rel";
            if (!is_file($path)) continue;
            if ($kind === 'twig') {
                FrontPress\TemplateRenderer::instance()->render($rel, $vars);
            } elseif ($kind === 'html') {
                readfile($path);
            } else {
                extract($vars, EXTR_SKIP);
                require $path;
            }
            return;
        }
        throw new RuntimeException("Partial not found: $name");
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL for a file under the active theme's `assets/` directory. The active
     * theme's assets are symlinked into the webroot as `assets/` by ThemeService.
     */
    function asset_url(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('paginate')) {
    /**
     * Render the prev / "Page X of Y" / next nav block used by archive and
     * taxonomy templates. Returns an empty string when there's only one page.
     *
     * `$baseUrl` is the URL for page 1 (no trailing slash); subsequent pages
     * append `/page/N`.
     */
    function paginate(int $page, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) return '';

        $base = e($baseUrl);
        $prevHref = $page === 2 ? $base : $base . '/page/' . ($page - 1);
        $nextHref = $base . '/page/' . ($page + 1);

        $out  = '<nav class="pagination">';
        if ($page > 1) {
            $out .= '<a href="' . $prevHref . '">&larr; Prev</a>';
        }
        $out .= '<span>Page ' . $page . ' of ' . $totalPages . '</span>';
        if ($page < $totalPages) {
            $out .= '<a href="' . $nextHref . '">Next &rarr;</a>';
        }
        $out .= '</nav>';
        return $out;
    }
}

if (!function_exists('slug_url')) {
    /**
     * URL for a taxonomy term archive, e.g. `/categories/php` for
     * `slug_url('PHP', 'categories')`. Uses FrontPress\Index::slugify() so the slug
     * matches what the public router accepts.
     */
    function slug_url(string $term, string $taxonomy = 'categories'): string
    {
        return '/' . e($taxonomy) . '/' . e(FrontPress\Index::slugify($term));
    }
}

if (!function_exists('seo_head')) {
    /**
     * Theme-facing accessor for the SEO block bootstrap.php otherwise
     * auto-injects before `</head>`. Use this when you want the tags at
     * an explicit position in your `<head>` partial — calling it tells
     * the framework to skip auto-injection so you don't get duplicates.
     *
     * Usage:
     *   PHP:   <?= seo_head() ?>
     *   Twig:  {{ seo_head()|raw }}
     */
    function seo_head(): string
    {
        $template = $GLOBALS['fp_current_template'] ?? '';
        $vars     = $GLOBALS['fp_current_vars']     ?? [];
        $config   = $GLOBALS['fp_config'] ?? null;
        $configArr = ($config && method_exists($config, 'all')) ? $config->all() : [];
        $url      = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $out      = FrontPress\Seo::tagsFor($template, $vars, $configArr, parse_url($url, PHP_URL_PATH) ?: '/');
        FrontPress\Seo::markEmittedThisRequest();
        return $out;
    }
}

if (!function_exists('inspect')) {
    /**
     * Render a pretty-printed, collapsible dump of any value as HTML. Useful
     * inside theme templates while you're figuring out what variables the
     * route handed you — pair with the `debug-twig` / `debug-php` starters,
     * or sprinkle into a real theme during development.
     *
     * Output is fully escaped and labelled with the value's PHP type, so it
     * is safe to drop straight into any template position.
     *
     * Usage:
     *   PHP:   <?= inspect($meta, 'meta') ?>
     *   Twig:  {{ inspect(meta, 'meta')|raw }}
     */
    function inspect(mixed $value, string $label = ''): string
    {
        $type = get_debug_type($value);
        $body = htmlspecialchars(print_r($value, true), ENT_QUOTES, 'UTF-8');
        $heading = $label !== ''
            ? '<strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> '
            : '';
        $style = 'background:#0b0f19;color:#e5e7eb;border-radius:6px;padding:.75rem 1rem;'
               . 'margin:.5rem 0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;'
               . 'font-size:12px;line-height:1.45;overflow-x:auto;';
        return '<details class="fp-inspect" open style="' . $style . '">'
             . '<summary style="cursor:pointer;color:#a7f3d0;margin-bottom:.5rem">'
             . $heading . '<code style="color:#fcd34d">' . $type . '</code>'
             . '</summary>'
             . '<pre style="margin:0;white-space:pre-wrap;word-break:break-word">' . $body . '</pre>'
             . '</details>';
    }
}
