<?php

declare(strict_types=1);

namespace FrontPress;

use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// (no top-level cache — each block builds its own short-lived Twig env)

defined('FRONTPRESS_BOOT') || exit;

/**
 * Walks a JSON block tree and renders it to HTML. Each block resolves to
 * its registered render.twig template, receives `data`, `children` (already-
 * rendered HTML string for nested children), and `page` (the host page's
 * meta) in scope. Unknown block types render as an HTML comment so a
 * mistyped slug surfaces in view-source rather than silently swallowing
 * the block.
 *
 * Dynamic data substitution: text fields that contain `{{ meta.foo }}` get
 * interpolated against the host page's meta before rendering. Keeps the
 * editor surface honest — what you type is what you get, plus a small
 * sprinkle of dynamic.
 */
final class BlockRenderer
{
    private bool $editorMode = false;

    public function __construct(private BlockRegistry $registry) {}

    /**
     * Editor mode wraps every block in
     * `<div data-block-id="…" data-block-type="…" class="fp-block">` so
     * the visual canvas can find and select it. The public renderer
     * leaves this off — production HTML has no extra wrappers.
     */
    public function setEditorMode(bool $on): void
    {
        $this->editorMode = $on;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed>       $page  host page's meta
     */
    public function render(array $blocks, array $page = []): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= $this->renderOne($block, $page);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $page
     */
    private function renderOne(array $block, array $page): string
    {
        $type = (string)($block['type'] ?? '');
        $def  = $this->registry->get($type);
        if ($def === null) {
            return '<!-- unknown block: ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        // Code block — special-cased so the user's Twig source can be
        // compiled at render time. Skip the meta interpolation pass for
        // `source`: Twig will resolve `{{ }}` itself, and pre-substituting
        // would clobber real Twig variables like {{ post.title }}.
        if ($type === 'code') {
            $html = $this->renderCode((string)($data['source'] ?? ''), $page);
            return $this->maybeWrap($block, $type, $def, $html);
        }

        $data = $this->interpolate($data, $page);

        $children = '';
        if (!empty($def['hasChildren']) && is_array($block['children'] ?? null)) {
            $children = $this->render($block['children'], $page);
        }

        $tplFile = (string)$def['template'];
        if (!is_file($tplFile)) {
            return '<!-- missing render.twig for ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . ' -->';
        }

        // Twig is loaded with the block's own directory as the loader root so
        // a block can `{% include "_partial.twig" %}` siblings without
        // namespacing. Each block gets its own short-lived Environment so a
        // misbehaving block can't poison the next render.
        $loader = new FilesystemLoader(dirname($tplFile));
        $twig   = new Environment($loader, ['autoescape' => 'html', 'cache' => false]);

        $html = $twig->render(basename($tplFile), [
            'data'     => $data,
            'children' => $children,
            'page'     => $page,
        ]);

        return $this->maybeWrap($block, $type, $def, $html);
    }

    /**
     * Compile and execute the user's Twig source against the page context.
     * Errors render as a visible block (red panel in editor, HTML comment
     * in production) — never silent. Same helpers as the host template
     * (e, partial, asset_url, paginate, slug_url, inspect, seo_head) are
     * registered so power users can write `{{ partial('header') }}` etc.
     *
     * Autoescape is OFF — code blocks are explicit raw HTML by design.
     *
     * @param array<string, mixed> $page
     */
    private function renderCode(string $source, array $page): string
    {
        if ($source === '') {
            return $this->editorMode
                ? '<div style="padding:1rem;color:#999;border:1px dashed #ccc">Empty code block — edit in inspector.</div>'
                : '';
        }
        try {
            $loader = new ArrayLoader(['__code' => $source]);
            $twig   = new Environment($loader, ['autoescape' => false, 'cache' => false]);
            self::registerHelpers($twig);
            return $twig->render('__code', ['page' => $page]);
        } catch (Throwable $e) {
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return $this->editorMode
                ? '<pre style="margin:0;padding:.75rem;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:4px;font:12px ui-monospace,monospace;white-space:pre-wrap">Twig error: ' . $msg . '</pre>'
                : '<!-- code block error: ' . $msg . ' -->';
        }
    }

    /**
     * Register the same helper surface the host theme has. Defined in
     * bootstrap.php's template_helpers and globals, so they're always
     * loaded by the time a code block renders.
     */
    private static function registerHelpers(Environment $twig): void
    {
        $isSafe = ['is_safe' => ['html']];
        $twig->addFunction(new TwigFunction('e',         'e'));
        $twig->addFunction(new TwigFunction('asset_url', 'asset_url'));
        $twig->addFunction(new TwigFunction('slug_url',  'slug_url'));
        $twig->addFunction(new TwigFunction('paginate',  'paginate', $isSafe));
        $twig->addFunction(new TwigFunction('inspect',   'inspect',  $isSafe));
        $twig->addFunction(new TwigFunction('seo_head',  'seo_head', $isSafe));
        $twig->addFunction(new TwigFunction('partial', function (string $name, array $vars = []): string {
            ob_start();
            partial($name, $vars);
            return (string)ob_get_clean();
        }, $isSafe));
        // `posts()` is registered by bootstrap.php for the host site; it
        // may not be loaded in unit tests so guard this one specifically.
        if (function_exists('posts')) {
            $twig->addFunction(new TwigFunction('posts', 'posts'));
        }
    }

    /**
     * Wrap the rendered block in `<div class="fp-block" …>` when we're
     * rendering for the visual canvas; pass through unchanged for public.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $def
     */
    private function maybeWrap(array $block, string $type, array $def, string $html): string
    {
        if (!$this->editorMode) return $html;
        $id   = htmlspecialchars((string)($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $slug = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $hasC = !empty($def['hasChildren']) ? '1' : '0';
        return '<div class="fp-block" data-block-id="' . $id . '" data-block-type="' . $slug . '" data-block-container="' . $hasC . '">' . $html . '</div>';
    }

    /**
     * Walk arbitrary data and interpolate `{{ meta.xxx }}` against the host
     * page. Only touches string leaves; arrays recurse, scalars pass through.
     *
     * @param mixed                $value
     * @param array<string, mixed> $page
     * @return mixed
     */
    private function interpolate(mixed $value, array $page): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) $out[$k] = $this->interpolate($v, $page);
            return $out;
        }
        if (!is_string($value)) return $value;
        if (!str_contains($value, '{{')) return $value;

        return (string)preg_replace_callback(
            '/\{\{\s*meta\.([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/',
            function (array $m) use ($page): string {
                $path = explode('.', $m[1]);
                $cur  = $page;
                foreach ($path as $segment) {
                    if (is_array($cur) && array_key_exists($segment, $cur)) {
                        $cur = $cur[$segment];
                    } else {
                        return '';
                    }
                }
                return is_scalar($cur) ? (string)$cur : '';
            },
            $value,
        );
    }
}
