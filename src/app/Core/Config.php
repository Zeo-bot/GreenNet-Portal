<?php

declare(strict_types=1);

namespace GreenNet\Core;

class Config
{
    private static array $items = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            self::$items[trim($key)] = trim($value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }

        $environment = getenv($key);

        return $environment !== false ? $environment : $default;
    }

    public static function appName(): string
    {
        return (string) self::get('APP_NAME', 'GreenNet');
    }

    public static function appTitle(): string
    {
        return (string) self::get('APP_TITLE', 'GreenNet Portal');
    }

    public static function appEnv(): string
    {
        return (string) self::get('APP_ENV', 'development');
    }

    public static function isDevelopment(): bool
    {
        return self::appEnv() === 'development';
    }

    public static function isProduction(): bool
    {
        return self::appEnv() === 'production';
    }

    public static function appVersion(): string
    {
        return (string) self::get('APP_VERSION', '1.0.0');
    }

    public static function supportPhone(): string
    {
        return (string) self::get('SUPPORT_PHONE', '0966393915');
    }

    public static function supportWhatsapp(): string
    {
        $countryCode = (string) self::get('SUPPORT_COUNTRY_CODE', '963');
        $phone = self::supportPhone();

        return $countryCode . ltrim($phone, '0');
    }

    public static function accessMode(): string
    {
        return (string) self::get('ACCESS_MODE', 'hybrid');
    }

    public static function authBackend(): string
    {
        return (string) self::get('AUTH_BACKEND', 'user-manager');
    }

    public static function cacheSeconds(): int
    {
        return (int) self::get('CACHE_SECONDS', 15);
    }

    public static function qosEnabled(): bool
    {
        return self::get('QOS_ENABLED', 'false') === 'true';
    }

    public static function qosMode(): string
    {
        return (string) self::get('QOS_MODE', 'smart');
    }

    public static function qosBackend(): string
    {
        return (string) self::get('QOS_BACKEND', 'mikrotik');
    }

    public static function mikrotikHost(): string
    {
        return (string) self::get('MIKROTIK_HOST', '192.168.250.2');
    }

    public static function mikrotikApiPort(): int
    {
        return (int) self::get('MIKROTIK_API_PORT', 8728);
    }

    public static function mikrotikUsername(): string
    {
        return (string) self::get('MIKROTIK_USERNAME', 'apiuser');
    }

    public static function mikrotikPassword(): string
    {
        return (string) self::get('MIKROTIK_PASSWORD', '');
    }

    public static function adminPath(): string
    {
        return (string) self::get('ADMIN_PATH', '/admin');
    }
}
