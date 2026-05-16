<?php

declare(strict_types=1);

namespace FrontPress\Api;

use FrontPress\BlockRegistry;
use FrontPress\BlockRenderer;

defined('FRONTPRESS_BOOT') || exit;

/**
 * Block-builder API. Two endpoints back the page composer:
 *
 *   GET  /admin/api/blocks                 - registered block definitions
 *   POST /admin/api/blocks/render          - render a tree to HTML for the
 *                                            live preview pane. body:
 *                                            { blocks: [...], page: {...} }
 *
 * The registry sits in `cms/blocks/<slug>/`. Themes can't override yet —
 * that's a v2 concern; v1 keeps the registry framework-built-in.
 */
class BlocksController
{
    /**
     * @param string[]             $pathParts
     * @param array<string, mixed> $config
     */
    public static function handle(array $pathParts, string $method, array $config): void
    {
        Router::requireAuth();

        $resource = $pathParts[0] ?? '';

        if ($resource === '' && $method === 'GET') {
            self::list($config);
            return;
        }

        Router::requireCsrf();

        if ($resource === 'render' && $method === 'POST') {
            self::render($config);
            return;
        }

        \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    /** @param array<string, mixed> $config */
    private static function list(array $config): void
    {
        $registry = self::registry($config);
        \json_response([
            'ok'     => true,
            'blocks' => array_values($registry->all()),
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function render(array $config): void
    {
        $body   = Router::jsonBody();
        $blocks = is_array($body['blocks'] ?? null) ? $body['blocks'] : [];
        $page   = is_array($body['page']   ?? null) ? $body['page']   : [];

        $renderer = new BlockRenderer(self::registry($config));
        $renderer->setEditorMode(true);
        \json_response([
            'ok'   => true,
            'html' => $renderer->render($blocks, $page),
        ]);
    }

    /** @param array<string, mixed> $config */
    private static function registry(array $config): BlockRegistry
    {
        return new BlockRegistry($config['cmsRoot'] . '/blocks');
    }
}
