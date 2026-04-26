<?php

declare(strict_types=1);

namespace MD\Api;

class AuthController
{
    public static function me(): void
    {
        $user = $_SESSION['admin_user'] ?? null;
        \json_response([
            'ok'            => true,
            'authenticated' => $user !== null,
            'user'          => $user,
            'csrf'          => \csrf_token(),
        ]);
    }

    /** @param array<string, mixed> $config */
    public static function login(string $method, array $config): void
    {
        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $body     = Router::jsonBody();
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($username === '' || $password === '') {
            \json_response(['ok' => false, 'error' => 'Missing credentials'], 400);
        }

        $ok = $username === $config['ADMIN_USER']
            && \passwordCheck($password, $config['ADMIN_PASS_HASH'] ?? '', $config['ADMIN_PASS'] ?? '');

        if (!$ok) {
            \json_response(['ok' => false, 'error' => 'Invalid credentials'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['admin_user'] = $config['ADMIN_USER'];
        \json_response([
            'ok'   => true,
            'user' => $_SESSION['admin_user'],
            'csrf' => \csrf_token(),
        ]);
    }

    public static function logout(string $method): void
    {
        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        \json_response(['ok' => true]);
    }
}
