<?php
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
     *   3. _<name>.php          (legacy convention)
     *   4. <name>.php           (legacy convention)
     *   5. _<name>.twig         (legacy convention)
     *   6. <name>.twig          (legacy convention)
     *
     * `.twig` partials are routed through `MD\TemplateRenderer`. PHP partials
     * are required directly with `$vars` extracted into local scope.
     *
     * @param array<string, mixed> $vars
     */
    function partial(string $name, array $vars = []): void
    {
        $dir = $GLOBALS['md_template_dir'];
        $candidates = [
            ["components/{$name}.php",  'php'],
            ["components/{$name}.twig", 'twig'],
            ["_{$name}.php",            'php'],
            ["{$name}.php",             'php'],
            ["_{$name}.twig",           'twig'],
            ["{$name}.twig",            'twig'],
        ];
        foreach ($candidates as [$rel, $kind]) {
            $path = "$dir/$rel";
            if (!is_file($path)) continue;
            if ($kind === 'twig') {
                MD\TemplateRenderer::instance()->render($rel, $vars);
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
     * theme's assets are symlinked into `public/assets/` by ThemeService.
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
     * `slug_url('PHP', 'categories')`. Uses MD\Index::slugify() so the slug
     * matches what the public router accepts.
     */
    function slug_url(string $term, string $taxonomy = 'categories'): string
    {
        return '/' . e($taxonomy) . '/' . e(MD\Index::slugify($term));
    }
}
