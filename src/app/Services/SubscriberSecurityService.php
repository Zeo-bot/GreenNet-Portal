<?php

declare(strict_types=1);

namespace GreenNet\Services;

final class SubscriberSecurityService
{
    public static function csrfToken(): string
    {
        if (!isset($_SESSION['subscriber_csrf']) || !is_string($_SESSION['subscriber_csrf'])) {
            $_SESSION['subscriber_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['subscriber_csrf'];
    }

    public static function validateCsrf(?string $token): bool
    {
        $expected = $_SESSION['subscriber_csrf'] ?? '';

        return is_string($expected)
            && $expected !== ''
            && is_string($token)
            && hash_equals($expected, $token);
    }

    public static function username(): string
    {
        if (($_SESSION['subscriber_logged_in'] ?? false) !== true) {
            return '';
        }

        return trim((string) ($_SESSION['subscriber_username'] ?? ''));
    }

    public static function establish(string $username): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['subscriber_logged_in'] = true;
        $_SESSION['subscriber_username'] = $username;
        $_SESSION['subscriber_login_at'] = date('Y-m-d H:i:s');
        unset($_SESSION['subscriber_csrf']);
        self::csrfToken();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
