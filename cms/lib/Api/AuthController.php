<?php

declare(strict_types=1);

namespace MD\Api;

defined('MD_BOOT') || exit;

use MD\Env;

class AuthController
{
    public static function me(): void
    {
        $user = $_SESSION['admin_user'] ?? null;
        \json_response([
            'ok'                => true,
            'authenticated'     => $user !== null,
            'user'              => $user,
            'csrf'              => \csrf_token(),
            // Surfaced so the admin shell can render the "rotate the default
            // password" banner; safe to expose pre-auth since it's a boolean,
            // not a credential.
            'passwordIsDefault' => Env::isPasswordDefault(),
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
            \json_response(['ok' => false, 'error' => 'Fill in both the username and password.'], 400);
        }

        $ok = $username === $config['ADMIN_USER']
            && \passwordCheck($password, $config['ADMIN_PASS_HASH'] ?? '');

        if (!$ok) {
            // Don't reveal which field is wrong — prevents username enumeration
            // and matches the conversational form in Yifrah Ch. 7.
            \json_response(['ok' => false, 'error' => "The username or password doesn't match. Try again, or check your config.php credentials."], 401);
        }

        session_regenerate_id(true);
        $_SESSION['admin_user'] = $config['ADMIN_USER'];
        \json_response([
            'ok'   => true,
            'user' => $_SESSION['admin_user'],
            'csrf' => \csrf_token(),
        ]);
    }

    /**
     * Rotate the admin password. Requires an authenticated session, CSRF,
     * and the current password as a second factor (so a hijacked session
     * can't quietly lock the operator out).
     *
     * @param array<string, mixed> $config
     */
    public static function password(string $method, array $config): void
    {
        if ($method !== 'POST') {
            \json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
        Router::requireAuth();
        Router::requireCsrf();

        $body    = Router::jsonBody();
        $current = (string)($body['current'] ?? '');
        $next    = (string)($body['next'] ?? '');

        if ($next === '') {
            \json_response(['ok' => false, 'error' => 'Choose a new password.'], 400);
        }
        if (strlen($next) < 8) {
            \json_response(['ok' => false, 'error' => 'New password should be at least 8 characters.'], 400);
        }
        // Tiny blocklist of obvious defaults. The client surfaces these as a
        // checklist item; the server enforces the same list so curl users
        // can't bypass the UI. Kept short on purpose — full breach-corpus
        // checks belong in a separate Have-I-Been-Pwned-style integration.
        $blocked = ['admin', 'password', '12345678', 'qwertyui', 'iloveyou', 'changeme', 'admin123'];
        if (in_array(strtolower($next), $blocked, true)) {
            \json_response(['ok' => false, 'error' => 'Pick something less common than that.'], 400);
        }

        $hash = (string)($config['ADMIN_PASS_HASH'] ?? '');
        if (!\passwordCheck($current, $hash)) {
            \json_response(['ok' => false, 'error' => 'The current password doesn\'t match.'], 401);
        }

        $envFile = (string)($config['ENV_FILE'] ?? '');
        if ($envFile === '' || !Env::changePassword($envFile, $next)) {
            \json_response(['ok' => false, 'error' => 'Could not write to .env. Check file permissions.'], 500);
        }

        \json_response(['ok' => true]);
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
