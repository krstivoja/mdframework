<?php

declare(strict_types=1);

namespace MD\Api;

/**
 * Dispatches /admin/api/* requests to controllers.
 *
 * Helper functions (json_response, csrf_token, csrf_verify, passwordCheck)
 * are defined in admin.php and are available in the global namespace.
 */
class Router
{
    /** @param array<string, mixed> $config */
    public static function dispatch(string $path, string $method, array $config): void
    {
        header('Content-Type: application/json');

        $parts    = array_values(array_filter(explode('/', $path), fn ($p) => $p !== ''));
        $resource = $parts[0] ?? '';
        $rest     = array_slice($parts, 1);

        try {
            switch ($resource) {
                case 'me':
                    AuthController::me();
                    return;
                case 'login':
                    AuthController::login($method, $config);
                    return;
                case 'logout':
                    AuthController::logout($method);
                    return;
                case 'pages':
                    PagesController::handle($rest, $method, $config);
                    return;
                case 'media':
                    MediaController::handle($rest, $method, $config);
                    return;
                case 'settings':
                    SettingsController::handle($method, $config);
                    return;
                case 'themes':
                    ThemesController::handle($rest, $method, $config);
                    return;
                case 'backup':
                    BackupController::handle($rest, $method, $config);
                    return;
                case 'search':
                    SearchController::handle($method, $config);
                    return;
                case 'update':
                    UpdateController::handle($method, $config);
                    return;
            }
        } catch (\Throwable $e) {
            \json_response(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        \json_response(['ok' => false, 'error' => 'Unknown endpoint'], 404);
    }

    public static function requireAuth(): void
    {
        if (empty($_SESSION['admin_user'])) {
            \json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
    }

    public static function requireCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
            \json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
        }
    }

    /** @return array<string, mixed> */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
