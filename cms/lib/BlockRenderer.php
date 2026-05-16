<?php

declare(strict_types=1);

namespace FrontPress;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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

        if ($this->editorMode) {
            $id   = htmlspecialchars((string)($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $slug = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
            $hasC = !empty($def['hasChildren']) ? '1' : '0';
            // Wrapper carries the metadata the canvas script needs to map
            // a click back to the block id and decide whether the block
            // accepts new children.
            return '<div class="fp-block" data-block-id="' . $id . '" data-block-type="' . $slug . '" data-block-container="' . $hasC . '">' . $html . '</div>';
        }
        return $html;
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
