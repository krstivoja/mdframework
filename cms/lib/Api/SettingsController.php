<?php

declare(strict_types=1);

namespace MD\Api;

defined('MD_BOOT') || exit;

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

            // One-shot migration: legacy taxonomy-level `multiple` is folded
            // into the first array-type field so existing config.json upgrades
            // silently on first save.
            $legacyMultiple = !empty($tax['multiple']);
            $folded = false;

            $fields = [];
            foreach ((array)($tax['fields'] ?? []) as $f) {
                $name = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($f['name'] ?? '')));
                if (!$name) continue;
                $type = (($f['type'] ?? '') === 'array') ? 'array' : 'single';
                $hidden = !empty($f['hidden']);
                if ($type === 'array') {
                    $widget = in_array($f['widget'] ?? '', ['select', 'checkbox', 'radio'], true) ? $f['widget'] : 'select';
                    $items  = array_values(array_filter(array_map(
                        fn ($v) => trim((string)$v),
                        (array)($f['items'] ?? [])
                    ), fn ($v) => $v !== ''));
                    $multiple = !empty($f['multiple']) || (!$folded && $legacyMultiple);
                    $folded   = $folded || $legacyMultiple;
                    $fields[] = ['name' => $name, 'type' => 'array', 'widget' => $widget, 'multiple' => $multiple, 'items' => $items, 'hidden' => $hidden];
                } else {
                    $fields[] = ['name' => $name, 'type' => 'single', 'value' => trim((string)($f['value'] ?? '')), 'hidden' => $hidden];
                }
            }

            $postTypes = array_values(array_filter(array_map(
                fn ($pt) => preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$pt)),
                (array)($tax['post_types'] ?? [])
            )));

            $taxonomies[$slug] = [
                'label'      => trim((string)($tax['label'] ?? $slug)),
                'post_types' => $postTypes,
                'fields'     => $fields,
            ];
        }

        $uploads = [
            'max_mb'     => max(1, min(512, (int)($body['uploads']['max_mb']     ?? 5))),
            'max_width'  => max(0, min(20000, (int)($body['uploads']['max_width']  ?? 0))),
            'max_height' => max(0, min(20000, (int)($body['uploads']['max_height'] ?? 0))),
        ];

        // SEO toggles + defaults. Only persist when the payload mentions
        // them — front-end can save Site / Fields / Uploads independently
        // without zeroing the SEO block.
        $existingSeo = (array)($cfg->all()['seo'] ?? []);
        $seo = $existingSeo;
        if (is_array($body['seo'] ?? null)) {
            $in = $body['seo'];
            $seo = [
                'enabled'        => self::flag($in, 'enabled',       $existingSeo['enabled']       ?? true),
                'opengraph'      => self::flag($in, 'opengraph',     $existingSeo['opengraph']     ?? true),
                'twitter_card'   => self::flag($in, 'twitter_card',  $existingSeo['twitter_card']  ?? true),
                'json_ld'        => self::flag($in, 'json_ld',       $existingSeo['json_ld']       ?? true),
                'indexable'      => self::flag($in, 'indexable',     $existingSeo['indexable']     ?? true),
                'twitter_handle' => trim((string)($in['twitter_handle'] ?? $existingSeo['twitter_handle'] ?? '')),
                'default_image'  => trim((string)($in['default_image']  ?? $existingSeo['default_image']  ?? '')),
                'locale'         => trim((string)($in['locale']         ?? $existingSeo['locale']         ?? 'en_US')),
            ];
        }

        $cfg->save(array_merge($cfg->all(), [
            'site'       => $site,
            'taxonomies' => $taxonomies,
            'uploads'    => $uploads,
            'seo'        => $seo,
        ]));

        \json_response(['ok' => true, 'settings' => $cfg->all()]);
    }

    /**
     * Read a boolean toggle out of a settings payload — accept true/false,
     * 1/0, "1"/"0", "on"/"off" so JSON-from-React and form-submit both work.
     *
     * @param array<string, mixed> $payload
     */
    private static function flag(array $payload, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $payload)) return $default;
        $v = $payload[$key];
        if (is_bool($v)) return $v;
        if (is_int($v))  return $v !== 0;
        if (is_string($v)) {
            $l = strtolower(trim($v));
            return $l !== '' && !in_array($l, ['0', 'false', 'off', 'no'], true);
        }
        return $default;
    }
}
