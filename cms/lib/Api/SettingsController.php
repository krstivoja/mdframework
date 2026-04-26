<?php

declare(strict_types=1);

namespace MD\Api;

use MD\Config;

class SettingsController
{
    /** @param array<string, mixed> $config */
    public static function handle(string $method, array $config): void
    {
        Router::requireAuth();
        /** @var Config $cfg */
        $cfg = $config['config'];

        if ($method === 'GET') {
            \json_response(['ok' => true, 'settings' => $cfg->all()]);
        }

        Router::requireCsrf();

        if ($method !== 'PUT') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $body = Router::jsonBody();

        $site = [
            'name' => trim((string)($body['site']['name'] ?? '')),
            'base' => '/' . trim(trim((string)($body['site']['base'] ?? '/'), '/')),
        ];

        $taxonomies = [];
        foreach ((array)($body['taxonomies'] ?? []) as $slug => $tax) {
            $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$slug));
            if (!$slug) continue;

            $fields = [];
            foreach ((array)($tax['fields'] ?? []) as $f) {
                $name = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($f['name'] ?? '')));
                if (!$name) continue;
                $type = (($f['type'] ?? '') === 'array') ? 'array' : 'single';
                if ($type === 'array') {
                    $widget = in_array($f['widget'] ?? '', ['select', 'checkbox', 'radio'], true) ? $f['widget'] : 'select';
                    $items  = array_values(array_filter(array_map(
                        fn ($v) => trim((string)$v),
                        (array)($f['items'] ?? [])
                    ), fn ($v) => $v !== ''));
                    $fields[] = ['name' => $name, 'type' => 'array', 'widget' => $widget, 'items' => $items];
                } else {
                    $fields[] = ['name' => $name, 'type' => 'single', 'value' => trim((string)($f['value'] ?? ''))];
                }
            }

            $postTypes = array_values(array_filter(array_map(
                fn ($pt) => preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$pt)),
                (array)($tax['post_types'] ?? [])
            )));

            $taxonomies[$slug] = [
                'label'      => trim((string)($tax['label'] ?? $slug)),
                'multiple'   => !empty($tax['multiple']),
                'post_types' => $postTypes,
                'fields'     => $fields,
            ];
        }

        $uploads = [
            'max_mb'     => max(1, min(512, (int)($body['uploads']['max_mb']     ?? 5))),
            'max_width'  => max(0, min(20000, (int)($body['uploads']['max_width']  ?? 0))),
            'max_height' => max(0, min(20000, (int)($body['uploads']['max_height'] ?? 0))),
        ];

        $cfg->save(array_merge($cfg->all(), [
            'site'       => $site,
            'taxonomies' => $taxonomies,
            'uploads'    => $uploads,
        ]));

        \json_response(['ok' => true, 'settings' => $cfg->all()]);
    }
}
