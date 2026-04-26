<?php

declare(strict_types=1);

namespace MD\Api;

use MD\Updater;

class UpdateController
{
    /** @param array<string, mixed> $config */
    public static function handle(string $method, array $config): void
    {
        Router::requireAuth();
        $updater = new Updater($config['appRoot']);

        if ($method === 'GET') {
            $latest = $updater->checkLatest();
            \json_response([
                'ok'              => true,
                'current'         => $updater->currentVersion(),
                'latest'          => $latest,
                'has_update'      => $latest ? version_compare($latest['version'], $updater->currentVersion(), '>') : false,
                'repo_configured' => !str_starts_with($updater->repo(), 'your-'),
            ]);
        }

        Router::requireCsrf();

        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        $body   = Router::jsonBody();
        $zipUrl = trim((string)($body['zip_url'] ?? ''));
        if ($zipUrl === '' || !str_starts_with($zipUrl, 'https://')) {
            \json_response(['ok' => false, 'error' => 'Invalid URL'], 400);
        }
        $result = $updater->apply($zipUrl, $config['appRoot'] . '/site/backups');
        if (!empty($result['ok'])) {
            \json_response(['ok' => true, 'version' => $result['version'] ?? '']);
        }
        \json_response(['ok' => false, 'error' => $result['error'] ?? 'Update failed'], 500);
    }
}
